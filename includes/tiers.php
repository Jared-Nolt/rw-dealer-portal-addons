<?php
/**
 * Dealer tiers.
 *
 * Each RW Dealer Portal viewer role (Dealer + custom Portal Roles) is a tier
 * with a rank and display content. The tier is chosen on the dealer post and
 * synced to the portal roles of every user linked to that dealer. With
 * cumulative tiers on, users also receive every lower-ranked tier role, so the
 * core plugin's plain role match ("Visible To") behaves as "this tier and up".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RWDPA_TIER_META   = '_rwdpa_tier';
const RWDPA_RADIUS_META = '_rwdpa_protected_radius';

add_action( 'rwdpa_dealer_portal_fields', 'rwdpa_render_dealer_tier_fields', 10 );
add_action( 'rwdpa_save_dealer_portal_fields', 'rwdpa_save_dealer_tier_fields', 10 );
add_action( 'added_user_meta', 'rwdpa_sync_on_user_meta_change', 10, 3 );
add_action( 'updated_user_meta', 'rwdpa_sync_on_user_meta_change', 10, 3 );
add_action( 'profile_update', 'rwdpa_sync_user_tier_roles', 20 );
add_filter( 'rwdpa_user_portal_summary_rows', 'rwdpa_tier_summary_rows', 10, 2 );
add_shortcode( 'rwdpa_tier_card', 'rwdpa_tier_card_shortcode' );
add_shortcode( 'rwdpa_tier_badge', 'rwdpa_tier_badge_shortcode' );

/**
 * Whether tiers are enabled.
 *
 * @return bool
 */
function rwdpa_tiers_enabled() {
	return (bool) rwdpa_portal_setting( 'enable_tiers' ) && function_exists( 'rwdp_get_viewer_roles' );
}

/**
 * Default display values for a tier that has not been configured.
 *
 * @param string $slug  Role slug.
 * @param string $label Role label.
 * @return array<string,mixed>
 */
function rwdpa_tier_defaults( $slug, $label ) {
	$order = array_keys( function_exists( 'rwdp_get_viewer_roles' ) ? rwdp_get_viewer_roles() : [] );
	$rank  = array_search( $slug, $order, true );

	return [
		'rank'     => false === $rank ? 0 : (int) $rank,
		'title'    => $label,
		'intro'    => '',
		'badge_id' => 0,
		'accent'   => '',
		'benefits' => [],
	];
}

/**
 * All tiers, lowest rank first.
 *
 * @param bool $include_unconfigured Include roles with no saved tier settings.
 * @return array<string,array<string,mixed>> role slug => tier
 */
function rwdpa_get_tiers( $include_unconfigured = true ) {
	$roles = function_exists( 'rwdp_get_viewer_roles' ) ? rwdp_get_viewer_roles() : [];
	$saved = (array) rwdpa_portal_setting( 'tiers' );
	$tiers = [];

	foreach ( $roles as $slug => $label ) {
		if ( isset( $saved[ $slug ] ) ) {
			$tiers[ $slug ] = wp_parse_args( $saved[ $slug ], rwdpa_tier_defaults( $slug, $label ) );
		} elseif ( $include_unconfigured ) {
			$tiers[ $slug ] = rwdpa_tier_defaults( $slug, $label );
		}
	}

	uasort( $tiers, static function ( $a, $b ) {
		return (int) $a['rank'] <=> (int) $b['rank'];
	} );

	return $tiers;
}

/**
 * Lowest-ranked tier slug (the default for dealers with no tier).
 *
 * @return string
 */
function rwdpa_get_default_tier() {
	$tiers = rwdpa_get_tiers();
	return $tiers ? (string) array_key_first( $tiers ) : 'rwdp_dealer';
}

/**
 * Tier of a dealer post.
 *
 * @param int $dealer_id Dealer post ID.
 * @return string Role slug.
 */
function rwdpa_get_dealer_tier( $dealer_id ) {
	$tier = (string) get_post_meta( $dealer_id, RWDPA_TIER_META, true );
	return isset( rwdpa_get_tiers()[ $tier ] ) ? $tier : rwdpa_get_default_tier();
}

/**
 * The dealer that determines a user's tier: their highest-ranked linked dealer.
 *
 * @param int $user_id User ID.
 * @return int Dealer post ID or 0.
 */
function rwdpa_get_user_primary_dealer( $user_id ) {
	$tiers   = rwdpa_get_tiers();
	$best_id = 0;
	$best    = null;

	foreach ( rwdpa_get_user_dealer_ids( $user_id ) as $dealer_id ) {
		$rank = (int) ( $tiers[ rwdpa_get_dealer_tier( $dealer_id ) ]['rank'] ?? 0 );
		if ( null === $best || $rank > $best ) {
			$best    = $rank;
			$best_id = $dealer_id;
		}
	}

	return $best_id;
}

/**
 * Whether the current user may preview other tiers.
 *
 * @return bool
 */
function rwdpa_can_preview_tiers() {
	return is_user_logged_in() && (
		current_user_can( 'edit_pages' ) || current_user_can( 'manage_options' ) || current_user_can( 'manage_rwdp_portal' )
	);
}

/**
 * Tier requested through the admin preview switcher, if any.
 *
 * @return string Role slug or ''.
 */
function rwdpa_get_preview_tier() {
	if ( ! rwdpa_can_preview_tiers() || empty( $_GET['rwdpa_preview_tier'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '';
	}
	$slug = sanitize_key( wp_unslash( $_GET['rwdpa_preview_tier'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( rwdpa_get_tiers()[ $slug ] ) ? $slug : '';
}

/**
 * A user's tier: from their highest-ranked linked dealer, else the highest
 * tier role they hold directly.
 *
 * @param int $user_id User ID.
 * @return string Role slug or '' when the user is not a dealer.
 */
function rwdpa_get_user_tier( $user_id ) {
	$dealer_id = rwdpa_get_user_primary_dealer( $user_id );
	if ( $dealer_id ) {
		return rwdpa_get_dealer_tier( $dealer_id );
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}

	$held = '';
	foreach ( array_keys( rwdpa_get_tiers() ) as $slug ) {
		if ( in_array( $slug, (array) $user->roles, true ) ) {
			$held = $slug; // Tiers are sorted by rank, so the last match is the highest.
		}
	}
	return $held;
}

/**
 * Protected radius text for a dealer.
 *
 * @param int $dealer_id Dealer post ID.
 * @return string
 */
function rwdpa_get_dealer_protected_radius( $dealer_id ) {
	return $dealer_id ? (string) get_post_meta( $dealer_id, RWDPA_RADIUS_META, true ) : '';
}

/**
 * Dealer editor fields.
 *
 * @param WP_Post $post Dealer post.
 */
function rwdpa_render_dealer_tier_fields( $post ) {
	if ( ! rwdpa_tiers_enabled() ) {
		return;
	}

	$current = rwdpa_get_dealer_tier( $post->ID );
	$radius  = rwdpa_get_dealer_protected_radius( $post->ID );
	?>
	<p>
		<label for="rwdpa_tier"><strong><?php esc_html_e( 'Dealer Tier', 'rw-dealer-portal-addons' ); ?></strong></label><br />
		<select id="rwdpa_tier" name="rwdpa_tier" style="width:100%;">
			<?php foreach ( rwdpa_get_tiers() as $slug => $tier ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $tier['title'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<span class="description"><?php esc_html_e( 'Linked users receive this tier\'s portal role on save.', 'rw-dealer-portal-addons' ); ?></span>
	</p>
	<p>
		<label for="rwdpa_protected_radius"><strong><?php esc_html_e( 'Protected Radius', 'rw-dealer-portal-addons' ); ?></strong></label><br />
		<input type="text" id="rwdpa_protected_radius" name="rwdpa_protected_radius" value="<?php echo esc_attr( $radius ); ?>" placeholder="<?php esc_attr_e( 'e.g. 25 miles', 'rw-dealer-portal-addons' ); ?>" style="width:100%;" />
		<span class="description"><?php esc_html_e( 'Private. Fills {protected_radius} in tier benefits; when empty, benefits that use it are hidden for this dealer.', 'rw-dealer-portal-addons' ); ?></span>
	</p>
	<?php
}

/**
 * Save dealer tier fields and resync linked users when the tier changes.
 *
 * @param int $post_id Dealer post ID.
 */
function rwdpa_save_dealer_tier_fields( $post_id ) {
	if ( ! rwdpa_tiers_enabled() || ! isset( $_POST['rwdpa_tier'] ) ) {
		return;
	}

	$old  = (string) get_post_meta( $post_id, RWDPA_TIER_META, true );
	$tier = sanitize_key( wp_unslash( $_POST['rwdpa_tier'] ) );
	if ( ! isset( rwdpa_get_tiers()[ $tier ] ) ) {
		$tier = rwdpa_get_default_tier();
	}

	update_post_meta( $post_id, RWDPA_TIER_META, $tier );
	update_post_meta( $post_id, RWDPA_RADIUS_META, sanitize_text_field( wp_unslash( $_POST['rwdpa_protected_radius'] ?? '' ) ) );

	if ( $old !== $tier ) {
		rwdpa_sync_dealer_users( $post_id );
	}
}

/**
 * Set a dealer's tier programmatically (imports, CLI) and resync its users.
 *
 * @param int    $dealer_id Dealer post ID.
 * @param string $tier      Role slug.
 * @return bool
 */
function rwdpa_set_dealer_tier( $dealer_id, $tier ) {
	if ( ! isset( rwdpa_get_tiers()[ $tier ] ) || 'rw_dealer' !== get_post_type( $dealer_id ) ) {
		return false;
	}
	update_post_meta( $dealer_id, RWDPA_TIER_META, $tier );
	rwdpa_sync_dealer_users( $dealer_id );
	return true;
}

/**
 * Resync every user linked to a dealer.
 *
 * @param int $dealer_id Dealer post ID.
 */
function rwdpa_sync_dealer_users( $dealer_id ) {
	foreach ( rwdpa_get_dealer_user_ids( $dealer_id ) as $user_id ) {
		rwdpa_sync_user_tier_roles( $user_id );
	}
}

/**
 * Resync when dealer links or approval status change.
 *
 * @param int    $meta_id  Meta ID.
 * @param int    $user_id  User ID.
 * @param string $meta_key Meta key.
 */
function rwdpa_sync_on_user_meta_change( $meta_id, $user_id, $meta_key ) {
	if ( in_array( $meta_key, [ '_rwdp_dealer_ids', '_rwdp_account_status' ], true ) ) {
		rwdpa_sync_user_tier_roles( $user_id );
	}
}

/**
 * Give a user the portal role(s) for their dealer's tier.
 *
 * Only touches tier roles, only for approved users linked to at least one
 * dealer. Users with no linked dealer keep whatever roles an admin set.
 *
 * @param int $user_id User ID.
 */
function rwdpa_sync_user_tier_roles( $user_id ) {
	static $running = [];

	if ( ! rwdpa_tiers_enabled() || isset( $running[ $user_id ] ) ) {
		return;
	}

	$user = get_userdata( $user_id );
	if ( ! $user || user_can( $user, 'manage_options' ) ) {
		return;
	}

	$status = get_user_meta( $user_id, '_rwdp_account_status', true );
	if ( in_array( $status, [ 'pending', 'denied' ], true ) ) {
		return;
	}

	$dealer_id = rwdpa_get_user_primary_dealer( $user_id );
	if ( ! $dealer_id ) {
		return;
	}

	$tiers  = rwdpa_get_tiers();
	$tier   = rwdpa_get_dealer_tier( $dealer_id );
	$rank   = (int) $tiers[ $tier ]['rank'];
	$target = [];
	foreach ( $tiers as $slug => $data ) {
		if ( $slug === $tier || ( rwdpa_portal_setting( 'cumulative_tiers' ) && (int) $data['rank'] <= $rank ) ) {
			$target[] = $slug;
		}
	}

	$running[ $user_id ] = true;
	foreach ( array_keys( $tiers ) as $slug ) {
		$has = in_array( $slug, (array) $user->roles, true );
		if ( in_array( $slug, $target, true ) && ! $has ) {
			$user->add_role( $slug );
		} elseif ( ! in_array( $slug, $target, true ) && $has ) {
			$user->remove_role( $slug );
		}
	}
	unset( $running[ $user_id ] );
}

/**
 * Tier row for the user profile summary.
 *
 * @param array<string,string> $rows Rows.
 * @param WP_User              $user User.
 * @return array<string,string>
 */
function rwdpa_tier_summary_rows( $rows, $user ) {
	if ( ! rwdpa_tiers_enabled() ) {
		return $rows;
	}

	$tier = rwdpa_get_user_tier( $user->ID );
	if ( ! $tier ) {
		return $rows;
	}

	$dealer_id = rwdpa_get_user_primary_dealer( $user->ID );
	$value     = esc_html( rwdpa_get_tiers()[ $tier ]['title'] );
	if ( $dealer_id ) {
		$value .= ' — ' . sprintf(
			/* translators: %s: dealer edit link */
			esc_html__( 'from %s', 'rw-dealer-portal-addons' ),
			'<a href="' . esc_url( get_edit_post_link( $dealer_id ) ) . '">' . esc_html( get_the_title( $dealer_id ) ) . '</a>'
		);
	} else {
		$value .= ' — ' . esc_html__( 'set by role (no linked dealer)', 'rw-dealer-portal-addons' );
	}

	$rows[ __( 'Dealer Tier', 'rw-dealer-portal-addons' ) ] = $value;
	return $rows;
}

/**
 * [rwdpa_tier_card] — the current user's tier, badge and benefits.
 *
 * Attributes: show_badge (yes|no), show_benefits (yes|no), show_preview (yes|no).
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function rwdpa_tier_card_shortcode( $atts = [] ) {
	if ( ! rwdpa_tiers_enabled() || ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts( [
		'show_badge'    => 'yes',
		'show_benefits' => 'yes',
		'show_preview'  => 'yes',
	], $atts, 'rwdpa_tier_card' );

	$tiers   = rwdpa_get_tiers();
	$preview = rwdpa_get_preview_tier();
	$user_id = get_current_user_id();
	$slug    = $preview ?: rwdpa_get_user_tier( $user_id );

	// Staff with no tier of their own see the lowest tier so the layout can be edited.
	if ( ! $slug && rwdpa_can_preview_tiers() ) {
		$slug = rwdpa_get_default_tier();
	}
	if ( ! $slug || ! isset( $tiers[ $slug ] ) ) {
		return '';
	}

	$tier   = $tiers[ $slug ];
	$radius = rwdpa_get_dealer_protected_radius( rwdpa_get_user_primary_dealer( $user_id ) );
	$fill   = static function ( $text ) use ( $radius ) {
		return str_replace( '{protected_radius}', esc_html( $radius ), $text );
	};

	// No protected radius means no territory agreement: drop benefits that reference it.
	if ( '' === $radius ) {
		$tier['benefits'] = array_values( array_filter( $tier['benefits'], static function ( $benefit ) {
			return false === strpos( $benefit['text'], '{protected_radius}' );
		} ) );
	}

	wp_enqueue_style( 'rwdpa-portal-display', RWDPA_PLUGIN_URL . 'assets/css/portal-display.css', [], RWDPA_VERSION );

	$style = $tier['accent'] ? ' style="--rwdpa-tier-accent:' . esc_attr( $tier['accent'] ) . ';"' : '';

	ob_start();
	?>
	<div class="rwdpa-tier-card rwdpa-tier-card--<?php echo esc_attr( sanitize_html_class( $slug ) ); ?><?php echo $tier['accent'] ? ' rwdpa-tier-card--accent' : ''; ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>>
		<div class="rwdpa-tier-card__header">
			<div class="rwdpa-tier-card__text">
				<h3 class="rwdpa-tier-card__title"><?php echo esc_html( $tier['title'] ); ?></h3>
				<?php if ( $tier['intro'] ) : ?>
					<div class="rwdpa-tier-card__intro"><?php echo wp_kses_post( wpautop( $fill( $tier['intro'] ) ) ); ?></div>
				<?php endif; ?>
			</div>
			<?php if ( 'yes' === $atts['show_badge'] && $tier['badge_id'] ) : ?>
				<div class="rwdpa-tier-card__badge">
					<?php echo wp_get_attachment_image( $tier['badge_id'], 'medium', false, [ 'alt' => esc_attr( $tier['title'] ) ] ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( 'yes' === $atts['show_benefits'] && $tier['benefits'] ) : ?>
			<?php $heading = (string) rwdpa_portal_setting( 'benefits_heading' ); ?>
			<?php if ( $heading ) : ?>
				<h4 class="rwdpa-tier-card__benefits-heading"><?php echo esc_html( $heading ); ?></h4>
			<?php endif; ?>
			<ul class="rwdpa-tier-card__benefits">
				<?php foreach ( $tier['benefits'] as $benefit ) : ?>
					<li class="rwdpa-tier-card__benefit"><?php echo wp_kses( $fill( $benefit['text'] ), rwdpa_benefit_allowed_html() ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php if ( 'yes' === $atts['show_preview'] && rwdpa_can_preview_tiers() ) : ?>
		<nav class="rwdpa-tier-preview" aria-label="<?php esc_attr_e( 'Preview dealer tiers', 'rw-dealer-portal-addons' ); ?>">
			<span><?php esc_html_e( 'Preview tier (staff only):', 'rw-dealer-portal-addons' ); ?></span>
			<?php foreach ( $tiers as $preview_slug => $preview_tier ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'rwdpa_preview_tier', $preview_slug ) ); ?>"<?php echo $preview_slug === $slug ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $preview_tier['title'] ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
	<?php
	return (string) ob_get_clean();
}

/**
 * [rwdpa_tier_badge] — the current dealer's tier badge with a download button.
 * Shows nothing for tiers without a badge. Honors the staff tier preview.
 *
 * Attributes: heading, button_text.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function rwdpa_tier_badge_shortcode( $atts = [] ) {
	if ( ! rwdpa_tiers_enabled() || ! is_user_logged_in() ) {
		return '';
	}

	$atts = shortcode_atts( [
		'heading'     => __( 'Your Dealer Badge', 'rw-dealer-portal-addons' ),
		'button_text' => __( 'Download', 'rw-dealer-portal-addons' ),
	], $atts, 'rwdpa_tier_badge' );

	$slug = rwdpa_get_preview_tier() ?: rwdpa_get_user_tier( get_current_user_id() );
	$tier = rwdpa_get_tiers()[ $slug ] ?? null;
	if ( ! $tier || ! $tier['badge_id'] ) {
		return '';
	}

	$url  = wp_get_attachment_url( $tier['badge_id'] );
	$file = basename( (string) get_attached_file( $tier['badge_id'] ) );
	if ( ! $url ) {
		return '';
	}

	wp_enqueue_style( 'rwdpa-portal-display', RWDPA_PLUGIN_URL . 'assets/css/portal-display.css', [], RWDPA_VERSION );

	ob_start();
	?>
	<div class="rwdpa-tier-badge rwdpa-tier-badge--<?php echo esc_attr( sanitize_html_class( $slug ) ); ?>">
		<?php if ( $atts['heading'] ) : ?>
			<h3 class="rwdpa-tier-badge__heading"><?php echo esc_html( $atts['heading'] ); ?></h3>
		<?php endif; ?>
		<div class="rwdpa-tier-badge__image"><?php echo wp_get_attachment_image( $tier['badge_id'], 'medium', false, [ 'alt' => esc_attr( $tier['title'] ) ] ); ?></div>
		<a class="rwdpa-tier-badge__download" href="<?php echo esc_url( $url ); ?>" download="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $atts['button_text'] ); ?></a>
	</div>
	<?php
	return (string) ob_get_clean();
}
