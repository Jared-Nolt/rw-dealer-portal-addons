<?php
/**
 * Extra fields on the core Request Access form (shortcode and Elementor
 * widget). The core form is submitted with jQuery serialize(), so injected
 * inputs arrive with the core AJAX request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'do_shortcode_tag', 'rwdpa_registration_filter_shortcode', 10, 2 );
add_filter( 'elementor/widget/render_content', 'rwdpa_registration_filter_widget', 10, 2 );
add_action( 'wp_ajax_nopriv_rwdp_register_request', 'rwdpa_registration_validate', 1 );
add_action( 'wp_ajax_rwdp_register_request', 'rwdpa_registration_validate', 1 );
add_action( 'user_register', 'rwdpa_registration_save' );
add_filter( 'rwdpa_user_portal_summary_rows', 'rwdpa_registration_summary_rows', 5, 2 );

/**
 * Whether the phone field is on.
 *
 * @return bool
 */
function rwdpa_registration_phone_enabled() {
	return (bool) rwdpa_portal_setting( 'registration_phone' );
}

/**
 * Inject fields into the request access shortcode output.
 *
 * @param string $output Shortcode output.
 * @param string $tag    Shortcode tag.
 * @return string
 */
function rwdpa_registration_filter_shortcode( $output, $tag ) {
	return 'rwdp_request_access' === $tag ? rwdpa_registration_inject_fields( $output ) : $output;
}

/**
 * Inject fields into the request access Elementor widget output.
 *
 * @param string                 $content Widget HTML.
 * @param \Elementor\Widget_Base $widget  Widget.
 * @return string
 */
function rwdpa_registration_filter_widget( $content, $widget ) {
	return 'rwdp_request_access' === $widget->get_name() ? rwdpa_registration_inject_fields( $content ) : $content;
}

/**
 * Insert the phone field before the form's message area.
 *
 * @param string $html Form HTML.
 * @return string
 */
function rwdpa_registration_inject_fields( $html ) {
	$marker = '<div id="rwdp-register-message"';
	if ( ! rwdpa_registration_phone_enabled() || false === strpos( $html, $marker ) || false !== strpos( $html, 'name="rwdpa_phone"' ) ) {
		return $html;
	}

	$text        = (string) rwdpa_portal_setting( 'registration_phone_text' );
	$required    = (bool) rwdpa_portal_setting( 'registration_phone_req' );
	$show_labels = false === strpos( $html, 'rwdp-auth-panel--no-labels' );

	ob_start();
	?>
	<div class="rwdp-form-row rwdpa-form-row--phone">
		<?php if ( $show_labels ) : ?>
			<label for="rwdpa_reg_phone"><?php echo esc_html( $text ); ?><?php echo $required ? ' <span class="required">*</span>' : ''; ?></label>
		<?php endif; ?>
		<input type="tel" id="rwdpa_reg_phone" name="rwdpa_phone" autocomplete="tel"<?php echo $required ? ' required' : ''; ?><?php echo $show_labels ? '' : ' placeholder="' . esc_attr( $text . ( $required ? ' *' : '' ) ) . '"'; ?> />
	</div>
	<?php
	$field = (string) ob_get_clean();

	$pos = strpos( $html, $marker );
	return substr( $html, 0, $pos ) . $field . substr( $html, $pos );
}

/**
 * Reject the request before the core handler runs when a required field is empty.
 */
function rwdpa_registration_validate() {
	if ( ! rwdpa_registration_phone_enabled() || ! rwdpa_portal_setting( 'registration_phone_req' ) ) {
		return;
	}
	check_ajax_referer( 'rwdp_registration', 'nonce' );

	$phone = sanitize_text_field( wp_unslash( $_POST['rwdpa_phone'] ?? '' ) );
	if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 7 ) {
		wp_send_json_error( [ 'message' => __( 'Please enter a valid phone number.', 'rw-dealer-portal-addons' ) ] );
	}
}

/**
 * Save fields when the core handler creates the user.
 *
 * @param int $user_id New user ID.
 */
function rwdpa_registration_save( $user_id ) {
	if ( ! wp_doing_ajax() || 'rwdp_register_request' !== ( $_POST['action'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the core handler verified the nonce before creating the user
		return;
	}
	$phone = sanitize_text_field( wp_unslash( $_POST['rwdpa_phone'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' !== $phone ) {
		update_user_meta( $user_id, '_rwdpa_phone', $phone );
	}
}

/**
 * Phone row for the user profile summary.
 *
 * @param array<string,string> $rows Rows.
 * @param WP_User              $user User.
 * @return array<string,string>
 */
function rwdpa_registration_summary_rows( $rows, $user ) {
	$phone = (string) get_user_meta( $user->ID, '_rwdpa_phone', true );
	if ( '' !== $phone ) {
		$rows[ __( 'Phone (registration)', 'rw-dealer-portal-addons' ) ] = esc_html( $phone );
	}
	return $rows;
}
