<?php
/**
 * Dealer State pages (Addons settings, off by default). INTERIM: meant to be
 * removed once RW Dealer Portal core ships public state pages and a state
 * filter on the finder (see the feature request); kept in this one file.
 *
 * Core 2.3.0 already keeps a hidden `rw_dealer_state` taxonomy in sync with each
 * dealer's State field (term slug = lowercase code, name = full state name).
 * This file only:
 *
 * - makes that taxonomy public (`register_taxonomy_args`), with full-name URLs
 *   such as /dealer-state/ohio/ (code URLs redirect), and lists it under Dealer Portal;
 * - optionally mirrors each dealer's state into a matching `rw_dealer_type`
 *   term, so the core finder's type dropdown / "Lock to Dealer Type" work by
 *   state. Mirrored terms carry term meta `_rwdpa_state_code`; turning the sync
 *   off deletes them (real types are never touched);
 * - optionally relabels the finder's "Dealer Type" dropdown as "State";
 * - adds [rwdpa_dealer_states] (state list with counts).
 *
 * Core code is never changed. If core already registers the taxonomy as
 * public, the page part steps aside and core's own settings win.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'register_taxonomy_args', 'rwdpa_dealer_states_taxonomy_args', 20, 2 );
add_filter( 'term_link', 'rwdpa_dealer_states_term_link', 10, 3 );
add_filter( 'request', 'rwdpa_dealer_states_request' );
add_action( 'template_redirect', 'rwdpa_dealer_states_redirect_code_url' );
add_action( 'set_object_terms', 'rwdpa_dealer_states_on_set_terms', 10, 4 );
add_action( 'update_option_rwdpa_settings', 'rwdpa_dealer_states_settings_changed', 10, 2 );
add_action( 'add_option_rwdpa_settings', 'rwdpa_dealer_states_settings_added', 10, 2 );
add_action( 'admin_menu', 'rwdpa_dealer_states_admin_menu', 30 );
add_filter( 'gettext', 'rwdpa_dealer_states_relabel', 10, 3 );
add_shortcode( 'rwdpa_dealer_states', 'rwdpa_dealer_states_shortcode' );

/**
 * One setting from rwdpa_settings (read directly: this runs before init).
 *
 * @param string $key Setting key.
 * @return mixed
 */
function rwdpa_dealer_states_setting( $key ) {
	$defaults = [
		'dealer_state_pages'     => 0,
		'dealer_state_base'      => 'dealer-state',
		'dealer_state_sync_type' => 0,
		'dealer_state_relabel'   => 0,
	];
	$saved = get_option( 'rwdpa_settings', [] );
	$value = is_array( $saved ) && array_key_exists( $key, $saved ) ? $saved[ $key ] : ( $defaults[ $key ] ?? null );
	if ( 'dealer_state_base' === $key ) {
		$value = trim( (string) $value, '/' );
		$value = implode( '/', array_filter( array_map( 'sanitize_title', explode( '/', $value ) ) ) );
		return '' !== $value ? $value : $defaults['dealer_state_base'];
	}
	return $value;
}

/**
 * Whether the add-on is providing the public state pages (on, and core isn't already).
 *
 * @return bool
 */
function rwdpa_dealer_states_pages_active() {
	return ! empty( rwdpa_dealer_states_setting( 'dealer_state_pages' ) ) && empty( $GLOBALS['rwdpa_core_states_public'] );
}

/**
 * Whether the Dealer Type sync is on.
 *
 * @return bool
 */
function rwdpa_dealer_states_sync_enabled() {
	return ! empty( rwdpa_dealer_states_setting( 'dealer_state_sync_type' ) ) && taxonomy_exists( 'rw_dealer_type' );
}

/**
 * Make core's hidden state taxonomy public.
 *
 * @param array<string,mixed> $args     Taxonomy args.
 * @param string              $taxonomy Taxonomy.
 * @return array<string,mixed>
 */
function rwdpa_dealer_states_taxonomy_args( $args, $taxonomy ) {
	if ( 'rw_dealer_state' !== $taxonomy ) {
		return $args;
	}
	if ( ! empty( $args['public'] ) ) {
		$GLOBALS['rwdpa_core_states_public'] = true; // Core provides state pages now: step aside.
		return $args;
	}
	if ( empty( rwdpa_dealer_states_setting( 'dealer_state_pages' ) ) ) {
		return $args;
	}

	$args['labels'] = array_merge(
		(array) ( $args['labels'] ?? [] ),
		[
			'name'          => __( 'Dealer States', 'rw-dealer-portal-addons' ),
			'singular_name' => __( 'Dealer State', 'rw-dealer-portal-addons' ),
			'menu_name'     => __( 'Dealer States', 'rw-dealer-portal-addons' ),
			'all_items'     => __( 'All Dealer States', 'rw-dealer-portal-addons' ),
			'edit_item'     => __( 'Edit Dealer State', 'rw-dealer-portal-addons' ),
			'view_item'     => __( 'View Dealer State', 'rw-dealer-portal-addons' ),
			'update_item'   => __( 'Update Dealer State', 'rw-dealer-portal-addons' ),
			'search_items'  => __( 'Search Dealer States', 'rw-dealer-portal-addons' ),
			'back_to_items' => __( '&larr; Back to Dealer States', 'rw-dealer-portal-addons' ),
		]
	);

	return array_merge(
		$args,
		[
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'query_var'           => 'rw_dealer_state',
			'rewrite'             => [
				'slug'       => rwdpa_dealer_states_setting( 'dealer_state_base' ),
				'with_front' => false,
			],
			'show_ui'             => true,
			'show_in_menu'        => false, // Linked under Dealer Portal instead.
			'show_in_rest'        => true,
			'show_in_nav_menus'   => true,
			'show_admin_column'   => true,
			'show_tagcloud'       => false,
			'meta_box_cb'         => false, // Assigned automatically from the State field.
			'capabilities'        => [
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'do_not_allow', // Core would re-create them.
				'assign_terms' => 'do_not_allow',
			],
		]
	);
}

/**
 * Full-name slug used in state URLs ("ohio", "new-york").
 *
 * @param WP_Term $term State term.
 * @return string
 */
function rwdpa_dealer_states_url_slug( $term ) {
	return sanitize_title( $term->name );
}

/**
 * Use the full-name slug in state links.
 *
 * @param string  $link     Term link.
 * @param WP_Term $term     Term.
 * @param string  $taxonomy Taxonomy.
 * @return string
 */
function rwdpa_dealer_states_term_link( $link, $term, $taxonomy ) {
	if ( 'rw_dealer_state' !== $taxonomy || ! rwdpa_dealer_states_pages_active() || ! get_option( 'permalink_structure' ) ) {
		return $link;
	}
	return home_url( user_trailingslashit( rwdpa_dealer_states_setting( 'dealer_state_base' ) . '/' . rwdpa_dealer_states_url_slug( $term ) ) );
}

/**
 * Map a full-name slug from the URL to core's code slug; remember code URLs for the redirect.
 *
 * @param array<string,mixed> $query_vars Query vars.
 * @return array<string,mixed>
 */
function rwdpa_dealer_states_request( $query_vars ) {
	if ( empty( $query_vars['rw_dealer_state'] ) || ! is_string( $query_vars['rw_dealer_state'] ) || ! rwdpa_dealer_states_pages_active() ) {
		return $query_vars;
	}
	$requested = sanitize_title( $query_vars['rw_dealer_state'] );
	$terms     = get_terms(
		[
			'taxonomy'   => 'rw_dealer_state',
			'hide_empty' => false,
		]
	);
	if ( is_wp_error( $terms ) ) {
		return $query_vars;
	}
	foreach ( $terms as $term ) {
		if ( rwdpa_dealer_states_url_slug( $term ) === $requested ) {
			$query_vars['rw_dealer_state'] = $term->slug;
			return $query_vars;
		}
		if ( $term->slug === $requested && rwdpa_dealer_states_url_slug( $term ) !== $requested ) {
			$GLOBALS['rwdpa_state_redirect'] = $term; // e.g. /oh/ → /ohio/.
		}
	}
	return $query_vars;
}

/**
 * Send code URLs (/dealer-state/oh/) to the full-name URL.
 */
function rwdpa_dealer_states_redirect_code_url() {
	if ( empty( $GLOBALS['rwdpa_state_redirect'] ) || ! is_tax( 'rw_dealer_state' ) || is_paged() ) {
		return;
	}
	$link = get_term_link( $GLOBALS['rwdpa_state_redirect'] );
	if ( ! is_wp_error( $link ) ) {
		wp_safe_redirect( $link, 301 );
		exit;
	}
}

/**
 * List Dealer States under Dealer Portal.
 */
function rwdpa_dealer_states_admin_menu() {
	if ( ! rwdpa_dealer_states_pages_active() ) {
		return;
	}
	add_submenu_page(
		'rw-dealer-portal',
		__( 'Dealer States', 'rw-dealer-portal-addons' ),
		__( 'Dealer States', 'rw-dealer-portal-addons' ),
		'manage_options',
		'edit-tags.php?taxonomy=rw_dealer_state&post_type=rw_dealer'
	);
}

/**
 * Core just (re)set a dealer's state: mirror it into Dealer Type.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     Terms.
 * @param array  $tt_ids    Term taxonomy IDs.
 * @param string $taxonomy  Taxonomy.
 */
function rwdpa_dealer_states_on_set_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( 'rw_dealer_state' === $taxonomy && rwdpa_dealer_states_sync_enabled() ) {
		rwdpa_dealer_states_mirror( (int) $object_id );
	}
}

/**
 * The Dealer Type term mirroring a state, created on first use.
 *
 * @param WP_Term $state State term.
 * @return int Term ID, or 0.
 */
function rwdpa_dealer_states_type_term( $state ) {
	$code  = strtoupper( $state->slug );
	$found = get_terms(
		[
			'taxonomy'   => 'rw_dealer_type',
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_key'   => '_rwdpa_state_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a handful of terms
			'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		]
	);
	if ( ! is_wp_error( $found ) && $found ) {
		return (int) $found[0];
	}

	$created = wp_insert_term( $state->name, 'rw_dealer_type', [ 'slug' => rwdpa_dealer_states_url_slug( $state ) ] );
	if ( is_wp_error( $created ) ) {
		// A type with this name already existed: adopt it (unassigned, never deleted, on clean-up).
		$existing = (int) $created->get_error_data( 'term_exists' );
		if ( ! $existing ) {
			return 0;
		}
		update_term_meta( $existing, '_rwdpa_state_code', $code );
		update_term_meta( $existing, '_rwdpa_state_adopted', 1 );
		return $existing;
	}
	update_term_meta( (int) $created['term_id'], '_rwdpa_state_code', $code );
	return (int) $created['term_id'];
}

/**
 * Set a dealer's state type(s) to match its state, keeping its other types.
 *
 * @param int $post_id Dealer ID.
 */
function rwdpa_dealer_states_mirror( $post_id ) {
	if ( 'rw_dealer' !== get_post_type( $post_id ) ) {
		return;
	}
	$states  = wp_get_object_terms( $post_id, 'rw_dealer_state' );
	$current = wp_get_object_terms( $post_id, 'rw_dealer_type', [ 'fields' => 'ids' ] );
	if ( is_wp_error( $states ) || is_wp_error( $current ) ) {
		return;
	}

	$keep = array_values( array_filter( array_map( 'intval', $current ), static fn( $id ) => '' === (string) get_term_meta( $id, '_rwdpa_state_code', true ) ) );
	foreach ( $states as $state ) {
		$type_id = rwdpa_dealer_states_type_term( $state );
		if ( $type_id ) {
			$keep[] = $type_id;
		}
	}
	$keep = array_values( array_unique( $keep ) );

	sort( $current );
	$sorted = $keep;
	sort( $sorted );
	if ( $sorted !== $current ) {
		wp_set_object_terms( $post_id, $keep, 'rw_dealer_type', false );
	}
}

/**
 * Mirror every dealer (used when the sync is turned on).
 *
 * @return int Dealers processed.
 */
function rwdpa_dealer_states_mirror_all() {
	$ids = get_posts(
		[
			'post_type'      => 'rw_dealer',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]
	);
	foreach ( $ids as $id ) {
		rwdpa_dealer_states_mirror( (int) $id );
	}
	return count( $ids );
}

/**
 * Delete the state types the sync created; unassign adopted ones. Real types are untouched.
 *
 * @return int Terms removed.
 */
function rwdpa_dealer_states_remove_types() {
	$terms = get_terms(
		[
			'taxonomy'   => 'rw_dealer_type',
			'hide_empty' => false,
			'meta_key'   => '_rwdpa_state_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		]
	);
	if ( is_wp_error( $terms ) ) {
		return 0;
	}
	foreach ( $terms as $term ) {
		if ( get_term_meta( $term->term_id, '_rwdpa_state_adopted', true ) ) {
			$objects = get_objects_in_term( $term->term_id, 'rw_dealer_type' );
			foreach ( is_array( $objects ) ? $objects : [] as $object_id ) {
				wp_remove_object_terms( (int) $object_id, $term->term_id, 'rw_dealer_type' );
			}
			delete_term_meta( $term->term_id, '_rwdpa_state_code' );
			delete_term_meta( $term->term_id, '_rwdpa_state_adopted' );
		} else {
			wp_delete_term( $term->term_id, 'rw_dealer_type' );
		}
	}
	return count( $terms );
}

/**
 * React to setting changes: flush URLs, start or undo the Dealer Type sync.
 *
 * @param mixed $old Old settings.
 * @param mixed $new New settings.
 */
function rwdpa_dealer_states_settings_changed( $old, $new ) {
	$old = is_array( $old ) ? $old : [];
	$new = is_array( $new ) ? $new : [];

	if ( ! empty( $old['dealer_state_pages'] ) !== ! empty( $new['dealer_state_pages'] ) || ( $old['dealer_state_base'] ?? '' ) !== ( $new['dealer_state_base'] ?? '' ) ) {
		update_option( 'rwdpa_flush_rewrite', 1, false );
	}

	$was = ! empty( $old['dealer_state_sync_type'] );
	$now = ! empty( $new['dealer_state_sync_type'] );
	if ( ! $was && $now ) {
		rwdpa_dealer_states_mirror_all();
	} elseif ( $was && ! $now ) {
		rwdpa_dealer_states_remove_types();
	}
}

/**
 * First save of the settings option.
 *
 * @param string $option Option name.
 * @param mixed  $value  Value.
 */
function rwdpa_dealer_states_settings_added( $option, $value ) {
	rwdpa_dealer_states_settings_changed( [], $value );
}

/**
 * Call the finder's Dealer Type dropdown "State" while the sync and relabel are on.
 *
 * @param string $translation Translated text.
 * @param string $text        Original text.
 * @param string $domain      Text domain.
 * @return string
 */
function rwdpa_dealer_states_relabel( $translation, $text, $domain ) {
	if ( 'rw-dealer-portal' !== $domain || ( 'Dealer Type:' !== $text && 'All Dealer Types' !== $text ) ) {
		return $translation;
	}
	static $on = null;
	if ( null === $on ) {
		$on = ! is_admin() && ! empty( rwdpa_dealer_states_setting( 'dealer_state_sync_type' ) ) && ! empty( rwdpa_dealer_states_setting( 'dealer_state_relabel' ) );
	}
	if ( ! $on ) {
		return $translation;
	}
	return 'Dealer Type:' === $text ? __( 'State:', 'rw-dealer-portal-addons' ) : __( 'All States', 'rw-dealer-portal-addons' );
}

/**
 * [rwdpa_dealer_states show_count="yes" hide_empty="yes" columns="3" class=""]
 *
 * @param array<string,string>|string $atts Attributes.
 * @return string
 */
function rwdpa_dealer_states_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'show_count' => 'yes',
			'hide_empty' => 'yes',
			'columns'    => '3',
			'class'      => '',
		],
		$atts,
		'rwdpa_dealer_states'
	);
	if ( ! taxonomy_exists( 'rw_dealer_state' ) || ! is_taxonomy_viewable( 'rw_dealer_state' ) ) {
		return '';
	}

	$terms = get_terms(
		[
			'taxonomy'   => 'rw_dealer_state',
			'hide_empty' => 'no' !== $atts['hide_empty'],
			'orderby'    => 'name',
		]
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}

	wp_enqueue_style( 'rwdpa-portal-display', RWDPA_PLUGIN_URL . 'assets/css/portal-display.css', [], RWDPA_VERSION );

	$columns = max( 1, min( 6, absint( $atts['columns'] ) ) );
	$class   = trim( 'rwdpa-dealer-states ' . sanitize_html_class( $atts['class'] ) );
	ob_start();
	?>
	<ul class="<?php echo esc_attr( $class ); ?>" style="--rwdpa-states-columns:<?php echo (int) $columns; ?>">
		<?php foreach ( $terms as $term ) : ?>
			<li class="rwdpa-dealer-states__item">
				<a class="rwdpa-dealer-states__link" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				<?php if ( 'no' !== $atts['show_count'] ) : ?>
					<span class="rwdpa-dealer-states__count">(<?php echo (int) $term->count; ?>)</span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
	return (string) ob_get_clean();
}
