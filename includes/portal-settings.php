<?php
/**
 * Portal display settings: dealer tiers, sales managers, office contact and
 * registration extras. Stored in the `rwdpa_portal` option, edited on
 * Dealer Portal → Portal Display. Every feature is off until enabled so
 * existing sites are unaffected by an update.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'rwdpa_portal_register_menu', 20 );
add_action( 'admin_init', 'rwdpa_portal_register_setting' );
add_action( 'admin_enqueue_scripts', 'rwdpa_portal_admin_assets' );

/**
 * Settings tabs.
 *
 * @return array<string,string>
 */
function rwdpa_portal_tabs() {
	/**
	 * Filter the Portal Display tabs. A tab with a
	 * `rwdpa_portal_render_tab_{key}` action renders outside the settings form.
	 *
	 * @param array<string,string> $tabs Tab key => label.
	 */
	return apply_filters( 'rwdpa_portal_tabs', [
		'tiers'        => __( 'Dealer Tiers', 'rw-dealer-portal-addons' ),
		'managers'     => __( 'Sales Managers', 'rw-dealer-portal-addons' ),
		'office'       => __( 'Office Contact', 'rw-dealer-portal-addons' ),
		'registration' => __( 'Registration', 'rw-dealer-portal-addons' ),
	] );
}

/**
 * Default settings.
 *
 * @return array<string,mixed>
 */
function rwdpa_portal_defaults() {
	return [
		'enable_tiers'            => 0,
		'cumulative_tiers'        => 1,
		'tiers'                   => [],
		'benefits_heading'        => __( 'Your Benefits Include:', 'rw-dealer-portal-addons' ),
		'enable_managers'         => 0,
		'manager_role_label'      => __( 'Sales Manager', 'rw-dealer-portal-addons' ),
		'manager_caps_from'       => '',
		'default_manager'         => 0,
		'manager_heading'         => __( 'Your Sales Manager:', 'rw-dealer-portal-addons' ),
		'office_name'             => '',
		'office_phone'            => '',
		'office_fax'              => '',
		'office_email'            => '',
		'office_address'          => '',
		'registration_phone'      => 0,
		'registration_phone_req'  => 1,
		'registration_phone_text' => __( 'Phone Number', 'rw-dealer-portal-addons' ),
	];
}

/**
 * Get portal display settings merged with defaults.
 *
 * @return array<string,mixed>
 */
function rwdpa_portal_settings() {
	$saved = get_option( 'rwdpa_portal', [] );
	return wp_parse_args( is_array( $saved ) ? $saved : [], rwdpa_portal_defaults() );
}

/**
 * Get a single portal display setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function rwdpa_portal_setting( $key ) {
	$settings = rwdpa_portal_settings();
	return $settings[ $key ] ?? null;
}

/**
 * Add the settings page under Dealer Portal.
 */
function rwdpa_portal_register_menu() {
	add_submenu_page(
		'rw-dealer-portal',
		__( 'Portal Display', 'rw-dealer-portal-addons' ),
		__( 'Portal Display', 'rw-dealer-portal-addons' ),
		'manage_options',
		'rwdpa-portal',
		'rwdpa_portal_render_page'
	);
}

/**
 * Register the option.
 */
function rwdpa_portal_register_setting() {
	register_setting( 'rwdpa_portal_group', 'rwdpa_portal', [
		'sanitize_callback' => 'rwdpa_portal_sanitize',
	] );
}

/**
 * Sanitize one tab's fields and merge them into the saved option, so saving
 * one tab never wipes the others.
 *
 * @param array<string,mixed> $raw Submitted values.
 * @return array<string,mixed>
 */
function rwdpa_portal_sanitize( $raw ) {
	$clean = rwdpa_portal_settings();
	$raw   = is_array( $raw ) ? $raw : [];
	$tab   = sanitize_key( $raw['_tab'] ?? '' );

	if ( 'tiers' === $tab ) {
		$clean['enable_tiers']     = ! empty( $raw['enable_tiers'] ) ? 1 : 0;
		$clean['cumulative_tiers'] = ! empty( $raw['cumulative_tiers'] ) ? 1 : 0;
		$clean['benefits_heading'] = sanitize_text_field( $raw['benefits_heading'] ?? '' );

		$tiers     = [];
		$raw_tiers = is_array( $raw['tiers'] ?? null ) ? $raw['tiers'] : [];
		$roles     = function_exists( 'rwdp_get_viewer_roles' ) ? rwdp_get_viewer_roles() : [];
		foreach ( $raw_tiers as $slug => $tier ) {
			$slug = sanitize_key( $slug );
			if ( ! isset( $roles[ $slug ] ) || ! is_array( $tier ) ) {
				continue;
			}
			$benefits = [];
			foreach ( (array) ( $tier['benefits'] ?? [] ) as $benefit ) {
				$text = wp_kses( $benefit['text'] ?? '', rwdpa_benefit_allowed_html() );
				if ( '' !== trim( wp_strip_all_tags( $text ) ) ) {
					$benefits[] = [ 'text' => $text ];
				}
			}
			$tiers[ $slug ] = [
				'rank'     => (int) ( $tier['rank'] ?? 0 ),
				'title'    => sanitize_text_field( $tier['title'] ?? '' ),
				'intro'    => wp_kses_post( $tier['intro'] ?? '' ),
				'badge_id' => absint( $tier['badge_id'] ?? 0 ),
				'accent'   => sanitize_hex_color( $tier['accent'] ?? '' ) ?: '',
				'benefits' => $benefits,
			];
		}
		$clean['tiers'] = $tiers;
	}

	if ( 'managers' === $tab ) {
		$clean['enable_managers']    = ! empty( $raw['enable_managers'] ) ? 1 : 0;
		$clean['manager_role_label'] = sanitize_text_field( $raw['manager_role_label'] ?? '' ) ?: rwdpa_portal_defaults()['manager_role_label'];
		$clean['default_manager']    = absint( $raw['default_manager'] ?? 0 );
		$caps_from                   = sanitize_key( $raw['manager_caps_from'] ?? '' );
		$clean['manager_caps_from']  = ( '' !== $caps_from && RWDPA_MANAGER_ROLE !== $caps_from && get_role( $caps_from ) ) ? $caps_from : '';
		$clean['manager_heading']    = sanitize_text_field( $raw['manager_heading'] ?? '' );
	}

	if ( 'office' === $tab ) {
		$clean['office_name']    = sanitize_text_field( $raw['office_name'] ?? '' );
		$clean['office_phone']   = sanitize_text_field( $raw['office_phone'] ?? '' );
		$clean['office_fax']     = sanitize_text_field( $raw['office_fax'] ?? '' );
		$clean['office_email']   = sanitize_email( $raw['office_email'] ?? '' );
		$clean['office_address'] = sanitize_textarea_field( $raw['office_address'] ?? '' );
	}

	if ( 'registration' === $tab ) {
		$clean['registration_phone']      = ! empty( $raw['registration_phone'] ) ? 1 : 0;
		$clean['registration_phone_req']  = ! empty( $raw['registration_phone_req'] ) ? 1 : 0;
		$clean['registration_phone_text'] = sanitize_text_field( $raw['registration_phone_text'] ?? '' );
	}

	return $clean;
}

/**
 * HTML allowed in a benefit line (bold highlights and line breaks).
 *
 * @return array<string,array>
 */
function rwdpa_benefit_allowed_html() {
	return [
		'strong' => [],
		'b'      => [],
		'em'     => [],
		'br'     => [],
		'span'   => [ 'class' => true ],
	];
}

/**
 * Admin assets for the settings page.
 *
 * @param string $hook Admin page hook.
 */
function rwdpa_portal_admin_assets( $hook ) {
	if ( 'dealer-portal_page_rwdpa-portal' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'rwdpa-portal-admin', RWDPA_PLUGIN_URL . 'assets/js/portal-admin.js', [ 'jquery', 'wp-color-picker' ], RWDPA_VERSION, true );
}

/**
 * Render the settings page.
 */
function rwdpa_portal_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'rw-dealer-portal-addons' ) );
	}

	$tabs     = rwdpa_portal_tabs();
	$tab      = sanitize_key( $_GET['tab'] ?? 'tiers' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab      = isset( $tabs[ $tab ] ) ? $tab : 'tiers';
	$settings = rwdpa_portal_settings();
	$base_url = admin_url( 'admin.php?page=rwdpa-portal' );
	?>
	<div class="wrap rwdpa-portal-settings">
		<h1><?php esc_html_e( 'Portal Display', 'rw-dealer-portal-addons' ); ?></h1>
		<?php settings_errors(); ?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base_url ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( has_action( 'rwdpa_portal_render_tab_' . $tab ) ) : ?>
			<?php do_action( 'rwdpa_portal_render_tab_' . $tab ); ?>
		<?php else : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'rwdpa_portal_group' ); ?>
			<input type="hidden" name="rwdpa_portal[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( add_query_arg( 'tab', $tab, $base_url ) ); ?>" />
			<?php
			switch ( $tab ) {
				case 'managers':
					rwdpa_portal_render_managers_tab( $settings );
					break;
				case 'office':
					rwdpa_portal_render_office_tab( $settings );
					break;
				case 'registration':
					rwdpa_portal_render_registration_tab( $settings );
					break;
				default:
					rwdpa_portal_render_tiers_tab( $settings );
			}
			submit_button();
			?>
		</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Tiers tab.
 *
 * @param array<string,mixed> $settings Settings.
 */
function rwdpa_portal_render_tiers_tab( $settings ) {
	$roles = function_exists( 'rwdp_get_viewer_roles' ) ? rwdp_get_viewer_roles() : [];
	$tiers = rwdpa_get_tiers( true );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Dealer Tiers', 'rw-dealer-portal-addons' ); ?></th>
			<td>
				<label><input type="checkbox" name="rwdpa_portal[enable_tiers]" value="1" <?php checked( $settings['enable_tiers'] ); ?> /> <?php esc_html_e( 'Enable dealer tiers', 'rw-dealer-portal-addons' ); ?></label>
				<p class="description"><?php esc_html_e( 'Adds a Tier field to each dealer. Users linked to a dealer get that tier\'s portal role automatically, and the [rwdpa_tier_card] shortcode shows their tier and benefits.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Tier Access', 'rw-dealer-portal-addons' ); ?></th>
			<td>
				<label><input type="checkbox" name="rwdpa_portal[cumulative_tiers]" value="1" <?php checked( $settings['cumulative_tiers'] ); ?> /> <?php esc_html_e( 'Higher tiers include lower tiers', 'rw-dealer-portal-addons' ); ?></label>
				<p class="description"><?php esc_html_e( 'Users also receive every lower-ranked tier role, so content limited to a tier is visible to that tier and above. Untick to give users only their own tier role.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rwdpa_benefits_heading"><?php esc_html_e( 'Benefits Heading', 'rw-dealer-portal-addons' ); ?></label></th>
			<td><input type="text" id="rwdpa_benefits_heading" class="regular-text" name="rwdpa_portal[benefits_heading]" value="<?php echo esc_attr( $settings['benefits_heading'] ); ?>" /></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Tiers', 'rw-dealer-portal-addons' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: %s: link to Portal Roles settings */
			esc_html__( 'Each portal role is a tier. Add or rename roles in %s. Rank orders the tiers: lowest is the default for dealers without a tier.', 'rw-dealer-portal-addons' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=rwdp-settings&tab=portal_roles' ) ) . '">' . esc_html__( 'Dealer Portal → Settings → Portal Roles', 'rw-dealer-portal-addons' ) . '</a>'
		);
		?>
		<?php esc_html_e( 'In benefits and intro text, {protected_radius} is replaced with the dealer\'s protected radius. Benefits that use it are hidden for dealers without one.', 'rw-dealer-portal-addons' ); ?>
	</p>

	<?php foreach ( $roles as $slug => $role_label ) : ?>
		<?php
		$tier     = $tiers[ $slug ] ?? rwdpa_tier_defaults( $slug, $role_label );
		$name     = 'rwdpa_portal[tiers][' . $slug . ']';
		$badge    = $tier['badge_id'] ? wp_get_attachment_image_url( $tier['badge_id'], 'thumbnail' ) : '';
		$benefits = $tier['benefits'] ?: [ [ 'text' => '' ] ];
		?>
		<div class="postbox" style="padding:12px 16px;margin-top:16px;">
			<h3 style="margin-top:0;"><?php echo esc_html( $role_label ); ?> <code><?php echo esc_html( $slug ); ?></code></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Rank', 'rw-dealer-portal-addons' ); ?></th>
					<td><input type="number" step="1" name="<?php echo esc_attr( $name ); ?>[rank]" value="<?php echo esc_attr( $tier['rank'] ); ?>" style="width:80px;" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Display Title', 'rw-dealer-portal-addons' ); ?></th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $tier['title'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Intro Text', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<?php
						wp_editor( $tier['intro'], 'rwdpa_tier_intro_' . $slug, [
							'textarea_name' => $name . '[intro]',
							'textarea_rows' => 4,
							'media_buttons' => false,
							'teeny'         => true,
						] );
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Badge', 'rw-dealer-portal-addons' ); ?></th>
					<td class="rwdpa-media-field">
						<img src="<?php echo esc_url( $badge ); ?>" alt="" class="rwdpa-media-preview" style="max-width:120px;height:auto;display:<?php echo $badge ? 'block' : 'none'; ?>;margin-bottom:8px;" />
						<input type="hidden" class="rwdpa-media-id" name="<?php echo esc_attr( $name ); ?>[badge_id]" value="<?php echo absint( $tier['badge_id'] ); ?>" />
						<button type="button" class="button rwdpa-media-select"><?php esc_html_e( 'Select Badge', 'rw-dealer-portal-addons' ); ?></button>
						<button type="button" class="button-link-delete rwdpa-media-remove"<?php echo $badge ? '' : ' style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'rw-dealer-portal-addons' ); ?></button>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Accent Color', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<input type="text" class="rwdpa-color" name="<?php echo esc_attr( $name ); ?>[accent]" value="<?php echo esc_attr( $tier['accent'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Card border color. Leave empty for no border.', 'rw-dealer-portal-addons' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Benefits', 'rw-dealer-portal-addons' ); ?></th>
					<td>
						<div class="rwdpa-repeater" data-name="<?php echo esc_attr( $name ); ?>[benefits]">
							<?php foreach ( array_values( $benefits ) as $i => $benefit ) : ?>
								<div class="rwdpa-repeater-row" style="display:flex;gap:8px;margin-bottom:6px;">
									<input type="text" class="large-text" name="<?php echo esc_attr( $name ); ?>[benefits][<?php echo absint( $i ); ?>][text]" value="<?php echo esc_attr( $benefit['text'] ); ?>" />
									<button type="button" class="button rwdpa-repeater-remove" aria-label="<?php esc_attr_e( 'Remove benefit', 'rw-dealer-portal-addons' ); ?>">&times;</button>
								</div>
							<?php endforeach; ?>
							<button type="button" class="button rwdpa-repeater-add"><?php esc_html_e( 'Add Benefit', 'rw-dealer-portal-addons' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'One tile per benefit. Wrap highlighted words in <strong>…</strong>, e.g. <strong>10% off</strong> floor models.', 'rw-dealer-portal-addons' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	<?php endforeach; ?>
	<?php
}

/**
 * Sales managers tab.
 *
 * @param array<string,mixed> $settings Settings.
 */
function rwdpa_portal_render_managers_tab( $settings ) {
	$managers = rwdpa_get_manager_users();
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Sales Managers', 'rw-dealer-portal-addons' ); ?></th>
			<td>
				<label><input type="checkbox" name="rwdpa_portal[enable_managers]" value="1" <?php checked( $settings['enable_managers'] ); ?> /> <?php esc_html_e( 'Enable sales managers and territories', 'rw-dealer-portal-addons' ); ?></label>
				<p class="description"><?php esc_html_e( 'Creates a manager user role and a Territories list. Each territory has a manager and the states it covers; dealers are matched by their state or a territory picked on the dealer.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rwdpa_manager_role_label"><?php esc_html_e( 'Manager Role Name', 'rw-dealer-portal-addons' ); ?></label></th>
			<td>
				<input type="text" id="rwdpa_manager_role_label" class="regular-text" name="rwdpa_portal[manager_role_label]" value="<?php echo esc_attr( $settings['manager_role_label'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Shown in Users → Role. The role slug stays rwdpa_sales_manager.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rwdpa_manager_caps_from"><?php esc_html_e( 'Manager Permissions', 'rw-dealer-portal-addons' ); ?></label></th>
			<td>
				<select id="rwdpa_manager_caps_from" name="rwdpa_portal[manager_caps_from]">
					<option value=""><?php esc_html_e( 'Portal access only', 'rw-dealer-portal-addons' ); ?></option>
					<?php foreach ( wp_roles()->role_names as $role_slug => $role_name ) : ?>
						<?php if ( RWDPA_MANAGER_ROLE === $role_slug ) { continue; } ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( $settings['manager_caps_from'], $role_slug ); ?>>
							<?php
							/* translators: %s: role name */
							printf( esc_html__( 'Same as %s', 'rw-dealer-portal-addons' ), esc_html( translate_user_role( $role_name ) ) );
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The manager role gets every capability of the chosen role, plus portal access — except it can never see or edit administrators or other managers, manage plugins or themes, change settings, or edit with Elementor.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rwdpa_default_manager"><?php esc_html_e( 'Fallback Manager', 'rw-dealer-portal-addons' ); ?></label></th>
			<td>
				<select id="rwdpa_default_manager" name="rwdpa_portal[default_manager]">
					<option value="0"><?php esc_html_e( '— None (hide the box) —', 'rw-dealer-portal-addons' ); ?></option>
					<?php foreach ( $managers as $manager ) : ?>
						<option value="<?php echo absint( $manager->ID ); ?>" <?php selected( (int) $settings['default_manager'], $manager->ID ); ?>><?php echo esc_html( $manager->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Shown to dealers with no territory match.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rwdpa_manager_heading"><?php esc_html_e( 'Box Heading', 'rw-dealer-portal-addons' ); ?></label></th>
			<td><input type="text" id="rwdpa_manager_heading" class="regular-text" name="rwdpa_portal[manager_heading]" value="<?php echo esc_attr( $settings['manager_heading'] ); ?>" /></td>
		</tr>
	</table>
	<?php if ( $settings['enable_managers'] ) : ?>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . RWDPA_TERRITORY_TAX . '&post_type=rw_dealer' ) ); ?>"><?php esc_html_e( 'Manage Territories', 'rw-dealer-portal-addons' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'users.php?role=' . RWDPA_MANAGER_ROLE ) ); ?>"><?php esc_html_e( 'View Managers', 'rw-dealer-portal-addons' ); ?></a>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Office contact tab.
 *
 * @param array<string,mixed> $settings Settings.
 */
function rwdpa_portal_render_office_tab( $settings ) {
	$fields = [
		'office_name'  => __( 'Office Name', 'rw-dealer-portal-addons' ),
		'office_phone' => __( 'Phone', 'rw-dealer-portal-addons' ),
		'office_fax'   => __( 'Fax', 'rw-dealer-portal-addons' ),
		'office_email' => __( 'Email', 'rw-dealer-portal-addons' ),
	];
	?>
	<p><?php esc_html_e( 'Shown by the [rwdpa_office_contact] shortcode. Empty fields are hidden.', 'rw-dealer-portal-addons' ); ?></p>
	<table class="form-table" role="presentation">
		<?php foreach ( $fields as $key => $label ) : ?>
			<tr>
				<th scope="row"><label for="rwdpa_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td><input type="<?php echo 'office_email' === $key ? 'email' : 'text'; ?>" id="rwdpa_<?php echo esc_attr( $key ); ?>" class="regular-text" name="rwdpa_portal[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ); ?>" /></td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><label for="rwdpa_office_address"><?php esc_html_e( 'Address', 'rw-dealer-portal-addons' ); ?></label></th>
			<td><textarea id="rwdpa_office_address" class="large-text" rows="3" name="rwdpa_portal[office_address]"><?php echo esc_textarea( $settings['office_address'] ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

/**
 * Registration tab.
 *
 * @param array<string,mixed> $settings Settings.
 */
function rwdpa_portal_render_registration_tab( $settings ) {
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Phone Field', 'rw-dealer-portal-addons' ); ?></th>
			<td>
				<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="rwdpa_portal[registration_phone]" value="1" <?php checked( $settings['registration_phone'] ); ?> /> <?php esc_html_e( 'Add a phone field to the Request Access form', 'rw-dealer-portal-addons' ); ?></label>
				<label style="display:block;"><input type="checkbox" name="rwdpa_portal[registration_phone_req]" value="1" <?php checked( $settings['registration_phone_req'] ); ?> /> <?php esc_html_e( 'Required', 'rw-dealer-portal-addons' ); ?></label>
				<p class="description"><?php esc_html_e( 'Saved to the user (meta key _rwdpa_phone) and shown on their user profile.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rwdpa_registration_phone_text"><?php esc_html_e( 'Phone Label / Placeholder', 'rw-dealer-portal-addons' ); ?></label></th>
			<td><input type="text" id="rwdpa_registration_phone_text" class="regular-text" name="rwdpa_portal[registration_phone_text]" value="<?php echo esc_attr( $settings['registration_phone_text'] ); ?>" /></td>
		</tr>
	</table>
	<?php
}
