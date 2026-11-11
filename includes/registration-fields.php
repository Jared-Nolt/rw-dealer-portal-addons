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
 * Whether the address field is on.
 *
 * @return bool
 */
function rwdpa_registration_address_enabled() {
	return (bool) rwdpa_portal_setting( 'registration_address' );
}

/**
 * Address parts: key => placeholder text.
 *
 * @return array<string,string>
 */
function rwdpa_registration_address_parts() {
	return [
		'street' => __( 'Street Address', 'rw-dealer-portal-addons' ),
		'city'   => __( 'City', 'rw-dealer-portal-addons' ),
		'state'  => __( 'State', 'rw-dealer-portal-addons' ),
		'zip'    => __( 'ZIP', 'rw-dealer-portal-addons' ),
	];
}

/**
 * Submitted address parts, sanitized.
 *
 * @return array<string,string>
 */
function rwdpa_registration_posted_address() {
	$raw     = isset( $_POST['rwdpa_address'] ) && is_array( $_POST['rwdpa_address'] ) ? wp_unslash( $_POST['rwdpa_address'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below; callers verify the nonce
	$address = [];
	foreach ( array_keys( rwdpa_registration_address_parts() ) as $key ) {
		$address[ $key ] = sanitize_text_field( is_string( $raw[ $key ] ?? null ) ? $raw[ $key ] : '' );
	}
	return $address;
}

/**
 * Format a saved address on one line.
 *
 * @param array<string,string> $address Address parts.
 * @return string
 */
function rwdpa_registration_format_address( $address ) {
	$city_line = trim( implode( ', ', array_filter( [ $address['city'] ?? '', $address['state'] ?? '' ] ) ) . ' ' . ( $address['zip'] ?? '' ) );
	return implode( ', ', array_filter( [ $address['street'] ?? '', $city_line ] ) );
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
 * Insert the enabled fields before the form's message area.
 *
 * @param string $html Form HTML.
 * @return string
 */
function rwdpa_registration_inject_fields( $html ) {
	$marker = '<div id="rwdp-register-message"';
	if ( false === strpos( $html, $marker ) || false !== strpos( $html, 'rwdpa-form-row--' ) ) {
		return $html;
	}

	$show_labels = false === strpos( $html, 'rwdp-auth-panel--no-labels' );
	$fields      = '';
	if ( rwdpa_registration_phone_enabled() ) {
		$fields .= rwdpa_registration_phone_html( $show_labels );
	}
	if ( rwdpa_registration_address_enabled() ) {
		$fields .= rwdpa_registration_address_html( $show_labels );
	}
	if ( '' === $fields ) {
		return $html;
	}

	$pos = strpos( $html, $marker );
	return substr( $html, 0, $pos ) . $fields . substr( $html, $pos );
}

/**
 * Phone field HTML.
 *
 * @param bool $show_labels Whether the form shows labels (otherwise placeholders).
 * @return string
 */
function rwdpa_registration_phone_html( $show_labels ) {
	$text     = (string) rwdpa_portal_setting( 'registration_phone_text' );
	$required = (bool) rwdpa_portal_setting( 'registration_phone_req' );

	ob_start();
	?>
	<div class="rwdp-form-row rwdpa-form-row--phone">
		<?php if ( $show_labels ) : ?>
			<label for="rwdpa_reg_phone"><?php echo esc_html( $text ); ?><?php echo $required ? ' <span class="required">*</span>' : ''; ?></label>
		<?php endif; ?>
		<input type="tel" id="rwdpa_reg_phone" name="rwdpa_phone" autocomplete="tel"<?php echo $required ? ' required' : ''; ?><?php echo $show_labels ? '' : ' placeholder="' . esc_attr( $text . ( $required ? ' *' : '' ) ) . '"'; ?> />
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Address field HTML: street on its own line, then city / state / ZIP.
 *
 * @param bool $show_labels Whether the form shows labels.
 * @return string
 */
function rwdpa_registration_address_html( $show_labels ) {
	$text         = (string) rwdpa_portal_setting( 'registration_address_text' );
	$required     = (bool) rwdpa_portal_setting( 'registration_address_req' );
	$autocomplete = [
		'street' => 'street-address',
		'city'   => 'address-level2',
		'state'  => 'address-level1',
		'zip'    => 'postal-code',
	];

	ob_start();
	?>
	<style>.rwdpa-address-row{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;margin-top:12px}@media (max-width:480px){.rwdpa-address-row{grid-template-columns:1fr 1fr}.rwdpa-address-row>:first-child{grid-column:1/-1}}</style>
	<div class="rwdp-form-row rwdpa-form-row--address">
		<?php if ( $show_labels ) : ?>
			<label for="rwdpa_reg_address_street"><?php echo esc_html( $text ); ?><?php echo $required ? ' <span class="required">*</span>' : ''; ?></label>
		<?php endif; ?>
		<?php foreach ( rwdpa_registration_address_parts() as $key => $placeholder ) : ?>
			<?php if ( 'city' === $key ) : ?>
				<div class="rwdpa-address-row">
			<?php endif; ?>
			<input type="text" id="rwdpa_reg_address_<?php echo esc_attr( $key ); ?>" name="rwdpa_address[<?php echo esc_attr( $key ); ?>]" autocomplete="<?php echo esc_attr( $autocomplete[ $key ] ); ?>" placeholder="<?php echo esc_attr( $placeholder . ( $required && ! $show_labels ? ' *' : '' ) ); ?>" aria-label="<?php echo esc_attr( $placeholder ); ?>"<?php echo $required ? ' required' : ''; ?> />
		<?php endforeach; ?>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Reject the request before the core handler runs when a required field is empty.
 */
function rwdpa_registration_validate() {
	$phone_req   = rwdpa_registration_phone_enabled() && rwdpa_portal_setting( 'registration_phone_req' );
	$address_req = rwdpa_registration_address_enabled() && rwdpa_portal_setting( 'registration_address_req' );
	if ( ! $phone_req && ! $address_req ) {
		return;
	}
	check_ajax_referer( 'rwdp_registration', 'nonce' );

	if ( $phone_req ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['rwdpa_phone'] ?? '' ) );
		if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 7 ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid phone number.', 'rw-dealer-portal-addons' ) ] );
		}
	}
	if ( $address_req && in_array( '', rwdpa_registration_posted_address(), true ) ) {
		wp_send_json_error( [ 'message' => __( 'Please enter your full address.', 'rw-dealer-portal-addons' ) ] );
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
	if ( rwdpa_registration_address_enabled() ) {
		$address = rwdpa_registration_posted_address();
		if ( '' !== implode( '', $address ) ) {
			update_user_meta( $user_id, '_rwdpa_address', $address );
		}
	}
}

/**
 * Phone and address rows for the user profile summary.
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
	$address = get_user_meta( $user->ID, '_rwdpa_address', true );
	if ( is_array( $address ) && '' !== rwdpa_registration_format_address( $address ) ) {
		$rows[ __( 'Address (registration)', 'rw-dealer-portal-addons' ) ] = esc_html( rwdpa_registration_format_address( $address ) );
	}
	return $rows;
}
