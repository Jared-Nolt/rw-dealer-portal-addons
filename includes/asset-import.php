<?php
/**
 * Bulk import for RW Dealer Portal assets (rw_asset), from Dealer Portal →
 * Portal Display → Import Assets. Creates or updates assets matched by title
 * within their category, using the same meta shapes as the core asset editor,
 * then runs the core file protection for PDF/ZIP assets.
 *
 * CSV columns (case-insensitive; title, category and type are required):
 *   title, category, type, section_heading, description, items, visible_to, order
 *
 * category: path with ">" between levels, e.g. "Photo & Video Library > Photos".
 * type:     gallery | video | file_pdf | file_zip | external_link
 * items:    entries separated by "|", each "Label = value":
 *   gallery        Section Title = modula:123   or   = file1.jpg, file2.jpg, 456
 *   video          Video Title = https://youtube.com/...
 *   file_pdf       Caption = file.pdf @ cover.jpg   (cover optional)
 *   file_zip       = file.zip
 *   external_link  Button Label = https://...
 * Files may be an attachment ID, an uploads URL, an uploads path (2025/07/x.pdf)
 * or a unique file name.
 * visible_to: tier titles or role slugs separated by ";" (empty = all portal roles).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'rwdpa_portal_tabs', 'rwdpa_asset_import_add_tab' );
add_action( 'rwdpa_portal_render_tab_assets', 'rwdpa_asset_import_render_tab' );
add_action( 'admin_post_rwdpa_asset_import', 'rwdpa_asset_import_handle' );

/**
 * Add the Import Assets tab.
 *
 * @param array<string,string> $tabs Tabs.
 * @return array<string,string>
 */
function rwdpa_asset_import_add_tab( $tabs ) {
	$tabs['assets'] = __( 'Import Assets', 'rw-dealer-portal-addons' );
	return $tabs;
}

/**
 * Render the tab.
 */
function rwdpa_asset_import_render_tab() {
	$key    = 'rwdpa_asset_import_report_' . get_current_user_id();
	$report = get_transient( $key );
	if ( $report ) {
		delete_transient( $key );
	}
	?>
	<p><?php esc_html_e( 'Create or update dealer portal assets from a CSV. Assets are matched by title within their category, so the same file can be imported again to update them. PDF and ZIP files are moved into the protected folder, as when saving an asset in the editor.', 'rw-dealer-portal-addons' ); ?></p>
	<table class="widefat striped" style="max-width:1000px;margin-bottom:16px;">
		<thead><tr><th><?php esc_html_e( 'Column', 'rw-dealer-portal-addons' ); ?></th><th><?php esc_html_e( 'Value', 'rw-dealer-portal-addons' ); ?></th></tr></thead>
		<tbody>
			<tr><td><code>title</code></td><td><?php esc_html_e( 'Required. Asset title.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>category</code></td><td><?php esc_html_e( 'Required. Category path, e.g. Photo & Video Library > Photos. Missing categories are created.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>type</code></td><td><code>gallery</code>, <code>video</code>, <code>file_pdf</code>, <code>file_zip</code>, <code>external_link</code></td></tr>
			<tr><td><code>section_heading</code>, <code>description</code></td><td><?php esc_html_e( 'Optional heading and description shown above the asset.', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>items</code></td><td>
				<?php esc_html_e( 'Entries separated by |, each "Label = value".', 'rw-dealer-portal-addons' ); ?><br />
				<code>Chrysalis Luxe = modula:14818</code> · <code>Making Of = https://youtu.be/…</code> · <code>Fuchsia Brochure = 2025/07/fuchsia.pdf @ 2025/07/fuchsia-cover.jpg</code> · <code>= 2023/07/logo.zip</code><br />
				<?php esc_html_e( 'Files can be an attachment ID, URL, uploads path or unique file name.', 'rw-dealer-portal-addons' ); ?>
			</td></tr>
			<tr><td><code>visible_to</code></td><td><?php esc_html_e( 'Tier titles or role slugs separated by ; (empty = every portal role).', 'rw-dealer-portal-addons' ); ?></td></tr>
			<tr><td><code>order</code></td><td><?php esc_html_e( 'Menu order (lower shows first).', 'rw-dealer-portal-addons' ); ?></td></tr>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'rwdpa_asset_import', 'rwdpa_asset_import_nonce' ); ?>
		<input type="hidden" name="action" value="rwdpa_asset_import" />
		<p><input type="file" name="rwdpa_asset_file" accept=".csv,text/csv" required /></p>
		<p><label><input type="checkbox" name="dry_run" value="1" checked /> <?php esc_html_e( 'Dry run (report only, change nothing)', 'rw-dealer-portal-addons' ); ?></label></p>
		<?php submit_button( __( 'Import Assets', 'rw-dealer-portal-addons' ), 'primary', 'submit', false ); ?>
	</form>

	<?php if ( $report ) : ?>
		<h2><?php echo $report['dry_run'] ? esc_html__( 'Dry Run Results', 'rw-dealer-portal-addons' ) : esc_html__( 'Import Results', 'rw-dealer-portal-addons' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: rows, 2: created, 3: updated, 4: problems */
				esc_html__( '%1$d rows · %2$d created · %3$d updated · %4$d problems', 'rw-dealer-portal-addons' ),
				absint( $report['rows'] ),
				absint( $report['created'] ),
				absint( $report['updated'] ),
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
function rwdpa_asset_import_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'rw-dealer-portal-addons' ) );
	}
	check_admin_referer( 'rwdpa_asset_import', 'rwdpa_asset_import_nonce' );

	$file    = $_FILES['rwdpa_asset_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below
	$dry_run = ! empty( $_POST['dry_run'] );

	if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
		$report = [ 'dry_run' => $dry_run, 'rows' => 0, 'created' => 0, 'updated' => 0, 'problems' => [ __( 'No CSV file was uploaded.', 'rw-dealer-portal-addons' ) ] ];
	} else {
		$report = rwdpa_asset_import_run( $file['tmp_name'], $dry_run );
	}

	set_transient( 'rwdpa_asset_import_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=rwdpa-portal&tab=assets' ) );
	exit;
}

/**
 * Resolve a file reference to an attachment ID.
 *
 * @param string $ref Attachment ID, URL, uploads-relative path or file name.
 * @return int
 */
function rwdpa_asset_import_resolve_file( $ref ) {
	global $wpdb;

	$ref = trim( (string) $ref );
	if ( '' === $ref ) {
		return 0;
	}
	if ( ctype_digit( $ref ) ) {
		return 'attachment' === get_post_type( (int) $ref ) ? (int) $ref : 0;
	}

	// URL: keep the part after /uploads/.
	if ( preg_match( '#/uploads/(.+)$#', wp_parse_url( $ref, PHP_URL_PATH ) ?: $ref, $m ) ) {
		$ref = $m[1];
	}
	$ref = ltrim( rawurldecode( $ref ), '/' );

	// Exact uploads path, or any path ending in it — files moved to the
	// protected folder keep their original path after the folder name.
	$ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND ( meta_value = %s OR meta_value LIKE %s )",
		$ref,
		'%/' . $wpdb->esc_like( $ref )
	) );

	// Protected files are flattened (rwdp-protected-x/name.pdf): retry by file name.
	if ( ! $ids && false !== strpos( $ref, '/' ) ) {
		return rwdpa_asset_import_resolve_file( basename( $ref ) );
	}

	// Same name in several folders: prefer the one already in the protected folder.
	if ( count( $ids ) > 1 ) {
		$protected = array_values( array_filter( $ids, static function ( $id ) {
			return 0 === strpos( (string) get_post_meta( $id, '_wp_attached_file', true ), 'rwdp-protected' );
		} ) );
		$ids = 1 === count( $protected ) ? $protected : $ids;
	}

	return 1 === count( $ids ) ? (int) $ids[0] : 0;
}

/**
 * Split an items cell into [label, value] pairs.
 *
 * @param string $cell Items cell.
 * @return array<int,array{0:string,1:string}>
 */
function rwdpa_asset_import_items( $cell ) {
	$out = [];
	foreach ( array_filter( array_map( 'trim', explode( '|', (string) $cell ) ) ) as $entry ) {
		$pos   = strpos( $entry, '=' );
		$label = false === $pos ? '' : trim( substr( $entry, 0, $pos ) );
		$value = false === $pos ? $entry : trim( substr( $entry, $pos + 1 ) );
		// Keep "=" inside URLs (e.g. ?v=) when there was no label separator.
		if ( false !== $pos && preg_match( '#^https?://#', $entry ) ) {
			$label = '';
			$value = $entry;
		}
		$out[] = [ $label, $value ];
	}
	return $out;
}

/**
 * Find or create a category from a "Parent > Child" path.
 *
 * @param string $path    Category path.
 * @param bool   $dry_run Don't create.
 * @return int Term ID (0 on dry run when missing).
 */
function rwdpa_asset_import_category( $path, $dry_run ) {
	$parent = 0;
	foreach ( array_filter( array_map( 'trim', explode( '>', $path ) ) ) as $name ) {
		$found = get_terms( [ 'taxonomy' => 'rw_asset_category', 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'fields' => 'ids' ] );
		if ( ! is_wp_error( $found ) && $found ) {
			$parent = (int) $found[0];
			continue;
		}
		if ( $dry_run ) {
			return 0;
		}
		$term = wp_insert_term( $name, 'rw_asset_category', [ 'parent' => $parent ] );
		if ( is_wp_error( $term ) ) {
			return 0;
		}
		$parent = (int) $term['term_id'];
	}
	return $parent;
}

/**
 * Run an asset import.
 *
 * @param string $path    CSV path.
 * @param bool   $dry_run Report only.
 * @return array{dry_run:bool,rows:int,created:int,updated:int,problems:string[]}
 */
function rwdpa_asset_import_run( $path, $dry_run = true ) {
	$report = [ 'dry_run' => (bool) $dry_run, 'rows' => 0, 'created' => 0, 'updated' => 0, 'problems' => [] ];

	if ( ! post_type_exists( 'rw_asset' ) ) {
		$report['problems'][] = __( 'RW Dealer Portal assets are not available.', 'rw-dealer-portal-addons' );
		return $report;
	}

	$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $fh ) {
		$report['problems'][] = __( 'Unable to read the CSV file.', 'rw-dealer-portal-addons' );
		return $report;
	}

	$header = array_map( static function ( $h ) {
		return sanitize_key( str_replace( [ ' ', '-' ], '_', strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ) ) );
	}, (array) fgetcsv( $fh ) );
	foreach ( [ 'title', 'category', 'type' ] as $required ) {
		if ( ! in_array( $required, $header, true ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			/* translators: %s: column */
			$report['problems'][] = sprintf( __( 'The CSV needs a "%s" column.', 'rw-dealer-portal-addons' ), $required );
			return $report;
		}
	}

	$types = [ 'gallery', 'video', 'file_pdf', 'file_zip', 'external_link' ];
	$roles = [];
	foreach ( ( function_exists( 'rwdp_get_viewer_roles' ) ? rwdp_get_viewer_roles() : [] ) as $slug => $label ) {
		$roles[ strtolower( $slug ) ] = $slug;
		$roles[ rwdpa_import_normalize( $label ) ] = $slug;
	}
	foreach ( rwdpa_get_tiers() as $slug => $tier ) {
		$roles[ rwdpa_import_normalize( $tier['title'] ) ] = $slug;
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
		$problem = static function ( $message ) use ( &$report, $line ) {
			/* translators: 1: line, 2: message */
			$report['problems'][] = sprintf( __( 'Line %1$d: %2$s', 'rw-dealer-portal-addons' ), $line, $message );
		};

		$type = strtolower( $data['type'] );
		if ( '' === $data['title'] || '' === $data['category'] || ! in_array( $type, $types, true ) ) {
			$problem( __( 'title, category and a valid type are required.', 'rw-dealer-portal-addons' ) );
			continue;
		}

		// Build the type's items, collecting unresolved files as problems.
		$meta  = [];
		$bad   = false;
		$items = rwdpa_asset_import_items( $data['items'] ?? '' );
		switch ( $type ) {
			case 'gallery':
				$sections = [];
				foreach ( $items as [ $label, $value ] ) {
					$ids = [];
					if ( preg_match( '/^modula:(\d+)$/i', $value, $m ) ) {
						foreach ( (array) get_post_meta( (int) $m[1], 'modula-images', true ) as $image ) {
							$ids[] = absint( is_array( $image ) ? ( $image['id'] ?? 0 ) : 0 );
						}
						if ( ! array_filter( $ids ) ) {
							/* translators: %s: gallery */
							$problem( sprintf( __( 'Modula gallery %s has no images.', 'rw-dealer-portal-addons' ), $m[1] ) );
							$bad = true;
						}
					} else {
						foreach ( array_filter( array_map( 'trim', explode( ',', $value ) ) ) as $ref ) {
							$id = rwdpa_asset_import_resolve_file( $ref );
							if ( ! $id ) {
								/* translators: %s: file */
								$problem( sprintf( __( 'image not found: %s', 'rw-dealer-portal-addons' ), $ref ) );
								$bad = true;
							}
							$ids[] = $id;
						}
					}
					$sections[] = [ 'title' => sanitize_text_field( $label ), 'image_ids' => array_values( array_filter( $ids ) ) ];
				}
				$meta['_rwdp_asset_gallery_items'] = $sections;
				$meta['_rwdp_asset_gallery_ids']   = $sections ? $sections[0]['image_ids'] : [];
				break;

			case 'video':
				$videos = [];
				foreach ( $items as [ $label, $value ] ) {
					$videos[] = [ 'title' => sanitize_text_field( $label ), 'url' => esc_url_raw( $value ) ];
				}
				$meta['_rwdp_asset_video_items'] = $videos;
				$meta['_rwdp_asset_video_url']   = $videos ? $videos[0]['url'] : '';
				break;

			case 'file_pdf':
				$pdfs = [];
				foreach ( $items as [ $label, $value ] ) {
					[ $file_ref, $cover_ref ] = array_pad( array_map( 'trim', explode( '@', $value, 2 ) ), 2, '' );
					$pdf_id   = rwdpa_asset_import_resolve_file( $file_ref );
					$thumb_id = $cover_ref ? rwdpa_asset_import_resolve_file( $cover_ref ) : 0;
					if ( ! $pdf_id || false === strpos( (string) get_post_mime_type( $pdf_id ), 'pdf' ) ) {
						/* translators: %s: file */
						$problem( sprintf( __( 'PDF not found: %s', 'rw-dealer-portal-addons' ), $file_ref ) );
						$bad = true;
						continue;
					}
					if ( $cover_ref && ! $thumb_id ) {
						/* translators: %s: file */
						$problem( sprintf( __( 'cover image not found: %s', 'rw-dealer-portal-addons' ), $cover_ref ) );
					}
					$pdfs[] = [ 'pdf_id' => $pdf_id, 'thumb_id' => $thumb_id, 'use_cover' => ! $thumb_id, 'caption' => sanitize_text_field( $label ) ];
				}
				$meta['_rwdp_asset_pdf_items'] = $pdfs;
				$meta['_rwdp_asset_pdf_id']    = $pdfs ? $pdfs[0]['pdf_id'] : 0;
				break;

			case 'file_zip':
				$zip_id = $items ? rwdpa_asset_import_resolve_file( $items[0][1] ) : 0;
				$mime   = (string) get_post_mime_type( $zip_id );
				if ( ! $zip_id || ( false === strpos( $mime, 'zip' ) && false === strpos( $mime, 'compressed' ) ) ) {
					/* translators: %s: file */
					$problem( sprintf( __( 'ZIP not found: %s', 'rw-dealer-portal-addons' ), $items ? $items[0][1] : '' ) );
					$bad = true;
				}
				$meta['_rwdp_asset_zip_id'] = $zip_id;
				break;

			case 'external_link':
				$links = [];
				foreach ( $items as [ $label, $value ] ) {
					$links[] = [ 'title' => '', 'url' => esc_url_raw( $value ), 'label' => sanitize_text_field( $label ) ];
				}
				$meta['_rwdp_asset_external_items'] = $links;
				$meta['_rwdp_asset_external_url']   = $links ? $links[0]['url'] : '';
				$meta['_rwdp_asset_external_label'] = $links ? $links[0]['label'] : '';
				break;
		}
		if ( $bad ) {
			continue;
		}

		$allowed = [];
		foreach ( array_filter( array_map( 'trim', explode( ';', $data['visible_to'] ?? '' ) ) ) as $role_ref ) {
			$slug = $roles[ strtolower( $role_ref ) ] ?? $roles[ rwdpa_import_normalize( $role_ref ) ] ?? '';
			if ( ! $slug ) {
				/* translators: %s: role */
				$problem( sprintf( __( 'unknown role "%s" ignored.', 'rw-dealer-portal-addons' ), $role_ref ) );
				continue;
			}
			$allowed[] = $slug;
		}

		$term_id = rwdpa_asset_import_category( $data['category'], $dry_run );

		// Existing asset with the same title in the same category.
		$existing = 0;
		if ( $term_id ) {
			foreach ( get_posts( [ 'post_type' => 'rw_asset', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'title' => $data['title'], 'tax_query' => [ [ 'taxonomy' => 'rw_asset_category', 'terms' => [ $term_id ], 'include_children' => false ] ], 'rwdp_skip_visibility' => true, 'suppress_filters' => false ] ) as $id ) {
				$existing = (int) $id;
				break;
			}
		}

		if ( $dry_run ) {
			++$report[ $existing ? 'updated' : 'created' ];
			continue;
		}

		$postarr = [
			'post_type'   => 'rw_asset',
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( $data['title'] ),
			'menu_order'  => (int) ( $data['order'] ?? 0 ),
		];
		if ( $existing ) {
			$postarr['ID'] = $existing;
			$asset_id      = wp_update_post( $postarr, true );
		} else {
			$asset_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $asset_id ) ) {
			$problem( $asset_id->get_error_message() );
			continue;
		}

		wp_set_object_terms( $asset_id, [ $term_id ], 'rw_asset_category' );

		// Clear every type's meta first, as the core editor does, then write this type.
		foreach ( [ '_rwdp_asset_gallery_items' => [], '_rwdp_asset_gallery_ids' => [], '_rwdp_asset_video_items' => [], '_rwdp_asset_video_url' => '', '_rwdp_asset_pdf_items' => [], '_rwdp_asset_pdf_id' => 0, '_rwdp_asset_zip_id' => 0, '_rwdp_asset_external_items' => [], '_rwdp_asset_external_url' => '', '_rwdp_asset_external_label' => '' ] as $key => $empty ) {
			update_post_meta( $asset_id, $key, $meta[ $key ] ?? $empty );
		}
		update_post_meta( $asset_id, '_rwdp_asset_type', $type );
		update_post_meta( $asset_id, '_rwdp_asset_section_heading', sanitize_text_field( $data['section_heading'] ?? '' ) );
		update_post_meta( $asset_id, '_rwdp_asset_description', wp_kses_post( $data['description'] ?? '' ) );

		if ( defined( 'RWDP_ALLOWED_ROLE_META' ) ) {
			delete_post_meta( $asset_id, RWDP_ALLOWED_ROLE_META );
			foreach ( array_unique( $allowed ) as $slug ) {
				add_post_meta( $asset_id, RWDP_ALLOWED_ROLE_META, $slug );
			}
		}

		// Same protection the core editor applies on save.
		if ( function_exists( 'rwdp_protect_typed_asset_files' ) ) {
			rwdp_protect_typed_asset_files( $asset_id );
		}

		++$report[ $existing ? 'updated' : 'created' ];
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return $report;
}
