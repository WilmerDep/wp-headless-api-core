<?php
/**
 * News REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\News;

use HeadlessApiCore\Core\Plugin;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class News_Controller {
	/**
	 * Default collection page size.
	 */
	const DEFAULT_PER_PAGE = 12;

	/**
	 * Maximum collection page size.
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Serializer instance.
	 *
	 * @var News_Serializer
	 */
	private $serializer;

	/**
	 * @param News_Serializer $serializer News serializer.
	 */
	public function __construct( News_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/**
	 * Register WordPress hooks for this controller.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register News REST routes.
	 *
	 * @return void
	 */
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

	/**
	 * Public read-only endpoints require no authentication.
	 *
	 * @return bool
	 */
	public function public_permission() {
		return true;
	}

	/**
	 * Return a paginated News collection.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ) {
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

	/**
	 * Return one published News item by slug.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$slug = (string) $request->get_param( 'slug' );
		$post = get_page_by_path( $slug, OBJECT, 'post' );

		if (
			! $post ||
			'publish' !== get_post_status( $post ) ||
			! empty( $post->post_password )
		) {
			return new WP_Error(
				'headless_core_news_not_found',
				__( 'News item not found.', 'wp-headless-api-core' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->serializer->detail( $post ), 200 );
	}

	/**
	 * Validate collection page.
	 *
	 * @param mixed $value Request value.
	 * @return bool
	 */
	public function validate_page( $value ) {
		return is_numeric( $value ) && (int) $value >= 1;
	}

	/**
	 * Validate collection page size.
	 *
	 * @param mixed $value Request value.
	 * @return bool
	 */
	public function validate_per_page( $value ) {
		return is_numeric( $value ) && (int) $value >= 1 && (int) $value <= self::MAX_PER_PAGE;
	}

	/**
	 * Validate collection sort direction.
	 *
	 * @param mixed $value Request value.
	 * @return bool
	 */
	public function validate_order( $value ) {
		return in_array( strtolower( (string) $value ), array( 'asc', 'desc' ), true );
	}

	/**
	 * Validate collection sort field.
	 *
	 * @param mixed $value Request value.
	 * @return bool
	 */
	public function validate_orderby( $value ) {
		return in_array( (string) $value, array( 'date', 'modified', 'title' ), true );
	}
}
