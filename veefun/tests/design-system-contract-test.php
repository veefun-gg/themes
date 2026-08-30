<?php

declare(strict_types=1);

$theme_root = dirname( __DIR__ );
$functions  = file_get_contents( $theme_root . '/functions.php' );
$foundation = file_get_contents( $theme_root . '/assets/css/design-system.css' );

if ( false === $functions || false === $foundation ) {
	fwrite( STDERR, "Unable to read the design-system contract files.\n" );
	exit( 1 );
}

$assertions = 0;

$assert_contains = static function ( string $needle, string $haystack, string $message ) use ( &$assertions ): void {
	$assertions++;
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$assert_absent = static function ( string $needle, string $haystack, string $message ) use ( &$assertions ): void {
	$assertions++;
	if ( false !== strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$assert_contains( "'veefun-theme-css'", $functions, 'base theme handle remains present' );
$assert_contains( "array( 'astra-theme-css' )", $functions, 'base theme keeps its Astra dependency' );
$assert_contains( "'veefun-design-system'", $functions, 'foundation handle is registered' );
$assert_contains( "array( 'veefun-theme-css' )", $functions, 'foundation depends on the child-theme stylesheet' );
$assert_contains( "add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 5 )", $functions, 'base and foundation enqueue at priority 5' );
$assert_contains( 'function veefun_enqueue_collector_entrypoint_styles()', $functions, 'Collector enqueue has a dedicated function' );
$assert_contains( "array( 'veefun-design-system' )", $functions, 'Collector depends on the shared foundation' );
$assert_contains( "add_action( 'wp_enqueue_scripts', 'veefun_enqueue_collector_entrypoint_styles', 15 )", $functions, 'Collector enqueue runs at priority 15' );

foreach (
	array(
		'.vf-c-object-identity',
		'.vf-c-relationship-link',
		'.vf-c-evidence-status',
		'.vf-c-action',
		'.vf-c-empty-state',
		'.vf-o-stack',
		'.vf-o-cluster',
		'.has-relationship',
		'.has-evidence',
		'.is-unavailable',
	) as $selector
) {
	$assert_contains( $selector, $foundation, "foundation exposes {$selector}" );
}

$assert_contains( '--vf-focus-width: 3px', $foundation, 'focus contract uses a three-pixel outline' );
$assert_contains( 'outline-offset: var(--vf-focus-offset)', $foundation, 'focus contract separates the outline' );
$assert_contains( '@media (prefers-reduced-motion: reduce)', $foundation, 'foundation honors reduced motion' );
$assert_contains( '@media (forced-colors: active)', $foundation, 'foundation honors forced colors' );
$assert_absent( '@layer', $foundation, 'foundation does not use CSS layers' );
$assert_absent( '!important', $foundation, 'foundation does not use important declarations' );

$selector_preludes = preg_split( '/\{/', preg_replace( '/\/\*.*?\*\//s', '', $foundation ) );
foreach ( $selector_preludes as $selector_prelude ) {
	$last_rule_end = strrpos( $selector_prelude, '}' );
	$selector      = trim( false === $last_rule_end ? $selector_prelude : substr( $selector_prelude, $last_rule_end + 1 ) );

	if ( '' !== $selector && '@' !== $selector[0] && preg_match( '/(?:^|[\s>+~,])#[A-Za-z_][A-Za-z0-9_-]*/', $selector ) ) {
		fwrite( STDERR, "FAIL: foundation contains an ID selector: {$selector}\n" );
		exit( 1 );
	}
}
$assertions++;

fwrite( STDOUT, "PASS assertions={$assertions}\n" );
