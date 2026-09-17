<?php
/**
 * Public Site Identity REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\SiteIdentity;

use HeadlessApiCore\Core\Plugin;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Site_Identity_Controller {
	/** @var Site_Identity_Serializer */
	private $serializer;

	public function __construct( Site_Identity_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/** Register the public site identity endpoint. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/site',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Return the current native WordPress site identity contract. */
	public function get() {
		$response = new WP_REST_Response( $this->serializer->serialize(), 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}
}
