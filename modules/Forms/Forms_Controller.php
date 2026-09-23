<?php
/**
 * Public Forms REST controller.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use HeadlessApiCore\Core\Plugin;
use HeadlessApiCore\Licensing\License_Gate;
use HeadlessApiCore\Licensing\License_Policy;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Forms_Controller {
	/** @var Forms_Serializer */
	private $serializer;

	/** @var Forms_Submission */
	private $submission;

	public function __construct( Forms_Serializer $serializer, Forms_Submission $submission ) {
		$this->serializer = $serializer;
		$this->submission = $submission;
	}

	/** Register public form schema and submission routes. */
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
				'args'                => array(
					'key' => array(
						'required'          => false,
						'sanitize_callback' => array( Forms_Identity::class, 'sanitize_key' ),
					),
				),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/forms/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'single' ),
				'permission_callback' => '__return_true',
				'args'                => $this->slug_args(),
			)
		);

		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/forms/(?P<slug>[a-z0-9-]+)/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => $this->slug_args(),
			)
		);
	}

	private function slug_args() {
		return array(
			'slug' => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_title',
			),
		);
	}

	/** Return published, enabled forms for discovery by headless consumers. */
	public function collection( WP_REST_Request $request ) {
		$restricted = $this->license_restriction_response( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'forms' );
		if ( null !== $restricted ) {
			return $restricted;
		}

		$key_param = $request->get_param( 'key' );
		$key       = null === $key_param ? '' : Forms_Identity::sanitize_key( $key_param );
		if ( null !== $key_param && '' === $key ) {
			return $this->response(
				array(
					'code'    => 'invalid_form_key',
					'message' => __( 'Invalid form key.', 'wp-headless-api-core' ),
				),
				400
			);
		}

		$meta_query = array(
			array(
				'key'     => Forms_Post_Type::META_ENABLED,
				'value'   => '1',
				'compare' => '=',
			),
		);
		if ( '' !== $key ) {
			$meta_query[] = array(
				'key'     => Forms_Identity::META_KEY,
				'value'   => $key,
				'compare' => '=',
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => Forms_Post_Type::FORM_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'meta_query'     => $meta_query,
			)
		);

		$items = array_map( array( $this->serializer, 'serialize' ), $posts );
		return $this->response( array( 'items' => array_values( $items ) ) );
	}

	/** Return one published + enabled form by slug. */
	public function single( WP_REST_Request $request ) {
		$restricted = $this->license_restriction_response( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'forms' );
		if ( null !== $restricted ) {
			return $restricted;
		}

		$post = $this->find_public_form( $request->get_param( 'slug' ) );
		if ( ! $post ) {
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

	/** Validate, sanitize and deliver one public submission. */
	public function submit( WP_REST_Request $request ) {
		// Transactional submissions stay available through ordinary expiration,
		// suspension and offline-tolerance exhaustion, but never for untrusted or
		// revoked licenses. Both Forms and Mail entitlements are required because
		// the submission pipeline delivers through Mail Core.
		$restricted = $this->license_restriction_response( License_Policy::CAPABILITY_CRITICAL_TRANSACTION, 'forms' );
		if ( null !== $restricted ) {
			return $restricted;
		}
		$restricted = $this->license_restriction_response( License_Policy::CAPABILITY_CRITICAL_TRANSACTION, 'mail' );
		if ( null !== $restricted ) {
			return $restricted;
		}

		$post = $this->find_public_form( $request->get_param( 'slug' ) );
		if ( ! $post ) {
			return $this->response(
				array( 'ok' => false, 'code' => 'form_not_found', 'message' => __( 'Form not found.', 'wp-headless-api-core' ) ),
				404
			);
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return $this->response(
				array( 'ok' => false, 'code' => 'invalid_payload', 'message' => __( 'The request body must be a JSON object.', 'wp-headless-api-core' ) ),
				400
			);
		}

		$client_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$result    = $this->submission->submit( $post, $payload, $client_ip );
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$data   = is_array( $data ) ? $data : array();
			$status = isset( $data['status'] ) ? absint( $data['status'] ) : 400;
			$body   = array(
				'ok'      => false,
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
			if ( ! empty( $data['fieldErrors'] ) && is_array( $data['fieldErrors'] ) ) {
				$body['fieldErrors'] = $data['fieldErrors'];
			}
			return $this->response( $body, $status );
		}

		if ( ! empty( $result['discarded'] ) ) {
			return $this->response(
				array( 'ok' => true, 'message' => (string) get_post_meta( $post->ID, Forms_Post_Type::META_SUCCESS_MESSAGE, true ) ),
				200
			);
		}

		return $this->response( $result, 200 );
	}

	/** Find one form that is safe for public discovery/submission. */
	private function find_public_form( $slug ) {
		$slug = sanitize_title( $slug );
		$post = get_page_by_path( $slug, OBJECT, Forms_Post_Type::FORM_POST_TYPE );
		if ( ! $post || 'publish' !== $post->post_status || ! (bool) get_post_meta( $post->ID, Forms_Post_Type::META_ENABLED, true ) ) {
			return null;
		}
		return $post;
	}

	/** Return a no-store licensing response or null when a capability is allowed. */
	private function license_restriction_response( $capability, $entitlement ) {
		$decision = License_Gate::evaluate( $capability, $entitlement );
		if ( ! empty( $decision['allowed'] ) ) {
			return null;
		}

		$code = isset( $decision['code'] ) && is_string( $decision['code'] ) && '' !== $decision['code'] ? $decision['code'] : 'LICENSE_RESTRICTION';
		$messages = array(
			'LICENSE_RENEWAL_REQUIRED'      => 'This Headless API license requires renewal.',
			'LICENSE_REVALIDATION_REQUIRED' => 'This Headless API license must be revalidated.',
			'LICENSE_SUSPENDED'             => 'This Headless API license is suspended.',
			'LICENSE_REVOKED'               => 'This Headless API license has been revoked.',
			'LICENSE_VERIFICATION_REQUIRED' => 'This Headless API license could not be verified.',
			'ENTITLEMENT_REQUIRED'          => 'This license does not include the required ' . $entitlement . ' capability.',
		);

		return $this->response(
			array(
				'ok'      => false,
				'code'    => $code,
				'message' => isset( $messages[ $code ] ) ? $messages[ $code ] : 'This form capability is currently restricted by licensing policy.',
				'module'  => (string) $entitlement,
				'status'  => isset( $decision['status'] ) ? (string) $decision['status'] : 'untrusted',
			),
			403
		);
	}

	/** Build a no-store REST response for live form capability/schema data. */
	private function response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}
}
