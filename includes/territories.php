<?php
/**
 * Sales managers and territories.
 *
 * A manager user role plus a private Territories taxonomy on dealers. Each
 * territory has one manager and the states it covers. A dealer's manager is
 * resolved as: manager override on the dealer → territory picked on the
 * dealer → territory covering the dealer's state → fallback manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RWDPA_TERRITORY_TAX     = 'rwdpa_territory';
const RWDPA_MANAGER_ROLE      = 'rwdpa_sales_manager';
const RWDPA_MANAGER_OVERRIDE  = '_rwdpa_manager_override';
const RWDPA_TERM_MANAGER_META = '_rwdpa_manager_id';
const RWDPA_TERM_STATES_META  = '_rwdpa_states';

add_action( 'init', 'rwdpa_register_territories', 20 );
add_action( 'admin_menu', 'rwdpa_territories_menu', 30 );
add_filter( 'parent_file', 'rwdpa_territories_parent_file' );
add_action( RWDPA_TERRITORY_TAX . '_add_form_fields', 'rwdpa_territory_add_fields' );
add_action( RWDPA_TERRITORY_TAX . '_edit_form_fields', 'rwdpa_territory_edit_fields' );
add_action( 'created_' . RWDPA_TERRITORY_TAX, 'rwdpa_save_territory_fields' );
add_action( 'edited_' . RWDPA_TERRITORY_TAX, 'rwdpa_save_territory_fields' );
add_filter( 'manage_edit-' . RWDPA_TERRITORY_TAX . '_columns', 'rwdpa_territory_columns' );
add_filter( 'manage_' . RWDPA_TERRITORY_TAX . '_custom_column', 'rwdpa_territory_column_content', 10, 3 );
add_action( 'rwdpa_dealer_portal_fields', 'rwdpa_render_dealer_manager_fields', 20 );
add_action( 'rwdpa_save_dealer_portal_fields', 'rwdpa_save_dealer_manager_fields', 20 );
add_action( 'show_user_profile', 'rwdpa_render_manager_profile_fields' );
add_action( 'edit_user_profile', 'rwdpa_render_manager_profile_fields' );
add_action( 'personal_options_update', 'rwdpa_save_manager_profile_fields' );
add_action( 'edit_user_profile_update', 'rwdpa_save_manager_profile_fields' );
add_action( 'profile_update', 'rwdpa_apply_manager_flag', 25 );
add_action( 'set_user_role', 'rwdpa_manager_role_changed', 10, 2 );
add_filter( 'rwdpa_user_portal_summary_rows', 'rwdpa_manager_summary_rows', 20, 2 );
add_shortcode( 'rwdpa_sales_manager', 'rwdpa_sales_manager_shortcode' );

/**
 * Whether sales managers are enabled.
 *
 * @return bool
 */
function rwdpa_managers_enabled() {
	return (bool) rwdpa_portal_setting( 'enable_managers' );
}

/**
 * Register the taxonomy and keep the manager role in sync with its label.
 */
function rwdpa_register_territories() {
	if ( ! rwdpa_managers_enabled() ) {
		return;
	}

	register_taxonomy( RWDPA_TERRITORY_TAX, 'rw_dealer', [
		'labels'            => [
			'name'          => __( 'Territories', 'rw-dealer-portal-addons' ),
			'singular_name' => __( 'Territory', 'rw-dealer-portal-addons' ),
			'add_new_item'  => __( 'Add Territory', 'rw-dealer-portal-addons' ),
			'edit_item'     => __( 'Edit Territory', 'rw-dealer-portal-addons' ),
			'search_items'  => __( 'Search Territories', 'rw-dealer-portal-addons' ),
			'not_found'     => __( 'No territories found.', 'rw-dealer-portal-addons' ),
		],
		'public'            => false,
		'show_ui'           => true,
		'show_in_menu'      => false,
		'show_in_rest'      => false,
		'show_admin_column' => true,
		'hierarchical'      => false,
		'meta_box_cb'       => false,
		'rewrite'           => false,
		'capabilities'      => [
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'edit_posts',
		],
	] );

	$label = (string) rwdpa_portal_setting( 'manager_role_label' );
	$caps  = rwdpa_manager_role_caps();
	$role  = get_role( RWDPA_MANAGER_ROLE );
	if ( ! $role ) {
		add_role( RWDPA_MANAGER_ROLE, $label, $caps );
		return;
	}

	// Rewrite the stored role only when its name or capabilities differ.
	if ( wp_roles()->role_names[ RWDPA_MANAGER_ROLE ] !== $label || $role->capabilities != $caps ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- order-insensitive array compare
		$roles                         = get_option( wp_roles()->role_key, [] );
		$roles[ RWDPA_MANAGER_ROLE ] = [ 'name' => $label, 'capabilities' => $caps ];
		update_option( wp_roles()->role_key, $roles );
		wp_roles()->for_site();
	}
}

/**
 * Capabilities for the manager role: portal access, plus every capability of
 * the role chosen in Manager Permissions.
 *
 * @return array<string,bool>
 */
function rwdpa_manager_role_caps() {
	$caps = [
		'read'        => true,
		'view_portal' => true,
	];

	$from = (string) rwdpa_portal_setting( 'manager_caps_from' );
	$base = ( '' !== $from && RWDPA_MANAGER_ROLE !== $from ) ? get_role( $from ) : null;
	if ( $base ) {
		$caps = array_merge( array_filter( $base->capabilities ), $caps );
	}

	// Never grant plugin/theme/settings capabilities (see manager-restrictions.php).
	foreach ( rwdpa_manager_blocked_caps() as $blocked ) {
		unset( $caps[ $blocked ] );
	}

	return $caps;
}

/**
 * Territories submenu under Dealer Portal.
 */
function rwdpa_territories_menu() {
	if ( ! rwdpa_managers_enabled() ) {
		return;
	}
	add_submenu_page(
		'rw-dealer-portal',
		__( 'Territories', 'rw-dealer-portal-addons' ),
		__( 'Territories', 'rw-dealer-portal-addons' ),
		'manage_options',
		'edit-tags.php?taxonomy=' . RWDPA_TERRITORY_TAX . '&post_type=rw_dealer'
	);
}

/**
 * Keep the Dealer Portal menu open on the Territories screens.
 *
 * @param string $parent_file Parent menu slug.
 * @return string
 */
function rwdpa_territories_parent_file( $parent_file ) {
	$screen = get_current_screen();
	if ( $screen && RWDPA_TERRITORY_TAX === $screen->taxonomy ) {
		return 'rw-dealer-portal';
	}
	return $parent_file;
}

/**
 * Users with the manager role.
 *
 * @return WP_User[]
 */
function rwdpa_get_manager_users() {
	return get_users( [
		'role'    => RWDPA_MANAGER_ROLE,
		'orderby' => 'display_name',
	] );
}

/**
 * US states (code => name) from the core plugin.
 *
 * @return array<string,string>
 */
function rwdpa_get_states() {
	return function_exists( 'rwdp_get_us_states_map' ) ? rwdp_get_us_states_map() : [];
}

/**
 * Manager select.
 *
 * @param string $name     Field name.
 * @param int    $selected Selected user ID.
 * @param string $none     Label for the empty option.
 */
function rwdpa_manager_select( $name, $selected, $none ) {
	?>
	<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" style="width:100%;max-width:25em;">
		<option value="0"><?php echo esc_html( $none ); ?></option>
		<?php foreach ( rwdpa_get_manager_users() as $manager ) : ?>
			<option value="<?php echo absint( $manager->ID ); ?>" <?php selected( (int) $selected, $manager->ID ); ?>><?php echo esc_html( $manager->display_name ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * State checkboxes.
 *
 * @param string[] $selected Selected state codes.
 */
function rwdpa_states_checkboxes( $selected ) {
	?>
	<div style="columns:4 10em;max-width:46em;">
		<?php foreach ( rwdpa_get_states() as $code => $name ) : ?>
			<label style="display:block;"><input type="checkbox" name="rwdpa_states[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $selected, true ) ); ?> /> <?php echo esc_html( $name ); ?></label>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Add-territory form fields.
 */
function rwdpa_territory_add_fields() {
	wp_nonce_field( 'rwdpa_territory', 'rwdpa_territory_nonce' );
	?>
	<div class="form-field">
		<label for="rwdpa_manager_id"><?php esc_html_e( 'Sales Manager', 'rw-dealer-portal-addons' ); ?></label>
		<?php rwdpa_manager_select( 'rwdpa_manager_id', 0, __( '— None —', 'rw-dealer-portal-addons' ) ); ?>
	</div>
	<div class="form-field">
		<label><?php esc_html_e( 'States Covered', 'rw-dealer-portal-addons' ); ?></label>
		<?php rwdpa_states_checkboxes( [] ); ?>
		<p><?php esc_html_e( 'Dealers in these states use this territory unless a different one is picked on the dealer.', 'rw-dealer-portal-addons' ); ?></p>
	</div>
	<?php
}

/**
 * Edit-territory form fields.
 *
 * @param WP_Term $term Territory.
 */
function rwdpa_territory_edit_fields( $term ) {
	wp_nonce_field( 'rwdpa_territory', 'rwdpa_territory_nonce' );
	?>
	<tr class="form-field">
		<th scope="row"><label for="rwdpa_manager_id"><?php esc_html_e( 'Sales Manager', 'rw-dealer-portal-addons' ); ?></label></th>
		<td><?php rwdpa_manager_select( 'rwdpa_manager_id', (int) get_term_meta( $term->term_id, RWDPA_TERM_MANAGER_META, true ), __( '— None —', 'rw-dealer-portal-addons' ) ); ?></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'States Covered', 'rw-dealer-portal-addons' ); ?></th>
		<td>
			<?php rwdpa_states_checkboxes( rwdpa_get_territory_states( $term->term_id ) ); ?>
			<p class="description"><?php esc_html_e( 'Dealers in these states use this territory unless a different one is picked on the dealer. A state should belong to one territory.', 'rw-dealer-portal-addons' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Save territory fields.
 *
 * @param int $term_id Territory term ID.
 */
function rwdpa_save_territory_fields( $term_id ) {
	if ( ! isset( $_POST['rwdpa_territory_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rwdpa_territory_nonce'] ) ), 'rwdpa_territory' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$states = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['rwdpa_states'] ?? [] ) );
	$states = array_values( array_intersect( $states, array_keys( rwdpa_get_states() ) ) );

	update_term_meta( $term_id, RWDPA_TERM_MANAGER_META, absint( $_POST['rwdpa_manager_id'] ?? 0 ) );
	update_term_meta( $term_id, RWDPA_TERM_STATES_META, $states );
}

/**
 * States covered by a territory.
 *
 * @param int $term_id Territory term ID.
 * @return string[]
 */
function rwdpa_get_territory_states( $term_id ) {
	$states = get_term_meta( $term_id, RWDPA_TERM_STATES_META, true );
	return is_array( $states ) ? $states : [];
}

/**
 * Territory list columns.
 *
 * @param array<string,string> $columns Columns.
 * @return array<string,string>
 */
function rwdpa_territory_columns( $columns ) {
	unset( $columns['description'], $columns['slug'] );
	$columns['rwdpa_manager'] = __( 'Sales Manager', 'rw-dealer-portal-addons' );
	$columns['rwdpa_states']  = __( 'States', 'rw-dealer-portal-addons' );
	return $columns;
}

/**
 * Territory list column content.
 *
 * @param string $content Column content.
 * @param string $column  Column key.
 * @param int    $term_id Term ID.
 * @return string
 */
function rwdpa_territory_column_content( $content, $column, $term_id ) {
	if ( 'rwdpa_manager' === $column ) {
		$user = get_userdata( (int) get_term_meta( $term_id, RWDPA_TERM_MANAGER_META, true ) );
		return $user ? esc_html( $user->display_name ) : '—';
	}
	if ( 'rwdpa_states' === $column ) {
		$states = rwdpa_get_territory_states( $term_id );
		return $states ? esc_html( implode( ', ', $states ) ) : '—';
	}
	return $content;
}

/**
 * Territory explicitly picked on a dealer.
 *
 * @param int $dealer_id Dealer post ID.
 * @return int Term ID or 0.
 */
function rwdpa_get_dealer_assigned_territory( $dealer_id ) {
	$terms = wp_get_object_terms( $dealer_id, RWDPA_TERRITORY_TAX, [ 'fields' => 'ids' ] );
	return ! is_wp_error( $terms ) && $terms ? (int) $terms[0] : 0;
}

/**
 * Territory covering a state.
 *
 * @param string $state Two-letter state code.
 * @return int Term ID or 0.
 */
function rwdpa_get_territory_for_state( $state ) {
	if ( '' === $state ) {
		return 0;
	}
	$terms = get_terms( [
		'taxonomy'   => RWDPA_TERRITORY_TAX,
		'hide_empty' => false,
		'fields'     => 'ids',
		'orderby'    => 'name',
	] );
	if ( is_wp_error( $terms ) ) {
		return 0;
	}
	foreach ( $terms as $term_id ) {
		if ( in_array( $state, rwdpa_get_territory_states( $term_id ), true ) ) {
			return (int) $term_id;
		}
	}
	return 0;
}

/**
 * A dealer's territory: picked on the dealer, else matched by state.
 *
 * @param int $dealer_id Dealer post ID.
 * @return int Term ID or 0.
 */
function rwdpa_get_dealer_territory( $dealer_id ) {
	return rwdpa_get_dealer_assigned_territory( $dealer_id )
		?: rwdpa_get_territory_for_state( (string) get_post_meta( $dealer_id, '_rwdp_state', true ) );
}

/**
 * Resolve a dealer's sales manager.
 *
 * @param int $dealer_id Dealer post ID (0 for none).
 * @return array{user_id:int,source:string,territory:int}
 */
function rwdpa_resolve_dealer_manager( $dealer_id ) {
	$result = [ 'user_id' => 0, 'source' => '', 'territory' => 0 ];

	if ( $dealer_id ) {
		$override = (int) get_post_meta( $dealer_id, RWDPA_MANAGER_OVERRIDE, true );
		if ( $override && get_userdata( $override ) ) {
			return [ 'user_id' => $override, 'source' => 'override', 'territory' => 0 ];
		}

		$territory = rwdpa_get_dealer_territory( $dealer_id );
		$manager   = $territory ? (int) get_term_meta( $territory, RWDPA_TERM_MANAGER_META, true ) : 0;
		if ( $manager && get_userdata( $manager ) ) {
			return [ 'user_id' => $manager, 'source' => 'territory', 'territory' => $territory ];
		}
		$result['territory'] = $territory;
	}

	$fallback = (int) rwdpa_portal_setting( 'default_manager' );
	if ( $fallback && get_userdata( $fallback ) ) {
		$result['user_id'] = $fallback;
		$result['source']  = 'fallback';
	}

	return $result;
}

/**
 * Sales manager for a user, via their primary linked dealer.
 *
 * @param int $user_id User ID.
 * @return int Manager user ID or 0.
 */
function rwdpa_get_user_manager( $user_id ) {
	$resolved = rwdpa_resolve_dealer_manager( rwdpa_get_user_primary_dealer( $user_id ) );
	return (int) $resolved['user_id'];
}

/**
 * Contact details shown for a manager.
 *
 * @param int $manager_id Manager user ID.
 * @return array{name:string,phone:string,email:string}
 */
function rwdpa_get_manager_contact( $manager_id ) {
	$user = get_userdata( $manager_id );
	if ( ! $user ) {
		return [ 'name' => '', 'phone' => '', 'email' => '' ];
	}
	$email = (string) get_user_meta( $manager_id, '_rwdpa_manager_email', true );

	return [
		'name'  => $user->display_name,
		'phone' => (string) get_user_meta( $manager_id, '_rwdpa_manager_phone', true ),
		'email' => $email ?: $user->user_email,
	];
}

/**
 * Dealer editor fields.
 *
 * @param WP_Post $post Dealer post.
 */
function rwdpa_render_dealer_manager_fields( $post ) {
	if ( ! rwdpa_managers_enabled() ) {
		return;
	}

	$assigned  = rwdpa_get_dealer_assigned_territory( $post->ID );
	$by_state  = rwdpa_get_territory_for_state( (string) get_post_meta( $post->ID, '_rwdp_state', true ) );
	$auto_name = $by_state ? get_term_field( 'name', $by_state, RWDPA_TERRITORY_TAX ) : __( 'no match', 'rw-dealer-portal-addons' );
	$resolved  = rwdpa_resolve_dealer_manager( $post->ID );
	$territories = get_terms( [ 'taxonomy' => RWDPA_TERRITORY_TAX, 'hide_empty' => false ] );
	?>
	<p>
		<label for="rwdpa_territory"><strong><?php esc_html_e( 'Territory', 'rw-dealer-portal-addons' ); ?></strong></label><br />
		<select id="rwdpa_territory" name="rwdpa_territory" style="width:100%;">
			<option value="0">
				<?php
				/* translators: %s: territory matched by the dealer's state */
				printf( esc_html__( 'Auto by state (%s)', 'rw-dealer-portal-addons' ), esc_html( $auto_name ) );
				?>
			</option>
			<?php if ( ! is_wp_error( $territories ) ) : ?>
				<?php foreach ( $territories as $territory ) : ?>
					<option value="<?php echo absint( $territory->term_id ); ?>" <?php selected( $assigned, $territory->term_id ); ?>><?php echo esc_html( $territory->name ); ?></option>
				<?php endforeach; ?>
			<?php endif; ?>
		</select>
	</p>
	<p>
		<label for="rwdpa_manager_override"><strong><?php esc_html_e( 'Sales Manager Override', 'rw-dealer-portal-addons' ); ?></strong></label><br />
		<?php rwdpa_manager_select( 'rwdpa_manager_override', (int) get_post_meta( $post->ID, RWDPA_MANAGER_OVERRIDE, true ), __( '— Use territory manager —', 'rw-dealer-portal-addons' ) ); ?>
	</p>
	<p class="description">
		<?php
		$current = get_userdata( $resolved['user_id'] );
		/* translators: %s: manager name */
		echo $current ? sprintf( esc_html__( 'Currently shown: %s', 'rw-dealer-portal-addons' ), esc_html( $current->display_name ) ) : esc_html__( 'Currently shown: no sales manager', 'rw-dealer-portal-addons' );
		?>
	</p>
	<?php
}

/**
 * Save dealer manager fields.
 *
 * @param int $post_id Dealer post ID.
 */
function rwdpa_save_dealer_manager_fields( $post_id ) {
	if ( ! rwdpa_managers_enabled() || ! isset( $_POST['rwdpa_territory'] ) ) {
		return;
	}

	$territory = absint( $_POST['rwdpa_territory'] );
	wp_set_object_terms( $post_id, $territory ? [ $territory ] : [], RWDPA_TERRITORY_TAX );
	update_post_meta( $post_id, RWDPA_MANAGER_OVERRIDE, absint( $_POST['rwdpa_manager_override'] ?? 0 ) );
}

/**
 * Contact fields on manager user profiles.
 *
 * @param WP_User $user User being edited.
 */
function rwdpa_render_manager_profile_fields( $user ) {
	if ( ! rwdpa_managers_enabled() ) {
		return;
	}

	$is_manager = rwdpa_user_is_manager( $user );
	if ( ! $is_manager && ! current_user_can( 'promote_users' ) ) {
		return;
	}

	$territories = get_terms( [
		'taxonomy'   => RWDPA_TERRITORY_TAX,
		'hide_empty' => false,
		'meta_key'   => RWDPA_TERM_MANAGER_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value' => $user->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
	wp_nonce_field( 'rwdpa_manager_profile', 'rwdpa_manager_profile_nonce' );
	?>
	<h2><?php echo esc_html( (string) rwdpa_portal_setting( 'manager_role_label' ) ); ?></h2>
	<table class="form-table" role="presentation">
		<?php if ( current_user_can( 'promote_users' ) ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( (string) rwdpa_portal_setting( 'manager_role_label' ) ); ?></th>
				<td>
					<label><input type="checkbox" name="rwdpa_is_manager" value="1" <?php checked( $is_manager ); ?> /> <?php esc_html_e( 'This user is a sales manager', 'rw-dealer-portal-addons' ); ?></label>
					<p class="description"><?php esc_html_e( 'Added as an extra role, so administrators and other staff keep their own role and permissions.', 'rw-dealer-portal-addons' ); ?></p>
				</td>
			</tr>
		<?php endif; ?>
		<?php if ( $is_manager ) : ?>
		<tr>
			<th scope="row"><label for="rwdpa_manager_phone"><?php esc_html_e( 'Mobile / Text', 'rw-dealer-portal-addons' ); ?></label></th>
			<td><input type="text" id="rwdpa_manager_phone" name="rwdpa_manager_phone" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, '_rwdpa_manager_phone', true ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="rwdpa_manager_email"><?php esc_html_e( 'Email Shown to Dealers', 'rw-dealer-portal-addons' ); ?></label></th>
			<td>
				<input type="email" id="rwdpa_manager_email" name="rwdpa_manager_email" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, '_rwdpa_manager_email', true ) ); ?>" placeholder="<?php echo esc_attr( $user->user_email ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to use the account email.', 'rw-dealer-portal-addons' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Territories', 'rw-dealer-portal-addons' ); ?></th>
			<td>
				<?php
				if ( ! is_wp_error( $territories ) && $territories ) {
					echo esc_html( implode( ', ', wp_list_pluck( $territories, 'name' ) ) );
				} else {
					esc_html_e( 'None yet — assign this manager on Dealer Portal → Territories.', 'rw-dealer-portal-addons' );
				}
				?>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	<?php
}

/**
 * Save manager profile fields.
 *
 * @param int $user_id User ID.
 */
function rwdpa_save_manager_profile_fields( $user_id ) {
	if ( ! isset( $_POST['rwdpa_manager_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rwdpa_manager_profile_nonce'] ) ), 'rwdpa_manager_profile' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( current_user_can( 'promote_users' ) ) {
		update_user_meta( $user_id, '_rwdpa_is_manager', empty( $_POST['rwdpa_is_manager'] ) ? '0' : '1' );
	}
	if ( isset( $_POST['rwdpa_manager_phone'] ) ) {
		update_user_meta( $user_id, '_rwdpa_manager_phone', sanitize_text_field( wp_unslash( $_POST['rwdpa_manager_phone'] ) ) );
		update_user_meta( $user_id, '_rwdpa_manager_email', sanitize_email( wp_unslash( $_POST['rwdpa_manager_email'] ?? '' ) ) );
	}
}

/**
 * Whether a user is a sales manager (primary or extra role).
 *
 * @param WP_User|int $user User or ID.
 * @return bool
 */
function rwdpa_user_is_manager( $user ) {
	$user = $user instanceof WP_User ? $user : get_userdata( $user );
	return $user && in_array( RWDPA_MANAGER_ROLE, (array) $user->roles, true );
}

/**
 * Apply the profile checkbox after WordPress has saved the role dropdown,
 * which replaces all roles with the single selected one.
 *
 * @param int $user_id User ID.
 */
function rwdpa_apply_manager_flag( $user_id ) {
	if ( ! rwdpa_managers_enabled() ) {
		return;
	}
	$flag = get_user_meta( $user_id, '_rwdpa_is_manager', true );
	$user = get_userdata( $user_id );
	if ( ! $user || '' === $flag ) {
		return;
	}

	if ( '1' === $flag && ! rwdpa_user_is_manager( $user ) ) {
		$user->add_role( RWDPA_MANAGER_ROLE );
	} elseif ( '0' === $flag && rwdpa_user_is_manager( $user ) ) {
		$user->remove_role( RWDPA_MANAGER_ROLE );
		if ( ! $user->roles ) {
			$user->set_role( get_option( 'default_role', 'subscriber' ) );
		}
	}
}

/**
 * Keep the manager flag in step with role changes made elsewhere (role
 * dropdown, bulk "Change role to"), re-adding the extra manager role when a
 * flagged user is given another primary role.
 *
 * @param int    $user_id User ID.
 * @param string $role    New primary role.
 */
function rwdpa_manager_role_changed( $user_id, $role ) {
	if ( ! rwdpa_managers_enabled() ) {
		return;
	}
	if ( RWDPA_MANAGER_ROLE === $role ) {
		update_user_meta( $user_id, '_rwdpa_is_manager', '1' );
		return;
	}
	if ( '1' === get_user_meta( $user_id, '_rwdpa_is_manager', true ) ) {
		$user = get_userdata( $user_id );
		if ( $user && ! rwdpa_user_is_manager( $user ) ) {
			$user->add_role( RWDPA_MANAGER_ROLE );
		}
	}
}

/**
 * Sales manager row for the user profile summary.
 *
 * @param array<string,string> $rows Rows.
 * @param WP_User              $user User.
 * @return array<string,string>
 */
function rwdpa_manager_summary_rows( $rows, $user ) {
	if ( ! rwdpa_managers_enabled() || rwdpa_user_is_manager( $user ) ) {
		return $rows;
	}

	$dealer_id = rwdpa_get_user_primary_dealer( $user->ID );
	if ( ! $dealer_id ) {
		return $rows;
	}

	$resolved = rwdpa_resolve_dealer_manager( $dealer_id );
	$manager  = get_userdata( $resolved['user_id'] );
	$sources  = [
		'override'  => __( 'override on dealer', 'rw-dealer-portal-addons' ),
		'territory' => $resolved['territory'] ? get_term_field( 'name', $resolved['territory'], RWDPA_TERRITORY_TAX ) : '',
		'fallback'  => __( 'fallback manager', 'rw-dealer-portal-addons' ),
	];

	$rows[ __( 'Sales Manager', 'rw-dealer-portal-addons' ) ] = $manager
		? esc_html( $manager->display_name . ' (' . ( $sources[ $resolved['source'] ] ?? '' ) . ')' )
		: esc_html__( 'None', 'rw-dealer-portal-addons' );

	return $rows;
}

/**
 * [rwdpa_sales_manager] — the current dealer's sales manager.
 *
 * Attributes: heading, phone_label, email_label.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function rwdpa_sales_manager_shortcode( $atts = [] ) {
	if ( ! rwdpa_managers_enabled() || ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts( [
		'heading'     => (string) rwdpa_portal_setting( 'manager_heading' ),
		'phone_label' => __( 'Mobile/Text:', 'rw-dealer-portal-addons' ),
		'email_label' => __( 'Email:', 'rw-dealer-portal-addons' ),
	], $atts, 'rwdpa_sales_manager' );

	$contact = rwdpa_get_manager_contact( rwdpa_get_user_manager( get_current_user_id() ) );
	if ( '' === $contact['name'] ) {
		return '';
	}

	wp_enqueue_style( 'rwdpa-portal-display', RWDPA_PLUGIN_URL . 'assets/css/portal-display.css', [], RWDPA_VERSION );

	ob_start();
	?>
	<div class="rwdpa-contact-card rwdpa-contact-card--manager">
		<?php if ( $atts['heading'] ) : ?>
			<h3 class="rwdpa-contact-card__heading"><?php echo esc_html( $atts['heading'] ); ?></h3>
		<?php endif; ?>
		<p class="rwdpa-contact-card__name"><?php echo esc_html( $contact['name'] ); ?></p>
		<?php if ( $contact['phone'] ) : ?>
			<p class="rwdpa-contact-card__line"><?php echo esc_html( $atts['phone_label'] ); ?> <a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $contact['phone'] ) ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a></p>
		<?php endif; ?>
		<?php if ( $contact['email'] ) : ?>
			<p class="rwdpa-contact-card__line"><?php echo esc_html( $atts['email_label'] ); ?> <a href="<?php echo esc_url( 'mailto:' . $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
