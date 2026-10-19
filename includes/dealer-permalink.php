<?php
/**
 * Optional URL base for RW Dealer Portal dealer pages.
 *
 * Core registers rw_dealer at /dealer/. On sites that already use /dealer/
 * (e.g. an existing store-listing post type) set Addons → Dealer URL Base to
 * something else so both keep working. Empty keeps the core default.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'register_post_type_args', 'rwdpa_dealer_permalink_args', 10, 2 );
add_action( 'update_option_rwdpa_settings', 'rwdpa_dealer_permalink_maybe_schedule_flush', 10, 2 );
add_action( 'init', 'rwdpa_dealer_permalink_flush', 999 );

/**
 * Dealer URL base from settings ('' = core default).
 *
 * @return string
 */
function rwdpa_get_dealer_url_base() {
	$settings = get_option( 'rwdpa_settings', [] );
	return is_array( $settings ) ? sanitize_title( $settings['dealer_url_base'] ?? '' ) : '';
}

/**
 * Change the rw_dealer rewrite slug.
 *
 * @param array<string,mixed> $args      Post type args.
 * @param string              $post_type Post type.
 * @return array<string,mixed>
 */
function rwdpa_dealer_permalink_args( $args, $post_type ) {
	if ( 'rw_dealer' !== $post_type ) {
		return $args;
	}
	$base = rwdpa_get_dealer_url_base();
	if ( '' !== $base ) {
		$args['rewrite'] = array_merge( is_array( $args['rewrite'] ?? null ) ? $args['rewrite'] : [], [ 'slug' => $base ] );
	}
	return $args;
}

/**
 * Flag a rewrite flush when the base changes.
 *
 * @param mixed $old Old settings.
 * @param mixed $new New settings.
 */
function rwdpa_dealer_permalink_maybe_schedule_flush( $old, $new ) {
	$old_base = is_array( $old ) ? ( $old['dealer_url_base'] ?? '' ) : '';
	$new_base = is_array( $new ) ? ( $new['dealer_url_base'] ?? '' ) : '';
	if ( $old_base !== $new_base ) {
		update_option( 'rwdpa_flush_rewrite', 1, false );
	}
}

/**
 * Flush once, after post types are registered with the new base.
 */
function rwdpa_dealer_permalink_flush() {
	if ( get_option( 'rwdpa_flush_rewrite' ) ) {
		delete_option( 'rwdpa_flush_rewrite' );
		flush_rewrite_rules( false );
	}
}
