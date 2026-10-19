<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rwdpa_dealer_portal_fields', 'rwdpa_render_service_radius_field', 5 );
add_action( 'rwdpa_save_dealer_portal_fields', 'rwdpa_save_service_radius_field', 5 );
add_action( 'save_post_rw_dealer', 'rwdpa_save_dealer_hours_field', 20, 2 );
add_action( 'add_meta_boxes_rw_dealer', 'rwdpa_register_dealer_editor_fallback_meta_box' );
add_filter( 'rwdp_map_localized_data', 'rwdpa_add_service_radius_map_text' );
add_filter( 'rwdp_ajax_dealer_data', 'rwdpa_add_service_radius_to_ajax_data', 10, 2 );
add_action( 'wp_enqueue_scripts', 'rwdpa_enqueue_service_area_assets', 100 );
add_action( 'wp_print_footer_scripts', 'rwdpa_enqueue_service_area_assets', 1 );
add_action( 'rwdp_import_dealer_extra_fields', 'rwdpa_import_service_radius', 10, 2 );
add_action( 'rwdp_import_csv_column_hints', 'rwdpa_import_service_radius_column_hint' );

/**
 * Whether the service area feature is on (Addons settings; on by default).
 *
 * @return bool
 */
function rwdpa_service_area_enabled() {
	$settings = function_exists( 'rwdpa_get_settings' ) ? rwdpa_get_settings() : [];
	return ! empty( $settings['enable_service_area'] );
}

/**
 * Determine whether the core dealer editor still provides the add-on hook and hours field.
 *
 * @return array{hook:bool,hours:bool}
 */
function rwdpa_core_dealer_editor_support() {
	static $support = null;

	if ( null !== $support ) {
		return $support;
	}

	$support = [
		'hook'  => false,
		'hours' => false,
	];

	$core_meta_fields = defined( 'RWDP_PLUGIN_DIR' ) ? RWDP_PLUGIN_DIR . 'includes/meta-fields.php' : '';
	if ( '' === $core_meta_fields || ! is_readable( $core_meta_fields ) ) {
		return $support;
	}

	$core_source = file_get_contents( $core_meta_fields );
	if ( false === $core_source ) {
		return $support;
	}

	$support['hook']  = false !== strpos( $core_source, "do_action( 'rwdp_dealer_info_meta_box_after'" );
	$support['hours'] = false !== strpos( $core_source, 'name="rwdp_hours"' ) || false !== strpos( $core_source, "name='rwdp_hours'" ) || false !== strpos( $core_source, '_rwdp_hours' );

	return $support;
}

/**
 * Check whether the current request can save dealer meta.
 *
 * @param int $post_id Dealer post ID.
 * @return bool
 */
function rwdpa_can_save_dealer_meta( $post_id ) {
	if ( ! isset( $_POST['rwdp_dealer_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rwdp_dealer_meta_nonce'] ) ), 'rwdp_save_dealer_meta' ) ) {
		return false;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	if ( ! current_user_can( 'edit_rw_dealer', $post_id ) ) {
		return false;
	}

	return true;
}

/**
 * Render service area field in the dealer editor.
 *
 * @param WP_Post $post Dealer post object.
 */
function rwdpa_render_service_radius_field( $post ) {
	if ( ! rwdpa_service_area_enabled() ) {
		return;
	}

	$service_radius = get_post_meta( $post->ID, '_rwdp_service_radius_miles', true );
	?>
	<p>
		<label for="rwdpa_service_radius_miles"><strong><?php esc_html_e( 'Service Area Radius (miles)', 'rw-dealer-portal-addons' ); ?></strong></label><br />
		<input type="number" id="rwdpa_service_radius_miles" name="rwdpa_service_radius_miles" min="0" step="1" value="<?php echo esc_attr( absint( $service_radius ) ); ?>" style="max-width:120px;" />
		<span class="description" style="display:block;"><?php esc_html_e( 'Public. Drawn on the dealer map. Leave blank or 0 to hide.', 'rw-dealer-portal-addons' ); ?></span>
	</p>
	<?php
}

/**
 * Register fallback dealer editor fields if the core editor stops rendering them.
 */
function rwdpa_register_dealer_editor_fallback_meta_box() {
	$support = rwdpa_core_dealer_editor_support();

	// Only needed for Business Hours on core versions that no longer render them.
	if ( ! empty( $support['hours'] ) ) {
		return;
	}

	add_meta_box(
		'rwdpa_dealer_fields_fallback',
		__( 'Dealer Add-ons', 'rw-dealer-portal-addons' ),
		'rwdpa_render_dealer_fields_fallback_meta_box',
		'rw_dealer',
		'normal',
		'default'
	);
}

/**
 * Fallback renderer for dealer fields that may be removed from the core editor.
 *
 * @param WP_Post $post Dealer post object.
 */
function rwdpa_render_dealer_fields_fallback_meta_box( $post ) {
	$support = rwdpa_core_dealer_editor_support();

	if ( empty( $support['hours'] ) ) {
		wp_nonce_field( 'rwdp_save_dealer_meta', 'rwdp_dealer_meta_nonce' );
	}

	if ( empty( $support['hours'] ) ) {
		$hours = get_post_meta( $post->ID, '_rwdp_hours', true );
		?>
		<p class="rwdp-meta-section-title"><?php esc_html_e( 'Business Hours', 'rw-dealer-portal' ); ?></p>

		<div class="rwdp-meta-row">
			<label for="rwdp_hours"><?php esc_html_e( 'Hours', 'rw-dealer-portal' ); ?></label>
			<textarea id="rwdp_hours" name="rwdp_hours" rows="4"><?php echo esc_textarea( $hours ); ?></textarea>
		</div>
		<?php
	}

}

/**
 * Persist service area field (Portal Details box; nonce and capability are
 * verified before this runs).
 *
 * @param int $post_id Dealer post ID.
 */
function rwdpa_save_service_radius_field( $post_id ) {
	if ( ! rwdpa_service_area_enabled() || ! isset( $_POST['rwdpa_service_radius_miles'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$radius = absint( wp_unslash( $_POST['rwdpa_service_radius_miles'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_post_meta( $post_id, '_rwdp_service_radius_miles', $radius );
}

/**
 * Persist fallback business hours when the core editor no longer renders them.
 *
 * @param int     $post_id Dealer post ID.
 * @param WP_Post $post    Dealer post object.
 */
function rwdpa_save_dealer_hours_field( $post_id, $post ) {
	unset( $post );

	if ( ! rwdpa_can_save_dealer_meta( $post_id ) ) {
		return;
	}

	if ( ! empty( rwdpa_core_dealer_editor_support()['hours'] ) ) {
		return;
	}

	$hours = sanitize_textarea_field( wp_unslash( $_POST['rwdp_hours'] ?? '' ) );
	update_post_meta( $post_id, '_rwdp_hours', $hours );
}

/**
 * Add add-on strings to localized map data.
 *
 * @param array<string,mixed> $map_data Existing map config.
 * @return array<string,mixed>
 */
function rwdpa_add_service_radius_map_text( $map_data ) {
	$settings = function_exists( 'rwdpa_get_settings' ) ? rwdpa_get_settings() : [];

	$map_data['showRadiusText'] = __( 'Show Radius', 'rw-dealer-portal-addons' );
	$map_data['hideRadiusText'] = __( 'Hide Radius', 'rw-dealer-portal-addons' );
	$map_data['showServiceAreaInResults'] = ! empty( $settings['show_service_area_in_results'] );
	$map_data['showServiceAreaInPopup'] = ! empty( $settings['show_service_area_in_popup'] );
	$map_data['serviceAreaTextTemplate'] = __( '{value} mile service area', 'rw-dealer-portal-addons' );

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
 * Build a map of dealer ID => service radius (miles) for every dealer that has one set.
 *
 * Core no longer runs dealer AJAX data through the `rwdp_ajax_dealer_data` filter, so this
 * is fetched independently and merged into dealer objects client-side.
 *
 * @return array<string,int>
 */
function rwdpa_get_service_radii_map() {
	$posts = get_posts( [
		'post_type'      => 'rw_dealer',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [
			[
				'key'     => '_rwdp_service_radius_miles',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			],
		],
	] );

	$map = [];
	foreach ( $posts as $dealer_id ) {
		$map[ $dealer_id ] = absint( get_post_meta( $dealer_id, '_rwdp_service_radius_miles', true ) );
	}

	return $map;
}

/**
 * Enqueue service area map enhancements only when core dealer map is in use.
 */
function rwdpa_enqueue_service_area_assets() {
	static $done = false;
	if ( $done || ! rwdpa_service_area_enabled() ) {
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

	/*
	 * Core (1.0.19+) no longer calls apply_filters( 'rwdp_map_localized_data' ) or
	 * apply_filters( 'rwdp_ajax_dealer_data' ), so the filters added above are never invoked.
	 * Provide the same data through independent globals that service-area-map.js reads directly,
	 * so the feature works regardless of whether core still fires those filters.
	 */
	wp_add_inline_script(
		'rwdpa-service-area-map',
		'window.rwdpaMapSettings = ' . wp_json_encode( rwdpa_add_service_radius_map_text( [] ) ) . ';' .
		'window.rwdpaServiceRadii = ' . wp_json_encode( rwdpa_get_service_radii_map() ) . ';',
		'before'
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
