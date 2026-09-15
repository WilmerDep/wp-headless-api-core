<?php
/**
 * Public Forms REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use HeadlessApiCore\Core\Plugin;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Forms_Controller {
	/** @var Forms_Serializer */
	private $serializer;

	public function __construct( Forms_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/** Register public read-only form-schema routes. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'collection' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/forms/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'single' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);
	}

	/** Return published, enabled forms for discovery by headless consumers. */
	public function collection() {
		$posts = get_posts(
			array(
				'post_type'      => Forms_Post_Type::FORM_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'meta_query'     => array(
					array(
						'key'     => Forms_Post_Type::META_ENABLED,
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		$items = array_map( array( $this->serializer, 'serialize' ), $posts );
		return $this->response( array( 'items' => array_values( $items ) ) );
	}

	/** Return one published + enabled form by slug. */
	public function single( WP_REST_Request $request ) {
		$slug = sanitize_title( $request->get_param( 'slug' ) );
		$post = get_page_by_path( $slug, OBJECT, Forms_Post_Type::FORM_POST_TYPE );

		if ( ! $post || 'publish' !== $post->post_status || ! (bool) get_post_meta( $post->ID, Forms_Post_Type::META_ENABLED, true ) ) {
			return $this->response(
				array(
					'code'    => 'form_not_found',
					'message' => __( 'Form not found.', 'wp-headless-api-core' ),
				),
				404
			);
		}

		return $this->response( array( 'item' => $this->serializer->serialize( $post ) ) );
	}

	/** Build a no-store REST response for live form capability/schema data. */
	private function response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}
}
