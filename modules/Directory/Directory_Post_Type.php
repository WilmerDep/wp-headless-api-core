<?php
/**
 * Directory structured WordPress data model.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

defined( 'ABSPATH' ) || exit;

final class Directory_Post_Type {
	const POST_TYPE = 'headless_person';
	const TAXONOMY  = 'headless_directory_group';

	const META_ROLE        = '_headless_directory_role';
	const META_JOINED_AT   = '_headless_directory_joined_at';
	const META_PHONE       = '_headless_directory_phone';
	const META_EMAIL       = '_headless_directory_email';
	const META_SUMMARY     = '_headless_directory_summary';
	const META_ALT         = '_headless_directory_alt';
	const META_EXTERNAL_ID = '_headless_directory_external_id';
	const META_GROUP_ORDER = '_headless_directory_group_order';

	const TERM_META_ORDER = '_headless_directory_order';

	/**
	 * Register model hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ), 5 );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Register the editorial-only person CPT.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Directorio', 'wp-headless-api-core' ),
					'singular_name'      => __( 'Persona', 'wp-headless-api-core' ),
					'menu_name'          => __( 'Directorio', 'wp-headless-api-core' ),
					'all_items'          => __( 'Personas', 'wp-headless-api-core' ),
					'add_new'            => __( 'Añadir persona', 'wp-headless-api-core' ),
					'add_new_item'       => __( 'Añadir persona', 'wp-headless-api-core' ),
					'edit_item'          => __( 'Editar persona', 'wp-headless-api-core' ),
					'new_item'           => __( 'Nueva persona', 'wp-headless-api-core' ),
					'search_items'       => __( 'Buscar personas', 'wp-headless-api-core' ),
					'not_found'          => __( 'No se encontraron personas.', 'wp-headless-api-core' ),
					'not_found_in_trash' => __( 'No hay personas en la papelera.', 'wp-headless-api-core' ),
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
				'menu_icon'           => 'dashicons-groups',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register reusable Directory groups.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Grupos', 'wp-headless-api-core' ),
					'singular_name' => __( 'Grupo', 'wp-headless-api-core' ),
					'menu_name'     => __( 'Grupos', 'wp-headless-api-core' ),
					'add_new_item'  => __( 'Añadir grupo', 'wp-headless-api-core' ),
					'edit_item'     => __( 'Editar grupo', 'wp-headless-api-core' ),
					'search_items'  => __( 'Buscar grupos', 'wp-headless-api-core' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_admin_column'  => false,
				'show_in_rest'       => false,
				'hierarchical'       => true,
				'rewrite'            => false,
				'query_var'          => false,
				'meta_box_cb'        => false,
			)
		);
	}

	/**
	 * Register person and group metadata without exposing raw meta in WP REST.
	 *
	 * @return void
	 */
	public function register_meta() {
		$this->register_string_meta( self::META_ROLE, 'sanitize_text_field' );
		$this->register_string_meta( self::META_JOINED_AT, array( __CLASS__, 'sanitize_date' ) );
		$this->register_string_meta( self::META_PHONE, 'sanitize_text_field' );
		$this->register_string_meta( self::META_EMAIL, array( __CLASS__, 'sanitize_email_value' ) );
		$this->register_string_meta( self::META_SUMMARY, 'sanitize_textarea_field' );
		$this->register_string_meta( self::META_ALT, 'sanitize_text_field' );
		$this->register_string_meta( self::META_EXTERNAL_ID, array( __CLASS__, 'sanitize_external_id' ) );

		register_post_meta(
			self::POST_TYPE,
			self::META_GROUP_ORDER,
			array(
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_group_order_map' ),
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			self::TERM_META_ORDER,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'can_manage_terms' ),
			)
		);
	}

	/**
	 * Register one private string meta field.
	 *
	 * @param string          $key      Meta key.
	 * @param callable|string $sanitize Sanitizer.
	 * @return void
	 */
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

	/**
	 * Restrict person meta mutation to users who can edit the owning post.
	 *
	 * @param bool   $allowed Existing authorization decision.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Restrict group ordering metadata to users who can manage categories.
	 *
	 * @return bool
	 */
	public function can_manage_terms() {
		return current_user_can( 'manage_categories' );
	}

	/**
	 * Strict YYYY-MM-DD sanitizer.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return '';
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
	}

	/**
	 * Sanitize a public email, returning empty when invalid.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_email_value( $value ) {
		$email = sanitize_email( (string) $value );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Normalize a private import identity key.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_external_id( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		$value = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );
		return substr( (string) $value, 0, 100 );
	}

	/**
	 * Sanitize per-group ordering metadata.
	 *
	 * @param mixed $value Raw map.
	 * @return array
	 */
	public static function sanitize_group_order_map( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $term_id => $position ) {
			$term_id = absint( $term_id );
			if ( $term_id <= 0 ) {
				continue;
			}
			$clean[ (string) $term_id ] = max( 0, (int) $position );
		}

		return $clean;
	}
}
