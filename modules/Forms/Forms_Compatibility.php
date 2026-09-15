<?php
/**
 * Forms Core compatibility and editorial UX migrations.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

defined( 'ABSPATH' ) || exit;

final class Forms_Compatibility {
	const MIGRATION_OPTION = 'headless_api_core_forms_json_unicode_v076';

	/** @var bool */
	private $repairing = false;

	/** Register compatibility hooks. */
	public function register() {
		add_action( 'admin_init', array( $this, 'repair_existing_meta_once' ) );
		add_action( 'added_post_meta', array( $this, 'repair_meta_after_write' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'repair_meta_after_write' ), 10, 4 );
		add_action( 'admin_enqueue_scripts', array( $this, 'editorial_labels' ), 30 );
	}

	/**
	 * WordPress unslashes metadata before storage. JSON created with the default
	 * wp_json_encode() may therefore lose the backslash in sequences such as
	 * \u00e9 or \u2014 and later render as u00e9 / u2014. Canonicalize Forms
	 * JSON with real UTF-8 while preserving the public schema.
	 */
	public function repair_meta_after_write( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		if ( $this->repairing || ! in_array( $meta_key, $this->json_meta_keys(), true ) ) {
			return;
		}
		$this->repair_one( (int) $post_id, $meta_key, $meta_value );
	}

	/** Repair already-imported metadata once after upgrading to the fixed build. */
	public function repair_existing_meta_once() {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => array( Forms_Post_Type::FORM_POST_TYPE, Forms_Post_Type::TEMPLATE_POST_TYPE ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $posts as $post_id ) {
			foreach ( $this->json_meta_keys() as $meta_key ) {
				$value = get_post_meta( $post_id, $meta_key, true );
				if ( is_string( $value ) && '' !== $value ) {
					$this->repair_one( (int) $post_id, $meta_key, $value );
				}
			}

		update_option( self::MIGRATION_OPTION, HEADLESS_API_CORE_VERSION, false );
	}

	/**
	 * Load a plain-language editorial layer on Forms screens.
	 *
	 * Internal schema keys stay stable for API compatibility, while the admin
	 * experience avoids implementation jargon that everyday editors should not
	 * need to understand.
	 */
	public function editorial_labels() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( Forms_Post_Type::FORM_POST_TYPE, Forms_Post_Type::TEMPLATE_POST_TYPE ), true ) ) {
			return;
		}
		if ( ! wp_script_is( 'headless-forms-admin', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'headless-forms-editorial-ux',
			plugins_url( 'assets/admin/forms-editorial-ux.js', HEADLESS_API_CORE_FILE ),
			array( 'headless-forms-admin' ),
			HEADLESS_API_CORE_VERSION,
			true
		);
	}

	/** Canonical JSON metadata keys owned by Forms Core. */
	private function json_meta_keys() {
		return array(
			Forms_Post_Type::META_SECTIONS,
			Forms_Post_Type::META_FIELDS,
			Forms_Post_Type::META_NOTIFICATIONS,
			Forms_Post_Type::META_ANTI_SPAM,
			Forms_Post_Type::META_RATE_LIMIT,
			Forms_Post_Type::META_TEMPLATE_VISUAL,
		);
	}

	/** Repair one JSON metadata value and rewrite only when canonical output differs. */
	private function repair_one( $post_id, $meta_key, $meta_value ) {
		if ( ! is_string( $meta_value ) || '' === trim( $meta_value ) ) {
			return;
		}

		$decoded = json_decode( $meta_value, true );
		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			return;
		}

		$decoded   = $this->repair_value( $decoded );
		$canonical = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $canonical ) || $canonical === $meta_value ) {
			return;
		}

		$this->repairing = true;
		update_post_meta( $post_id, $meta_key, wp_slash( $canonical ) );
		$this->repairing = false;
	}

	/**
	 * Recursively recover legacy uXXXX sequences left by an unslashed JSON
	 * write. Decode surrogate pairs first so supplementary Unicode remains
	 * valid, then decode ordinary four-hex escapes such as u00e9 and u2014.
	 */
	private function repair_value( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->repair_value( $item );
			}
			return $value;
		}
		if ( ! is_string( $value ) || ! preg_match( '/u[0-9a-fA-F]{4}/', $value ) ) {
			return $value;
		}

		$value = preg_replace_callback(
			'/u(d[89ab][0-9a-f]{2})u(d[cdef][0-9a-f]{2})/i',
			static function ( $matches ) {
				$decoded = json_decode( '"\\u' . strtolower( $matches[1] ) . '\\u' . strtolower( $matches[2] ) . '"' );
				return is_string( $decoded ) ? $decoded : $matches[0];
			},
			$value
		);

		return preg_replace_callback(
			'/u([0-9a-fA-F]{4})/',
			static function ( $matches ) {
				$decoded = json_decode( '"\\u' . strtolower( $matches[1] ) . '"' );
				return is_string( $decoded ) ? $decoded : $matches[0];
			},
			$value
		);
	}
}
