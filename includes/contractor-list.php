<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'rwdpa_enqueue_contractor_print_filter_assets', 110 );
add_action( 'wp_print_footer_scripts', 'rwdpa_enqueue_contractor_print_filter_assets', 2 );

if ( ! function_exists( 'rwdpa_enqueue_contractor_print_filter_assets' ) ) {
	/**
	 * Enqueue print filter URL enhancements when the core dealer map script is active.
	 */
	function rwdpa_enqueue_contractor_print_filter_assets() {
		static $done = false;
		if ( $done ) {
			return;
		}

		if ( ! wp_script_is( 'rwdp-dealer-map', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'rwdpa-print-filters',
			RWDPA_PLUGIN_URL . 'assets/js/print-filters.js',
			[ 'rwdp-dealer-map', 'jquery' ],
			RWDPA_VERSION,
			true
		);

		$done = true;
	}
}

if ( ! function_exists( 'rwdp_get_contractor_list_settings' ) ) {
	/**
	 * Contractor list settings defaults and sanitization-safe shape.
	 *
	 * @return array<string,mixed>
	 */
	function rwdp_get_contractor_list_settings() {
		if ( function_exists( 'rwdpa_get_settings' ) ) {
			return rwdpa_get_settings();
		}

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

		return wp_parse_args( $settings, $defaults );
	}
}

if ( ! function_exists( 'rwdp_get_contractor_list_column_labels' ) ) {
	/**
	 * Column labels for contractor list table.
	 *
	 * @return array<string,string>
	 */
	function rwdp_get_contractor_list_column_labels() {
		return [
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
	}
}

if ( ! function_exists( 'rwdp_get_contractor_print_url' ) ) {
	/**
	 * Build a contractor print URL.
	 *
	 * @param array<int> $dealer_ids Dealer IDs.
	 * @param string     $dealer_type_slug Dealer type slug.
	 * @return string
	 */
	function rwdp_get_contractor_print_url( $dealer_ids = [], $dealer_type_slug = '' ) {
		$args = [
			'rwdp_print_dealers' => '1',
		];

		$dealer_ids = array_values( array_filter( array_map( 'absint', (array) $dealer_ids ) ) );
		if ( ! empty( $dealer_ids ) ) {
			$args['dealer_ids'] = implode( ',', $dealer_ids );
		}

		$dealer_type_slug = sanitize_title( $dealer_type_slug );
		if ( '' !== $dealer_type_slug ) {
			$args['dealer_type'] = $dealer_type_slug;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}
}

if ( ! function_exists( 'rwdp_get_contractor_rows' ) ) {
	/**
	 * Return normalized contractor rows for rendering in widget or print page.
	 *
	 * @param array<string,mixed> $args Filter args.
	 * @return array<int,array<string,string|int>>
	 */
	function rwdp_get_contractor_rows( $args = [] ) {
		$dealer_ids  = array_values( array_filter( array_map( 'absint', (array) ( $args['dealer_ids'] ?? [] ) ) ) );
		$dealer_type = sanitize_title( $args['dealer_type'] ?? '' );

		$query_args = [
			'post_type'      => 'rw_dealer',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'     => '_rwdp_address_valid',
					'value'   => '1',
					'compare' => '=',
				],
			],
		];

		if ( ! empty( $dealer_ids ) ) {
			$query_args['post__in'] = $dealer_ids;
			$query_args['orderby']  = 'post__in';
		}

		if ( '' !== $dealer_type ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'rw_dealer_type',
					'field'    => 'slug',
					'terms'    => $dealer_type,
				],
			];
		}

		$posts = get_posts( $query_args );
		if ( empty( $posts ) ) {
			return [];
		}

		$rows = [];
		foreach ( $posts as $post ) {
			$website = get_post_meta( $post->ID, '_rwdp_website', true );
			$website_display = $website ? preg_replace( '#^https?://(www\.)?#i', '', $website ) : '';
			$hours = get_post_meta( $post->ID, '_rwdp_hours', true );
			if ( '' === trim( (string) $hours ) ) {
				$hours = get_post_meta( $post->ID, 'rwdp_hours', true );
			}
			if ( '' === trim( (string) $hours ) ) {
				$hours = get_post_meta( $post->ID, 'hours', true );
			}

			$rows[] = [
				'id'           => $post->ID,
				'company'      => (string) $post->post_title,
				'contact_name' => (string) get_post_meta( $post->ID, '_rwdp_contact_name', true ),
				'address'      => (string) get_post_meta( $post->ID, '_rwdp_address', true ),
				'city'         => (string) get_post_meta( $post->ID, '_rwdp_city', true ),
				'state'        => (string) get_post_meta( $post->ID, '_rwdp_state', true ),
				'zip'          => (string) get_post_meta( $post->ID, '_rwdp_zip', true ),
				'phone'        => (string) get_post_meta( $post->ID, '_rwdp_phone', true ),
				'email'        => (string) get_post_meta( $post->ID, '_rwdp_public_email', true ),
				'website'      => (string) $website,
				'website_text' => (string) $website_display,
				'hours'        => (string) $hours,
			];
		}

		return $rows;
	}
}

if ( ! function_exists( 'rwdp_maybe_render_contractor_print_page' ) ) {
	/**
	 * Frontend print route for contractor list.
	 */
	function rwdp_maybe_render_contractor_print_page() {
		if ( empty( $_GET['rwdp_print_dealers'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['rwdp_print_dealers'] ) ) ) {
			return;
		}

		$dealer_ids_raw = sanitize_text_field( wp_unslash( $_GET['dealer_ids'] ?? '' ) );
		$dealer_ids = [];
		if ( '' !== $dealer_ids_raw ) {
			$dealer_ids = array_values( array_filter( array_map( 'absint', explode( ',', $dealer_ids_raw ) ) ) );
		}

		$dealer_type_raw = sanitize_text_field( wp_unslash( $_GET['dealer_type'] ?? '' ) );
		// Handle dealer_type as either term ID or slug.
		$dealer_type = '';
		if ( '' !== $dealer_type_raw ) {
			$term_id = absint( $dealer_type_raw );
			if ( $term_id > 0 ) {
				// Try as term ID first.
				$term = get_term( $term_id, 'rw_dealer_type' );
				if ( $term && ! is_wp_error( $term ) ) {
					$dealer_type = $term->slug;
				}
			} else {
				// Try as slug (fallback for backward compatibility).
				$dealer_type = sanitize_title( $dealer_type_raw );
			}
		}
		$filter_title_display = sanitize_text_field( wp_unslash( $_GET['filter_title_display'] ?? '' ) );

		$settings = rwdp_get_contractor_list_settings();
		$rows = rwdp_get_contractor_rows( [
			'dealer_ids'  => $dealer_ids,
			'dealer_type' => $dealer_type,
		] );

		$labels = rwdp_get_contractor_list_column_labels();
		$columns = $settings['contractor_list_show_columns'];
		$logo_url = $settings['contractor_list_logo_id'] ? wp_get_attachment_image_url( $settings['contractor_list_logo_id'], 'full' ) : '';
		$list_subject_label = sanitize_text_field( $settings['contractor_list_subject_label'] ?? '' );
		if ( '' === $list_subject_label ) {
			$list_subject_label = __( 'Contractor', 'rw-dealer-portal-addons' );
		}
		$header_title = sprintf(
			/* translators: %s: list subject label */
			__( '%s List', 'rw-dealer-portal-addons' ),
			$list_subject_label
		);
		$header_parts = [];
		$seen_parts = [];

		$add_header_part = static function ( $part ) use ( &$header_parts, &$seen_parts ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				return;
			}

			$key = strtolower( $part );
			if ( isset( $seen_parts[ $key ] ) ) {
				return;
			}

			$seen_parts[ $key ] = true;
			$header_parts[] = $part;
		};

		if ( '' !== $dealer_type ) {
			$term = get_term_by( 'slug', $dealer_type, 'rw_dealer_type' );
			if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
				$add_header_part( $term->name );
			}
		}

		if ( '' !== $filter_title_display ) {
			$pieces = preg_split( '/\s*\+\s*/', $filter_title_display );
			if ( is_array( $pieces ) ) {
				foreach ( $pieces as $piece ) {
					$add_header_part( $piece );
				}
			}
		}

		if ( ! empty( $header_parts ) ) {
			$header_title = sprintf(
				/* translators: 1: active filter names, 2: list subject label */
				__( '%1$s %2$s List', 'rw-dealer-portal-addons' ),
				implode( ' + ', $header_parts ),
				$list_subject_label
			);
		}

		nocache_headers();
		status_header( 200 );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html( $header_title ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
				.rwdp-print-topbar { margin-bottom: 16px; }
				.rwdp-print-topbar button { margin-right: 8px; }
				.rwdp-print-header { display: flex;flex-direction: column; justify-content: space-between; gap: 24px; align-items: flex-start; margin-bottom: 18px; }
				.rwdp-print-logo img { max-height: 90px; width: auto; }
				.rwdp-print-address { white-space: pre-line; font-size: 14px; line-height: 1.4; }
				h1 { margin: 0 0 14px; font-size: 28px; }
				table { width: 100%; border-collapse: collapse; }
				th, td { border: 1px solid #cfcfcf; padding: 8px 10px; font-size: 13px; vertical-align: top; }
				th { background: #f5f5f5; text-align: left; }
				.rwdp-print-disclaimer { margin-top: 16px; font-size: 12px; color: #555; white-space: pre-line; }
				.rwdp-print-empty { padding: 18px; background: #f5f5f5; border: 1px solid #ddd; }
				@media print {
					.rwdp-print-topbar { display: none; }
					body { margin: 0.5in; }
				}
			</style>
		</head>
		<body>
			<div class="rwdp-print-topbar">
				<button type="button" onclick="window.print();"><?php esc_html_e( 'Print / Save PDF', 'rw-dealer-portal-addons' ); ?></button>
				<button type="button" onclick="window.close();"><?php esc_html_e( 'Close', 'rw-dealer-portal-addons' ); ?></button>
			</div>

			<h1><?php echo esc_html( $header_title ); ?></h1>

			<div class="rwdp-print-header">
				<div class="rwdp-print-logo">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $settings['contractor_list_address'] ) ) : ?>
					<div class="rwdp-print-address"><?php echo esc_html( $settings['contractor_list_address'] ); ?></div>
				<?php endif; ?>
			</div>

			<?php if ( empty( $rows ) ) : ?>
				<div class="rwdp-print-empty"><?php esc_html_e( 'No contractors found for the selected filters.', 'rw-dealer-portal-addons' ); ?></div>
			<?php else : ?>
				<table>
					<thead>
						<tr>
							<?php foreach ( $columns as $column ) : ?>
								<th><?php echo esc_html( $labels[ $column ] ?? ucfirst( $column ) ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<?php foreach ( $columns as $column ) : ?>
									<td>
										<?php if ( 'website' === $column && ! empty( $row['website'] ) ) : ?>
											<a href="<?php echo esc_url( $row['website'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['website_text'] ?: $row['website'] ); ?></a>
										<?php elseif ( 'hours' === $column ) : ?>
											<?php echo nl2br( esc_html( trim( (string) ( $row[ $column ] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php else : ?>
											<?php echo esc_html( $row[ $column ] ?? '' ); ?>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $settings['contractor_list_disclaimer'] ) ) : ?>
				<div class="rwdp-print-disclaimer"><?php echo esc_html( $settings['contractor_list_disclaimer'] ); ?></div>
			<?php endif; ?>
		</body>
		</html>
		<?php
		exit;
	}
}

add_action( 'template_redirect', 'rwdp_maybe_render_contractor_print_page' );
