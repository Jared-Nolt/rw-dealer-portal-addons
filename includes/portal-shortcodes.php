<?php
/**
 * Small portal display shortcodes: account greeting bar and office contact.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'rwdpa_account_bar', 'rwdpa_account_bar_shortcode' );
add_shortcode( 'rwdpa_office_contact', 'rwdpa_office_contact_shortcode' );
add_shortcode( 'rwdpa_asset_category', 'rwdpa_asset_category_shortcode' );

/**
 * [rwdpa_account_bar] — "Hello, First Last" with a log out button.
 *
 * Attributes: greeting, logout_text.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function rwdpa_account_bar_shortcode( $atts = [] ) {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts( [
		'greeting'    => __( 'Hello,', 'rw-dealer-portal-addons' ),
		'logout_text' => __( 'Log Out', 'rw-dealer-portal-addons' ),
	], $atts, 'rwdpa_account_bar' );

	$user = wp_get_current_user();
	$name = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;

	$redirect = function_exists( 'rwdp_get_page_url' ) ? rwdp_get_page_url( 'login' ) : '';
	$logout   = wp_logout_url( $redirect ?: home_url( '/' ) );

	wp_enqueue_style( 'rwdpa-portal-display', RWDPA_PLUGIN_URL . 'assets/css/portal-display.css', [], RWDPA_VERSION );

	ob_start();
	?>
	<div class="rwdpa-account-bar">
		<span class="rwdpa-account-bar__greeting"><?php echo esc_html( $atts['greeting'] ); ?> <strong><?php echo esc_html( $name ); ?></strong></span>
		<a class="rwdpa-account-bar__logout" href="<?php echo esc_url( $logout ); ?>"><?php echo esc_html( $atts['logout_text'] ); ?></a>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * [rwdpa_office_contact] — company office details from Portal Display settings.
 *
 * Attributes: heading (defaults to the office name), phone_label, fax_label, email_label.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function rwdpa_office_contact_shortcode( $atts = [] ) {
	$settings = rwdpa_portal_settings();
	$atts     = shortcode_atts( [
		'heading'     => $settings['office_name'],
		'phone_label' => __( 'Phone:', 'rw-dealer-portal-addons' ),
		'fax_label'   => __( 'Fax:', 'rw-dealer-portal-addons' ),
		'email_label' => __( 'Email:', 'rw-dealer-portal-addons' ),
	], $atts, 'rwdpa_office_contact' );

	if ( ! $atts['heading'] && ! $settings['office_phone'] && ! $settings['office_email'] ) {
		return '';
	}

	wp_enqueue_style( 'rwdpa-portal-display', RWDPA_PLUGIN_URL . 'assets/css/portal-display.css', [], RWDPA_VERSION );

	ob_start();
	?>
	<div class="rwdpa-contact-card rwdpa-contact-card--office">
		<?php if ( $atts['heading'] ) : ?>
			<h3 class="rwdpa-contact-card__heading"><?php echo esc_html( $atts['heading'] ); ?></h3>
		<?php endif; ?>
		<?php if ( $settings['office_phone'] ) : ?>
			<p class="rwdpa-contact-card__line"><?php echo esc_html( $atts['phone_label'] ); ?> <a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $settings['office_phone'] ) ); ?>"><?php echo esc_html( $settings['office_phone'] ); ?></a></p>
		<?php endif; ?>
		<?php if ( $settings['office_fax'] ) : ?>
			<p class="rwdpa-contact-card__line"><?php echo esc_html( $atts['fax_label'] ); ?> <?php echo esc_html( $settings['office_fax'] ); ?></p>
		<?php endif; ?>
		<?php if ( $settings['office_email'] ) : ?>
			<p class="rwdpa-contact-card__line"><?php echo esc_html( $atts['email_label'] ); ?> <a href="<?php echo esc_url( 'mailto:' . $settings['office_email'] ); ?>"><?php echo esc_html( $settings['office_email'] ); ?></a></p>
		<?php endif; ?>
		<?php if ( $settings['office_address'] ) : ?>
			<p class="rwdpa-contact-card__line rwdpa-contact-card__address"><?php echo nl2br( esc_html( $settings['office_address'] ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * [rwdpa_asset_category term="slug"] — one RW Dealer Portal asset category
 * (child category cards, then its assets), rendered by the core view. Lets a
 * page show several categories in any order.
 *
 * Attributes: term (category slug), download_icon (CSS classes).
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function rwdpa_asset_category_shortcode( $atts = [] ) {
	if ( ! function_exists( 'rwdp_render_asset_taxonomy_view' ) ) {
		return '';
	}
	$atts = shortcode_atts( [
		'term'          => '',
		'download_icon' => 'dashicons dashicons-download',
	], $atts, 'rwdpa_asset_category' );

	if ( '' === $atts['term'] ) {
		return '';
	}
	return rwdp_render_asset_taxonomy_view( $atts );
}
