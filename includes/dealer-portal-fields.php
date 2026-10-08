<?php
/**
 * Shared "Portal Details" meta box on dealers and a read-only portal summary
 * on user profiles. Tier and territory modules add their fields through the
 * actions below, so the box only appears when one of them is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes_rw_dealer', 'rwdpa_register_portal_details_meta_box' );
add_action( 'save_post_rw_dealer', 'rwdpa_save_portal_details_meta_box', 30, 2 );
add_action( 'show_user_profile', 'rwdpa_render_user_portal_summary', 20 );
add_action( 'edit_user_profile', 'rwdpa_render_user_portal_summary', 20 );

/**
 * Register the meta box when a module needs it.
 */
function rwdpa_register_portal_details_meta_box() {
	if ( ! rwdpa_tiers_enabled() && ! rwdpa_managers_enabled() ) {
		return;
	}

	add_meta_box(
		'rwdpa_portal_details',
		__( 'Portal Details', 'rw-dealer-portal-addons' ),
		'rwdpa_render_portal_details_meta_box',
		'rw_dealer',
		'side',
		'default'
	);
}

/**
 * Render the meta box.
 *
 * @param WP_Post $post Dealer post.
 */
function rwdpa_render_portal_details_meta_box( $post ) {
	wp_nonce_field( 'rwdpa_save_portal_details', 'rwdpa_portal_details_nonce' );

	/**
	 * Fires inside the Portal Details meta box.
	 *
	 * @param WP_Post $post Dealer post.
	 */
	do_action( 'rwdpa_dealer_portal_fields', $post );
}

/**
 * Save the meta box.
 *
 * @param int     $post_id Dealer post ID.
 * @param WP_Post $post    Dealer post.
 */
function rwdpa_save_portal_details_meta_box( $post_id, $post ) {
	if ( ! isset( $_POST['rwdpa_portal_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rwdpa_portal_details_nonce'] ) ), 'rwdpa_save_portal_details' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	/**
	 * Fires when the Portal Details meta box is saved. Nonce and capability
	 * are already verified.
	 *
	 * @param int     $post_id Dealer post ID.
	 * @param WP_Post $post    Dealer post.
	 */
	do_action( 'rwdpa_save_dealer_portal_fields', $post_id, $post );
}

/**
 * Linked dealer IDs for a user.
 *
 * @param int $user_id User ID.
 * @return int[]
 */
function rwdpa_get_user_dealer_ids( $user_id ) {
	$ids = get_user_meta( $user_id, '_rwdp_dealer_ids', true );
	$ids = is_array( $ids ) ? array_filter( array_map( 'absint', $ids ) ) : [];
	return array_values( array_filter( $ids, static function ( $id ) {
		return 'rw_dealer' === get_post_type( $id );
	} ) );
}

/**
 * Users linked to a dealer.
 *
 * @param int $dealer_id Dealer post ID.
 * @return int[] User IDs.
 */
function rwdpa_get_dealer_user_ids( $dealer_id ) {
	$dealer_id = absint( $dealer_id );
	if ( ! $dealer_id ) {
		return [];
	}

	// Serialized int arrays store values as "i:123;" — narrow with LIKE, then confirm in PHP.
	$candidates = get_users( [
		'fields'     => 'ID',
		'meta_query' => [
			[
				'key'     => '_rwdp_dealer_ids',
				'value'   => 'i:' . $dealer_id . ';',
				'compare' => 'LIKE',
			],
		],
	] );

	return array_values( array_filter( array_map( 'absint', $candidates ), static function ( $user_id ) use ( $dealer_id ) {
		return in_array( $dealer_id, rwdpa_get_user_dealer_ids( $user_id ), true );
	} ) );
}

/**
 * Read-only portal summary on user profiles (tier, dealer, sales manager).
 *
 * @param WP_User $user User being edited.
 */
function rwdpa_render_user_portal_summary( $user ) {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_rwdp_portal' ) ) {
		return;
	}

	$rows = apply_filters( 'rwdpa_user_portal_summary_rows', [], $user );
	if ( ! $rows ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Dealer Portal Summary', 'rw-dealer-portal-addons' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php foreach ( $rows as $label => $value ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td><?php echo wp_kses_post( $value ); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}
