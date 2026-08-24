<?php
/**
 * veefun Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package veefun
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_VEEFUN_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'veefun-theme-css', get_stylesheet_directory_uri() . '/style.css', array( 'astra-theme-css' ), CHILD_THEME_VEEFUN_VERSION, 'all' );

	if ( is_front_page() || is_page( 1524 ) ) {
		$collector_stylesheet = get_stylesheet_directory() . '/assets/css/collector-entrypoint.css';

		wp_enqueue_style(
			'veefun-collector-entrypoint',
			get_stylesheet_directory_uri() . '/assets/css/collector-entrypoint.css',
			array( 'veefun-theme-css' ),
			(string) filemtime( $collector_stylesheet ),
			'all'
		);
	}

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

/**
 * Attach Elementor's frontend configuration for the custom front page.
 *
 * The static front page retains Elementor's saved asset metadata, but this
 * child-theme template does not render its former Elementor content. Run just
 * after Elementor's normal footer callback so its public frontend lifecycle
 * can supply the configuration only when the saved-assets loader enqueued the
 * frontend script and Elementor has not already supplied it.
 */
function veefun_prepare_front_page_elementor_config() {
	static $prepared = false;

	if (
		$prepared
		|| ! is_front_page()
		|| ! did_action( 'elementor/loaded' )
		|| ! class_exists( '\\Elementor\\Plugin' )
		|| ! isset( \Elementor\Plugin::$instance->frontend )
		|| ! wp_script_is( 'elementor-frontend', 'enqueued' )
		|| did_action( 'elementor/frontend/after_enqueue_scripts' )
	) {
		return;
	}

	$elementor_frontend = \Elementor\Plugin::$instance->frontend;

	if ( ! is_callable( array( $elementor_frontend, 'enqueue_scripts' ) ) ) {
		return;
	}

	$prepared = true;
	$elementor_frontend->enqueue_scripts();
}

add_action( 'wp_footer', 'veefun_prepare_front_page_elementor_config', 11 );
