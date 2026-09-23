<?php
/**
 * Public Site Identity REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\SiteIdentity;

use HeadlessApiCore\Core\Plugin;
use HeadlessApiCore\Licensing\License_Gate;
use HeadlessApiCore\Licensing\License_Policy;
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
		$restricted = $this->license_restriction_response();
		if ( null !== $restricted ) {
			return $restricted;
		}

		$response = new WP_REST_Response( $this->serializer->serialize(), 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	/** Build an explicit non-cacheable licensing response for Site Identity. */
	private function license_restriction_response() {
		$decision = License_Gate::evaluate( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'site-identity' );
		if ( ! empty( $decision['allowed'] ) ) {
			return null;
		}

		$code = isset( $decision['code'] ) && is_string( $decision['code'] ) && '' !== $decision['code'] ? $decision['code'] : 'LICENSE_RESTRICTION';
		$messages = array(
			'LICENSE_RENEWAL_REQUIRED'      => 'This Headless API license requires renewal.',
			'LICENSE_REVALIDATION_REQUIRED' => 'This Headless API license must be revalidated.',
			'LICENSE_SUSPENDED'             => 'This Headless API license is suspended.',
			'LICENSE_REVOKED'               => 'This Headless API license has been revoked.',
			'ENTITLEMENT_REQUIRED'          => 'This license does not include the Site Identity module.',
			'LICENSE_VERIFICATION_REQUIRED' => 'This Headless API license could not be verified.',
		);

		$response = new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => isset( $messages[ $code ] ) ? $messages[ $code ] : 'The Site Identity Headless API is currently restricted by licensing policy.',
				'module'  => 'site-identity',
				'status'  => isset( $decision['status'] ) ? (string) $decision['status'] : 'untrusted',
			),
			403
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}
}
