<?php
/**
 * Services REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use HeadlessApiCore\Core\Plugin;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Services_Controller {
	/** @var Services_Serializer */
	private $serializer;

	public function __construct( Services_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/** Register REST hooks. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'prevent_services_http_cache' ), 10, 3 );
	}

	/** Register public read-only routes. */
	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/services',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'search' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/services/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);
	}

	public function public_permission() {
		return true;
	}

	/** Return published services. */
	public function get_items( WP_REST_Request $request ) {
		$args = array(
			'post_type'           => Services_Post_Type::POST_TYPE,
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
		);

		$search = trim( sanitize_text_field( (string) $request->get_param( 'search' ) ) );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->serializer->item( $post );
		}

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	/** Return one published service by slug. */
	public function get_item( WP_REST_Request $request ) {
		$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		if ( '' === $slug ) {
			return new WP_REST_Response( array( 'item' => null ), 404 );
		}

		$query = new WP_Query(
			array(
				'post_type'           => Services_Post_Type::POST_TYPE,
				'post_status'         => 'publish',
				'name'                => $slug,
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'posts_per_page'      => 1,
				'no_found_rows'       => true,
				'cache_results'       => false,
			)
		);

		if ( empty( $query->posts ) ) {
			return new WP_REST_Response( array( 'item' => null ), 404 );
		}

		return new WP_REST_Response( array( 'item' => $this->serializer->item( $query->posts[0] ) ), 200 );
	}

	/** Prevent stale Provider-side HTTP caching. */
	public function prevent_services_http_cache( $response, $server, $request ) {
		unset( $server );
		if ( ! ( $request instanceof WP_REST_Request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}

		$route  = (string) $request->get_route();
		$prefix = '/' . Plugin::REST_NAMESPACE . '/services';
		if ( 0 !== strpos( $route, $prefix ) ) {
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
