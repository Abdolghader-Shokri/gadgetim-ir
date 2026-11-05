<?php
/**
 * Gadgetim CLI bootstrap: loads the Local WordPress site from the command line and runs a
 * PHP file or inline code inside it. Plain-PHP alternative to WP-CLI (architecture 2.6).
 *
 * Usage (Git Bash; $PHP = Local's php.exe, $INI = the site's rendered php.ini):
 *   "$PHP" -c "$INI" tools/wp-bootstrap.php [options] <script.php> [script args...]
 *   "$PHP" -c "$INI" tools/wp-bootstrap.php [options] -r '<php code>' [script args...]
 *   "$PHP" -c "$INI" tools/wp-bootstrap.php [options] - < code.php      (code from STDIN)
 *
 * Options (before the script; everything after the script belongs to the script):
 *   --path=<dir>          WordPress root. Default: $GADGETIM_WP_PATH or the Local site path below.
 *   --host=<host>         HTTP_HOST of the simulated request. Default: $GADGETIM_WP_HOST or gadgetim.local.
 *   --user=<id|login>     Run as this user (wp_set_current_user) once WordPress has loaded.
 *   --skip-plugins[=a,b]  Load no plugins at all, or skip only the listed ones (folder or basename).
 *   --no-admin            Do not load wp-admin/includes/admin.php (loaded by default, like WP-CLI).
 *   --debug               Define WP_DEBUG and WP_DEBUG_DISPLAY before wp-config.php runs.
 *   --verbose             Print a one-line summary of what was loaded (STDERR).
 *   -h, --help            Show the help text.
 *
 * Exit codes: 0 ok, 1 usage error, 2 WordPress root not found, 3 script not found; otherwise the
 * script's own exit code (PHP fatal errors exit with 255).
 *
 * The rendered php.ini matters: it carries Local's MySQL port (mysqli.default_port), so always pass
 * it with -c. Add "-d display_startup_errors=0" to silence the harmless php_imagick.dll warning.
 * The working directory is left untouched; relative paths in script arguments resolve from it.
 * The script runs in the global scope, so $wpdb, $wp_query etc. are available directly.
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "wp-bootstrap.php runs from the command line only.\n" );
	exit( 1 );
}

define( 'GADGETIM_CLI', true );

const GADGETIM_CLI_DEFAULT_PATH = 'C:/Users/GHADER/Local Sites/gadgetim/app/public';
const GADGETIM_CLI_DEFAULT_HOST = 'gadgetim.local';
const GADGETIM_CLI_MEMORY_LIMIT = '512M';

/**
 * Writes one line to STDERR.
 */
function gadgetim_cli_err( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
}

/**
 * Help text (user-facing output is Persian; option names stay Latin).
 */
function gadgetim_cli_usage(): string {
	return <<<'TXT'
wp-bootstrap.php — اجرای کد PHP داخل وردپرسِ سایت Local (جایگزین سادهٴ WP-CLI)

کاربرد:
  php -c <php.ini> tools/wp-bootstrap.php [گزینه‌ها] <script.php> [آرگومان‌ها...]
  php -c <php.ini> tools/wp-bootstrap.php [گزینه‌ها] -r '<کد PHP>' [آرگومان‌ها...]
  php -c <php.ini> tools/wp-bootstrap.php [گزینه‌ها] -            (کد از STDIN)

گزینه‌ها (پیش از اسکریپت):
  --path=<dir>          ریشهٴ وردپرس (پیش‌فرض: GADGETIM_WP_PATH یا سایت Local)
  --host=<host>         مقدار HTTP_HOST (پیش‌فرض: GADGETIM_WP_HOST یا gadgetim.local)
  --user=<id|login>     اجرا به‌عنوان این کاربر
  --skip-plugins[=a,b]  بارگذاری نکردن همهٴ افزونه‌ها، یا فقط افزونه‌های فهرست‌شده
  --no-admin            بارگذاری نکردن wp-admin/includes/admin.php
  --debug               تعریف WP_DEBUG و WP_DEBUG_DISPLAY
  --verbose             چاپ یک خط خلاصه از چیزی که بارگذاری شد
  -h, --help            این راهنما

کدهای خروج: 0 موفق، 1 خطای کاربرد، 2 وردپرس پیدا نشد، 3 اسکریپت پیدا نشد؛ در بقیهٴ موارد کد خروج خود اسکریپت.

TXT;
}

/**
 * Parses the command line. Exits on usage errors.
 *
 * @param string[] $argv_in Raw argv including the bootstrap path at index 0.
 * @return array{path:string,host:string,user:string,skip_plugins:?array,admin:bool,debug:bool,verbose:bool,mode:string,script:string,code:?string,script_args:string[]}
 */
function gadgetim_cli_parse_args( array $argv_in ): array {
	array_shift( $argv_in );

	$env_path = getenv( 'GADGETIM_WP_PATH' );
	$env_host = getenv( 'GADGETIM_WP_HOST' );

	$options = array(
		'path'         => is_string( $env_path ) && '' !== $env_path ? $env_path : GADGETIM_CLI_DEFAULT_PATH,
		'host'         => is_string( $env_host ) && '' !== $env_host ? $env_host : GADGETIM_CLI_DEFAULT_HOST,
		'user'         => '',
		'skip_plugins' => null, // null: load everything; []: skip all; [names]: skip those.
		'admin'        => true,
		'debug'        => false,
		'verbose'      => false,
		'mode'         => '',   // file | code | stdin
		'script'       => '',
		'code'         => null,
		'script_args'  => array(),
	);

	$end_of_options = false;

	while ( array() !== $argv_in ) {
		$arg = array_shift( $argv_in );

		if ( '' !== $options['mode'] ) {
			$options['script_args'][] = $arg;
			continue;
		}

		if ( $end_of_options ) {
			$options['mode']   = 'file';
			$options['script'] = $arg;
			continue;
		}

		if ( '--' === $arg ) {
			$end_of_options = true;
			continue;
		}
		if ( '-h' === $arg || '--help' === $arg ) {
			fwrite( STDOUT, gadgetim_cli_usage() );
			exit( 0 );
		}
		if ( '-r' === $arg ) {
			$code = array_shift( $argv_in );
			if ( null === $code ) {
				gadgetim_cli_err( 'خطا: بعد از -r باید کد PHP بیاید.' );
				exit( 1 );
			}
			$options['mode'] = 'code';
			$options['code'] = $code;
			continue;
		}
		if ( '-' === $arg ) {
			$options['mode'] = 'stdin';
			continue;
		}
		if ( str_starts_with( $arg, '--path=' ) ) {
			$options['path'] = substr( $arg, 7 );
			continue;
		}
		if ( str_starts_with( $arg, '--host=' ) ) {
			$options['host'] = substr( $arg, 7 );
			continue;
		}
		if ( str_starts_with( $arg, '--user=' ) ) {
			$options['user'] = substr( $arg, 7 );
			continue;
		}
		if ( '--skip-plugins' === $arg ) {
			$options['skip_plugins'] = array();
			continue;
		}
		if ( str_starts_with( $arg, '--skip-plugins=' ) ) {
			$list                    = array_map( 'trim', explode( ',', substr( $arg, 15 ) ) );
			$options['skip_plugins'] = array_values( array_filter( $list, 'strlen' ) );
			continue;
		}
		if ( '--no-admin' === $arg ) {
			$options['admin'] = false;
			continue;
		}
		if ( '--debug' === $arg ) {
			$options['debug'] = true;
			continue;
		}
		if ( '--verbose' === $arg ) {
			$options['verbose'] = true;
			continue;
		}
		if ( str_starts_with( $arg, '-' ) ) {
			gadgetim_cli_err( 'خطا: گزینهٴ ناشناخته ' . $arg );
			gadgetim_cli_err( gadgetim_cli_usage() );
			exit( 1 );
		}

		$options['mode']   = 'file';
		$options['script'] = $arg;
	}

	if ( '' === $options['mode'] ) {
		gadgetim_cli_err( 'خطا: اسکریپت یا کدی برای اجرا داده نشده.' );
		gadgetim_cli_err( gadgetim_cli_usage() );
		exit( 1 );
	}

	return $options;
}

/**
 * Writes inline code to a temporary PHP file so it can be included like a script
 * (file/line numbers in error messages; no eval()). Returns '' on failure.
 */
function gadgetim_cli_write_temp( string $code ): string {
	$trimmed = ltrim( $code );
	if ( ! str_starts_with( $trimmed, '<?' ) ) {
		$code = "<?php\n" . $code;
	}

	$dirs = array( sys_get_temp_dir(), __DIR__ . '/wp-cli' );
	foreach ( $dirs as $dir ) {
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0777, true ) ) {
			continue;
		}
		if ( ! is_writable( $dir ) ) {
			continue;
		}
		$file = rtrim( $dir, '/\\' ) . '/gadgetim-cli-' . bin2hex( random_bytes( 6 ) ) . '.php';
		if ( false !== @file_put_contents( $file, $code, LOCK_EX ) ) {
			return $file;
		}
	}

	gadgetim_cli_err( 'خطا: فایل موقت برای کد نوشته نشد (' . implode( ', ', $dirs ) . ').' );
	return '';
}

/**
 * Converts a php.ini shorthand size (128M, 1G, -1) to bytes.
 */
function gadgetim_cli_to_bytes( string $value ): int {
	$value = trim( $value );
	if ( '' === $value ) {
		return 0;
	}
	$number = (int) $value;
	return match ( strtolower( substr( $value, -1 ) ) ) {
		'g'     => $number * 1024 * 1024 * 1024,
		'm'     => $number * 1024 * 1024,
		'k'     => $number * 1024,
		default => $number,
	};
}

/**
 * Raises memory_limit to $target when the current limit is lower (never lowers it).
 */
function gadgetim_cli_raise_memory_limit( string $target ): void {
	$current = (string) ini_get( 'memory_limit' );
	if ( '-1' === trim( $current ) ) {
		return;
	}
	if ( gadgetim_cli_to_bytes( $current ) >= gadgetim_cli_to_bytes( $target ) ) {
		return;
	}
	@ini_set( 'memory_limit', $target );
}

/**
 * Registers a hook before WordPress loads. wp-includes/plugin.php turns a pre-filled
 * $wp_filter array into WP_Hook objects (WP_Hook::build_preinitialized_hooks). Local's
 * auto-prepended bootstrap uses the same mechanism, so this merges and never overwrites.
 */
function gadgetim_cli_add_hook( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	if ( ! isset( $GLOBALS['wp_filter'] ) || ! is_array( $GLOBALS['wp_filter'] ) ) {
		$GLOBALS['wp_filter'] = array();
	}
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ][ $priority ] ) ) {
		$GLOBALS['wp_filter'][ $hook ][ $priority ] = array();
	}
	$id = 'gadgetim_cli_' . count( $GLOBALS['wp_filter'][ $hook ][ $priority ] );

	$GLOBALS['wp_filter'][ $hook ][ $priority ][ $id ] = array(
		'function'      => $callback,
		'accepted_args' => $accepted_args,
	);
}

/**
 * wp_die() handler for the terminal: plain text on STDERR instead of an HTML page
 * (this is what you see when the database is down: "Error establishing a database connection").
 *
 * @param string|\WP_Error $message Message or error object.
 * @param string|int       $title   Title (unused by WordPress for WP_Error, kept for the signature).
 * @param array            $args    wp_die() arguments; honours 'exit'.
 */
function gadgetim_cli_die_handler( $message, $title = '', $args = array() ): void {
	if ( function_exists( 'is_wp_error' ) && is_wp_error( $message ) ) {
		$message = implode( "\n", $message->get_error_messages() );
	}
	$text = (string) $message;
	$text = preg_replace( '~<br\s*/?>|</p>|</h[1-6]>|</li>~i', "\n", $text );
	$text = html_entity_decode( strip_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );

	$title = is_string( $title ) ? trim( strip_tags( $title ) ) : '';
	if ( '' !== $title ) {
		$text = $title . "\n" . $text;
	}

	gadgetim_cli_err( 'wp_die: ' . $text );

	if ( ! is_array( $args ) || ! isset( $args['exit'] ) || $args['exit'] ) {
		exit( 1 );
	}
}

/**
 * Builds the superglobals of a plain GET request to the site's front page.
 */
function gadgetim_cli_fake_request( string $wp_root, string $host ): void {
	$port = '80';
	if ( str_contains( $host, ':' ) ) {
		list( $host, $port ) = explode( ':', $host, 2 );
	}

	$_SERVER['HTTP_HOST']       = $host;
	$_SERVER['SERVER_NAME']     = $host;
	$_SERVER['SERVER_PORT']     = $port;
	$_SERVER['REQUEST_URI']     = '/';
	$_SERVER['REQUEST_METHOD']  = 'GET';
	$_SERVER['QUERY_STRING']    = '';
	$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
	$_SERVER['SCRIPT_NAME']     = '/index.php';
	$_SERVER['PHP_SELF']        = '/index.php';
	$_SERVER['SCRIPT_FILENAME'] = $wp_root . '/index.php';
	$_SERVER['DOCUMENT_ROOT']   = $wp_root;
	$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
	$_SERVER['HTTP_USER_AGENT'] = 'gadgetim-cli';
	unset( $_SERVER['HTTPS'] );
}

/**
 * Everything that must happen before wp-load.php: paths, constants, hooks, a database probe.
 * Returns the options with the resolved 'wp_root' and 'script' keys. Exits on hard errors.
 */
function gadgetim_cli_prepare( array $options ): array {
	$wp_root = rtrim( str_replace( '\\', '/', (string) $options['path'] ), '/' );
	if ( '' === $wp_root || ! is_file( $wp_root . '/wp-load.php' ) ) {
		gadgetim_cli_err( 'خطا: wp-load.php در این مسیر پیدا نشد: ' . ( '' === $wp_root ? '(خالی)' : $wp_root ) );
		gadgetim_cli_err( 'مسیر را با --path=<dir> یا متغیر GADGETIM_WP_PATH بده.' );
		exit( 2 );
	}
	$options['wp_root'] = $wp_root;

	if ( 'file' === $options['mode'] ) {
		$real = realpath( $options['script'] );
		if ( false === $real || ! is_file( $real ) ) {
			gadgetim_cli_err( 'خطا: اسکریپت پیدا نشد: ' . $options['script'] );
			exit( 3 );
		}
		$options['script'] = $real;
	} else {
		if ( 'stdin' === $options['mode'] ) {
			if ( stream_isatty( STDIN ) ) {
				gadgetim_cli_err( 'خطا: کدی روی STDIN نیست (مثال: php ... wp-bootstrap.php - < code.php).' );
				exit( 1 );
			}
			$options['code'] = (string) stream_get_contents( STDIN );
		}
		if ( '' === trim( (string) $options['code'] ) ) {
			gadgetim_cli_err( 'خطا: کد خالی است.' );
			exit( 1 );
		}
		$temp = gadgetim_cli_write_temp( (string) $options['code'] );
		if ( '' === $temp ) {
			exit( 1 );
		}
		$options['script'] = $temp;
		register_shutdown_function(
			static function () use ( $temp ): void {
				if ( is_file( $temp ) ) {
					@unlink( $temp );
				}
			}
		);
	}

	gadgetim_cli_fake_request( $wp_root, (string) $options['host'] );

	if ( ! defined( 'FS_METHOD' ) ) {
		define( 'FS_METHOD', 'direct' );
	}
	if ( $options['debug'] ) {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}
		if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
			define( 'WP_DEBUG_DISPLAY', true );
		}
	}

	// Errors belong on STDERR so a script's STDOUT stays parseable.
	ini_set( 'display_errors', 'stderr' );
	gadgetim_cli_raise_memory_limit( GADGETIM_CLI_MEMORY_LIMIT );

	gadgetim_cli_add_hook(
		'wp_die_handler',
		static function (): string {
			return 'gadgetim_cli_die_handler';
		}
	);

	if ( null !== $options['skip_plugins'] ) {
		$skip = $options['skip_plugins'];
		gadgetim_cli_add_hook(
			'option_active_plugins',
			static function ( $plugins ) use ( $skip ) {
				if ( ! is_array( $plugins ) ) {
					return $plugins;
				}
				if ( array() === $skip ) {
					return array();
				}
				return array_values(
					array_filter(
						$plugins,
						static function ( $basename ) use ( $skip ): bool {
							$basename = (string) $basename;
							return ! in_array( $basename, $skip, true )
								&& ! in_array( dirname( $basename ), $skip, true )
								&& ! in_array( basename( $basename, '.php' ), $skip, true );
						}
					)
				);
			}
		);
	}

	// Local serves MySQL on a per-machine port written into the rendered php.ini; a closed port
	// almost always means the site is stopped in Local. Warn early, before WordPress dies on it.
	$db_port = (int) ini_get( 'mysqli.default_port' );
	if ( $db_port > 0 && 3306 !== $db_port ) {
		$socket = @fsockopen( '127.0.0.1', $db_port, $errno, $errstr, 1.5 );
		if ( false === $socket ) {
			gadgetim_cli_err( 'هشدار: هیچ سرویسی روی 127.0.0.1:' . $db_port . ' گوش نمی‌دهد — سایت در Local روشن است؟' );
		} else {
			fclose( $socket );
		}
	}

	return $options;
}

/**
 * Everything that happens after WordPress is loaded: admin includes, current user, summary.
 */
function gadgetim_cli_after_load( array $options ): void {
	if ( $options['admin'] ) {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
	}

	// wp_debug_mode() may have switched display_errors back to STDOUT.
	ini_set( 'display_errors', 'stderr' );

	if ( '' !== $options['user'] ) {
		$user = is_numeric( $options['user'] ) ? get_user_by( 'id', (int) $options['user'] ) : get_user_by( 'login', $options['user'] );
		if ( ! $user && ! is_numeric( $options['user'] ) ) {
			$user = get_user_by( 'email', $options['user'] );
		}
		if ( ! $user ) {
			gadgetim_cli_err( 'خطا: کاربر پیدا نشد: ' . $options['user'] );
			exit( 1 );
		}
		wp_set_current_user( $user->ID );
	}

	if ( $options['verbose'] ) {
		$parts = array( 'WordPress ' . get_bloginfo( 'version' ) );
		if ( defined( 'WC_VERSION' ) ) {
			$parts[] = 'WooCommerce ' . WC_VERSION;
		}
		$parts[] = 'پوسته: ' . wp_get_theme()->get( 'Name' );
		$parts[] = 'کاربر: ' . ( get_current_user_id() ? wp_get_current_user()->user_login : '-' );
		$parts[] = 'ریشه: ' . ABSPATH;
		gadgetim_cli_err( 'بارگذاری شد — ' . implode( ' · ', $parts ) );
	}
}

// ---------------------------------------------------------------------------------------------
// Main. WordPress is loaded in the global scope on purpose (wp-settings.php expects it), and the
// user's script is included in the same scope so that $wpdb, $wp_query, ... are plain globals.
// ---------------------------------------------------------------------------------------------

$gadgetim_cli = gadgetim_cli_prepare( gadgetim_cli_parse_args( $_SERVER['argv'] ?? array() ) );

require $gadgetim_cli['wp_root'] . '/wp-load.php';

gadgetim_cli_after_load( $gadgetim_cli );

$gadgetim_cli_script = $gadgetim_cli['script'];
$argv                = array_merge( array( 'file' === $gadgetim_cli['mode'] ? $gadgetim_cli_script : '-' ), $gadgetim_cli['script_args'] );
$argc                = count( $argv );
$_SERVER['argv']     = $argv;
$_SERVER['argc']     = $argc;
unset( $gadgetim_cli );

require $gadgetim_cli_script;
