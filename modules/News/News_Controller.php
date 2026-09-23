<?php
/**
 * News REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\News;

use HeadlessApiCore\Core\Plugin;
use HeadlessApiCore\Licensing\License_Gate;
use HeadlessApiCore\Licensing\License_Policy;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class News_Controller {
	/** Default collection page size. */
	const DEFAULT_PER_PAGE = 12;

	/** Maximum collection page size. */
	const MAX_PER_PAGE = 50;

	/** @var News_Serializer */
	private $serializer;

	/** @param News_Serializer $serializer News serializer. */
	public function __construct( News_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/** Register WordPress hooks for this controller. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'prevent_news_http_cache' ), 10, 3 );
	}

	/** Register News REST routes. */
	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/news',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => array( $this, 'validate_page' ),
					),
					'per_page' => array(
						'default'           => self::DEFAULT_PER_PAGE,
						'sanitize_callback' => 'absint',
						'validate_callback' => array( $this, 'validate_per_page' ),
					),
					'order'    => array(
						'default'           => 'desc',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( $this, 'validate_order' ),
					),
					'orderby'  => array(
						'default'           => 'date',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( $this, 'validate_orderby' ),
					),
				),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/news/(?P<slug>[^/]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);
	}

	/** Public read-only endpoints require no authentication. */
	public function public_permission() {
		return true;
	}

	/** Keep the Provider REST surface authoritative and non-cacheable. */
	public function prevent_news_http_cache( $response, $server, $request ) {
		unset( $server );

		if ( ! ( $request instanceof WP_REST_Request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}

		$route  = (string) $request->get_route();
		$prefix = '/' . Plugin::REST_NAMESPACE . '/news';
		if ( $route !== $prefix && 0 !== strpos( $route, $prefix . '/' ) ) {
			return $response;
		}

		if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Expires', '0' );
		}

		return $response;
	}

	/** Return a paginated News collection. */
	public function get_items( WP_REST_Request $request ) {
		$restricted = $this->license_restriction_response();
		if ( null !== $restricted ) {
			return $restricted;
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$order    = strtoupper( (string) $request->get_param( 'order' ) );
		$orderby  = (string) $request->get_param( 'orderby' );

		$query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'paged'               => $page,
				'posts_per_page'      => $per_page,
				'order'               => $order,
				'orderby'             => $orderby,
				'no_found_rows'       => false,
				'cache_results'       => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->serializer->summary( $post );
		}

		return new WP_REST_Response(
			array(
				'items'      => $items,
				'pagination' => array(
					'page'       => $page,
					'perPage'    => $per_page,
					'totalItems' => (int) $query->found_posts,
					'totalPages' => (int) $query->max_num_pages,
				),
			),
			200
		);
	}

	/** Return one published News item by slug. */
	public function get_item( WP_REST_Request $request ) {
		$restricted = $this->license_restriction_response();
		if ( null !== $restricted ) {
			return $restricted;
		}

		$slug = (string) $request->get_param( 'slug' );
		$query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'name'                => $slug,
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'posts_per_page'      => 1,
				'no_found_rows'       => true,
				'cache_results'       => false,
			)
		);

		$post = ! empty( $query->posts ) ? $query->posts[0] : null;
		if ( ! $post || 'publish' !== get_post_status( $post ) || ! empty( $post->post_password ) ) {
			return new WP_Error(
				'headless_core_news_not_found',
				__( 'News item not found.', 'wp-headless-api-core' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->serializer->detail( $post ), 200 );
	}

	/** Return an explicit non-cacheable licensing response when News is restricted. */
	private function license_restriction_response() {
		$decision = License_Gate::evaluate( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'news' );
		if ( ! empty( $decision['allowed'] ) ) {
			return null;
		}

		$code = isset( $decision['code'] ) && is_string( $decision['code'] ) && '' !== $decision['code']
			? $decision['code']
			: 'LICENSE_RESTRICTION';

		$messages = array(
			'LICENSE_RENEWAL_REQUIRED'      => 'This Headless API license requires renewal.',
			'LICENSE_REVALIDATION_REQUIRED' => 'This Headless API license must be revalidated.',
			'LICENSE_SUSPENDED'             => 'This Headless API license is suspended.',
			'LICENSE_REVOKED'               => 'This Headless API license has been revoked.',
			'ENTITLEMENT_REQUIRED'          => 'This license does not include the News module.',
			'LICENSE_VERIFICATION_REQUIRED' => 'This Headless API license could not be verified.',
		);

		$message = isset( $messages[ $code ] ) ? $messages[ $code ] : 'The News Headless API is currently restricted by licensing policy.';
		$response = new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
				'module'  => 'news',
				'status'  => isset( $decision['status'] ) ? (string) $decision['status'] : 'untrusted',
			),
			403
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	public function validate_page( $value ) {
		return is_numeric( $value ) && (int) $value >= 1;
	}

	public function validate_per_page( $value ) {
		return is_numeric( $value ) && (int) $value >= 1 && (int) $value <= self::MAX_PER_PAGE;
	}

	public function validate_order( $value ) {
		return in_array( strtolower( (string) $value ), array( 'asc', 'desc' ), true );
	}

	public function validate_orderby( $value ) {
		return in_array( (string) $value, array( 'date', 'modified' ), true );
	}
}
