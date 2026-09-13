<?php
/**
 * Services structured WordPress data model.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

defined( 'ABSPATH' ) || exit;

final class Services_Post_Type {
	const POST_TYPE = 'headless_service';

	const META_DESCRIPTION = '_headless_service_description';
	const META_AUDIENCE    = '_headless_service_audience';
	const META_DEPARTMENT  = '_headless_service_department';
	const META_REQUIREMENTS = '_headless_service_requirements';
	const META_PROCEDURE   = '_headless_service_procedure';
	const META_SCHEDULE    = '_headless_service_schedule';
	const META_COST        = '_headless_service_cost';
	const META_DURATION    = '_headless_service_duration';
	const META_CHANNEL     = '_headless_service_channel';
	const META_PHONE       = '_headless_service_phone';
	const META_EMAIL       = '_headless_service_email';
	const META_ADDRESS     = '_headless_service_address';
	const META_ALT         = '_headless_service_alt';
	const META_EXTERNAL_ID = '_headless_service_external_id';

	/** Register Services hooks. */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/** Register the editorial-only service CPT. */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Servicios', 'wp-headless-api-core' ),
					'singular_name'      => __( 'Servicio', 'wp-headless-api-core' ),
					'menu_name'          => __( 'Servicios', 'wp-headless-api-core' ),
					'all_items'          => __( 'Servicios', 'wp-headless-api-core' ),
					'add_new'            => __( 'Añadir servicio', 'wp-headless-api-core' ),
					'add_new_item'       => __( 'Añadir servicio', 'wp-headless-api-core' ),
					'edit_item'          => __( 'Editar servicio', 'wp-headless-api-core' ),
					'new_item'           => __( 'Nuevo servicio', 'wp-headless-api-core' ),
					'search_items'       => __( 'Buscar servicios', 'wp-headless-api-core' ),
					'not_found'          => __( 'No se encontraron servicios.', 'wp-headless-api-core' ),
					'not_found_in_trash' => __( 'No hay servicios en la papelera.', 'wp-headless-api-core' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-heart',
				'supports'            => array( 'title', 'thumbnail' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/** Register private service metadata. */
	public function register_meta() {
		$this->register_string_meta( self::META_DESCRIPTION, 'sanitize_textarea_field' );
		$this->register_string_meta( self::META_AUDIENCE, 'sanitize_textarea_field' );
		$this->register_string_meta( self::META_DEPARTMENT, 'sanitize_text_field' );
		$this->register_string_meta( self::META_PROCEDURE, 'sanitize_textarea_field' );
		$this->register_string_meta( self::META_SCHEDULE, 'sanitize_textarea_field' );
		$this->register_string_meta( self::META_COST, 'sanitize_text_field' );
		$this->register_string_meta( self::META_DURATION, 'sanitize_text_field' );
		$this->register_string_meta( self::META_CHANNEL, 'sanitize_text_field' );
		$this->register_string_meta( self::META_PHONE, 'sanitize_text_field' );
		$this->register_string_meta( self::META_EMAIL, array( __CLASS__, 'sanitize_email_value' ) );
		$this->register_string_meta( self::META_ADDRESS, 'sanitize_textarea_field' );
		$this->register_string_meta( self::META_ALT, 'sanitize_text_field' );
		$this->register_string_meta( self::META_EXTERNAL_ID, array( __CLASS__, 'sanitize_external_id' ) );

		register_post_meta(
			self::POST_TYPE,
			self::META_REQUIREMENTS,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_requirements' ),
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	/** Register one private string meta field. */
	private function register_string_meta( $key, $sanitize ) {
		register_post_meta(
			self::POST_TYPE,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => $sanitize,
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/** Sanitize public email. */
	public static function sanitize_email_value( $value ) {
		$email = sanitize_email( (string) $value );
		return is_email( $email ) ? $email : '';
	}

	/** Normalize private import identity. */
	public static function sanitize_external_id( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		$value = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );
		return substr( (string) $value, 0, 100 );
	}

	/** Sanitize and preserve ordered requirements. */
	public static function sanitize_requirements( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $item ) {
			$item = trim( sanitize_textarea_field( (string) $item ) );
			if ( '' !== $item ) {
				$clean[] = $item;
			}
		}

		return array_values( $clean );
	}
}
