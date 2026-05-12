<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rwdp_dealer_info_meta_box_after', 'rwdpa_render_service_radius_field' );
add_action( 'rwdp_save_dealer_meta', 'rwdpa_save_service_radius_field', 10, 2 );
add_filter( 'rwdp_map_localized_data', 'rwdpa_add_service_radius_map_text' );
add_filter( 'rwdp_ajax_dealer_data', 'rwdpa_add_service_radius_to_ajax_data', 10, 2 );
add_action( 'wp_enqueue_scripts', 'rwdpa_enqueue_service_area_assets', 100 );
add_action( 'wp_print_footer_scripts', 'rwdpa_enqueue_service_area_assets', 1 );
add_action( 'rwdp_import_dealer_extra_fields', 'rwdpa_import_service_radius', 10, 2 );
add_action( 'rwdp_import_csv_column_hints', 'rwdpa_import_service_radius_column_hint' );

/**
 * Render service area field in the dealer editor.
 *
 * @param WP_Post $post Dealer post object.
 */
function rwdpa_render_service_radius_field( $post ) {
	$service_radius = get_post_meta( $post->ID, '_rwdp_service_radius_miles', true );
	?>
	<p class="rwdp-meta-section-title"><?php esc_html_e( 'Service Area', 'rw-dealer-portal-addons' ); ?></p>

	<div class="rwdp-meta-row">
		<label for="rwdpa_service_radius_miles"><?php esc_html_e( 'Radius (miles)', 'rw-dealer-portal-addons' ); ?></label>
		<div>
			<input type="number" id="rwdpa_service_radius_miles" name="rwdpa_service_radius_miles" min="0" step="1" value="<?php echo esc_attr( absint( $service_radius ) ); ?>" style="max-width:120px;" />
			<p class="description"><?php esc_html_e( 'Optional contractor service area radius in miles. Leave blank or 0 to hide.', 'rw-dealer-portal-addons' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Persist service area field.
 *
 * @param int     $post_id Dealer post ID.
 * @param WP_Post $post    Dealer post object.
 */
function rwdpa_save_service_radius_field( $post_id, $post ) {
	unset( $post );

	$radius = absint( wp_unslash( $_POST['rwdpa_service_radius_miles'] ?? 0 ) );
	update_post_meta( $post_id, '_rwdp_service_radius_miles', $radius );
}

/**
 * Add add-on strings to localized map data.
 *
 * @param array<string,mixed> $map_data Existing map config.
 * @return array<string,mixed>
 */
function rwdpa_add_service_radius_map_text( $map_data ) {
	$map_data['showRadiusText'] = __( 'Show Radius', 'rw-dealer-portal-addons' );
	$map_data['hideRadiusText'] = __( 'Hide Radius', 'rw-dealer-portal-addons' );

	return $map_data;
}

/**
 * Add service radius value per dealer to AJAX payload.
 *
 * @param array<string,mixed> $dealer_data Existing dealer data.
 * @param WP_Post             $dealer      Dealer post object.
 * @return array<string,mixed>
 */
function rwdpa_add_service_radius_to_ajax_data( $dealer_data, $dealer ) {
	$dealer_data['service_radius_miles'] = absint( get_post_meta( $dealer->ID, '_rwdp_service_radius_miles', true ) );

	return $dealer_data;
}

/**
 * Enqueue service area map enhancements only when core dealer map is in use.
 */
function rwdpa_enqueue_service_area_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}

	if ( ! wp_script_is( 'rwdp-dealer-map', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script(
		'rwdpa-service-area-map',
		RWDPA_PLUGIN_URL . 'assets/js/service-area-map.js',
		[ 'rwdp-dealer-map' ],
		RWDPA_VERSION,
		true
	);

	wp_enqueue_style(
		'rwdpa-service-area-map',
		RWDPA_PLUGIN_URL . 'assets/css/service-area-map.css',
		[ 'rwdp-dealer-map' ],
		RWDPA_VERSION
	);

	$done = true;
}

/**
 * Save service radius from an imported CSV row.
 *
 * Expects a `service_radius_miles` column in the CSV (integer, miles).
 *
 * @param int                 $post_id  Newly inserted dealer post ID.
 * @param array<string,mixed> $row_data Keyed CSV row data (headers normalised to lowercase snake_case).
 */
function rwdpa_import_service_radius( $post_id, $row_data ) {
	if ( ! array_key_exists( 'service_radius_miles', $row_data ) ) {
		return;
	}

	$radius = absint( $row_data['service_radius_miles'] );
	update_post_meta( $post_id, '_rwdp_service_radius_miles', $radius );
}

/**
 * Append the service_radius_miles column hint to the import page description.
 */
function rwdpa_import_service_radius_column_hint() {
	echo esc_html__( ' Add-on column: service_radius_miles.', 'rw-dealer-portal-addons' );
}
