<?php
/**
 * Writes a minified copy, <name>.min.css, next to every linked theme stylesheet in
 * wp-content/themes/gadgetim/assets/css/ (tokens.css is inlined instead). The theme loads the
 * copy when it is at least as new as its source (inc/assets.php, gadgetim_css_file()).
 * Run it after every stylesheet change; tools/lint.sh fails while a copy is stale.
 *
 * The minifier is deliberately conservative: comments go, runs of whitespace become one space,
 * and the space next to { } ; , goes. Strings, unquoted url(...) and escapes are copied as
 * written, and nothing else is rewritten (no colon, combinator or value tricks).
 *
 * Usage: php tools/minify-css.php [--check]
 *   --check  write nothing; exit 1 when a copy is missing or differs from its source (tools/lint.sh)
 */

declare( strict_types=1 );

/**
 * Minified CSS.
 */
function gadgetim_minify_css( string $css ): string {
	$out   = '';
	$len   = strlen( $css );
	$space = false;
	$tight = '{};,';
	$flush = static function () use ( &$out, &$space, $tight ): void {
		if ( $space && '' !== $out && ! str_contains( $tight, substr( $out, -1 ) ) ) {
			$out .= ' ';
		}
		$space = false;
	};

	for ( $i = 0; $i < $len; ) {
		$c = $css[ $i ];

		// Comments separate nothing on their own: the whitespace around them does.
		if ( '/' === $c && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
			$end = strpos( $css, '*/', $i + 2 );
			$i   = false === $end ? $len : $end + 2;
			continue;
		}
		if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c || "\f" === $c ) {
			$space = true;
			++$i;
			continue;
		}
		if ( str_contains( $tight, $c ) ) {
			$space = false;
			$out  .= $c;
			++$i;
			continue;
		}

		$flush();

		// Strings, byte for byte (escapes included).
		if ( '"' === $c || "'" === $c ) {
			$j = $i + 1;
			while ( $j < $len && $css[ $j ] !== $c ) {
				$j += '\\' === $css[ $j ] ? 2 : 1;
			}
			$out .= substr( $css, $i, $j - $i + 1 );
			$i    = $j + 1;
			continue;
		}
		// An escape and the byte it protects.
		if ( '\\' === $c ) {
			$out .= substr( $css, $i, 2 );
			$i   += 2;
			continue;
		}
		// Unquoted url(...), byte for byte.
		if ( ( 'u' === $c || 'U' === $c ) && 0 === strncasecmp( substr( $css, $i, 4 ), 'url(', 4 ) ) {
			$k = $i + 4;
			while ( $k < $len && ( ' ' === $css[ $k ] || "\t" === $css[ $k ] || "\n" === $css[ $k ] ) ) {
				++$k;
			}
			if ( $k < $len && '"' !== $css[ $k ] && "'" !== $css[ $k ] ) {
				$end  = strpos( $css, ')', $k );
				$end  = false === $end ? $len - 1 : $end;
				$out .= substr( $css, $i, $end - $i + 1 );
				$i    = $end + 1;
				continue;
			}
		}

		$out .= $c;
		++$i;
	}
	return trim( $out ) . "\n";
}

$check = in_array( '--check', array_slice( $argv, 1 ), true );
$dir   = dirname( __DIR__ ) . '/wp-content/themes/gadgetim/assets/css';
$stale = [];
$saved = 0;
$total = 0;

foreach ( glob( $dir . '/*.css' ) ?: [] as $source ) {
	// tokens.css is inlined in <head> (gadgetim_inline_tokens() strips it there), never linked.
	if ( str_ends_with( $source, '.min.css' ) || 'tokens.css' === basename( $source ) ) {
		continue;
	}
	$css  = (string) file_get_contents( $source );
	$min  = gadgetim_minify_css( $css );
	$file = substr( $source, 0, -4 ) . '.min.css';
	if ( $check ) {
		if ( ! is_file( $file ) || (string) file_get_contents( $file ) !== $min ) {
			$stale[] = basename( $file );
		}
		continue;
	}
	file_put_contents( $file, $min );
	$total += strlen( $css );
	$saved += strlen( $css ) - strlen( $min );
	printf( "%-22s %7d -> %7d bytes\n", basename( $file ), strlen( $css ), strlen( $min ) );
}

if ( $check ) {
	if ( $stale ) {
		fwrite( STDERR, 'stale or missing: ' . implode( ', ', $stale ) . "\n" );
		exit( 1 );
	}
	echo "all .min.css files match their sources\n";
	exit( 0 );
}
printf( "saved %d of %d bytes (%.0f%%)\n", $saved, $total, $total ? 100 * $saved / $total : 0 );
