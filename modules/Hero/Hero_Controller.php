<?php
/**
 * Hero REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

use HeadlessApiCore\Core\Plugin;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Hero_Controller {
	/**
	 * @var Hero_Serializer
	 */
	private $serializer;

	/**
	 * @param Hero_Serializer $serializer Hero serializer.
	 */
	public function __construct( Hero_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/**
	 * Register REST hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'prevent_hero_http_cache' ), 10, 3 );
	}

	/**
	 * Register the public Hero collection route.
	 *
	 * @return void
	 */
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

	/**
	 * Public read-only endpoint.
	 *
	 * @return bool
	 */
	public function public_permission() {
		return true;
	}

	/**
	 * Return all valid published Hero slides in deterministic order.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ) {
		unset( $request );

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

	/**
	 * Prevent stale Provider-side HTTP caching for the Hero route.
	 *
	 * Consumer caching belongs outside WordPress and can be invalidated through
	 * the integration layer without weakening the Provider source-of-truth.
	 *
	 * @param mixed           $response REST response.
	 * @param WP_REST_Server  $server   REST server.
	 * @param WP_REST_Request $request  Current request.
	 * @return mixed
	 */
	public function prevent_hero_http_cache( $response, $server, $request ) {
		unset( $server );

		if ( ! ( $request instanceof WP_REST_Request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( '/' . Plugin::REST_NAMESPACE . '/hero' !== $route ) {
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
