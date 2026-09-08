<?php
/**
 * Health REST endpoint.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Rest;

use HeadlessApiCore\Core\Plugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Health_Controller {
	/**
	 * Register WordPress hooks for this controller.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'public_permission' ),
			)
		);
	}

	/**
	 * Public permission callback for the read-only health endpoint.
	 *
	 * @return bool
	 */
	public function public_permission() {
		return true;
	}

	/**
	 * Return service health information.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_health( WP_REST_Request $request ) {
		unset( $request );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'service' => 'Headless API Core',
				'version' => HEADLESS_API_CORE_VERSION,
			),
			200
		);
	}
}
