<?php
/**
 * Stable semantic identity for reusable Forms Core contracts.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Forms_Identity {
	const META_KEY         = '_headless_form_key';
	const NONCE_ACTION     = 'headless_form_identity_save';
	const NONCE_NAME       = 'headless_form_identity_nonce';
	const MIGRATION_OPTION = 'headless_api_core_forms_semantic_key_v0713';
	const NOTICE_PREFIX    = 'headless_api_core_form_key_notice_';

	/** Register persistence, migration and editorial UI hooks. */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'init', array( $this, 'backfill_existing_keys_once' ), 30 );
		add_action( 'add_meta_boxes_' . Forms_Post_Type::FORM_POST_TYPE, array( $this, 'add_meta_box' ), 20 );
		add_action( 'save_post_' . Forms_Post_Type::FORM_POST_TYPE, array( $this, 'save' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/** Register the semantic key as private Provider-owned metadata. */
	public function register_meta() {
		register_post_meta(
			Forms_Post_Type::FORM_POST_TYPE,
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_key' ),
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	/** Restrict identity metadata to users who can edit the owning form. */
	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Normalize a semantic form key as lowercase kebab-case.
	 *
	 * The key is intentionally independent from WordPress post_title/post_name.
	 */
	public static function sanitize_key( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		$value = strtolower( remove_accents( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
		$value = trim( (string) $value, '-' );
		if ( '' === $value || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value ) ) {
			return '';
		}
		return substr( $value, 0, 80 );
	}

	/** Return the persisted semantic key without deriving it again from title. */
	public static function get_key( WP_Post $post ) {
		return self::sanitize_key( get_post_meta( $post->ID, self::META_KEY, true ) );
	}

	/** Build the one-time initial key for a form that does not have one yet. */
	public static function initial_key( WP_Post $post ) {
		$base = self::sanitize_key( $post->post_name );
		if ( '' === $base ) {
			$base = self::sanitize_key( $post->post_title );
		}
		if ( '' === $base ) {
			$base = 'form-' . absint( $post->ID );
		}
		return $base;
	}

	/** Persist an initial key exactly once, selecting a unique fallback if needed. */
	public static function ensure_persisted_key( WP_Post $post ) {
		$current = self::get_key( $post );
		if ( '' !== $current ) {
			return $current;
		}

		$base = self::initial_key( $post );
		$key  = $base;
		$i    = 2;
		while ( self::key_in_use( $key, (int) $post->ID, false ) ) {
			$key = self::sanitize_key( $base . '-' . $i );
			++$i;
		}
		update_post_meta( $post->ID, self::META_KEY, $key );
		return $key;
	}

	/** True when no other active form currently exposes this semantic key. */
	public static function is_active_key_available( $key, $exclude_post_id = 0 ) {
		$key = self::sanitize_key( $key );
		if ( '' === $key ) {
			return false;
		}
		return ! self::key_in_use( $key, (int) $exclude_post_id, true );
	}

	/** Add the identity box without mixing it into the dynamic field schema. */
	public function add_meta_box() {
		add_meta_box(
			'headless-form-identity',
			__( 'Identidad del formulario', 'wp-headless-api-core' ),
			array( $this, 'render' ),
			Forms_Post_Type::FORM_POST_TYPE,
			'side',
			'high'
		);
	}

	/** Render the stable semantic key next to the installation-specific slug. */
	public function render( WP_Post $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$key  = self::get_key( $post );
		$slug = sanitize_title( $post->post_name );
		if ( '' === $key && '' !== $slug ) {
			$key = self::initial_key( $post );
		}
		?>
		<p>
			<label for="headless-form-key"><strong><?php esc_html_e( 'Key semántico', 'wp-headless-api-core' ); ?></strong></label>
			<input id="headless-form-key" class="widefat" type="text" name="headless_form_key" value="<?php echo esc_attr( $key ); ?>" placeholder="appointment-request" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" autocomplete="off">
		</p>
		<p class="description"><?php esc_html_e( 'Identifica el propósito del formulario entre sitios. Usa minúsculas y guiones. Si se genera automáticamente, queda guardado y no cambia al editar el título o el slug.', 'wp-headless-api-core' ); ?></p>
		<p>
			<label for="headless-form-resource-slug"><strong><?php esc_html_e( 'Slug de esta instalación', 'wp-headless-api-core' ); ?></strong></label>
			<input id="headless-form-resource-slug" class="widefat" type="text" value="<?php echo esc_attr( $slug ); ?>" readonly>
		</p>
		<p class="description"><?php esc_html_e( 'El slug sigue siendo la ruta canónica de api.schema y api.submit. Cambiar el key puede requerir actualizar Consumers que lo usen.', 'wp-headless-api-core' ); ?></p>
		<?php
	}

	/** Persist explicit identity edits while preserving active-key uniqueness. */
	public function save( $post_id, WP_Post $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$existing = self::get_key( $post );
		$has_ui   = isset( $_POST[ self::NONCE_NAME ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION );
		$raw      = $has_ui && isset( $_POST['headless_form_key'] ) ? trim( (string) wp_unslash( $_POST['headless_form_key'] ) ) : '';
		$key      = $has_ui && '' !== $raw ? self::sanitize_key( $raw ) : $existing;

		if ( $has_ui && '' !== $raw && '' === $key ) {
			$key = '' !== $existing ? $existing : self::initial_key( $post );
			$this->set_notice( __( 'El key semántico no era válido. Se conservó una identidad válida para el formulario.', 'wp-headless-api-core' ), 'error' );
		}
		if ( '' === $key ) {
			$key = self::ensure_persisted_key( $post );
		}

		$is_active = 'publish' === $post->post_status && (bool) get_post_meta( $post_id, Forms_Post_Type::META_ENABLED, true );
		if ( $is_active && ! self::is_active_key_available( $key, $post_id ) ) {
			if ( '' !== $existing && $existing !== $key && self::is_active_key_available( $existing, $post_id ) ) {
				$key = $existing;
				$this->set_notice( __( 'Ese key semántico ya pertenece a otro formulario activo. Se conservó el key anterior.', 'wp-headless-api-core' ), 'error' );
			} else {
				update_post_meta( $post_id, self::META_KEY, $key );
				update_post_meta( $post_id, Forms_Post_Type::META_ENABLED, false );
				$this->set_notice( __( 'Ese key semántico ya pertenece a otro formulario activo. El formulario se guardó deshabilitado para mantener la identidad pública única.', 'wp-headless-api-core' ), 'error' );
				return;
			}
		}

		update_post_meta( $post_id, self::META_KEY, $key );
	}

	/** Backfill legacy forms once from their already-persisted slug, never from a moving title afterward. */
	public function backfill_existing_keys_once() {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}
		$posts = get_posts(
			array(
				'post_type'      => Forms_Post_Type::FORM_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				self::ensure_persisted_key( $post );
			}
		}
		update_option( self::MIGRATION_OPTION, HEADLESS_API_CORE_VERSION, false );
	}

	/** Show one short persisted identity warning after WordPress redirects. */
	public function admin_notice() {
		$key    = self::NOTICE_PREFIX . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$class = isset( $notice['type'] ) && 'error' === $notice['type'] ? 'notice-error' : 'notice-warning';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $notice['message'] ) );
	}

	/** Query identity collisions across active or all non-trashed forms. */
	private static function key_in_use( $key, $exclude_post_id, $active_only ) {
		$meta_query = array(
			array(
				'key'     => self::META_KEY,
				'value'   => $key,
				'compare' => '=',
			),
		);
		if ( $active_only ) {
			$meta_query[] = array(
				'key'     => Forms_Post_Type::META_ENABLED,
				'value'   => '1',
				'compare' => '=',
			);
		}
		$args = array(
			'post_type'      => Forms_Post_Type::FORM_POST_TYPE,
			'post_status'    => $active_only ? 'publish' : array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => $meta_query,
		);
		if ( $exclude_post_id > 0 ) {
			$args['post__not_in'] = array( $exclude_post_id );
		}
		return ! empty( get_posts( $args ) );
	}

	private function set_notice( $message, $type ) {
		set_transient(
			self::NOTICE_PREFIX . get_current_user_id(),
			array( 'message' => sanitize_text_field( $message ), 'type' => sanitize_key( $type ) ),
			60
		);
	}
}
