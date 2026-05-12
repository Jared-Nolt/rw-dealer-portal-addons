<?php
namespace RW_Dealer_Portal_Addons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {

	private $repo_owner  = 'Jared-Nolt';
	private $repo_name   = 'rw-dealer-portal-addons';
	private $plugin_file = 'rw-dealer-portal-addons.php';
	private $plugin_name = 'RW Dealer Portal Addons';
	private $core_plugin = 'rw-dealer-portal/rw-dealer-portal.php';

	public $plugin_slug;
	public $version;
	public $cache_key;
	public $cache_allowed;

	private $auth_token;
	private $basename;

	public function __construct() {
		if ( defined( 'RWDPA_DEV_MODE' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
			add_filter( 'https_local_ssl_verify', '__return_false' );
			add_filter( 'http_request_host_is_external', '__return_true' );
		}

		$this->basename      = plugin_basename( RWDPA_PLUGIN_DIR . $this->plugin_file );
		$this->plugin_slug   = dirname( $this->basename );
		$this->version       = RWDPA_VERSION;
		$this->cache_key     = 'rwdpa_github_updater';
		$this->cache_allowed = true;
		$this->auth_token    = defined( 'RWDPA_GITHUB_TOKEN' ) ? RWDPA_GITHUB_TOKEN : '';

		add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'update' ] );
		add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
		add_filter( 'http_request_args', [ $this, 'maybe_authenticate_download' ], 10, 2 );
		add_filter( "plugin_action_links_{$this->basename}", [ $this, 'add_check_link' ] );
		add_filter( 'plugin_row_meta', [ $this, 'add_core_update_note' ], 10, 4 );
		add_action( 'admin_init', [ $this, 'process_manual_check' ] );
	}

	private function request() {
		$remote = get_transient( $this->cache_key );

		if ( false === $remote || ! $this->cache_allowed ) {
			$headers = [
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			];

			if ( $this->auth_token ) {
				$headers['Authorization'] = 'token ' . $this->auth_token;
			}

			$remote = wp_remote_get(
				'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases/latest',
				[
					'timeout' => 10,
					'headers' => $headers,
				]
			);

			if ( is_wp_error( $remote ) || 200 !== wp_remote_retrieve_response_code( $remote ) || empty( wp_remote_retrieve_body( $remote ) ) ) {
				return false;
			}

			set_transient( $this->cache_key, $remote, 6 * HOUR_IN_SECONDS );
		}

		return json_decode( wp_remote_retrieve_body( $remote ) );
	}

	public function info( $response, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $response;
		}

		if ( empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $response;
		}

		$remote = $this->request();
		if ( ! $remote ) {
			return $response;
		}

		$remote_version = isset( $remote->tag_name ) ? ltrim( $remote->tag_name, 'vV' ) : $this->version;
		$zip_url        = isset( $remote->zipball_url ) ? $remote->zipball_url : '';

		if ( $this->auth_token && $zip_url ) {
			$zip_url = add_query_arg( 'access_token', $this->auth_token, $zip_url );
		}

		$response                = new \stdClass();
		$response->name          = $this->plugin_name;
		$response->slug          = $this->plugin_slug;
		$response->version       = $remote_version;
		$response->author        = $this->repo_owner;
		$response->homepage      = 'https://github.com/' . $this->repo_owner . '/' . $this->repo_name;
		$response->download_link = $zip_url;
		$response->trunk         = $zip_url;
		$response->last_updated  = isset( $remote->published_at ) ? $remote->published_at : '';
		$response->sections      = [
			'description'  => $this->plugin_name . ' - auto-updates from GitHub releases.',
			'installation' => 'Install as a standard WordPress plugin.',
			'changelog'    => isset( $remote->body ) ? $remote->body : '',
		];

		return $response;
	}

	public function update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->request();
		if ( $remote && isset( $remote->tag_name ) ) {
			$remote_version = ltrim( $remote->tag_name, 'vV' );
			if ( version_compare( $this->version, $remote_version, '<' ) ) {
				$obj              = new \stdClass();
				$obj->slug        = $this->plugin_slug;
				$obj->plugin      = $this->basename;
				$obj->new_version = $remote_version;
				$obj->package     = $remote->zipball_url;

				if ( $this->auth_token ) {
					$obj->package = add_query_arg( 'access_token', $this->auth_token, $obj->package );
				}

				$transient->response[ $obj->plugin ] = $obj;
			}
		}

		return $transient;
	}

	public function purge( $upgrader, $options ) {
		if ( $this->cache_allowed && 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
			delete_transient( $this->cache_key );
		}
	}

	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $response;
		}

		$target = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug;
		$wp_filesystem->move( $result['destination'], $target, true );
		$result['destination'] = $target;

		if ( is_plugin_active( $this->basename ) ) {
			activate_plugin( $this->basename );
		}

		return $result;
	}

	public function maybe_authenticate_download( $args, $url ) {
		if ( ! $this->auth_token ) {
			return $args;
		}

		$is_github = strpos( $url, 'github.com' ) !== false || strpos( $url, 'api.github.com' ) !== false;
		if ( ! $is_github ) {
			return $args;
		}

		if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}

		$args['headers']['Authorization'] = 'token ' . $this->auth_token;
		$args['headers']['User-Agent']    = $args['headers']['User-Agent'] ?? 'WordPress/' . get_bloginfo( 'version' );

		return $args;
	}

	public function add_check_link( $links ) {
		$check_url = add_query_arg(
			[
				'rwdpa_gh_check' => $this->plugin_slug,
				'nonce'          => wp_create_nonce( 'rwdpa_gh_check' ),
			],
			admin_url( 'plugins.php' )
		);

		$links['rwdpa_gh_check'] = '<a href="' . esc_url( $check_url ) . '">' . esc_html__( 'Check for updates', 'rw-dealer-portal-addons' ) . '</a>';

		return $links;
	}

	public function add_core_update_note( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		unset( $plugin_data, $status );

		if ( $plugin_file !== $this->basename ) {
			return $plugin_meta;
		}

		$updates = get_site_transient( 'update_plugins' );
		$core    = is_object( $updates ) && ! empty( $updates->response[ $this->core_plugin ] ) ? $updates->response[ $this->core_plugin ] : null;
		if ( ! $core ) {
			return $plugin_meta;
		}

		$core_version = ! empty( $core->new_version ) ? $core->new_version : esc_html__( 'a newer version', 'rw-dealer-portal-addons' );
		$plugin_meta[] = '<span style="color:#d63638; font-weight:600;">' . sprintf(
			esc_html__( 'RW Dealer Portal core update available: %s. Update the core plugin to keep add-ons in sync.', 'rw-dealer-portal-addons' ),
			esc_html( $core_version )
		) . '</span>';

		return $plugin_meta;
	}

	public function process_manual_check() {
		$check = isset( $_GET['rwdpa_gh_check'] ) ? sanitize_text_field( wp_unslash( $_GET['rwdpa_gh_check'] ) ) : '';
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( $check !== $this->plugin_slug ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) || ! wp_verify_nonce( $nonce, 'rwdpa_gh_check' ) ) {
			return;
		}

		delete_site_transient( 'update_plugins' );
		delete_transient( $this->cache_key );
		wp_safe_redirect( admin_url( 'plugins.php?rwdpa_gh_checked=1' ) );
		exit;
	}
}
