<?php
/**
 * Optional store-locator layout for the core Dealer Search / Dealer Map /
 * Dealer List widgets (Addons setting `finder_style`, off by default).
 * CSS only: a body class scopes the stylesheet so it can't affect sites that
 * leave the setting off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'body_class', 'rwdpa_finder_style_body_class' );
add_action( 'wp_enqueue_scripts', 'rwdpa_finder_style_enqueue', 20 );

/**
 * Whether the finder layout is on.
 *
 * @return bool
 */
function rwdpa_finder_style_enabled() {
	return function_exists( 'rwdpa_get_settings' ) && ! empty( rwdpa_get_settings()['finder_style'] );
}

/**
 * @param string[] $classes Body classes.
 * @return string[]
 */
function rwdpa_finder_style_body_class( $classes ) {
	if ( rwdpa_finder_style_enabled() ) {
		$classes[] = 'rwdpa-finder-styled';
	}
	return $classes;
}

/**
 * Load the finder stylesheet (small; the widgets enqueue their own assets on
 * render, so it is loaded site-wide while the setting is on).
 */
function rwdpa_finder_style_enqueue() {
	if ( rwdpa_finder_style_enabled() ) {
		wp_enqueue_style( 'rwdpa-dealer-finder', RWDPA_PLUGIN_URL . 'assets/css/dealer-finder.css', [], RWDPA_VERSION );
	}
}
