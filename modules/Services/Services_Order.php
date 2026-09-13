<?php
/**
 * Services global drag-and-drop ordering workspace.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Services_Order {
	const NONCE_ACTION = 'headless_services_order';
	const PAGE_SLUG    = 'headless-services-order';

	/** Register admin workspace and AJAX handler. */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'wp_ajax_headless_services_save_order', array( $this, 'save_order' ) );
	}

	/** Register Services > Ordenar. */
	public function register_page() {
		add_submenu_page(
			'edit.php?post_type=' . Services_Post_Type::POST_TYPE,
			__( 'Ordenar servicios', 'wp-headless-api-core' ),
			__( 'Ordenar', 'wp-headless-api-core' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/** Render ordering workspace using the same visual system as Directory. */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ordenar servicios.', 'wp-headless-api-core' ) );
		}

		$query = new WP_Query(
			array(
				'post_type'           => Services_Post_Type::POST_TYPE,
				'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'cache_results'       => false,
				'orderby'             => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			)
		);
		?>
		<div class="wrap headless-directory-workspace">
			<h1><?php esc_html_e( 'Ordenar servicios', 'wp-headless-api-core' ); ?></h1>
			<p class="headless-directory-lead"><?php esc_html_e( 'Arrastra los servicios para definir el orden público. Los cambios se guardan automáticamente sin recargar la página.', 'wp-headless-api-core' ); ?></p>

			<div class="headless-directory-toolbar-card">
				<label><?php esc_html_e( 'Orden que estás editando', 'wp-headless-api-core' ); ?></label>
				<strong><?php esc_html_e( 'Orden global · Todos los servicios', 'wp-headless-api-core' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Este es el orden utilizado por la API pública y por el catálogo de servicios.', 'wp-headless-api-core' ); ?></p>
			</div>

			<div class="headless-directory-save-state" aria-live="polite"></div>

			<?php if ( empty( $query->posts ) ) : ?>
				<div class="headless-directory-empty-state"><?php esc_html_e( 'Aún no hay servicios para ordenar.', 'wp-headless-api-core' ); ?></div>
			<?php else : ?>
				<ul class="headless-directory-sortable headless-services-sortable" data-order-type="services">
					<?php foreach ( $query->posts as $post ) :
						$thumb      = get_the_post_thumbnail_url( $post, 'thumbnail' );
						$department = (string) get_post_meta( $post->ID, Services_Post_Type::META_DEPARTMENT, true );
						$status      = sanitize_key( $post->post_status );
						?>
						<li class="headless-directory-sortable-item" data-id="<?php echo esc_attr( (int) $post->ID ); ?>">
							<span class="dashicons dashicons-menu headless-directory-drag-handle" aria-hidden="true"></span>
							<div class="headless-directory-sortable-thumb">
								<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" /><?php else : ?><span class="dashicons dashicons-heart"></span><?php endif; ?>
							</div>
							<div class="headless-directory-sortable-copy">
								<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong>
								<?php if ( '' !== $department ) : ?><span><?php echo esc_html( $department ); ?></span><?php endif; ?>
								<small><?php echo esc_html( sprintf( __( 'Orden %d', 'wp-headless-api-core' ), (int) $post->menu_order ) ); ?></small>
							</div>
							<span class="headless-directory-status is-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Persist global service order. */
	public function save_order() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para ordenar servicios.', 'wp-headless-api-core' ) ), 403 );
		}

		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No se recibieron servicios para ordenar.', 'wp-headless-api-core' ) ), 400 );
		}

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || Services_Post_Type::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'La lista contiene un servicio no válido o sin permisos.', 'wp-headless-api-core' ) ), 400 );
			}
		}

		do_action( 'headless_api_core_services_bulk_start', 'order' );
		foreach ( $ids as $index => $post_id ) {
			wp_update_post( array( 'ID' => $post_id, 'menu_order' => (int) $index ) );
		}
		do_action( 'headless_api_core_services_bulk_end', 'order' );
		do_action( 'headless_api_core_services_collection_changed', 'order' );

		wp_send_json_success( array( 'message' => __( 'Orden guardado.', 'wp-headless-api-core' ), 'count' => count( $ids ) ) );
	}
}
