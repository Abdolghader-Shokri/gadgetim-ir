<?php
/**
 * Builds the one-file update package (owner, 2026-11-22: every update is one zip that is put on the
 * host once, then the dashboard is opened once and everything is done). gadgetim-core's
 * Import\Bundle applies it from wp-content/gadgetim-drop/.
 *
 *   "$PHP" -c "$INI" tools/build-update-bundle.php X.Y.Z [<drop.zip> ...] [options]
 *
 * X.Y.Z is a release: the tag vX.Y.Z exists and tools/build-release.sh vX.Y.Z has made
 * release/gadgetim-core-X.Y.Z.zip and release/gadgetim-theme-X.Y.Z.zip (their .sha256 files must
 * still match). Every delivery carries both, also when only content changed: the site skips a
 * version it already has and catches up on one it missed.
 *
 * <drop.zip>: packages for the drop folder (tools/build-content-drop.php, build-product-drop.php,
 * build-page-drop.php, build-media-drop.php), imported after the code in the order given.
 *
 * Options:
 *   --out <dir>      where the package goes (default release/deploy)
 *   --date <Y-m-d>   date in the file name (default today in Tehran)
 *   --force          replace a package with the same name
 *   --test           test package: no tag or .sha256 check, versions are read from the zips,
 *                    "-test" in the name; never a delivery
 *   --plugin <zip>   another plugin zip (with --test)
 *   --theme <zip>    another theme zip (with --test)
 *   --no-plugin, --no-theme   leave one out (with --test)
 *
 * Writes gadgetim-update-X.Y.Z-<date>.zip and a .sha256 next to it. Inside:
 *   gadgetim-update.json   format, version 1, release, commit, built, plugin, theme, drops[]
 *   plugin/<zip>, theme/<zip>, drops/<zip>   stored as they are (no second compression)
 * The finished zip is read back and every file checked against the manifest.
 * Output for the user is Persian; technical tokens stay Latin.
 */

declare( strict_types=1 );

const FORMAT   = 'gadgetim-update';
const MANIFEST = 'gadgetim-update.json';
const CODE     = [
	'plugin' => [ 'name' => 'gadgetim-core', 'entry' => 'gadgetim-core/gadgetim-core.php' ],
	'theme'  => [ 'name' => 'gadgetim-theme', 'entry' => 'gadgetim/style.css' ],
];
const DROP_LISTS = [ 'products', 'pages', 'media', 'posts', 'terms' ];

/**
 * Prints a Persian error and stops.
 */
function bundle_die( string $message ): never {
	fwrite( STDERR, '✘ ' . $message . "\n" );
	exit( 1 );
}

/**
 * The Version header of $entry inside the zip at $path ('' when there is none); the same reading
 * as Import\Bundle::zip_version().
 */
function bundle_zip_version( string $path, string $entry ): string {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return '';
	}
	$head = $zip->getFromName( $entry, 8192 );
	$zip->close();
	if ( ! is_string( $head ) || ! preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', $head, $m ) ) {
		return '';
	}
	return trim( (string) preg_replace( '/\s*(?:\*\/|\?>).*$/', '', $m[1] ) );
}

/**
 * Runs git in the repository; null when it fails.
 */
function bundle_git( string $root, string $args ): ?string {
	$out = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' ' . $args . ' 2>' . ( '\\' === DIRECTORY_SEPARATOR ? 'NUL' : '/dev/null' ) );
	$out = is_string( $out ) ? trim( $out ) : '';
	return '' === $out ? null : $out;
}

/**
 * Human size.
 */
function bundle_size( int $bytes ): string {
	return $bytes >= 1048576 ? sprintf( '%.1f MB', $bytes / 1048576 ) : sprintf( '%d KB', (int) ceil( $bytes / 1024 ) );
}

if ( ! class_exists( ZipArchive::class ) ) {
	bundle_die( 'افزونهٔ zip در PHP فعال نیست؛ با php.ini خود Local اجرا کن (-c "$INI").' );
}

// ---------------------------------------------------------------------------
// Arguments.
// ---------------------------------------------------------------------------
$root    = dirname( __DIR__ );
$args    = array_slice( $argv, 1 );
$opts    = [
	'out'       => $root . '/release/deploy',
	'date'      => ( new DateTime( 'now', new DateTimeZone( 'Asia/Tehran' ) ) )->format( 'Y-m-d' ),
	'force'     => false,
	'test'      => false,
	'plugin'    => null,
	'theme'     => null,
	'no-plugin' => false,
	'no-theme'  => false,
];
$version = null;
$drops   = [];
for ( $i = 0; $i < count( $args ); $i++ ) {
	$a = $args[ $i ];
	if ( in_array( $a, [ '-h', '--help' ], true ) ) {
		$doc = (string) file_get_contents( __FILE__ );
		preg_match( '#/\*\*(.*?)\*/#s', $doc, $m );
		echo preg_replace( '/^\s*\* ?/m', '', trim( $m[1] ?? '' ) ) . "\n";
		exit( 0 );
	}
	if ( in_array( $a, [ '--force', '--test', '--no-plugin', '--no-theme' ], true ) ) {
		$opts[ substr( $a, 2 ) ] = true;
		continue;
	}
	if ( in_array( $a, [ '--out', '--date', '--plugin', '--theme' ], true ) ) {
		if ( ! isset( $args[ $i + 1 ] ) ) {
			bundle_die( "مقدار $a داده نشده است." );
		}
		$opts[ substr( $a, 2 ) ] = $args[ ++$i ];
		continue;
	}
	if ( str_starts_with( $a, '--' ) ) {
		bundle_die( "گزینهٔ ناشناخته: $a" );
	}
	if ( null === $version ) {
		$version = $a;
	} else {
		$drops[] = $a;
	}
}

if ( null === $version || ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
	bundle_die( 'شمارهٔ نسخه را به شکل X.Y.Z بده، مثل 1.4.0 (راهنما: --help).' );
}
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $opts['date'] ) ) {
	bundle_die( 'تاریخ باید به شکل YYYY-MM-DD باشد: ' . $opts['date'] );
}
if ( ! $opts['test'] && ( null !== $opts['plugin'] || null !== $opts['theme'] || $opts['no-plugin'] || $opts['no-theme'] ) ) {
	bundle_die( '--plugin، --theme، --no-plugin و --no-theme فقط با --test پذیرفته می‌شوند: بستهٔ تحویلی همیشه هر دو نسخهٔ انتشار را دارد.' );
}

// ---------------------------------------------------------------------------
// The release: tag, zips and their checksums.
// ---------------------------------------------------------------------------
$commit = '';
if ( ! $opts['test'] ) {
	$commit = (string) bundle_git( $root, 'rev-parse --verify --quiet ' . escapeshellarg( "v$version^{commit}" ) );
	if ( '' === $commit ) {
		bundle_die( "تگ v$version پیدا نشد. اول نسخه را منتشر کن: bump-version، CHANGELOG، ادغام، تگ و tools/build-release.sh v$version." );
	}
}

$code = [];
foreach ( CODE as $kind => $spec ) {
	if ( $opts[ 'no-' . $kind ] ) {
		continue;
	}
	$path = null !== $opts[ $kind ] ? (string) $opts[ $kind ] : $root . '/release/' . $spec['name'] . '-' . $version . '.zip';
	if ( ! is_file( $path ) ) {
		bundle_die( "پیدا نشد: $path (tools/build-release.sh v$version)" );
	}
	$inside = bundle_zip_version( $path, $spec['entry'] );
	if ( '' === $inside ) {
		bundle_die( "در $path فایل {$spec['entry']} با سرآیند Version پیدا نشد." );
	}
	$sha = (string) hash_file( 'sha256', $path );
	if ( ! $opts['test'] ) {
		if ( $inside !== $version ) {
			bundle_die( "نسخهٔ داخل $path ($inside) با $version نمی‌خواند." );
		}
		$side = $path . '.sha256';
		if ( ! is_file( $side ) || ! str_starts_with( trim( (string) file_get_contents( $side ) ), $sha ) ) {
			bundle_die( "فایل $side نیست یا با زیپ نمی‌خواند؛ زیپ را دوباره از تگ بساز (tools/build-release.sh v$version)." );
		}
	}
	$code[ $kind ] = [
		'version' => $inside,
		'file'    => $kind . '/' . basename( $path ),
		'sha256'  => $sha,
		'size'    => (int) filesize( $path ),
		'source'  => $path,
	];
}

// ---------------------------------------------------------------------------
// Drop packages.
// ---------------------------------------------------------------------------
$listed = [];
$names  = [];
foreach ( $drops as $path ) {
	$name = basename( $path );
	if ( ! is_file( $path ) ) {
		bundle_die( "بستهٔ محتوا پیدا نشد: $path" );
	}
	if ( ! preg_match( '/^[A-Za-z0-9._-]+\.zip$/', $name ) ) {
		bundle_die( "نام بستهٔ محتوا فقط حروف لاتین، رقم، نقطه، خط تیره و زیرخط دارد: $name" );
	}
	if ( isset( $names[ $name ] ) ) {
		bundle_die( "بستهٔ محتوا دو بار آمده است: $name" );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		bundle_die( "زیپ باز نشد: $path" );
	}
	$data   = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$nested = false !== $zip->locateName( MANIFEST );
	$zip->close();
	$lists = [];
	foreach ( DROP_LISTS as $list ) {
		if ( is_array( $data ) && ! empty( $data[ $list ] ) && is_array( $data[ $list ] ) ) {
			$lists[] = $list . ' ' . count( $data[ $list ] );
		}
	}
	if ( $nested || ! is_array( $data ) || 1 !== (int) ( $data['version'] ?? 0 ) || ! $lists ) {
		bundle_die( "$name بستهٔ gadgetim-drop نسخهٔ 1 نیست (manifest.json با products/pages/media/posts/terms)." );
	}
	$names[ $name ] = true;
	$listed[]       = [
		'file'   => 'drops/' . $name,
		'sha256' => (string) hash_file( 'sha256', $path ),
		'size'   => (int) filesize( $path ),
		'source' => $path,
		'lists'  => implode( '، ', $lists ),
	];
}
if ( ! $code && ! $listed ) {
	bundle_die( 'بسته خالی است: نه افزونه، نه قالب، نه محتوا.' );
}

// ---------------------------------------------------------------------------
// Write the package.
// ---------------------------------------------------------------------------
$out_dir = rtrim( str_replace( '\\', '/', (string) $opts['out'] ), '/' );
if ( ! is_dir( $out_dir ) && ! mkdir( $out_dir, 0777, true ) ) {
	bundle_die( "پوشهٔ خروجی ساخته نشد: $out_dir" );
}
$file = $out_dir . '/gadgetim-update-' . $version . '-' . $opts['date'] . ( $opts['test'] ? '-test' : '' ) . '.zip';
if ( is_file( $file ) && ! $opts['force'] ) {
	bundle_die( "این بسته از قبل هست: $file (برای جایگزینی --force)" );
}

$strip    = static fn( array $e ): array => array_diff_key( $e, [ 'source' => 1, 'lists' => 1 ] );
$manifest = [
	'format'  => FORMAT,
	'version' => 1,
	'release' => $version,
	'commit'  => $commit,
	'built'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
];
if ( $opts['test'] ) {
	$manifest['test'] = true;
}
foreach ( $code as $kind => $entry ) {
	$manifest[ $kind ] = $strip( $entry );
}
$manifest['drops'] = array_map( $strip, $listed );

$tmp = $file . '.part';
@unlink( $tmp );
$zip = new ZipArchive();
if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	bundle_die( "زیپ ساخته نشد: $tmp" );
}
$zip->addFromString( MANIFEST, json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
foreach ( array_merge( array_values( $code ), $listed ) as $entry ) {
	$zip->addFile( $entry['source'], $entry['file'] );
	$zip->setCompressionName( $entry['file'], ZipArchive::CM_STORE );
}
if ( ! $zip->close() ) {
	@unlink( $tmp );
	bundle_die( "نوشتن زیپ کامل نشد: $file" );
}

// Read it back: every file against the manifest.
$check = new ZipArchive();
if ( true !== $check->open( $tmp ) ) {
	bundle_die( "زیپ ساخته‌شده باز نشد: $tmp" );
}
$back = json_decode( (string) $check->getFromName( MANIFEST ), true );
if ( ! is_array( $back ) || FORMAT !== ( $back['format'] ?? '' ) ) {
	bundle_die( 'gadgetim-update.json در زیپ ساخته‌شده خوانده نشد.' );
}
foreach ( array_merge( array_values( $code ), $listed ) as $entry ) {
	$stream = $check->getStream( $entry['file'] );
	if ( false === $stream ) {
		bundle_die( "در زیپ ساخته‌شده نیست: {$entry['file']}" );
	}
	$ctx = hash_init( 'sha256' );
	hash_update_stream( $ctx, $stream );
	fclose( $stream );
	if ( hash_final( $ctx ) !== $entry['sha256'] ) {
		bundle_die( "کد بررسی {$entry['file']} در زیپ ساخته‌شده نمی‌خواند." );
	}
}
$check->close();
if ( is_file( $file ) ) {
	unlink( $file );
}
rename( $tmp, $file );
$sha = (string) hash_file( 'sha256', $file );
file_put_contents( $file . '.sha256', $sha . '  ' . basename( $file ) . "\n" );

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------
echo ( $opts['test'] ? 'بستهٔ آزمایشی (تحویل نشود)' : "بستهٔ به‌روزرسانی نسخهٔ $version" ) . "\n";
echo '  ' . $file . ' — ' . bundle_size( (int) filesize( $file ) ) . "\n";
echo '  SHA-256: ' . $sha . "\n";
if ( '' !== $commit ) {
	echo '  کامیت تگ: ' . substr( $commit, 0, 12 ) . "\n";
}
foreach ( $code as $kind => $entry ) {
	echo '  ' . ( 'plugin' === $kind ? 'افزونه' : 'قالب' ) . ': ' . $entry['file'] . ' (نسخهٔ ' . $entry['version'] . '، ' . bundle_size( $entry['size'] ) . ")\n";
}
foreach ( $listed as $entry ) {
	echo '  محتوا: ' . $entry['file'] . ' (' . $entry['lists'] . '، ' . bundle_size( $entry['size'] ) . ")\n";
}
echo "روی هاست: همین یک فایل در public_html/wp-content/gadgetim-drop/ و بعد یک بار باز کردن پیشخوان.\n";
