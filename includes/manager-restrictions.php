<?php
/**
 * Limits on the sales manager role, even when Manager Permissions copies a
 * powerful role such as Administrator. Managers never:
 * - see, edit, promote or delete administrators or other managers;
 * - install, activate, update, edit or delete plugins and themes, or update core;
 * - change settings (WordPress, Elementor, RW Dealer Portal or these add-ons);
 * - edit with Elementor.
 *
 * Users who are also administrators (manager as an extra role) are unaffected.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'map_meta_cap', 'rwdpa_manager_protect_users', 10, 4 );
add_filter( 'editable_roles', 'rwdpa_manager_editable_roles' );
add_action( 'pre_get_users', 'rwdpa_manager_hide_protected_users' );
add_filter( 'views_users', 'rwdpa_manager_user_views' );
add_filter( 'option_elementor_exclude_user_roles', 'rwdpa_manager_exclude_from_elementor' );
add_filter( 'default_option_elementor_exclude_user_roles', 'rwdpa_manager_exclude_from_elementor' );

/**
 * Capabilities the manager role never receives.
 *
 * @return string[]
 */
function rwdpa_manager_blocked_caps() {
	/**
	 * Filter capabilities removed from the sales manager role.
	 *
	 * @param string[] $caps Capability names.
	 */
	return apply_filters( 'rwdpa_manager_blocked_caps', [
		// Settings screens (WordPress, Elementor, RW Dealer Portal, add-ons).
		'manage_options',
		// Plugins.
		'install_plugins',
		'activate_plugins',
		'update_plugins',
		'delete_plugins',
		'edit_plugins',
		// Themes and core.
		'install_themes',
		'switch_themes',
		'update_themes',
		'delete_themes',
		'edit_themes',
		'update_core',
		'edit_files',
		// Raw HTML/JS could be used to act as an administrator.
		'unfiltered_html',
		'unfiltered_upload',
	] );
}

/**
 * Whether the user is limited as a manager: holds the manager role and is
 * not an administrator.
 *
 * @param WP_User|int|null $user User, ID, or null for the current user.
 * @return bool
 */
function rwdpa_is_restricted_manager( $user = null ) {
	if ( ! function_exists( 'rwdpa_managers_enabled' ) || ! rwdpa_managers_enabled() ) {
		return false;
	}
	$user = null === $user ? wp_get_current_user() : ( $user instanceof WP_User ? $user : get_userdata( $user ) );
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	$roles = (array) $user->roles;
	return in_array( RWDPA_MANAGER_ROLE, $roles, true ) && ! in_array( 'administrator', $roles, true );
}

/**
 * Roles a manager may not see or act on.
 *
 * @return string[]
 */
function rwdpa_manager_protected_roles() {
	return (array) apply_filters( 'rwdpa_manager_protected_roles', [ 'administrator', RWDPA_MANAGER_ROLE ] );
}

/**
 * Whether a user holds a protected role.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function rwdpa_user_is_protected_from_managers( $user_id ) {
	$user = get_userdata( $user_id );
	return $user && (bool) array_intersect( (array) $user->roles, rwdpa_manager_protected_roles() );
}

/**
 * Deny managers any action on administrators and other managers.
 *
 * @param string[] $caps    Primitive caps required.
 * @param string   $cap     Meta cap being checked.
 * @param int      $user_id Acting user.
 * @param array    $args    Extra args; [0] is the target user ID for user caps.
 * @return string[]
 */
function rwdpa_manager_protect_users( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, [ 'edit_user', 'delete_user', 'remove_user', 'promote_user' ], true ) || empty( $args[0] ) ) {
		return $caps;
	}
	$target = (int) $args[0];
	if ( $target === (int) $user_id || ! rwdpa_is_restricted_manager( $user_id ) ) {
		return $caps;
	}
	if ( rwdpa_user_is_protected_from_managers( $target ) ) {
		$caps[] = 'do_not_allow';
	}
	return $caps;
}

/**
 * Managers can't assign administrator or manager roles.
 *
 * @param array<string,array> $roles Editable roles.
 * @return array<string,array>
 */
function rwdpa_manager_editable_roles( $roles ) {
	if ( rwdpa_is_restricted_manager() ) {
		foreach ( rwdpa_manager_protected_roles() as $role ) {
			unset( $roles[ $role ] );
		}
	}
	return $roles;
}

/**
 * Hide administrators and other managers from managers' user lists and searches.
 *
 * @param WP_User_Query $query User query.
 */
function rwdpa_manager_hide_protected_users( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'users.php' !== $pagenow || ! rwdpa_is_restricted_manager() ) {
		return;
	}
	$exclude = array_merge( (array) $query->get( 'role__not_in' ), rwdpa_manager_protected_roles() );
	$query->set( 'role__not_in', array_values( array_unique( $exclude ) ) );
}

/**
 * Remove administrator/manager filter links (and their counts) on Users.
 *
 * @param array<string,string> $views Role view links.
 * @return array<string,string>
 */
function rwdpa_manager_user_views( $views ) {
	if ( rwdpa_is_restricted_manager() ) {
		foreach ( rwdpa_manager_protected_roles() as $role ) {
			unset( $views[ $role ] );
		}
		unset( $views['all'] ); // The "All" count includes hidden users.
	}
	return $views;
}

/**
 * Exclude managers from the Elementor editor. Applied per request so an
 * administrator who is also a manager keeps Elementor access.
 *
 * @param mixed $roles Excluded role slugs.
 * @return array
 */
function rwdpa_manager_exclude_from_elementor( $roles ) {
	$roles = is_array( $roles ) ? $roles : [];
	if ( did_action( 'init' ) && rwdpa_is_restricted_manager() && ! in_array( RWDPA_MANAGER_ROLE, $roles, true ) ) {
		$roles[] = RWDPA_MANAGER_ROLE;
	}
	return $roles;
}
