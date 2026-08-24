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
