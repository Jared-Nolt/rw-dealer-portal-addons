<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'rwdpa_register_settings_menu' );
add_action( 'admin_init', 'rwdpa_register_settings' );
add_action( 'admin_enqueue_scripts', 'rwdpa_admin_enqueue_assets' );

/**
 * Register add-on settings menu.
 */
function rwdpa_register_settings_menu() {
	add_submenu_page(
		'rw-dealer-portal',
		__( 'Addons', 'rw-dealer-portal-addons' ),
		__( 'Addons', 'rw-dealer-portal-addons' ),
		'manage_options',
		'rwdpa-settings',
		'rwdpa_render_settings_page'
	);
}

/**
 * Register add-on settings option.
 */
function rwdpa_register_settings() {
	register_setting( 'rwdpa_settings_group', 'rwdpa_settings', [
		'sanitize_callback' => 'rwdpa_sanitize_settings',
	] );
}

/**
 * Get add-on settings with legacy fallback values.
 *
 * @return array<string,mixed>
 */
function rwdpa_get_settings() {
	$defaults = [
		'contractor_list_subject_label' => __( 'Contractor', 'rw-dealer-portal-addons' ),
		'contractor_list_logo_id'      => 0,
		'contractor_list_address'      => '',
		'contractor_list_disclaimer'   => '',
		'contractor_list_show_columns' => [ 'company', 'address', 'city', 'state', 'zip', 'phone' ],
		'dealer_url_base'              => '',
		'enable_service_area'          => 1,
		'show_service_area_in_results' => 1,
		'show_service_area_in_popup'   => 1,
		'show_print_button_on_map'     => 0,
		'print_button_text'            => __( 'Download Contractor List', 'rw-dealer-portal-addons' ),
		'finder_style'                 => 0,
		'core_watch'                   => 0,
		'dealer_state_pages'           => 0,
		'dealer_state_base'            => 'dealer-state',
		'dealer_state_sync_type'       => 0,
		'dealer_state_relabel'         => 0,
	];

	$settings = get_option( 'rwdpa_settings', [] );
	if ( ! is_array( $settings ) ) {
		$settings = [];
	}

	// Fallback to legacy core option keys if add-on option is empty.
	if ( empty( $settings ) ) {
		$legacy = get_option( 'rwdp_settings', [] );
		if ( is_array( $legacy ) ) {
			$settings = [
				'contractor_list_subject_label' => $legacy['contractor_list_subject_label'] ?? $defaults['contractor_list_subject_label'],
				'contractor_list_logo_id'      => $legacy['contractor_list_logo_id'] ?? 0,
				'contractor_list_address'      => $legacy['contractor_list_address'] ?? '',
				'contractor_list_disclaimer'   => $legacy['contractor_list_disclaimer'] ?? '',
				'contractor_list_show_columns' => $legacy['contractor_list_show_columns'] ?? $defaults['contractor_list_show_columns'],
				'show_service_area_in_results' => $legacy['show_service_area_in_results'] ?? $defaults['show_service_area_in_results'],
				'show_service_area_in_popup'   => $legacy['show_service_area_in_popup'] ?? $defaults['show_service_area_in_popup'],
			];
		}
	}

	$settings = wp_parse_args( $settings, $defaults );
	$settings['contractor_list_subject_label'] = sanitize_text_field( $settings['contractor_list_subject_label'] ?? '' );
	if ( '' === $settings['contractor_list_subject_label'] ) {
		$settings['contractor_list_subject_label'] = $defaults['contractor_list_subject_label'];
	}
	$settings['contractor_list_logo_id'] = absint( $settings['contractor_list_logo_id'] ?? 0 );
	$settings['contractor_list_address'] = sanitize_textarea_field( $settings['contractor_list_address'] ?? '' );
	$settings['contractor_list_disclaimer'] = sanitize_textarea_field( $settings['contractor_list_disclaimer'] ?? '' );
	$settings['show_service_area_in_results'] = ! empty( $settings['show_service_area_in_results'] ) ? 1 : 0;
	$settings['dealer_url_base'] = sanitize_title( $settings['dealer_url_base'] ?? '' );
	$settings['enable_service_area'] = ! empty( $settings['enable_service_area'] ) ? 1 : 0;
	$settings['show_service_area_in_popup'] = ! empty( $settings['show_service_area_in_popup'] ) ? 1 : 0;
	$settings['show_print_button_on_map'] = ! empty( $settings['show_print_button_on_map'] ) ? 1 : 0;
	$settings['finder_style'] = ! empty( $settings['finder_style'] ) ? 1 : 0;
	$settings['core_watch']   = ! empty( $settings['core_watch'] ) ? 1 : 0;
	$settings['dealer_state_pages']     = ! empty( $settings['dealer_state_pages'] ) ? 1 : 0;
	$settings['dealer_state_base']      = rwdpa_sanitize_state_base( $settings['dealer_state_base'] ?? '' );
	$settings['dealer_state_sync_type'] = ! empty( $settings['dealer_state_sync_type'] ) ? 1 : 0;
	$settings['dealer_state_relabel']   = ! empty( $settings['dealer_state_relabel'] ) ? 1 : 0;
	$settings['print_button_text'] = sanitize_text_field( $settings['print_button_text'] ?? '' );
	if ( '' === $settings['print_button_text'] ) {
		$settings['print_button_text'] = $defaults['print_button_text'];
	}

	$allowed_cols = [ 'company', 'contact_name', 'address', 'city', 'state', 'zip', 'phone', 'email', 'website', 'hours' ];
	$raw_cols = is_array( $settings['contractor_list_show_columns'] ?? null ) ? $settings['contractor_list_show_columns'] : [];
	$cols = [];
	foreach ( $raw_cols as $col ) {
		$col = sanitize_key( $col );
		if ( in_array( $col, $allowed_cols, true ) ) {
			$cols[] = $col;
		}
	}
	$settings['contractor_list_show_columns'] = ! empty( $cols ) ? array_values( array_unique( $cols ) ) : $defaults['contractor_list_show_columns'];
	$settings['contractor_list_show_columns'] = array_values( array_intersect( array_keys( rwdp_get_contractor_list_column_labels() ), $settings['contractor_list_show_columns'] ) );

	return $settings;
}

/**
 * State page URL base: path segments of letters, numbers and dashes ('dealer-state' when empty).
 *
 * @param mixed $base Raw base.
 * @return string
 */
function rwdpa_sanitize_state_base( $base ) {
	$base = implode( '/', array_filter( array_map( 'sanitize_title', explode( '/', trim( (string) $base, '/' ) ) ) ) );
	return '' !== $base ? $base : 'dealer-state';
}

/**
 * Sanitize settings before save.
 *
 * @param array<string,mixed> $raw Raw settings.
 * @return array<string,mixed>
 */
function rwdpa_sanitize_settings( $raw ) {
	$clean = [];
	$clean['contractor_list_subject_label'] = sanitize_text_field( $raw['contractor_list_subject_label'] ?? '' );
	if ( '' === $clean['contractor_list_subject_label'] ) {
		$clean['contractor_list_subject_label'] = __( 'Contractor', 'rw-dealer-portal-addons' );
	}
	$clean['contractor_list_logo_id'] = absint( $raw['contractor_list_logo_id'] ?? 0 );
	$clean['contractor_list_address'] = sanitize_textarea_field( $raw['contractor_list_address'] ?? '' );
	$clean['contractor_list_disclaimer'] = sanitize_textarea_field( $raw['contractor_list_disclaimer'] ?? '' );
	$clean['show_service_area_in_results'] = ! empty( $raw['show_service_area_in_results'] ) ? 1 : 0;
	$clean['dealer_url_base'] = sanitize_title( $raw['dealer_url_base'] ?? '' );
	if ( 'dealer' === $clean['dealer_url_base'] ) {
		$clean['dealer_url_base'] = '';
	}
	$clean['enable_service_area'] = ! empty( $raw['enable_service_area'] ) ? 1 : 0;
	$clean['show_service_area_in_popup'] = ! empty( $raw['show_service_area_in_popup'] ) ? 1 : 0;
	$clean['show_print_button_on_map'] = ! empty( $raw['show_print_button_on_map'] ) ? 1 : 0;
	$clean['finder_style'] = ! empty( $raw['finder_style'] ) ? 1 : 0;
	$clean['core_watch']   = ! empty( $raw['core_watch'] ) ? 1 : 0;
	$clean['dealer_state_pages']     = ! empty( $raw['dealer_state_pages'] ) ? 1 : 0;
	$clean['dealer_state_base']      = rwdpa_sanitize_state_base( $raw['dealer_state_base'] ?? '' );
	$clean['dealer_state_sync_type'] = ! empty( $raw['dealer_state_sync_type'] ) ? 1 : 0;
	$clean['dealer_state_relabel']   = ! empty( $raw['dealer_state_relabel'] ) ? 1 : 0;
	$clean['print_button_text'] = sanitize_text_field( $raw['print_button_text'] ?? '' );
	if ( '' === $clean['print_button_text'] ) {
		$clean['print_button_text'] = __( 'Download Contractor List', 'rw-dealer-portal-addons' );
	}

	$allowed_cols = [ 'company', 'contact_name', 'address', 'city', 'state', 'zip', 'phone', 'email', 'website', 'hours' ];
	$raw_cols = is_array( $raw['contractor_list_show_columns'] ?? null ) ? $raw['contractor_list_show_columns'] : [];
	$cols = [];
	foreach ( $raw_cols as $col ) {
		$col = sanitize_key( $col );
		if ( in_array( $col, $allowed_cols, true ) ) {
			$cols[] = $col;
		}
	}
	$clean['contractor_list_show_columns'] = ! empty( $cols ) ? array_values( array_unique( $cols ) ) : [ 'company', 'address', 'city', 'state', 'zip', 'phone' ];

	return $clean;
}

/**
 * Render add-on settings page.
 */
function rwdpa_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'rw-dealer-portal-addons' ) );
	}

	$settings = rwdpa_get_settings();
	$columns  = [
		'company'      => __( 'Company', 'rw-dealer-portal-addons' ),
		'contact_name' => __( 'Contact Name', 'rw-dealer-portal-addons' ),
		'address'      => __( 'Address', 'rw-dealer-portal-addons' ),
		'city'         => __( 'City', 'rw-dealer-portal-addons' ),
		'state'        => __( 'State', 'rw-dealer-portal-addons' ),
		'zip'          => __( 'ZIP', 'rw-dealer-portal-addons' ),
		'phone'        => __( 'Phone', 'rw-dealer-portal-addons' ),
		'email'        => __( 'Email', 'rw-dealer-portal-addons' ),
		'website'      => __( 'Website', 'rw-dealer-portal-addons' ),
		'hours'        => __( 'Hours', 'rw-dealer-portal-addons' ),
	];
	$logo_src = $settings['contractor_list_logo_id'] ? wp_get_attachment_image_url( $settings['contractor_list_logo_id'], 'medium' ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Addons', 'rw-dealer-portal-addons' ); ?></h1>
		<p><?php esc_html_e( 'Configure contractor list print/PDF settings used by the map print button and Contractor List Elementor widget.', 'rw-dealer-portal-addons' ); ?></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'rwdpa_settings_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwdpa_contractor_list_subject_label"><?php esc_html_e( 'List Subject Label', 'rw-dealer-portal-addons' ); ?></label></th>
					<td>
						<input type="text" id="rwdpa_contractor_list_subject_label" name="rwdpa_settings[contractor_list_subject_label]" class="regular-text" value="<?php echo esc_attr( $settings['contractor_list_subject_label'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Used in print title as "{Subject} List" and "{Filter} {Subject} List". Example: Contractor, Dealer, Supplier.', 'rw-dealer-portal-addons' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Header Logo', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<div id="rwdpa-contractor-logo-preview-wrap" style="margin-bottom:8px;">
							<?php if ( $logo_src ) : ?>
								<img id="rwdpa-contractor-logo-preview" src="<?php echo esc_url( $logo_src ); ?>" alt="" style="max-width:220px;height:auto;display:block;" />
							<?php else : ?>
								<img id="rwdpa-contractor-logo-preview" src="" alt="" style="max-width:220px;height:auto;display:none;" />
							<?php endif; ?>
						</div>
						<input type="hidden" id="rwdpa_contractor_list_logo_id" name="rwdpa_settings[contractor_list_logo_id]" value="<?php echo absint( $settings['contractor_list_logo_id'] ); ?>" />
						<button type="button" class="button" id="rwdpa-contractor-logo-upload"><?php esc_html_e( 'Upload / Change Logo', 'rw-dealer-portal-addons' ); ?></button>
						<button type="button" class="button" id="rwdpa-contractor-logo-remove"<?php echo $settings['contractor_list_logo_id'] ? '' : ' style="display:none;"'; ?>><?php esc_html_e( 'Remove Logo', 'rw-dealer-portal-addons' ); ?></button>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="rwdpa_contractor_list_address"><?php esc_html_e( 'Header Address', 'rw-dealer-portal-addons' ); ?></label></th>
					<td>
						<textarea id="rwdpa_contractor_list_address" name="rwdpa_settings[contractor_list_address]" rows="4" class="large-text"><?php echo esc_textarea( $settings['contractor_list_address'] ); ?></textarea>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Visible Columns', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $columns as $column_key => $column_label ) : ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox" name="rwdpa_settings[contractor_list_show_columns][]" value="<?php echo esc_attr( $column_key ); ?>" <?php checked( in_array( $column_key, $settings['contractor_list_show_columns'], true ) ); ?> />
									<?php echo esc_html( $column_label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="rwdpa_contractor_list_disclaimer"><?php esc_html_e( 'Disclaimer', 'rw-dealer-portal-addons' ); ?></label></th>
					<td>
						<textarea id="rwdpa_contractor_list_disclaimer" name="rwdpa_settings[contractor_list_disclaimer]" rows="4" class="large-text"><?php echo esc_textarea( $settings['contractor_list_disclaimer'] ); ?></textarea>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="rwdpa_dealer_url_base"><?php esc_html_e( 'Dealer URL Base', 'rw-dealer-portal-addons' ); ?></label></th>
					<td>
						<code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" id="rwdpa_dealer_url_base" name="rwdpa_settings[dealer_url_base]" value="<?php echo esc_attr( $settings['dealer_url_base'] ); ?>" placeholder="dealer" style="width:160px;" /><code>/dealer-name/</code>
						<p class="description"><?php esc_html_e( 'Leave empty for the default /dealer/. Change it if another post type already uses /dealer/ on this site. Permalinks are refreshed when you save.', 'rw-dealer-portal-addons' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Service Area', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="rwdpa_settings[enable_service_area]" value="1" <?php checked( ! empty( $settings['enable_service_area'] ) ); ?> />
							<?php esc_html_e( 'Use service area (radius field on dealers, circle and labels on the dealer map)', 'rw-dealer-portal-addons' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Service Area Display', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<fieldset>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="rwdpa_settings[show_service_area_in_results]" value="1" <?php checked( ! empty( $settings['show_service_area_in_results'] ) ); ?> />
								<?php esc_html_e( 'Show service area value on Dealer Results cards', 'rw-dealer-portal-addons' ); ?>
							</label>
							<label style="display:block;">
								<input type="checkbox" name="rwdpa_settings[show_service_area_in_popup]" value="1" <?php checked( ! empty( $settings['show_service_area_in_popup'] ) ); ?> />
								<?php esc_html_e( 'Show service area value in Dealer Map popup', 'rw-dealer-portal-addons' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Dealer Finder Layout', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="rwdpa_settings[finder_style]" value="1" <?php checked( ! empty( $settings['finder_style'] ) ); ?> />
							<?php esc_html_e( 'Store-locator layout for the Dealer Search, Dealer Map and Dealer List widgets: search on top, a scrollable dealer list beside a map that stays in view (stacked on phones), and cleaner result cards.', 'rw-dealer-portal-addons' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Put the three widgets in the same classic Elementor Container. Colors and fonts: --rwdpa-finder-accent, --rwdpa-finder-link, --rwdpa-finder-label-font, --rwdpa-finder-height (CSS custom properties on body).', 'rw-dealer-portal-addons' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Dealer State Pages', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<?php if ( ! empty( $GLOBALS['rwdpa_core_states_public'] ) ) : ?>
							<p class="description"><strong><?php esc_html_e( 'RW Dealer Portal now provides state pages itself, so this setting has no effect. Turn it off and use the core feature.', 'rw-dealer-portal-addons' ); ?></strong></p>
						<?php endif; ?>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="rwdpa_settings[dealer_state_pages]" value="1" <?php checked( ! empty( $settings['dealer_state_pages'] ) ); ?> />
							<?php esc_html_e( 'Give each state a page listing its dealers, from the State field in each dealer\'s address', 'rw-dealer-portal-addons' ); ?>
						</label>
						<p style="margin:0 0 10px;">
							<label for="rwdpa_dealer_state_base"><?php esc_html_e( 'URL base', 'rw-dealer-portal-addons' ); ?></label><br />
							<code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" id="rwdpa_dealer_state_base" name="rwdpa_settings[dealer_state_base]" value="<?php echo esc_attr( $settings['dealer_state_base'] ); ?>" placeholder="dealer-state" style="width:160px;" /><code>/ohio/</code>
						</p>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="rwdpa_settings[dealer_state_sync_type]" value="1" <?php checked( ! empty( $settings['dealer_state_sync_type'] ) ); ?> />
							<?php esc_html_e( 'Keep Dealer Type in sync with the state, so the finder\'s filter and "Lock to Dealer Type" work by state', 'rw-dealer-portal-addons' ); ?>
						</label>
						<label style="display:block;margin:0 0 6px 24px;">
							<input type="checkbox" name="rwdpa_settings[dealer_state_relabel]" value="1" <?php checked( ! empty( $settings['dealer_state_relabel'] ) ); ?> />
							<?php esc_html_e( 'Label the finder\'s Dealer Type dropdown "State"', 'rw-dealer-portal-addons' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Design state pages with an Elementor Theme Builder archive template (condition: Dealer States). List states with [rwdpa_dealer_states]. To limit a finder to one state, set the Dealer Search widget\'s "Lock to Dealer Type" to the state slug, e.g. ohio. Turning the Dealer Type sync off removes the state types it added; real types stay. Interim feature: remove it when RW Dealer Portal adds state pages.', 'rw-dealer-portal-addons' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Core Update Watch', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="rwdpa_settings[core_watch]" value="1" <?php checked( ! empty( $settings['core_watch'] ) ); ?> />
							<?php esc_html_e( 'On the Plugins page, show a notice under this add-on when RW Dealer Portal has an update or is newer than the version this add-on was tested with, and check that everything the add-on needs from RW Dealer Portal is still there.', 'rw-dealer-portal-addons' ); ?>
						</label>
						<p class="description">
							<?php
							printf(
								/* translators: 1: tested core version, 2: installed core version */
								esc_html__( 'Tested with RW Dealer Portal %1$s. Installed: %2$s. Only administrators see the notice; the check re-runs automatically after RW Dealer Portal updates.', 'rw-dealer-portal-addons' ),
								esc_html( RWDPA_TESTED_CORE ),
								esc_html( defined( 'RWDP_VERSION' ) ? RWDP_VERSION : '—' )
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Map Print Button', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<fieldset>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="rwdpa_settings[show_print_button_on_map]" value="1" <?php checked( ! empty( $settings['show_print_button_on_map'] ) ); ?> />
								<?php esc_html_e( 'Show a "Download Contractor List" print button on the Dealer Map widget', 'rw-dealer-portal-addons' ); ?>
							</label>
						</fieldset>
						<p>
							<label for="rwdpa_print_button_text"><?php esc_html_e( 'Button Text', 'rw-dealer-portal-addons' ); ?></label><br />
							<input type="text" id="rwdpa_print_button_text" name="rwdpa_settings[print_button_text]" class="regular-text" value="<?php echo esc_attr( $settings['print_button_text'] ); ?>" />
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<script>
		(function($){
			var frame;
			$('#rwdpa-contractor-logo-upload').on('click', function(e){
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({
					title: <?php echo wp_json_encode( __( 'Select Contractor List Logo', 'rw-dealer-portal-addons' ) ); ?>,
					button: { text: <?php echo wp_json_encode( __( 'Use this logo', 'rw-dealer-portal-addons' ) ); ?> },
					multiple: false
				});
				frame.on('select', function(){
					var attachment = frame.state().get('selection').first().toJSON();
					$('#rwdpa_contractor_list_logo_id').val(attachment.id);
					$('#rwdpa-contractor-logo-preview').attr('src', attachment.url).show();
					$('#rwdpa-contractor-logo-remove').show();
				});
				frame.open();
			});

			$('#rwdpa-contractor-logo-remove').on('click', function(e){
				e.preventDefault();
				$('#rwdpa_contractor_list_logo_id').val('0');
				$('#rwdpa-contractor-logo-preview').attr('src','').hide();
				$(this).hide();
			});
		})(jQuery);
		</script>
	</div>
	<?php
}

/**
 * Enqueue media scripts only on add-on settings page.
 */
function rwdpa_admin_enqueue_assets( $hook ) {
	if ( 'dealer-portal_page_rwdpa-settings' !== $hook ) {
		return;
	}

	wp_enqueue_media();
}
