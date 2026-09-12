<?php
/**
 * Directory REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

use HeadlessApiCore\Core\Plugin;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Directory_Controller {
	/**
	 * @var Directory_Serializer
	 */
	private $serializer;

	/**
	 * @param Directory_Serializer $serializer Directory serializer.
	 */
	public function __construct( Directory_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/**
	 * Register REST hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'prevent_directory_http_cache' ), 10, 3 );
	}

	/**
	 * Register public read-only Directory routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/directory',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'group' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/directory/groups',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_groups' ),
				'permission_callback' => array( $this, 'public_permission' ),
			)
		);
	}

	/**
	 * Public read-only permission callback.
	 *
	 * @return bool
	 */
	public function public_permission() {
		return true;
	}

	/**
	 * Return valid published people, optionally scoped to one group.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ) {
		$group_slug = sanitize_title( (string) $request->get_param( 'group' ) );
		$group_id   = 0;
		$tax_query  = array();

		if ( '' !== $group_slug ) {
			$term = get_term_by( 'slug', $group_slug, Directory_Post_Type::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_REST_Response( array( 'items' => array() ), 200 );
			}

			$group_id  = (int) $term->term_id;
			$tax_query = array(
				array(
					'taxonomy' => Directory_Post_Type::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array( $group_id ),
				),
			);
		}

		$args = array(
			'post_type'           => Directory_Post_Type::POST_TYPE,
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

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		$query = new WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$item = $this->serializer->item( $post, $group_id );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['order'] !== $b['order'] ) {
					return $a['order'] <=> $b['order'];
				}
				return $a['id'] <=> $b['id'];
			}
		);

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	/**
	 * Return groups that have at least one currently public/valid person.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_groups( WP_REST_Request $request ) {
		unset( $request );

		$query = new WP_Query(
			array(
				'post_type'           => Directory_Post_Type::POST_TYPE,
				'post_status'         => 'publish',
				'has_password'        => false,
				'ignore_sticky_posts' => true,
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'cache_results'       => false,
				'orderby'             => array( 'ID' => 'ASC' ),
			)
		);

		$groups_by_id = array();
		foreach ( $query->posts as $post ) {
			$item = $this->serializer->item( $post );
			if ( null === $item ) {
				continue;
			}

			foreach ( $item['groups'] as $group ) {
				$groups_by_id[ (int) $group['id'] ] = $group;
			}
		}

		$groups = array_values( $groups_by_id );
		usort(
			$groups,
			static function ( $a, $b ) {
				if ( $a['order'] !== $b['order'] ) {
					return $a['order'] <=> $b['order'];
				}
				$name_compare = strcasecmp( $a['name'], $b['name'] );
				return 0 !== $name_compare ? $name_compare : ( $a['id'] <=> $b['id'] );
			}
		);

		return new WP_REST_Response( array( 'items' => $groups ), 200 );
	}

	/**
	 * Prevent stale Provider-side HTTP caching for Directory routes.
	 *
	 * @param mixed           $response REST response.
	 * @param WP_REST_Server  $server   REST server.
	 * @param WP_REST_Request $request  Current request.
	 * @return mixed
	 */
	public function prevent_directory_http_cache( $response, $server, $request ) {
		unset( $server );

		if ( ! ( $request instanceof WP_REST_Request ) || ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}

		$route  = (string) $request->get_route();
		$prefix = '/' . Plugin::REST_NAMESPACE . '/directory';
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
