<?php
/**
 * Hero REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

use HeadlessApiCore\Core\Plugin;
use HeadlessApiCore\Licensing\License_Gate;
use HeadlessApiCore\Licensing\License_Policy;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Hero_Controller {
	/** @var Hero_Serializer */
	private $serializer;

	/** @param Hero_Serializer $serializer Hero serializer. */
	public function __construct( Hero_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/** @return void */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'prevent_hero_http_cache' ), 10, 3 );
	}

	/** @return void */
	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/hero',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'public_permission' ),
			)
		);
	}

	/** @return bool */
	public function public_permission() {
		return true;
	}

	/** @param WP_REST_Request $request Current request. @return WP_REST_Response */
	public function get_items( WP_REST_Request $request ) {
		unset( $request );

		$restricted = $this->license_restriction_response();
		if ( null !== $restricted ) {
			return $restricted;
		}

		$query = new WP_Query(
			array(
				'post_type'           => Hero_Post_Type::POST_TYPE,
				'post_status'         => 'publish',
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'cache_results'       => false,
				'orderby'             => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$item = $this->serializer->item( $post );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	/** @return WP_REST_Response|null */
	private function license_restriction_response() {
		$decision = License_Gate::evaluate( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'hero' );
		if ( ! empty( $decision['allowed'] ) ) {
			return null;
		}

		$code = isset( $decision['code'] ) && is_string( $decision['code'] ) && '' !== $decision['code'] ? $decision['code'] : 'LICENSE_RESTRICTION';
		$messages = array(
			'LICENSE_RENEWAL_REQUIRED'      => 'This Headless API license requires renewal.',
			'LICENSE_REVALIDATION_REQUIRED' => 'This Headless API license must be revalidated.',
			'LICENSE_SUSPENDED'             => 'This Headless API license is suspended.',
			'LICENSE_REVOKED'               => 'This Headless API license has been revoked.',
			'ENTITLEMENT_REQUIRED'          => 'This license does not include the Hero module.',
			'LICENSE_VERIFICATION_REQUIRED' => 'This Headless API license could not be verified.',
		);

		$response = new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => isset( $messages[ $code ] ) ? $messages[ $code ] : 'The Hero Headless API is currently restricted by licensing policy.',
				'module'  => 'hero',
				'status'  => isset( $decision['status'] ) ? (string) $decision['status'] : 'untrusted',
			),
			403
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	/** Prevent stale Provider-side HTTP caching for the Hero route. */
	public function prevent_hero_http_cache( $response, $server, $request ) {
		unset( $server );
		if ( ! ( $request instanceof WP_REST_Request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}
		if ( '/' . Plugin::REST_NAMESPACE . '/hero' !== (string) $request->get_route() ) {
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
