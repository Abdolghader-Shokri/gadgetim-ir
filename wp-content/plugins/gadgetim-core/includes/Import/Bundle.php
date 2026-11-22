<?php
/**
 * One-file updates (owner, 2026-11-22: every update arrives as one zip that is put on the host once,
 * then the dashboard is opened once and everything is done). tools/build-update-bundle.php packs
 * the release zips of the plugin and the theme and any drop packages (content, products, page
 * images, media) into gadgetim-update-<version>-<date>.zip. The owner puts it into
 * wp-content/gadgetim-drop/ with the host's file manager and opens the dashboard.
 *
 * gadgetim-update.json (format "gadgetim-update", version 1) at the root of the zip lists:
 * - plugin and theme { version, file, sha256 }: the zips tools/build-release.sh made from the tag;
 * - drops[] { file, sha256 }: packages for Drop, imported after the code.
 *
 * On a dashboard page load (GET) by a user who may install plugins and themes, the oldest waiting
 * update package is applied:
 * 1. Every file is checked against its SHA-256, and the plugin and theme against the version in
 *    their own headers.
 * 2. A version older than the installed one refuses the whole package; the same version is skipped.
 * 3. A newer plugin or theme is installed with WordPress's own upgraders, the way "Replace current
 *    with uploaded" does, and the old copy waits in wp-content/upgrade-temp-backup/ until the end
 *    of the request (WordPress 6.6+ keeps it there for an automatic update the same way).
 * 4. The server loads the home page and the dashboard with WordPress's fatal-error scrape (the check
 *    the plugin and theme editors make before they keep a change). When the new code stops the
 *    site with a fatal error, the old copies are moved back and nothing else happens.
 * 5. The drop packages move into the drop folder, the update package into done/ (failed/ when
 *    refused), and when code changed the browser is sent to the same page: the next request runs
 *    the new code, which imports the content and shows its usual notice.
 *
 * Nothing is applied from WP-Cron, by a shop manager without the install capabilities, during a
 * POST or on the upload and update screens. Code is never installed over a plugin or theme folder
 * that is a link: the Local development site links both to the repository, and WordPress's backup
 * move and delete would follow the link into the working tree.
 *
 * Plugin versions before 1.4.0 read every zip in the folder as a drop package and move an update
 * package into failed/ ("manifest.json is missing"). Such a package, never seen here, is taken back
 * from failed/ once.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Automatic_Upgrader_Skin;
use Plugin_Upgrader;
use Theme_Upgrader;
use WP_Error;
use WP_Upgrader;
use ZipArchive;

/**
 * Applies update packages dropped into the drop folder.
 */
final class Bundle {

	public const MANIFEST = 'gadgetim-update.json';

	public const FORMAT = 'gadgetim-update';

	public const VERSION = 1;

	/** Applied packages: sha256 => { time, release, status, file (name in done/ or failed/) }. */
	public const SEEN = 'gadgetim_drop_bundles';

	/** The code a package may carry: folder name, file with the Version header, upgrade-temp-backup dir. */
	public const CODE = [
		'plugin' => [
			'slug'   => 'gadgetim-core',
			'header' => 'gadgetim-core.php',
			'dir'    => 'plugins',
		],
		'theme'  => [
			'slug'   => 'gadgetim',
			'header' => 'style.css',
			'dir'    => 'themes',
		],
	];

	/** Dashboard screens that never apply a package: uploads and updates in progress. */
	private const SKIP_PAGES = [ 'update.php', 'update-core.php', 'admin-post.php', 'admin-ajax.php', 'async-upload.php', 'media-upload.php', 'customize.php', 'plugin-install.php', 'theme-install.php' ];

	/** How many applied packages SEEN remembers. */
	private const KEEP = 50;

	/**
	 * admin_init (from Drop::maybe_scan): applies the oldest waiting update package. When it changed
	 * the plugin or the theme, the browser is sent to the same page and nothing else runs in this
	 * request: the old code is still in memory and the new one on disk.
	 */
	public static function maybe_apply(): void {
		if ( ! self::may_apply() ) {
			return;
		}
		$path = self::next();
		if ( null === $path || get_transient( Drop::LOCK ) ) {
			return;
		}
		set_transient( Drop::LOCK, 1, 15 * MINUTE_IN_SECONDS );
		$result = self::apply( $path );
		delete_transient( Drop::LOCK );
		Drop::remember( $result );

		if ( ! empty( $result['code_changed'] ) ) {
			wp_safe_redirect( self::here() );
			exit;
		}
	}

	/**
	 * Whether this request may apply a package: a plain dashboard page (GET) of a user who may
	 * install plugins and themes, and not the fatal-error check of an update in progress.
	 */
	private static function may_apply(): bool {
		global $pagenow;
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		return is_admin()
			&& 'GET' === $method
			&& ! Drop::speculative()
			&& ! wp_doing_ajax()
			&& ! wp_doing_cron()
			&& ! isset( $_GET['wp_scrape_key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only tells the update's own check apart.
			&& ! in_array( (string) $pagenow, self::SKIP_PAGES, true )
			&& current_user_can( 'install_plugins' )
			&& current_user_can( 'install_themes' );
	}

	/**
	 * The page being shown, to come back to after the code changed (the dashboard when it cannot be
	 * rebuilt or points elsewhere).
	 */
	private static function here(): string {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$url  = '' !== $host && str_starts_with( $uri, '/' ) ? set_url_scheme( 'http://' . $host . $uri ) : '';
		return wp_validate_redirect( $url, admin_url() );
	}

	/**
	 * Whether a zip is an update package: it has gadgetim-update.json at its root.
	 */
	public static function is_bundle( string $path ): bool {
		static $known = [];
		if ( ! str_ends_with( strtolower( $path ), '.zip' ) || ! is_file( $path ) || ! class_exists( ZipArchive::class ) ) {
			return false;
		}
		$key = $path . '|' . (int) filesize( $path ) . '|' . (int) filemtime( $path );
		if ( ! isset( $known[ $key ] ) ) {
			$zip           = new ZipArchive();
			$open          = true === $zip->open( $path );
			$known[ $key ] = $open && false !== $zip->locateName( self::MANIFEST );
			if ( $open ) {
				$zip->close();
			}
		}
		return $known[ $key ];
	}

	/**
	 * The oldest waiting update package (by build time, then name), or null. A package that a plugin
	 * version before 1.4.0 moved into failed/ comes back to the drop folder first.
	 */
	public static function next(): ?string {
		$dir   = Drop::dir();
		$files = array_column( (array) get_option( self::SEEN, [] ), 'file' );
		foreach ( glob( $dir . '/failed/*.zip' ) ?: [] as $old ) {
			$name = (string) preg_replace( '/^\d{8}-\d{6}-/', '', basename( $old ) );
			if ( str_contains( $name, 'gadgetim-update' ) && ! in_array( basename( $old ), $files, true )
				&& ! file_exists( $dir . '/' . $name ) && self::is_bundle( $old )
				&& @rename( $old, $dir . '/' . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a file that stays is simply not taken back.
				self::forget_old_failure( $name );
			}
		}

		$found = [];
		foreach ( glob( $dir . '/*.zip' ) ?: [] as $path ) {
			if ( self::is_bundle( $path ) ) {
				$found[ $path ] = self::built( $path ) . '|' . basename( $path );
			}
		}
		if ( ! $found ) {
			return null;
		}
		asort( $found, SORT_STRING );
		return (string) array_key_first( $found );
	}

	/**
	 * Hides the red notice an older plugin version left for a package it could not read ("manifest.json
	 * is missing"): the package is being applied now and gets its own notice.
	 */
	private static function forget_old_failure( string $name ): void {
		$all     = (array) get_option( Drop::RESULTS, [] );
		$changed = false;
		foreach ( $all as $k => $r ) {
			if ( is_array( $r ) && $name === ( $r['package'] ?? '' ) && empty( $r['items'] ) && empty( $r['seen'] ) && 'update' !== ( $r['kind'] ?? '' ) ) {
				$all[ $k ]['seen'] = true;
				$changed           = true;
			}
		}
		if ( $changed ) {
			update_option( Drop::RESULTS, $all, false );
		}
	}

	/**
	 * Build time written in a package's gadgetim-update.json ('' when unreadable).
	 */
	private static function built( string $path ): string {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return '';
		}
		$data = json_decode( (string) $zip->getFromName( self::MANIFEST ), true );
		$zip->close();
		return is_array( $data ) ? (string) ( $data['built'] ?? '' ) : '';
	}

	/**
	 * Applies one update package and moves it into done/ or failed/.
	 *
	 * @return array Result in Drop's format (package, time, items, errors, seen) plus kind "update"
	 *               and code_changed.
	 */
	public static function apply( string $path ): array {
		$result = [
			'package'      => basename( $path ),
			'time'         => time(),
			'items'        => [],
			'errors'       => [],
			'seen'         => false,
			'kind'         => 'update',
			'code_changed' => false,
		];
		$sha    = (string) hash_file( 'sha256', $path );
		$seen   = (array) get_option( self::SEEN, [] );
		if ( 'done' === ( $seen[ $sha ]['status'] ?? '' ) ) {
			/* translators: %s: date and time */
			$result['errors'][] = sprintf( __( 'This update package was already applied on %s; nothing was done again.', 'gadgetim-core' ), wp_date( 'Y-m-d H:i', (int) ( $seen[ $sha ]['time'] ?? 0 ) ) );
			$archived           = Drop::archive( $path, true, 'done' );
			self::remember( $sha, (string) ( $seen[ $sha ]['release'] ?? '' ), 'done', $archived );
			Drop::log( $result );
			return $result;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- not allowed on every host.
		}
		$work   = Drop::dir() . '/work/' . substr( $sha, 0, 16 );
		$plan   = self::unpack( $path, $work, $result['errors'] );
		$status = null !== $plan && self::install( $plan, $result ) ? 'done' : 'failed';
		self::remove_work( $work );

		$archived = Drop::archive( $path, true, $status );
		self::remember( $sha, (string) ( $plan['release'] ?? '' ), $status, $archived );
		Drop::log( $result );
		return $result;
	}

	/**
	 * Checks a package and copies its files into $work.
	 *
	 * @param string[] $errors Error list.
	 * @return array|null { release, drops: string[], plugin?|theme?: { path, version } }; null when
	 *                    refused.
	 */
	private static function unpack( string $path, string $work, array &$errors ): ?array {
		if ( ! class_exists( ZipArchive::class ) ) {
			$errors[] = __( 'PHP zip support is not available on this server: install the plugin and the theme from the dashboard instead.', 'gadgetim-core' );
			return null;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			$errors[] = __( 'The zip file could not be opened.', 'gadgetim-core' );
			return null;
		}
		$data = json_decode( (string) $zip->getFromName( self::MANIFEST ), true );
		if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) || self::VERSION !== (int) ( $data['version'] ?? 0 ) ) {
			$zip->close();
			$errors[] = __( 'gadgetim-update.json is missing or not a version 1 update package.', 'gadgetim-core' );
			return null;
		}

		$entries = [];
		foreach ( array_keys( self::CODE ) as $kind ) {
			if ( is_array( $data[ $kind ] ?? null ) ) {
				$entries[ $kind ] = $data[ $kind ];
			}
		}
		foreach ( array_values( (array) ( $data['drops'] ?? [] ) ) as $n => $drop ) {
			if ( is_array( $drop ) ) {
				$entries[ 'drop' . $n ] = $drop;
			}
		}
		if ( ! $entries ) {
			$zip->close();
			$errors[] = __( 'The update package lists no plugin, theme or content.', 'gadgetim-core' );
			return null;
		}

		$plan = [
			'release' => sanitize_text_field( (string) ( $data['release'] ?? '' ) ),
			'drops'   => [],
		];
		foreach ( $entries as $key => $entry ) {
			$file  = (string) ( $entry['file'] ?? '' );
			$local = self::extract( $zip, $file, $work );
			if ( null === $local || ! hash_equals( strtolower( (string) ( $entry['sha256'] ?? '' ) ), (string) hash_file( 'sha256', $local ) ) ) {
				$zip->close();
				/* translators: %s: file inside the update package */
				$errors[] = sprintf( __( 'The update package is damaged: %s does not match its checksum.', 'gadgetim-core' ), $file );
				return null;
			}
			if ( ! isset( self::CODE[ $key ] ) ) {
				$plan['drops'][] = $local;
				continue;
			}
			$spec    = self::CODE[ $key ];
			$version = self::zip_version( $local, $spec['slug'] . '/' . $spec['header'] );
			$want    = (string) ( $entry['version'] ?? '' );
			if ( '' === $version || $version !== $want ) {
				$zip->close();
				/* translators: 1: plugin or theme name, 2: version number */
				$errors[] = sprintf( __( '%1$s in the update package is not version %2$s.', 'gadgetim-core' ), self::label( $key ), $want );
				return null;
			}
			$plan[ $key ] = [
				'path'    => $local,
				'version' => $version,
			];
		}
		$zip->close();
		return $plan;
	}

	/**
	 * Copies one file of the package into $work; null when its name is not allowed or it is missing.
	 */
	private static function extract( ZipArchive $zip, string $file, string $work ): ?string {
		if ( ! preg_match( '#^(plugin|theme|drops)/[A-Za-z0-9._-]+\.zip$#', $file ) ) {
			return null;
		}
		$local = $work . '/' . $file;
		if ( ! wp_mkdir_p( dirname( $local ) ) ) {
			return null;
		}
		$in = $zip->getStream( $file );
		if ( false === $in ) {
			return null;
		}
		$out = @fopen( $local, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- result checked.
		if ( false === $out ) {
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- zip stream.
			return null;
		}
		stream_copy_to_stream( $in, $out );
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- zip stream.
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- local file.
		return $local;
	}

	/**
	 * The Version header of $entry inside the zip at $path ('' when there is none).
	 */
	public static function zip_version( string $path, string $entry ): string {
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
	 * Installs the newer code and moves the drop packages into the drop folder.
	 *
	 * @param array $plan   From unpack().
	 * @param array $result Package result.
	 * @return bool False when the package was refused or its code was taken back.
	 */
	private static function install( array $plan, array &$result ): bool {
		$todo = [];
		foreach ( array_keys( self::CODE ) as $kind ) {
			if ( empty( $plan[ $kind ] ) ) {
				continue;
			}
			$new = (string) $plan[ $kind ]['version'];
			$old = self::installed_version( $kind );
			if ( '' !== $old && version_compare( $new, $old, '<' ) ) {
				/* translators: 1: plugin or theme name, 2: version in the package, 3: installed version */
				$result['errors'][] = sprintf( __( 'The update package holds %1$s %2$s, older than the installed %3$s: nothing was changed.', 'gadgetim-core' ), self::label( $kind ), $new, $old );
				return false;
			}
			if ( '' !== $old && version_compare( $new, $old, '=' ) ) {
				$result['items'][] = self::item( $kind, 'code_same', $old, $new );
				continue;
			}
			$todo[ $kind ] = $plan[ $kind ] + [ 'old' => $old ];
		}

		if ( $todo ) {
			$why = self::cannot_install();
			if ( '' !== $why ) {
				$result['errors'][] = $why;
				return false;
			}
			if ( ! self::install_code( $todo, $result ) ) {
				return false;
			}
			$result['code_changed'] = true;
		}

		foreach ( $plan['drops'] as $local ) {
			$name = basename( $local );
			$dest = Drop::dir() . '/' . $name;
			if ( ( is_file( $dest ) && ! @unlink( $dest ) ) || ! @rename( $local, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.rename_rename -- result checked.
				/* translators: %s: package file name */
				$result['errors'][] = sprintf( __( '%s could not be put into the drop folder.', 'gadgetim-core' ), $name );
				continue;
			}
			$result['items'][] = [
				'id'     => 0,
				'sku'    => '',
				'title'  => $name,
				'action' => 'queued',
				'url'    => '',
			];
		}
		return true;
	}

	/**
	 * Version of the installed plugin or theme ('' when it is not installed).
	 */
	public static function installed_version( string $kind ): string {
		$spec = self::CODE[ $kind ];
		if ( 'plugin' === $kind ) {
			$file = WP_PLUGIN_DIR . '/' . $spec['slug'] . '/' . $spec['header'];
			return is_file( $file ) ? trim( (string) ( get_file_data( $file, [ 'Version' => 'Version' ] )['Version'] ?? '' ) ) : '';
		}
		$theme = wp_get_theme( $spec['slug'] );
		return $theme->exists() ? trim( (string) $theme->get( 'Version' ) ) : '';
	}

	/**
	 * Why code cannot be installed here ('' when it can).
	 */
	private static function cannot_install(): string {
		if ( ! wp_is_file_mod_allowed( 'gadgetim_update_package' ) ) {
			return __( 'This site does not allow installing plugins and themes (DISALLOW_FILE_MODS), so the update package was not applied.', 'gadgetim-core' );
		}
		foreach ( self::CODE as $kind => $spec ) {
			if ( self::is_link( self::code_root( $kind ) . '/' . $spec['slug'] ) ) {
				return __( 'The plugin or theme folder is a link to a development copy, so code is never installed over it.', 'gadgetim-core' );
			}
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( 'direct' !== get_filesystem_method( [], WP_CONTENT_DIR ) ) {
			return __( 'The host asks for FTP details to change files, so the plugin and the theme cannot be installed from the drop folder: install them from the dashboard instead.', 'gadgetim-core' );
		}
		return '';
	}

	/**
	 * Folder that holds the plugin or the theme.
	 */
	private static function code_root( string $kind ): string {
		return 'plugin' === $kind ? WP_PLUGIN_DIR : (string) get_theme_root( self::CODE['theme']['slug'] );
	}

	/**
	 * Whether a folder is a symbolic link or a Windows junction (its real path is somewhere else).
	 */
	public static function is_link( string $dir ): bool {
		if ( ! file_exists( $dir ) ) {
			return false;
		}
		if ( is_link( $dir ) ) {
			return true;
		}
		$real   = realpath( $dir );
		$parent = realpath( dirname( $dir ) );
		if ( false === $real || false === $parent ) {
			return true;
		}
		$same = wp_normalize_path( $real ) === wp_normalize_path( $parent . '/' . basename( $dir ) );
		if ( ! $same && '\\' === DIRECTORY_SEPARATOR ) {
			$same = strtolower( wp_normalize_path( $real ) ) === strtolower( wp_normalize_path( $parent . '/' . basename( $dir ) ) );
		}
		return ! $same;
	}

	/**
	 * Installs the plugin and/or the theme, checks the site and takes the code back on a fatal error.
	 *
	 * @param array $todo   kind => { path, version, old }.
	 * @param array $result Package result.
	 */
	private static function install_code( array $todo, array &$result ): bool {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		wp_raise_memory_limit( 'admin' );

		$done = [];
		foreach ( $todo as $kind => $code ) {
			$spec   = self::CODE[ $kind ];
			$root   = self::code_root( $kind );
			$backup = is_dir( $root . '/' . $spec['slug'] ) ? [
				'slug' => $spec['slug'],
				'src'  => $root,
				'dir'  => $spec['dir'],
			] : null;
			$skin   = new Automatic_Upgrader_Skin();
			$up     = 'plugin' === $kind ? new Plugin_Upgrader( $skin ) : new Theme_Upgrader( $skin );
			// Keep the old copy the way an automatic update does, so a failed install is put back.
			$keep = static function ( array $options ) use ( $backup ): array {
				if ( null !== $backup && 'install' === ( $options['hook_extra']['action'] ?? '' ) ) {
					$options['hook_extra']['temp_backup'] = $backup;
				}
				return $options;
			};
			add_filter( 'upgrader_package_options', $keep );
			$ok = $up->install( (string) $code['path'], [ 'overwrite_package' => true ] );
			remove_filter( 'upgrader_package_options', $keep );

			if ( true !== $ok ) {
				// WordPress puts this one's old copy back on shutdown; the ones installed before it go back now.
				self::restore( $done );
				/* translators: 1: plugin or theme name, 2: error message */
				$result['errors'][] = sprintf( __( 'Installing %1$s failed, so nothing was changed: %2$s', 'gadgetim-core' ), self::label( $kind ), self::upgrader_error( $ok, $skin ) );
				return false;
			}
			$done[ $kind ]     = [
				'upgrader' => $up,
				'backup'   => $backup,
			];
			$result['items'][] = self::item( $kind, 'code_updated', (string) $code['old'], (string) $code['version'] );
		}

		$check = self::health();
		if ( 'fatal' === $check['state'] ) {
			$restored        = self::restore( $done );
			$result['items'] = array_values( array_filter( $result['items'], static fn( array $i ): bool => 'code_updated' !== ( $i['action'] ?? '' ) ) );
			/* translators: %s: PHP error message */
			$result['errors'][] = sprintf( __( 'The new version stopped the site with an error, so the previous version was put back: %s', 'gadgetim-core' ), $check['message'] );
			if ( ! $restored ) {
				$result['errors'][] = __( 'Putting the previous version back failed: upload the previous plugin and theme from the dashboard.', 'gadgetim-core' );
			}
			return false;
		}
		if ( 'unknown' === $check['state'] ) {
			/* translators: %s: what the check got back */
			$result['errors'][] = sprintf( __( 'The site could not be loaded from the server after the update (%s): open the home page once to make sure it works.', 'gadgetim-core' ), $check['message'] );
		}
		return true;
	}

	/**
	 * Moves the old copies kept by the upgraders back into place.
	 *
	 * @param array $done kind => { upgrader: WP_Upgrader, backup: array|null }.
	 * @return bool False when a copy could not be put back.
	 */
	private static function restore( array $done ): bool {
		$ok = true;
		foreach ( $done as $kind => $d ) {
			if ( null === $d['backup'] || ! $d['upgrader'] instanceof WP_Upgrader ) {
				continue;
			}
			$back = $d['upgrader']->restore_temp_backup( [ $d['backup'] ] );
			$ok   = $ok && true === $back;
			if ( function_exists( 'wp_opcache_invalidate_directory' ) ) {
				wp_opcache_invalidate_directory( self::code_root( (string) $kind ) . '/' . self::CODE[ $kind ]['slug'] );
			}
		}
		wp_clean_plugins_cache();
		wp_clean_themes_cache();
		return $ok;
	}

	/**
	 * The message of a failed install.
	 *
	 * @param mixed                   $ok   What the upgrader returned.
	 * @param Automatic_Upgrader_Skin $skin Its skin.
	 */
	private static function upgrader_error( $ok, Automatic_Upgrader_Skin $skin ): string {
		if ( $ok instanceof WP_Error ) {
			return $ok->get_error_message();
		}
		$messages = array_filter( array_map( 'trim', array_map( 'wp_strip_all_tags', (array) $skin->get_upgrade_messages() ) ) );
		return $messages ? implode( ' ', array_slice( $messages, -3 ) ) : __( 'unknown error', 'gadgetim-core' );
	}

	/**
	 * Loads the home page and the dashboard from the server with WordPress's fatal-error scrape.
	 *
	 * @return array{state:string,message:string} state: ok, fatal, or unknown when a page did not
	 *                                             come back with the scrape result (loopback blocked,
	 *                                             a cache in front, a timeout).
	 */
	private static function health(): array {
		if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
			session_write_close(); // An open session would block the loopback request.
		}
		$state   = 'ok';
		$message = '';
		foreach ( [ home_url( '/' ), admin_url( 'index.php' ) ] as $n => $url ) {
			$key   = md5( wp_generate_password( 32, false ) );
			$nonce = wp_generate_password( 32, false );
			set_transient( 'scrape_key_' . $key, $nonce, MINUTE_IN_SECONDS );
			$args = [
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => [ 'Cache-Control' => 'no-cache' ],
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false, $url ),
			];
			if ( 1 === $n ) {
				$args['cookies'] = wp_unslash( $_COOKIE );
			}
			if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
				$args['headers']['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HTTP Basic auth, as WordPress's own check sends it.
			}
			$response = wp_remote_get(
				add_query_arg(
					[
						'wp_scrape_key'   => $key,
						'wp_scrape_nonce' => $nonce,
					],
					$url
				),
				$args
			);
			delete_transient( 'scrape_key_' . $key );

			$body  = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
			$start = "###### wp_scraping_result_start:$key ######";
			$at    = strpos( $body, $start );
			if ( false === $at ) {
				$state   = 'unknown';
				$message = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . (int) wp_remote_retrieve_response_code( $response );
				continue;
			}
			$tail = substr( $body, $at + strlen( $start ) );
			$end  = strpos( $tail, "###### wp_scraping_result_end:$key ######" );
			$seen = json_decode( trim( false === $end ? $tail : substr( $tail, 0, $end ) ), true );
			if ( is_array( $seen ) && isset( $seen['type'] ) ) {
				$text = mb_substr( trim( (string) strtok( (string) ( $seen['message'] ?? '' ), "\n" ) ), 0, 300 );
				$file = (string) ( $seen['file'] ?? '' );
				if ( '' !== $file && ! str_contains( $text, $file ) ) {
					$text .= ' (' . $file . ':' . (int) ( $seen['line'] ?? 0 ) . ')';
				}
				return [
					'state'   => 'fatal',
					'message' => $text,
				];
			}
		}
		return [
			'state'   => $state,
			'message' => $message,
		];
	}

	/**
	 * A result line for the plugin or the theme.
	 */
	private static function item( string $kind, string $action, string $from, string $to ): array {
		return [
			'id'     => 0,
			'sku'    => '',
			'title'  => self::label( $kind ),
			'action' => $action,
			'url'    => admin_url( 'plugin' === $kind ? 'plugins.php' : 'themes.php' ),
			'from'   => $from,
			'to'     => $to,
		];
	}

	/**
	 * Name of the plugin or the theme in notices.
	 */
	private static function label( string $kind ): string {
		return 'plugin' === $kind ? __( 'Gadgetim Core plugin', 'gadgetim-core' ) : __( 'Gadgetim theme', 'gadgetim-core' );
	}

	/**
	 * Remembers an applied package so it is never applied or taken back from failed/ twice.
	 */
	private static function remember( string $sha, string $release, string $status, string $archived ): void {
		$seen         = (array) get_option( self::SEEN, [] );
		$seen[ $sha ] = [
			'time'    => time(),
			'release' => $release,
			'status'  => $status,
			'file'    => basename( $archived ),
		];
		uasort( $seen, static fn( $a, $b ): int => (int) ( $b['time'] ?? 0 ) <=> (int) ( $a['time'] ?? 0 ) );
		update_option( self::SEEN, array_slice( $seen, 0, self::KEEP, true ), false );
	}

	/**
	 * Deletes the working copy of a package (only ever a folder under gadgetim-drop/work/).
	 */
	private static function remove_work( string $work ): void {
		$base = wp_normalize_path( Drop::dir() . '/work/' );
		if ( ! is_dir( $work ) || ! str_starts_with( wp_normalize_path( $work ), $base ) || self::is_link( $work ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $work, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- best effort.
			} else {
				@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- best effort.
			}
		}
		@rmdir( $work ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- best effort.
	}
}
