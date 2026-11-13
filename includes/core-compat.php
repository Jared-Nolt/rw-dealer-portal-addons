<?php
/**
 * Core update watch (Addons setting `core_watch`, off by default).
 *
 * Notes on the Plugins page when RW Dealer Portal (core) has an update, or is
 * newer than the version this add-on was tested with, and a self-check of the
 * core functions, hooks, markup and CSS classes the add-on relies on. Results
 * are cached per core version, so the core source is scanned once per update.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Core version this add-on release was last tested with. Bump after testing a new core. */
define( 'RWDPA_TESTED_CORE', '2.3.0' );

add_action( 'load-plugins.php', 'rwdpa_core_compat_maybe_recheck' );
add_action( 'after_plugin_row_' . plugin_basename( RWDPA_PLUGIN_FILE ), 'rwdpa_core_compat_plugin_row', 10, 2 );
add_action( 'admin_init', 'rwdpa_core_compat_register_core_update_message' );

/**
 * Whether the watch is on.
 *
 * @return bool
 */
function rwdpa_core_compat_enabled() {
	return function_exists( 'rwdpa_get_settings' ) && ! empty( rwdpa_get_settings()['core_watch'] );
}

/**
 * Plugin basename of RW Dealer Portal (core).
 *
 * @return string
 */
function rwdpa_core_basename() {
	return defined( 'RWDP_PLUGIN_DIR' ) ? plugin_basename( RWDP_PLUGIN_DIR . 'rw-dealer-portal.php' ) : '';
}

/**
 * Newer core version waiting in WordPress updates, or ''.
 *
 * @return string
 */
function rwdpa_core_available_update() {
	$updates = get_site_transient( 'update_plugins' );
	$file    = rwdpa_core_basename();
	if ( ! $file || ! is_object( $updates ) || empty( $updates->response[ $file ]->new_version ) ) {
		return '';
	}
	$version = (string) $updates->response[ $file ]->new_version;
	return version_compare( $version, RWDP_VERSION, '>' ) ? $version : '';
}

/**
 * What the add-on needs from core, grouped by feature.
 *
 * Each check: [ feature, description, type, value ]. Types: `function`,
 * `constant`, `post_type`, `taxonomy`, or `source` (a string that must appear
 * in core's PHP/JS/CSS). Things the add-on already has a fallback for are left
 * out (e.g. the map/import filters core stopped calling in 1.0.19).
 *
 * @return array<int,array{0:string,1:string,2:string,3:string}>
 */
function rwdpa_core_compat_checks() {
	$reg    = __( 'Registration fields (username, phone, address)', 'rw-dealer-portal-addons' );
	$finder = __( 'Dealer Finder Layout', 'rw-dealer-portal-addons' );
	$area   = __( 'Service area map', 'rw-dealer-portal-addons' );
	$tiers  = __( 'Tiers and content visibility', 'rw-dealer-portal-addons' );
	$assets = __( 'Asset shortcode and asset import', 'rw-dealer-portal-addons' );
	$list   = __( 'Contractor list and map print button', 'rw-dealer-portal-addons' );
	$fields = __( 'Portal Details on dealers', 'rw-dealer-portal-addons' );
	$common = __( 'General', 'rw-dealer-portal-addons' );
	$states = __( 'Dealer State pages', 'rw-dealer-portal-addons' );

	$checks = [
		[ $reg, __( 'Request Access shortcode/widget name', 'rw-dealer-portal-addons' ), 'source', "'rwdp_request_access'" ],
		[ $reg, __( 'Message area the fields are inserted before', 'rw-dealer-portal-addons' ), 'source', 'id="rwdp-register-message"' ],
		[ $reg, __( 'Email field the username/address follow', 'rw-dealer-portal-addons' ), 'source', 'id="rwdp_reg_email"' ],
		[ $reg, __( 'Registration AJAX action', 'rw-dealer-portal-addons' ), 'source', 'rwdp_register_request' ],
		[ $reg, __( 'Registration nonce', 'rw-dealer-portal-addons' ), 'source', "'rwdp_registration'" ],
		[ $reg, __( 'Hidden-labels form class', 'rw-dealer-portal-addons' ), 'source', 'rwdp-auth-panel--no-labels' ],
		[ $finder, __( 'Dealer Search widget', 'rw-dealer-portal-addons' ), 'source', "'rwdp_dealer_search'" ],
		[ $finder, __( 'Dealer Map widget', 'rw-dealer-portal-addons' ), 'source', "'rwdp_dealer_map'" ],
		[ $finder, __( 'Dealer List widget', 'rw-dealer-portal-addons' ), 'source', "'rwdp_dealer_list'" ],
		[ $finder, __( 'Map element', 'rw-dealer-portal-addons' ), 'source', 'rwdp-map' ],
	];
	foreach ( [ 'body', 'actions', 'address-row', 'directions', 'view-on-map', 'more-info', 'title', 'phone' ] as $part ) {
		/* translators: %s: CSS class */
		$checks[] = [ $finder, sprintf( __( 'Result card class %s', 'rw-dealer-portal-addons' ), 'rwdp-result-card__' . $part ), 'source', 'rwdp-result-card__' . $part ];
	}

	return array_merge(
		$checks,
		[
			[ $area, __( 'Dealer map script handle', 'rw-dealer-portal-addons' ), 'source', "'rwdp-dealer-map'" ],
			[ $area, __( 'Map settings object (rwdpMap)', 'rw-dealer-portal-addons' ), 'source', 'rwdpMap' ],
			[ $area, __( 'Map info window class', 'rw-dealer-portal-addons' ), 'source', 'rwdp-infowindow' ],
			[ $area, __( '"View on map" button class', 'rw-dealer-portal-addons' ), 'source', 'rwdp-vom-btn' ],
			[ $tiers, 'rwdp_get_viewer_roles()', 'function', 'rwdp_get_viewer_roles' ],
			[ $tiers, 'rwdp_user_has_viewer_role()', 'function', 'rwdp_user_has_viewer_role' ],
			[ $tiers, 'RWDP_ALLOWED_ROLE_META', 'constant', 'RWDP_ALLOWED_ROLE_META' ],
			[ $tiers, __( 'Custom portal roles option', 'rw-dealer-portal-addons' ), 'source', 'rwdp_portal_roles' ],
			[ $tiers, __( 'User–dealer link meta', 'rw-dealer-portal-addons' ), 'source', '_rwdp_dealer_ids' ],
			[ $tiers, __( 'Account approval status meta', 'rw-dealer-portal-addons' ), 'source', '_rwdp_account_status' ],
			[ $assets, __( 'Asset post type', 'rw-dealer-portal-addons' ), 'post_type', 'rw_asset' ],
			[ $assets, __( 'Asset category taxonomy', 'rw-dealer-portal-addons' ), 'taxonomy', 'rw_asset_category' ],
			[ $assets, 'rwdp_render_asset_taxonomy_view()', 'function', 'rwdp_render_asset_taxonomy_view' ],
			[ $assets, 'rwdp_protect_typed_asset_files()', 'function', 'rwdp_protect_typed_asset_files' ],
			[ $assets, __( 'Asset type meta', 'rw-dealer-portal-addons' ), 'source', '_rwdp_asset_type' ],
			[ $assets, __( 'Asset gallery meta', 'rw-dealer-portal-addons' ), 'source', '_rwdp_asset_gallery_items' ],
			[ $assets, __( 'Asset PDF meta', 'rw-dealer-portal-addons' ), 'source', '_rwdp_asset_pdf_items' ],
			[ $assets, __( 'Asset video meta', 'rw-dealer-portal-addons' ), 'source', '_rwdp_asset_video_items' ],
			[ $list, 'rwdp_get_us_states_map()', 'function', 'rwdp_get_us_states_map' ],
			[ $list, __( 'Category filter', 'rw-dealer-portal-addons' ), 'source', 'rwdp-tax-filter' ],
			[ $list, __( 'ACF filters', 'rw-dealer-portal-addons' ), 'source', 'rwdp-acf-filter' ],
			[ $fields, __( 'Dealer post type', 'rw-dealer-portal-addons' ), 'post_type', 'rw_dealer' ],
			[ $fields, __( 'Dealer editor nonce', 'rw-dealer-portal-addons' ), 'source', 'rwdp_dealer_meta_nonce' ],
			[ $fields, __( 'Dealer coordinates meta', 'rw-dealer-portal-addons' ), 'source', '_rwdp_lat' ],
			[ $common, 'rwdp_get_page_url()', 'function', 'rwdp_get_page_url' ],
			[ $states, __( 'Dealer State taxonomy', 'rw-dealer-portal-addons' ), 'taxonomy', 'rw_dealer_state' ],
			[ $states, 'rwdp_sync_dealer_state_taxonomy()', 'function', 'rwdp_sync_dealer_state_taxonomy' ],
			[ $states, __( 'Dealer Type taxonomy', 'rw-dealer-portal-addons' ), 'taxonomy', 'rw_dealer_type' ],
			[ $states, __( 'Finder Dealer Type label', 'rw-dealer-portal-addons' ), 'source', "'Dealer Type:'" ],
		]
	);
}

/**
 * Core's PHP/JS/CSS as one string (bundled libraries excluded).
 *
 * @return string
 */
function rwdpa_core_compat_source() {
	if ( ! defined( 'RWDP_PLUGIN_DIR' ) || ! is_dir( RWDP_PLUGIN_DIR ) ) {
		return '';
	}
	$source = '';
	$files  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( RWDP_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		$path = $file->getPathname();
		if ( ! preg_match( '/\.(php|js|css)$/', $path ) || preg_match( '#/(plugin-update-checker|vendor|node_modules)/#', $path ) ) {
			continue;
		}
		$source .= (string) file_get_contents( $path ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin files
	}
	return $source;
}

/**
 * Run every check.
 *
 * @return array{core:string,checked:int,failed:array<int,array{feature:string,label:string}>,total:int}
 */
function rwdpa_core_compat_run() {
	$source = rwdpa_core_compat_source();
	$failed = [];
	$checks = rwdpa_core_compat_checks();

	foreach ( $checks as [ $feature, $label, $type, $value ] ) {
		switch ( $type ) {
			case 'function':
				$ok = function_exists( $value );
				break;
			case 'constant':
				$ok = defined( $value );
				break;
			case 'post_type':
				$ok = post_type_exists( $value );
				break;
			case 'taxonomy':
				$ok = taxonomy_exists( $value );
				break;
			default:
				$ok = '' !== $source && false !== strpos( $source, $value );
		}
		if ( ! $ok ) {
			$failed[] = [ 'feature' => $feature, 'label' => $label ];
		}
	}

	$result = [
		'core'    => RWDP_VERSION,
		'checked' => time(),
		'failed'  => $failed,
		'total'   => count( $checks ),
	];
	update_option( 'rwdpa_core_compat', $result, false );
	return $result;
}

/**
 * Latest results, re-running when core's version changed since the last run.
 *
 * @return array{core:string,checked:int,failed:array,total:int}
 */
function rwdpa_core_compat_results() {
	$saved = get_option( 'rwdpa_core_compat', [] );
	if ( ! is_array( $saved ) || ( $saved['core'] ?? '' ) !== RWDP_VERSION ) {
		$saved = rwdpa_core_compat_run();
	}
	return $saved;
}

/**
 * Handle the "Run check again" link on the Plugins page.
 */
function rwdpa_core_compat_maybe_recheck() {
	if ( empty( $_GET['rwdpa_core_check'] ) || ! rwdpa_core_compat_enabled() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	check_admin_referer( 'rwdpa_core_check' );
	rwdpa_core_compat_run();
	wp_safe_redirect( remove_query_arg( [ 'rwdpa_core_check', '_wpnonce' ] ) );
	exit;
}

/**
 * Notice row under the add-on on the Plugins page.
 *
 * @param string $file        Plugin basename.
 * @param array  $plugin_data Plugin data.
 */
function rwdpa_core_compat_plugin_row( $file, $plugin_data ) {
	if ( ! rwdpa_core_compat_enabled() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$results  = rwdpa_core_compat_results();
	$update   = rwdpa_core_available_update();
	$newer    = version_compare( RWDP_VERSION, RWDPA_TESTED_CORE, '>' );
	$messages = [];
	$type     = 'success';

	if ( $update && version_compare( $update, RWDPA_TESTED_CORE, '>' ) ) {
		$type       = 'warning';
		$messages[] = sprintf(
			/* translators: 1: new core version, 2: tested core version */
			esc_html__( 'RW Dealer Portal %1$s is available. This add-on was tested with %2$s: update on a staging site first and check the add-on features still work.', 'rw-dealer-portal-addons' ),
			esc_html( $update ),
			esc_html( RWDPA_TESTED_CORE )
		);
	}
	if ( $newer ) {
		$type       = 'warning';
		$messages[] = sprintf(
			/* translators: 1: installed core version, 2: tested core version */
			esc_html__( 'RW Dealer Portal was updated to %1$s. This add-on was tested with %2$s.', 'rw-dealer-portal-addons' ),
			esc_html( RWDP_VERSION ),
			esc_html( RWDPA_TESTED_CORE )
		);
	}

	if ( $results['failed'] ) {
		$type  = 'error';
		$items = '';
		foreach ( $results['failed'] as $fail ) {
			$items .= '<li><strong>' . esc_html( $fail['feature'] ) . ':</strong> ' . esc_html( $fail['label'] ) . '</li>';
		}
		$messages[] = sprintf(
			/* translators: 1: number of failed checks, 2: number of checks, 3: core version */
			esc_html__( 'Compatibility check: %1$d of %2$d things this add-on needs were not found in RW Dealer Portal %3$s. These features may not work:', 'rw-dealer-portal-addons' ),
			count( $results['failed'] ),
			(int) $results['total'],
			esc_html( $results['core'] )
		) . '<ul style="list-style:disc;margin:4px 0 0 20px;">' . $items . '</ul>';
	} else {
		$messages[] = sprintf(
			/* translators: 1: number of checks, 2: core version */
			esc_html__( 'Compatibility check passed: all %1$d things this add-on needs were found in RW Dealer Portal %2$s.', 'rw-dealer-portal-addons' ),
			(int) $results['total'],
			esc_html( $results['core'] )
		);
	}

	$recheck = wp_nonce_url( add_query_arg( 'rwdpa_core_check', 1, self_admin_url( 'plugins.php' ) ), 'rwdpa_core_check' );
	$active  = is_plugin_active( $file ) ? ' active' : '';
	?>
	<tr class="plugin-update-tr<?php echo esc_attr( $active ); ?> rwdpa-core-compat-row">
		<td colspan="<?php echo esc_attr( (string) ( isset( $GLOBALS['wp_list_table'] ) ? $GLOBALS['wp_list_table']->get_column_count() : 4 ) ); ?>" class="plugin-update colspanchange">
			<div class="notice inline notice-<?php echo esc_attr( $type ); ?> notice-alt">
				<?php foreach ( $messages as $message ) : ?>
					<p><?php echo wp_kses( $message, [ 'strong' => [], 'ul' => [ 'style' => [] ], 'li' => [] ] ); ?></p>
				<?php endforeach; ?>
				<p>
					<?php
					printf(
						/* translators: %s: date and time of the last check */
						esc_html__( 'Last checked %s.', 'rw-dealer-portal-addons' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $results['checked'] ) )
					);
					?>
					<a href="<?php echo esc_url( $recheck ); ?>"><?php esc_html_e( 'Run check again', 'rw-dealer-portal-addons' ); ?></a>
				</p>
			</div>
		</td>
	</tr>
	<?php
}

/**
 * Add a line to core's own "update available" message on the Plugins page.
 */
function rwdpa_core_compat_register_core_update_message() {
	if ( rwdpa_core_compat_enabled() && rwdpa_core_basename() ) {
		add_action( 'in_plugin_update_message-' . rwdpa_core_basename(), 'rwdpa_core_compat_core_update_message', 10, 2 );
	}
}

/**
 * @param array  $plugin_data Plugin data.
 * @param object $response    Update response.
 */
function rwdpa_core_compat_core_update_message( $plugin_data, $response ) {
	$version = (string) ( $response->new_version ?? '' );
	if ( ! $version || ! version_compare( $version, RWDPA_TESTED_CORE, '>' ) ) {
		return;
	}
	echo '<br /><strong>' . esc_html(
		sprintf(
			/* translators: %s: tested core version */
			__( 'RW Dealer Portal Addons was tested with %s. Update on a staging site first.', 'rw-dealer-portal-addons' ),
			RWDPA_TESTED_CORE
		)
	) . '</strong>';
}
