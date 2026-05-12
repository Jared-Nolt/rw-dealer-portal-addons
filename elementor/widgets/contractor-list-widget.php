<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWDP_Contractor_List_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'rwdp_contractor_list';
	}

	public function get_title() {
		return __( 'Contractor List', 'rw-dealer-portal-addons' );
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return [ 'rw-dealer-portal' ];
	}

	public function get_keywords() {
		return [ 'contractor', 'list', 'pdf', 'print', 'dealer' ];
	}

	public function get_style_depends() {
		return [ 'rwdp-dealer-map' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Content', 'rw-dealer-portal-addons' ),
		] );

		$terms = get_terms( [
			'taxonomy'   => 'rw_dealer_type',
			'hide_empty' => false,
		] );

		$options = [ '' => __( 'All Dealer Types', 'rw-dealer-portal-addons' ) ];
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->slug ] = $term->name;
			}
		}

		$this->add_control( 'dealer_type_slug', [
			'label'   => __( 'Dealer Type Filter', 'rw-dealer-portal-addons' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $options,
			'default' => '',
		] );

		$this->add_control( 'show_header', [
			'label'        => __( 'Show Header (Logo + Address)', 'rw-dealer-portal-addons' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'rw-dealer-portal-addons' ),
			'label_off'    => __( 'Hide', 'rw-dealer-portal-addons' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_print_button', [
			'label'        => __( 'Show Print/PDF Button', 'rw-dealer-portal-addons' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'rw-dealer-portal-addons' ),
			'label_off'    => __( 'Hide', 'rw-dealer-portal-addons' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'print_button_text', [
			'label'     => __( 'Button Label', 'rw-dealer-portal-addons' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Download Contractor List', 'rw-dealer-portal-addons' ),
			'condition' => [ 'show_print_button' => 'yes' ],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$dealer_type = sanitize_title( $s['dealer_type_slug'] ?? '' );
		$rows = rwdp_get_contractor_rows( [ 'dealer_type' => $dealer_type ] );
		$settings = rwdp_get_contractor_list_settings();
		$labels = rwdp_get_contractor_list_column_labels();
		$columns = $settings['contractor_list_show_columns'];
		$logo_url = $settings['contractor_list_logo_id'] ? wp_get_attachment_image_url( $settings['contractor_list_logo_id'], 'full' ) : '';
		$print_url = rwdp_get_contractor_print_url( [], $dealer_type );
		?>
		<div class="rwdp-contractor-list-widget">
			<?php if ( ( $s['show_header'] ?? 'yes' ) === 'yes' ) : ?>
				<div class="rwdp-contractor-list-header">
					<div class="rwdp-contractor-list-header__logo">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $settings['contractor_list_address'] ) ) : ?>
						<div class="rwdp-contractor-list-header__address"><?php echo esc_html( $settings['contractor_list_address'] ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ( $s['show_print_button'] ?? 'yes' ) === 'yes' ) : ?>
				<p class="rwdp-contractor-list-actions">
					<a href="<?php echo esc_url( $print_url ); ?>" target="_blank" rel="noopener noreferrer" class="rwdp-contractor-list-download-all">
						<?php echo esc_html( $s['print_button_text'] ?: __( 'Download Contractor List', 'rw-dealer-portal-addons' ) ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( empty( $rows ) ) : ?>
				<p class="rwdp-contractor-list-empty"><?php esc_html_e( 'No contractors found for this filter.', 'rw-dealer-portal-addons' ); ?></p>
			<?php else : ?>
				<div class="rwdp-contractor-table-wrap">
					<table class="rwdp-contractor-table">
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
											<?php else : ?>
												<?php echo esc_html( $row[ $column ] ?? '' ); ?>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $settings['contractor_list_disclaimer'] ) ) : ?>
				<div class="rwdp-contractor-list-disclaimer">
					<?php echo esc_html( $settings['contractor_list_disclaimer'] ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
