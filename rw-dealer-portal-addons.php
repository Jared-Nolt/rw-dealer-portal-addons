<?php
/**
 * Plugin Name: RW Dealer Portal Addons
 * Description: Optional add-ons for RW Dealer Portal: contractor list print/PDF tools, service radius on the dealer map, dealer tiers, sales managers and territories, and portal display shortcodes.
 * Version: 1.5.0
 * Author: Jared Nolt
 * Plugin URI: https://github.com/Jared-Nolt/rw-dealer-portal-addons
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: rw-dealer-portal-addons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RWDPA_VERSION', '1.5.0' );
define( 'RWDPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RWDPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RWDPA_PLUGIN_DIR . 'includes/github-updater.php';
if ( is_admin() && class_exists( '\RW_Dealer_Portal_Addons\Updater' ) ) {
	new \RW_Dealer_Portal_Addons\Updater();
}

add_action( 'plugins_loaded', 'rwdpa_bootstrap' );

/**
 * Bootstrap add-on modules when RW Dealer Portal is available.
 */
function rwdpa_bootstrap() {
	if ( ! defined( 'RWDP_VERSION' ) ) {
		add_action( 'admin_notices', 'rwdpa_core_missing_notice' );
		return;
	}

	require_once RWDPA_PLUGIN_DIR . 'includes/settings-page.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/dealer-permalink.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/contractor-list.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/service-area.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/portal-settings.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/dealer-portal-fields.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/tiers.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/territories.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/manager-restrictions.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/portal-shortcodes.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/registration-fields.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/import-tool.php';
	require_once RWDPA_PLUGIN_DIR . 'includes/asset-import.php';

	add_action( 'elementor/widgets/register', 'rwdpa_register_elementor_widgets' );
}

/**
 * Admin notice when core plugin is not active.
 */
function rwdpa_core_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'rw-dealer-portal-addons requires RW Dealer Portal to be active.', 'rw-dealer-portal-addons' );
	echo '</p></div>';
}

/**
 * Register add-on Elementor widgets.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function rwdpa_register_elementor_widgets( $widgets_manager ) {
	require_once RWDPA_PLUGIN_DIR . 'elementor/widgets/contractor-list-widget.php';
	$widgets_manager->register( new \RWDP_Contractor_List_Widget() );
}
