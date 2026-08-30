<?php
/**
 * Limit the Starter Templates design-library bootstrap on frontend reads.
 *
 * The current vendor lifecycle can write an empty
 * ast-block-templates-json/index.html during wp_loaded. This guard addresses
 * that current mechanism; it does not prove what caused Attempt 11.
 *
 * @package veefun
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine whether the current request is an ordinary frontend read.
 *
 * Unknown or operational contexts fail open to the vendor integration. REST
 * routes need an early URI check because the vendor filter runs on init,
 * before WordPress routing necessarily defines REST_REQUEST.
 *
 * @return bool
 */
function veefun_is_ordinary_frontend_read() {
	if ( in_array( PHP_SAPI, array( 'cli', 'phpdbg' ), true ) ) {
		return false;
	}

	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
		? strtoupper( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
		: '';

	if ( ! in_array( $request_method, array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}

	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	if (
		( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'WP_CLI' ) && WP_CLI )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		|| ( defined( 'WP_INSTALLING' ) && WP_INSTALLING )
		|| ( defined( 'WP_REPAIRING' ) && WP_REPAIRING )
		|| ( defined( 'WP_IMPORTING' ) && WP_IMPORTING )
		|| ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST )
	) {
		return false;
	}

	if (
		isset( $GLOBALS['pagenow'] )
		&& in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ), true )
	) {
		return false;
	}

	if ( isset( $_GET['rest_route'] ) ) {
		return false;
	}

	if (
		! isset( $_SERVER['REQUEST_URI'] )
		|| ! is_string( $_SERVER['REQUEST_URI'] )
		|| ! function_exists( 'rest_get_url_prefix' )
	) {
		return false;
	}

	$request_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$rest_prefix  = trim( (string) rest_get_url_prefix(), '/' );

	if ( ! is_string( $request_path ) || '' === $rest_prefix ) {
		return false;
	}

	if ( 1 === preg_match( '#(?:^|/)' . preg_quote( $rest_prefix, '#' ) . '(?:/|$)#', $request_path ) ) {
		return false;
	}

	return true;
}

/**
 * Disable the bundled design library only for ordinary frontend reads.
 *
 * Existing filter decisions are preserved in every excluded context.
 *
 * @param mixed $disabled Current vendor-filter value.
 * @return mixed|bool
 */
function veefun_guard_frontend_starter_templates_writer( $disabled ) {
	if ( ! veefun_is_ordinary_frontend_read() ) {
		return $disabled;
	}

	return true;
}

add_filter( 'ast_block_templates_disable', 'veefun_guard_frontend_starter_templates_writer', 10, 1 );
