<?php

namespace AvailabilityBoard;

use WpApp\BaseApp;
use WpApp\WpApp;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class App extends BaseApp {
	const REST_NAMESPACE = 'availability-board/v1';

	public function __construct() {
		$this->app = new WpApp(
			$this->get_template_dir(),
			$this->get_url_path(),
			[
				'app_name'            => $this->get_plugin_name(),
				'app_name_textdomain' => 'availability-board',
				'require_capability'  => 'manage_woocommerce',
				'launcher'            => $this->get_plugin_name(),
			]
		);

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	protected function get_url_path(): string {
		return 'availability-board';
	}

	protected function get_template_dir(): string {
		return dirname( __DIR__ ) . '/templates';
	}

	protected function get_plugin_name(): string {
		if ( ! function_exists( 'get_file_data' ) ) {
			return 'Availability Board';
		}

		$plugin_data = get_file_data( dirname( __DIR__ ) . '/availability-board.php', [ 'name' => 'Plugin Name' ] );

		return $plugin_data['name'] ?: 'Availability Board';
	}

	protected function setup_database(): void {
		// No storage of our own: WooCommerce products are the only data source.
	}

	protected function setup_routes(): void {
		// The default '' route (index.php) is enough: this is a single board, not a multi-page app.
	}

	protected function setup_menu(): void {
		// Nothing beyond the single board view.
	}

	public function activate(): void {
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}

	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/products/(?P<id>\d+)/availability',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_set_availability' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => [
					'id'       => [
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
					],
					'in_stock' => [
						'required' => true,
						'type'     => 'boolean',
					],
				],
			]
		);
	}

	/**
	 * REST callback: set a product's stock status.
	 *
	 * The only write action this app performs, matching its single-purpose
	 * scope: toggling availability, not editing products.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_set_availability( WP_REST_Request $request ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'availability_board_no_woocommerce', __( 'WooCommerce is not active.', 'availability-board' ), [ 'status' => 503 ] );
		}

		$product_id = (int) $request['id'];
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'availability_board_not_found', __( 'Product not found.', 'availability-board' ), [ 'status' => 404 ] );
		}

		$in_stock = (bool) $request['in_stock'];
		$product->set_stock_status( $in_stock ? 'instock' : 'outofstock' );
		$product->save();

		return rest_ensure_response(
			[
				'id'          => $product->get_id(),
				'stock_status' => $product->get_stock_status(),
			]
		);
	}
}
