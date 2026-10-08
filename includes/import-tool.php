<?php
/**
 * Bulk import for dealer portal details: tier, protected radius, territory,
 * sales manager override and linked users, matched to existing dealers by
 * title. Runs from Dealer Portal → Portal Display → Import, with a dry run.
 *
 * CSV columns (header names are case-insensitive; only "dealer" is required):
 *   dealer, tier, protected_radius, territory, manager, user_emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'rwdpa_portal_tabs', 'rwdpa_import_add_tab' );
add_action( 'rwdpa_portal_render_tab_import', 'rwdpa_import_render_tab' );
add_action( 'admin_post_rwdpa_import', 'rwdpa_import_handle' );

/**
 * Add the Import tab.
 *
 * @param array<string,string> $tabs Tabs.
 * @return array<string,string>
 */
function rwdpa_import_add_tab( $tabs ) {
	$tabs['import'] = __( 'Import', 'rw-dealer-portal-addons' );
	return $tabs;
}

/**
 * Normalize a dealer title for matching (case, punctuation, curly quotes).
 *
 * @param string $title Title.
 * @return string
 */
function rwdpa_import_normalize( $title ) {
	$title = html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' );
	// Apostrophes are dropped so "Woodys" matches "Woody's".
	$title = str_replace( [ "\u{2019}", "\u{2018}", "'" ], '', $title );
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $title ) ) );
}

/**
 * Render the Import tab (outside the options form).
 */
function rwdpa_import_render_tab() {
	$report = get_transient( 'rwdpa_import_report_' . get_current_user_id() );
	if ( $report ) {
		delete_transient( 'rwdpa_import_report_' . get_current_user_id() );
	}
	?>
	<p><?php esc_html_e( 'Update existing dealers from a CSV. Dealers are matched by title; create them first with Dealers → Import. Empty cells leave that value unchanged.', 'rw-dealer-portal-addons' ); ?></p>
	<table class="widefat striped" style="max-width:900px;margin-bottom:16px;">
		<thead><tr><th><?php esc_html_e( 'Column', 'rw-dealer-portal-addons' ); ?></th><th><?php esc_html_e( 'Value', 'rw-dealer-portal-addons' ); ?></th></tr></thead>
		<tbody>
			<tr><td><code>dealer</code></td><td><?php esc_html_e( 'Required. Dealer title.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>tier</code></td><td><?php esc_html_e( 'Tier title or role slug, e.g. Craftsman or rwdp_craftsman.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>protected_radius</code></td><td><?php esc_html_e( 'Text, e.g. 25 miles.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>territory</code></td><td><?php esc_html_e( 'Territory name; created if it does not exist. Requires sales managers.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>manager</code></td><td><?php esc_html_e( 'Manager override: email or username of a sales manager user.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>user_emails</code></td><td><?php esc_html_e( 'Emails of users to link to this dealer, separated by ; or ,', 'rw-dealer-portal-addons' ); ?></td></tr>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'rwdpa_import', 'rwdpa_import_nonce' ); ?>
		<input type="hidden" name="action" value="rwdpa_import" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwdpa_import_file"><?php esc_html_e( 'CSV File', 'rw-dealer-portal-addons' ); ?></label></th>
				<td><input type="file" id="rwdpa_import_file" name="rwdpa_import_file" accept=".csv,text/csv" required /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Linked Users', 'rw-dealer-portal-addons' ); ?></th>
				<td>
					<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="give_dealer_role" value="1" checked /> <?php esc_html_e( 'Give linked users the Dealer role if they have no portal role', 'rw-dealer-portal-addons' ); ?></label>
					<label for="rwdpa_remove_role"><?php esc_html_e( 'Also remove this role from linked users (optional):', 'rw-dealer-portal-addons' ); ?></label>
					<input type="text" id="rwdpa_remove_role" name="remove_role" class="regular-text" placeholder="<?php esc_attr_e( 'role slug, e.g. dealer_1', 'rw-dealer-portal-addons' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Mode', 'rw-dealer-portal-addons' ); ?></th>
				<td><label><input type="checkbox" name="dry_run" value="1" checked /> <?php esc_html_e( 'Dry run (report only, change nothing)', 'rw-dealer-portal-addons' ); ?></label></td>
			</tr>
		</table>
		<?php submit_button( __( 'Run Import', 'rw-dealer-portal-addons' ), 'primary', 'submit', false ); ?>
	</form>

	<?php if ( $report ) : ?>
		<h2><?php echo $report['dry_run'] ? esc_html__( 'Dry Run Results', 'rw-dealer-portal-addons' ) : esc_html__( 'Import Results', 'rw-dealer-portal-addons' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: rows, 2: dealers updated, 3: users linked, 4: problems */
				esc_html__( '%1$d rows · %2$d dealers updated · %3$d users linked · %4$d problems', 'rw-dealer-portal-addons' ),
				absint( $report['rows'] ),
				absint( $report['dealers'] ),
				absint( $report['users'] ),
				count( $report['problems'] )
			);
			?>
		</p>
		<?php if ( $report['problems'] ) : ?>
			<ul style="list-style:disc;padding-left:20px;">
				<?php foreach ( $report['problems'] as $problem ) : ?>
					<li><?php echo esc_html( $problem ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
	<?php
}

/**
 * Handle the upload.
 */
function rwdpa_import_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'rw-dealer-portal-addons' ) );
	}
	check_admin_referer( 'rwdpa_import', 'rwdpa_import_nonce' );

	$file    = $_FILES['rwdpa_import_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below
	$options = [
		'dry_run'          => ! empty( $_POST['dry_run'] ),
		'give_dealer_role' => ! empty( $_POST['give_dealer_role'] ),
		'remove_role'      => sanitize_key( wp_unslash( $_POST['remove_role'] ?? '' ) ),
	];

	if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		$report = [ 'dry_run' => $options['dry_run'], 'rows' => 0, 'dealers' => 0, 'users' => 0, 'problems' => [ __( 'No CSV file was uploaded.', 'rw-dealer-portal-addons' ) ] ];
	} else {
		$report = rwdpa_import_run( $file['tmp_name'], $options );
	}

	set_transient( 'rwdpa_import_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=rwdpa-portal&tab=import' ) );
	exit;
}

/**
 * Run an import from a CSV path. Usable from WP-CLI: wp eval 'print_r( rwdpa_import_run( "/path.csv", [ "dry_run" => true ] ) );'
 *
 * @param string               $path    CSV file path.
 * @param array<string,mixed>  $options dry_run, give_dealer_role, remove_role.
 * @return array{dry_run:bool,rows:int,dealers:int,users:int,problems:string[]}
 */
function rwdpa_import_run( $path, $options = [] ) {
	$options = wp_parse_args( $options, [ 'dry_run' => true, 'give_dealer_role' => true, 'remove_role' => '' ] );
	$report  = [ 'dry_run' => (bool) $options['dry_run'], 'rows' => 0, 'dealers' => 0, 'users' => 0, 'problems' => [] ];

	$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $fh ) {
		$report['problems'][] = __( 'Unable to read the CSV file.', 'rw-dealer-portal-addons' );
		return $report;
	}

	$header = fgetcsv( $fh );
	$header = is_array( $header ) ? array_map( static function ( $h ) {
		return sanitize_key( str_replace( [ ' ', '-' ], '_', strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ) ) );
	}, $header ) : [];
	if ( ! in_array( 'dealer', $header, true ) ) {
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$report['problems'][] = __( 'The CSV needs a "dealer" column.', 'rw-dealer-portal-addons' );
		return $report;
	}

	// Dealer lookup by normalized title.
	$dealers = [];
	foreach ( get_posts( [ 'post_type' => 'rw_dealer', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] ) as $id ) {
		$dealers[ rwdpa_import_normalize( get_the_title( $id ) ) ][] = (int) $id;
	}

	// Tier lookup by slug or title.
	$tiers = [];
	foreach ( rwdpa_get_tiers() as $slug => $tier ) {
		$tiers[ strtolower( $slug ) ]                       = $slug;
		$tiers[ rwdpa_import_normalize( $tier['title'] ) ] = $slug;
	}

	$line = 1;
	while ( false !== ( $row = fgetcsv( $fh ) ) ) {
		++$line;
		if ( ! array_filter( (array) $row, 'strlen' ) ) {
			continue;
		}
		++$report['rows'];
		$data = [];
		foreach ( $header as $i => $key ) {
			$data[ $key ] = trim( (string) ( $row[ $i ] ?? '' ) );
		}

		$matches = $dealers[ rwdpa_import_normalize( $data['dealer'] ) ] ?? [];
		if ( 1 !== count( $matches ) ) {
			/* translators: 1: line, 2: dealer title */
			$report['problems'][] = sprintf( $matches ? __( 'Line %1$d: more than one dealer is titled "%2$s" — rename one.', 'rw-dealer-portal-addons' ) : __( 'Line %1$d: no dealer titled "%2$s".', 'rw-dealer-portal-addons' ), $line, $data['dealer'] );
			continue;
		}
		$dealer_id = $matches[0];
		$changed   = false;

		if ( '' !== ( $data['tier'] ?? '' ) ) {
			$slug = $tiers[ strtolower( $data['tier'] ) ] ?? $tiers[ rwdpa_import_normalize( $data['tier'] ) ] ?? '';
			if ( ! $slug ) {
				/* translators: 1: line, 2: tier */
				$report['problems'][] = sprintf( __( 'Line %1$d: unknown tier "%2$s".', 'rw-dealer-portal-addons' ), $line, $data['tier'] );
			} elseif ( ! $options['dry_run'] ) {
				update_post_meta( $dealer_id, RWDPA_TIER_META, $slug );
				$changed = true;
			} else {
				$changed = true;
			}
		}

		if ( '' !== ( $data['protected_radius'] ?? '' ) ) {
			if ( ! $options['dry_run'] ) {
				update_post_meta( $dealer_id, RWDPA_RADIUS_META, sanitize_text_field( $data['protected_radius'] ) );
			}
			$changed = true;
		}

		if ( '' !== ( $data['territory'] ?? '' ) ) {
			if ( ! rwdpa_managers_enabled() ) {
				/* translators: %d: line */
				$report['problems'][] = sprintf( __( 'Line %d: territory ignored — enable sales managers first.', 'rw-dealer-portal-addons' ), $line );
			} else {
				if ( ! $options['dry_run'] ) {
					$term = term_exists( $data['territory'], RWDPA_TERRITORY_TAX ) ?: wp_insert_term( sanitize_text_field( $data['territory'] ), RWDPA_TERRITORY_TAX );
					if ( ! is_wp_error( $term ) ) {
						wp_set_object_terms( $dealer_id, [ (int) $term['term_id'] ], RWDPA_TERRITORY_TAX );
					}
				}
				$changed = true;
			}
		}

		if ( '' !== ( $data['manager'] ?? '' ) ) {
			$manager = get_user_by( 'email', $data['manager'] ) ?: get_user_by( 'login', $data['manager'] );
			if ( ! $manager || ! in_array( RWDPA_MANAGER_ROLE, (array) $manager->roles, true ) ) {
				/* translators: 1: line, 2: manager */
				$report['problems'][] = sprintf( __( 'Line %1$d: "%2$s" is not a sales manager user.', 'rw-dealer-portal-addons' ), $line, $data['manager'] );
			} else {
				if ( ! $options['dry_run'] ) {
					update_post_meta( $dealer_id, RWDPA_MANAGER_OVERRIDE, $manager->ID );
				}
				$changed = true;
			}
		}

		foreach ( array_filter( array_map( 'trim', preg_split( '/[;,]/', $data['user_emails'] ?? '' ) ) ) as $email ) {
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				/* translators: 1: line, 2: email */
				$report['problems'][] = sprintf( __( 'Line %1$d: no user with email %2$s.', 'rw-dealer-portal-addons' ), $line, $email );
				continue;
			}
			if ( user_can( $user, 'manage_options' ) ) {
				/* translators: 1: line, 2: email */
				$report['problems'][] = sprintf( __( 'Line %1$d: %2$s is an administrator — not linked.', 'rw-dealer-portal-addons' ), $line, $email );
				continue;
			}
			++$report['users'];
			if ( $options['dry_run'] ) {
				continue;
			}

			if ( $options['give_dealer_role'] && function_exists( 'rwdp_user_has_viewer_role' ) && ! rwdp_user_has_viewer_role( $user ) ) {
				$user->add_role( 'rwdp_dealer' );
			}
			if ( $options['remove_role'] && in_array( $options['remove_role'], (array) $user->roles, true ) ) {
				$user->remove_role( $options['remove_role'] );
			}

			$ids = rwdpa_get_user_dealer_ids( $user->ID );
			if ( ! in_array( $dealer_id, $ids, true ) ) {
				$ids[] = $dealer_id;
				update_user_meta( $user->ID, '_rwdp_dealer_ids', $ids ); // Triggers the tier sync.
			} else {
				rwdpa_sync_user_tier_roles( $user->ID );
			}
		}

		if ( $changed ) {
			++$report['dealers'];
			if ( ! $options['dry_run'] ) {
				rwdpa_sync_dealer_users( $dealer_id );
			}
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return $report;
}
