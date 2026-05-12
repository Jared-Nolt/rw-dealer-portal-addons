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
