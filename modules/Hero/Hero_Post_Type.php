<?php
/**
 * Hero structured WordPress data model.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

defined( 'ABSPATH' ) || exit;

final class Hero_Post_Type {
	const POST_TYPE = 'headless_hero';

	const META_MOBILE_IMAGE_ID = '_headless_hero_mobile_image_id';
	const META_HREF            = '_headless_hero_href';
	const META_ALT             = '_headless_hero_alt';
	const META_OBJECT_POSITION = '_headless_hero_object_position';

	/**
	 * Register the post type and its structured metadata.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Register the editorial-only Hero CPT.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Hero Slides', 'wp-headless-api-core' ),
					'singular_name' => __( 'Hero Slide', 'wp-headless-api-core' ),
					'add_new_item'  => __( 'Add Hero Slide', 'wp-headless-api-core' ),
					'edit_item'     => __( 'Edit Hero Slide', 'wp-headless-api-core' ),
					'new_item'      => __( 'New Hero Slide', 'wp-headless-api-core' ),
					'view_item'     => __( 'View Hero Slide', 'wp-headless-api-core' ),
					'search_items'  => __( 'Search Hero Slides', 'wp-headless-api-core' ),
					'not_found'     => __( 'No Hero Slides found.', 'wp-headless-api-core' ),
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
				'menu_icon'           => 'dashicons-images-alt2',
				'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register reusable structured metadata without exposing raw post meta via
	 * the WordPress REST API.
	 *
	 * @return void
	 */
	public function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_MOBILE_IMAGE_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_HREF,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_href' ),
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_ALT,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_OBJECT_POSITION,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_object_position' ),
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	/**
	 * Restrict Hero meta mutation to users who can edit the owning post.
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
	 * Sanitize an optional Hero target while preserving headless-friendly
	 * relative paths and allowing only absolute HTTP(S) URLs.
	 *
	 * @param mixed $value Raw target.
	 * @return string
	 */
	public static function sanitize_href( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			return '';
		}

		if ( 0 === strpos( $value, '/' ) ) {
			if ( 0 === strpos( $value, '//' ) ) {
				return '';
			}

			$sanitized = esc_url_raw( $value, array( 'http', 'https' ) );
			return 0 === strpos( $sanitized, '/' ) ? $sanitized : '';
		}

		$scheme = strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * Sanitize a small, predictable subset of CSS object-position syntax.
	 *
	 * Accepted values contain one or two tokens. Each token may be one of the
	 * standard position keywords or a percentage from 0 through 100.
	 *
	 * @param mixed $value Raw object-position value.
	 * @return string
	 */
	public static function sanitize_object_position( $value ) {
		$value = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $value ) ) );

		if ( '' === $value ) {
			return '';
		}

		$tokens = explode( ' ', $value );
		if ( count( $tokens ) > 2 ) {
			return '';
		}

		$keywords = array( 'left', 'center', 'right', 'top', 'bottom' );

		foreach ( $tokens as $token ) {
			if ( in_array( $token, $keywords, true ) ) {
				continue;
			}

			if ( ! preg_match( '/^(\d{1,3}(?:\.\d+)?)%$/', $token, $matches ) ) {
				return '';
			}

			$percentage = (float) $matches[1];
			if ( $percentage < 0 || $percentage > 100 ) {
				return '';
			}
		}

		return implode( ' ', $tokens );
	}
}
