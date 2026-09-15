<?php
/**
 * Forms and reusable mail-template editorial entities.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

defined( 'ABSPATH' ) || exit;

final class Forms_Post_Type {
	const FORM_POST_TYPE     = 'headless_form';
	const TEMPLATE_POST_TYPE = 'headless_mail_template';

	const META_DESCRIPTION      = '_headless_form_description';
	const META_ENABLED          = '_headless_form_enabled';
	const META_SCHEMA_VERSION   = '_headless_form_schema_version';
	const META_SECTIONS         = '_headless_form_sections';
	const META_FIELDS           = '_headless_form_fields';
	const META_NOTIFICATIONS    = '_headless_form_notifications';
	const META_SUBMIT_LABEL     = '_headless_form_submit_label';
	const META_SUCCESS_MESSAGE  = '_headless_form_success_message';
	const META_ERROR_MESSAGE    = '_headless_form_error_message';
	const META_ANTI_SPAM        = '_headless_form_anti_spam';
	const META_RATE_LIMIT       = '_headless_form_rate_limit';

	const META_TEMPLATE_DESCRIPTION = '_headless_mail_template_description';
	const META_TEMPLATE_SUBJECT     = '_headless_mail_template_subject';
	const META_TEMPLATE_PREHEADER   = '_headless_mail_template_preheader';
	const META_TEMPLATE_MODE        = '_headless_mail_template_mode';
	const META_TEMPLATE_VISUAL      = '_headless_mail_template_visual_schema';
	const META_TEMPLATE_HTML        = '_headless_mail_template_html';
	const META_TEMPLATE_TEXT        = '_headless_mail_template_text_fallback';

	/** Register CPTs and private structured metadata. */
	public function register() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/** Register editorial-only post types. */
	public function register_post_types() {
		register_post_type(
			self::FORM_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Forms', 'wp-headless-api-core' ),
					'singular_name' => __( 'Form', 'wp-headless-api-core' ),
					'add_new_item'  => __( 'Add Form', 'wp-headless-api-core' ),
					'edit_item'     => __( 'Edit Form', 'wp-headless-api-core' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-feedback',
				'supports'            => array( 'title' ),
				'map_meta_cap'        => true,
			)
		);

		register_post_type(
			self::TEMPLATE_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Mail Templates', 'wp-headless-api-core' ),
					'singular_name' => __( 'Mail Template', 'wp-headless-api-core' ),
					'add_new_item'  => __( 'Add Mail Template', 'wp-headless-api-core' ),
					'edit_item'     => __( 'Edit Mail Template', 'wp-headless-api-core' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-email-alt2',
				'supports'            => array( 'title' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/** Register private metadata used by the Provider contract. */
	public function register_meta() {
		$this->register_string_meta( self::FORM_POST_TYPE, self::META_DESCRIPTION, 'sanitize_textarea_field', '' );
		$this->register_bool_meta( self::FORM_POST_TYPE, self::META_ENABLED, true );
		$this->register_int_meta( self::FORM_POST_TYPE, self::META_SCHEMA_VERSION, Forms_Schema::SCHEMA_VERSION );
		$this->register_json_meta( self::FORM_POST_TYPE, self::META_SECTIONS, array( Forms_Schema::class, 'normalize_sections' ), array() );
		$this->register_json_meta( self::FORM_POST_TYPE, self::META_FIELDS, array( Forms_Schema::class, 'normalize_fields' ), array() );
		$this->register_json_meta( self::FORM_POST_TYPE, self::META_NOTIFICATIONS, array( Forms_Schema::class, 'normalize_notifications' ), array() );
		$this->register_string_meta( self::FORM_POST_TYPE, self::META_SUBMIT_LABEL, 'sanitize_text_field', __( 'Submit', 'wp-headless-api-core' ) );
		$this->register_string_meta( self::FORM_POST_TYPE, self::META_SUCCESS_MESSAGE, 'sanitize_text_field', __( 'Your request was sent successfully.', 'wp-headless-api-core' ) );
		$this->register_string_meta( self::FORM_POST_TYPE, self::META_ERROR_MESSAGE, 'sanitize_text_field', __( 'We could not send your request. Please try again.', 'wp-headless-api-core' ) );
		$this->register_json_meta( self::FORM_POST_TYPE, self::META_ANTI_SPAM, array( Forms_Schema::class, 'normalize_anti_spam' ), Forms_Schema::normalize_anti_spam( array() ) );
		$this->register_json_meta( self::FORM_POST_TYPE, self::META_RATE_LIMIT, array( Forms_Schema::class, 'normalize_rate_limit' ), Forms_Schema::normalize_rate_limit( array() ) );

		$this->register_string_meta( self::TEMPLATE_POST_TYPE, self::META_TEMPLATE_DESCRIPTION, 'sanitize_textarea_field', '' );
		$this->register_string_meta( self::TEMPLATE_POST_TYPE, self::META_TEMPLATE_SUBJECT, 'sanitize_text_field', '' );
		$this->register_string_meta( self::TEMPLATE_POST_TYPE, self::META_TEMPLATE_PREHEADER, 'sanitize_text_field', '' );
		$this->register_string_meta( self::TEMPLATE_POST_TYPE, self::META_TEMPLATE_MODE, array( $this, 'sanitize_template_mode' ), 'visual' );
		$this->register_string_meta( self::TEMPLATE_POST_TYPE, self::META_TEMPLATE_VISUAL, array( $this, 'sanitize_visual_schema' ), '{}' );
		$this->register_string_meta( self::TEMPLATE_POST_TYPE, self::META_TEMPLATE_HTML, array( $this, 'sanitize_template_html' ), '' );
		$this->register_string_meta( self::TEMPLATE_POST_TYPE, self::META_TEMPLATE_TEXT, 'sanitize_textarea_field', '' );
	}

	private function register_string_meta( $post_type, $key, $sanitize_callback, $default ) {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => $default,
				'sanitize_callback' => $sanitize_callback,
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	private function register_bool_meta( $post_type, $key, $default ) {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => (bool) $default,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	private function register_int_meta( $post_type, $key, $default ) {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => absint( $default ),
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	private function register_json_meta( $post_type, $key, $normalizer, $default ) {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => wp_json_encode( $default ),
				'sanitize_callback' => static function ( $value ) use ( $normalizer ) {
					$normalized = call_user_func( $normalizer, $value );
					return wp_json_encode( $normalized );
				},
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	/** Restrict structured metadata to editors of the owning entity. */
	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/** Only visual and advanced HTML modes are supported. */
	public function sanitize_template_mode( $value ) {
		$value = sanitize_key( $value );
		return in_array( $value, array( 'visual', 'html' ), true ) ? $value : 'visual';
	}

	/** Persist a safe JSON visual schema. */
	public function sanitize_visual_schema( $value ) {
		$decoded = Forms_Schema::decode_json( $value, array() );
		return wp_json_encode( $decoded );
	}

	/** Sanitize administrator-authored email HTML. */
	public function sanitize_template_html( $value ) {
		return wp_kses_post( (string) $value );
	}
}
