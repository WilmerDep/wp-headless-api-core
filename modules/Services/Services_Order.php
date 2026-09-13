<?php
/**
 * Services drag-and-drop ordering workspace.
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

	/** Render global or featured ordering workspace. */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ordenar servicios.', 'wp-headless-api-core' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all';
		if ( ! in_array( $view, array( 'all', 'featured' ), true ) ) {
			$view = 'all';
		}

		$args = array(
			'post_type'           => Services_Post_Type::POST_TYPE,
			'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'      => -1,
			'no_found_rows'       => true,
			'cache_results'       => false,
			'orderby'             => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
		);

		if ( 'featured' === $view ) {
			$args['meta_query'] = array(
				array(
					'key'     => Services_Features::META_FEATURED,
					'value'   => '1',
					'compare' => '=',
				),
			);
			$args['meta_key'] = Services_Features::META_FEATURED_ORDER;
			$args['orderby'] = array(
				'meta_value_num' => 'ASC',
				'menu_order'     => 'ASC',
				'ID'             => 'ASC',
			);
		}

		$query = new WP_Query( $args );
		$base_url = admin_url( 'edit.php?post_type=' . Services_Post_Type::POST_TYPE . '&page=' . self::PAGE_SLUG );
		?>
		<div class="wrap headless-directory-workspace">
			<h1><?php esc_html_e( 'Ordenar servicios', 'wp-headless-api-core' ); ?></h1>
			<p class="headless-directory-lead"><?php esc_html_e( 'Arrastra los servicios para definir el orden público. Los cambios se guardan automáticamente sin recargar la página.', 'wp-headless-api-core' ); ?></p>

			<nav class="nav-tab-wrapper" style="margin-bottom:18px">
				<a class="nav-tab <?php echo 'all' === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&view=all' ); ?>"><?php esc_html_e( 'Todos los servicios', 'wp-headless-api-core' ); ?></a>
				<a class="nav-tab <?php echo 'featured' === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base_url . '&view=featured' ); ?>"><?php esc_html_e( 'Destacados', 'wp-headless-api-core' ); ?></a>
			</nav>

			<div class="headless-directory-toolbar-card">
				<label><?php esc_html_e( 'Orden que estás editando', 'wp-headless-api-core' ); ?></label>
				<?php if ( 'featured' === $view ) : ?>
					<strong><?php esc_html_e( 'Orden destacado · Home', 'wp-headless-api-core' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Solo aparecen los servicios marcados como “Destacado en inicio”. Este orden es independiente del catálogo general.', 'wp-headless-api-core' ); ?></p>
				<?php else : ?>
					<strong><?php esc_html_e( 'Orden global · Todos los servicios', 'wp-headless-api-core' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Este es el orden utilizado por la API pública y por el catálogo de servicios.', 'wp-headless-api-core' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="headless-directory-save-state" aria-live="polite"></div>

			<?php if ( empty( $query->posts ) ) : ?>
				<div class="headless-directory-empty-state"><?php echo esc_html( 'featured' === $view ? __( 'Aún no hay servicios destacados para ordenar.', 'wp-headless-api-core' ) : __( 'Aún no hay servicios para ordenar.', 'wp-headless-api-core' ) ); ?></div>
			<?php else : ?>
				<ul class="headless-directory-sortable headless-services-sortable" data-order-type="<?php echo esc_attr( $view ); ?>">
					<?php foreach ( $query->posts as $post ) :
						$thumb      = get_the_post_thumbnail_url( $post, 'thumbnail' );
						$department = (string) get_post_meta( $post->ID, Services_Post_Type::META_DEPARTMENT, true );
						$status      = sanitize_key( $post->post_status );
						$current_order = 'featured' === $view ? Services_Features::sanitize_order( get_post_meta( $post->ID, Services_Features::META_FEATURED_ORDER, true ) ) : (int) $post->menu_order;
						?>
						<li class="headless-directory-sortable-item" data-id="<?php echo esc_attr( (int) $post->ID ); ?>">
							<span class="dashicons dashicons-menu headless-directory-drag-handle" aria-hidden="true"></span>
							<div class="headless-directory-sortable-thumb">
								<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" /><?php else : ?><span class="dashicons dashicons-heart"></span><?php endif; ?>
							</div>
							<div class="headless-directory-sortable-copy">
								<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong>
								<?php if ( '' !== $department ) : ?><span><?php echo esc_html( $department ); ?></span><?php endif; ?>
								<small><?php echo esc_html( sprintf( __( 'Orden %d', 'wp-headless-api-core' ), $current_order ) ); ?></small>
							</div>
							<?php if ( 'featured' === $view ) : ?><span class="headless-directory-status is-publish"><?php esc_html_e( 'Destacado', 'wp-headless-api-core' ); ?></span><?php else : ?><span class="headless-directory-status is-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></span><?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Persist global or featured service order. */
	public function save_order() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para ordenar servicios.', 'wp-headless-api-core' ) ), 403 );
		}

		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$order_type = isset( $_POST['orderType'] ) ? sanitize_key( wp_unslash( $_POST['orderType'] ) ) : 'all';
		if ( ! in_array( $order_type, array( 'all', 'featured' ), true ) ) {
			$order_type = 'all';
		}
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No se recibieron servicios para ordenar.', 'wp-headless-api-core' ) ), 400 );
		}

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || Services_Post_Type::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'La lista contiene un servicio no válido o sin permisos.', 'wp-headless-api-core' ) ), 400 );
			}
			if ( 'featured' === $order_type && ! (bool) get_post_meta( $post_id, Services_Features::META_FEATURED, true ) ) {
				wp_send_json_error( array( 'message' => __( 'La lista contiene un servicio que ya no está destacado.', 'wp-headless-api-core' ) ), 409 );
			}
		}

		do_action( 'headless_api_core_services_bulk_start', 'order' );
		foreach ( $ids as $index => $post_id ) {
			if ( 'featured' === $order_type ) {
				update_post_meta( $post_id, Services_Features::META_FEATURED_ORDER, (int) $index );
			} else {
				wp_update_post( array( 'ID' => $post_id, 'menu_order' => (int) $index ) );
			}
		}
		do_action( 'headless_api_core_services_bulk_end', 'order' );
		do_action( 'headless_api_core_services_collection_changed', 'order' );

		wp_send_json_success( array( 'message' => __( 'Orden guardado.', 'wp-headless-api-core' ), 'count' => count( $ids ), 'orderType' => $order_type ) );
	}
}
