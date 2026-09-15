<?php
/**
 * Mail capability REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Mail;

use HeadlessApiCore\Core\Plugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Mail_Controller {
	/** @var Mail_Settings */
	private $settings;

	public function __construct( Mail_Settings $settings ) {
		$this->settings = $settings;
	}

	/** Register REST hooks. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'prevent_http_cache' ), 10, 3 );
	}

	/** Register read-only capability route for headless consumers. */
	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/mail/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'public_permission' ),
			)
		);
	}

	/** Public capability metadata contains no credentials or connection secrets. */
	public function public_permission() {
		return true;
	}

	/** Return safe mail capability status. */
	public function get_status() {
		return new WP_REST_Response( $this->settings->public_status(), 200 );
	}

	/** Prevent stale capability state from being cached by providers/proxies. */
	public function prevent_http_cache( $response, $server, $request ) {
		unset( $server );
		if ( ! ( $request instanceof WP_REST_Request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( '/' . Plugin::REST_NAMESPACE . '/mail/status' !== $route ) {
			return $response;
		}

		if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Expires', '0' );
		}

		return $response;
	}
}
