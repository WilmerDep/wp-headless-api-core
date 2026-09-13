<?php
/**
 * Services grouping and featured-home controls.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Services_Features {
	const TAXONOMY = 'headless_service_group';
	const META_FEATURED = '_headless_service_featured';
	const META_FEATURED_ORDER = '_headless_service_featured_order';
	const NONCE_ACTION = 'headless_service_features_save';
	const NONCE_NAME = 'headless_service_features_nonce';

	/** Register taxonomy, metadata and editorial controls. */
	public function register() {
		add_action( 'init', array( $this, 'register_taxonomy' ), 11 );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes_' . Services_Post_Type::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Services_Post_Type::POST_TYPE, array( $this, 'save' ), 20, 2 );
		add_action( 'set_object_terms', array( $this, 'on_terms_changed' ), 20, 6 );
		add_filter( 'manage_' . Services_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ), 20 );
		add_action( 'manage_' . Services_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 20, 2 );
	}

	/** Register reusable service groups. */
	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			array( Services_Post_Type::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Grupos', 'wp-headless-api-core' ),
					'singular_name' => __( 'Grupo', 'wp-headless-api-core' ),
					'menu_name'     => __( 'Grupos', 'wp-headless-api-core' ),
					'all_items'     => __( 'Grupos', 'wp-headless-api-core' ),
					'edit_item'     => __( 'Editar grupo', 'wp-headless-api-core' ),
					'add_new_item'  => __( 'Añadir grupo', 'wp-headless-api-core' ),
					'new_item_name' => __( 'Nombre del grupo', 'wp-headless-api-core' ),
				),
				'public'            => false,
				'publicly_queryable' => false,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'show_tagcloud'     => false,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/** Register private feature metadata. */
	public function register_meta() {
		register_post_meta(
			Services_Post_Type::POST_TYPE,
			self::META_FEATURED,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);

		register_post_meta(
			Services_Post_Type::POST_TYPE,
			self::META_FEATURED_ORDER,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'default'           => 0,
				'sanitize_callback' => array( __CLASS__, 'sanitize_order' ),
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}

	public static function sanitize_order( $value ) {
		return max( 0, (int) $value );
	}

	public function add_meta_box() {
		add_meta_box(
			'headless-service-home-visibility',
			__( 'Visibilidad en inicio', 'wp-headless-api-core' ),
			array( $this, 'render_meta_box' ),
			Services_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render_meta_box( WP_Post $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$featured = (bool) get_post_meta( $post->ID, self::META_FEATURED, true );
		$order = self::sanitize_order( get_post_meta( $post->ID, self::META_FEATURED_ORDER, true ) );
		?>
		<p>
			<label>
				<input type="checkbox" name="headless_service_featured" value="1" <?php checked( $featured ); ?> />
				<strong><?php esc_html_e( 'Destacado en inicio', 'wp-headless-api-core' ); ?></strong>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Actívalo para que este servicio pueda aparecer en el bloque destacado del Home. No existe un límite rígido de ocho.', 'wp-headless-api-core' ); ?></p>
		<p>
			<label for="headless_service_featured_order"><strong><?php esc_html_e( 'Orden destacado', 'wp-headless-api-core' ); ?></strong></label>
			<input id="headless_service_featured_order" class="small-text" type="number" min="0" step="1" name="headless_service_featured_order" value="<?php echo esc_attr( $order ); ?>" />
		</p>
		<p class="description"><?php esc_html_e( 'También puedes ordenarlos visualmente desde Servicios → Ordenar → Destacados.', 'wp-headless-api-core' ); ?></p>
		<?php
	}

	public function save( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$featured = isset( $_POST['headless_service_featured'] ) ? 1 : 0;
		$order = isset( $_POST['headless_service_featured_order'] ) ? self::sanitize_order( wp_unslash( $_POST['headless_service_featured_order'] ) ) : 0;
		$old_featured = (int) get_post_meta( $post_id, self::META_FEATURED, true );
		$old_order = self::sanitize_order( get_post_meta( $post_id, self::META_FEATURED_ORDER, true ) );

		update_post_meta( $post_id, self::META_FEATURED, $featured );
		update_post_meta( $post_id, self::META_FEATURED_ORDER, $order );

		if ( 'publish' === $post->post_status && ( $old_featured !== $featured || $old_order !== $order ) ) {
			do_action( 'headless_api_core_services_collection_changed', 'features' );
		}
	}

	/** Trigger collection revalidation when service groups change. */
	public function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $terms, $tt_ids, $append, $old_tt_ids );
		if ( self::TAXONOMY !== $taxonomy ) {
			return;
		}
		$post = get_post( $object_id );
		if ( $post instanceof WP_Post && Services_Post_Type::POST_TYPE === $post->post_type && 'publish' === $post->post_status ) {
			do_action( 'headless_api_core_services_collection_changed', 'groups' );
		}
	}

	/** Add concise group/featured columns to the service list. */
	public function add_columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['services_groups'] = __( 'Grupos', 'wp-headless-api-core' );
				$out['services_featured'] = __( 'Inicio', 'wp-headless-api-core' );
			}
		}
		return $out;
	}

	public function render_column( $column, $post_id ) {
		if ( 'services_groups' === $column ) {
			$groups = self::groups_for_post( $post_id );
			echo empty( $groups ) ? '—' : esc_html( implode( ', ', wp_list_pluck( $groups, 'name' ) ) );
			return;
		}
		if ( 'services_featured' === $column ) {
			if ( (bool) get_post_meta( $post_id, self::META_FEATURED, true ) ) {
				echo '<span class="dashicons dashicons-star-filled" aria-hidden="true"></span> ' . esc_html__( 'Destacado', 'wp-headless-api-core' );
			} else {
				echo '—';
			}
		}
	}

	/** Return normalized public groups for one service. */
	public static function groups_for_post( $post_id ) {
		$terms = get_the_terms( (int) $post_id, self::TAXONOMY );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		usort(
			$terms,
			function ( $a, $b ) {
				return strcasecmp( (string) $a->name, (string) $b->name );
			}
		);

		return array_map(
			function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'slug' => sanitize_title( $term->slug ),
					'name' => sanitize_text_field( $term->name ),
				);
			},
			$terms
		);
	}
}
