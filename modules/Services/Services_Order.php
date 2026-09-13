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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
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

	/** Load sortable only on ordering screen. */
	public function enqueue( $hook ) {
		if ( 'headless_service_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/** Render ordering workspace. */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ordenar servicios.', 'wp-headless-api-core' ) );
		}

		$query = new WP_Query(
			array(
				'post_type'      => Services_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'  => true,
			)
		);
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ordenar servicios', 'wp-headless-api-core' ); ?></h1>
			<p><?php esc_html_e( 'Arrastra los servicios para definir el orden público del catálogo.', 'wp-headless-api-core' ); ?></p>
			<style>
				.hac-services-order{max-width:860px;margin-top:18px}.hac-services-order__list{margin:0}.hac-services-order__item{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin:0 0 8px;box-shadow:0 1px 1px rgba(0,0,0,.03)}.hac-services-order__handle{cursor:move;color:#646970}.hac-services-order__title{font-weight:600;flex:1}.hac-services-order__status{font-size:12px;color:#646970}.hac-services-order__notice{margin:14px 0 0;min-height:22px}.hac-services-order__item.ui-sortable-helper{box-shadow:0 8px 24px rgba(0,0,0,.12)}
			</style>
			<div class="hac-services-order">
				<ul class="hac-services-order__list" id="hac-services-order-list">
					<?php foreach ( $query->posts as $post ) : ?>
						<li class="hac-services-order__item" data-id="<?php echo esc_attr( (string) $post->ID ); ?>">
							<span class="dashicons dashicons-menu hac-services-order__handle" aria-hidden="true"></span>
							<span class="hac-services-order__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
							<span class="hac-services-order__status"><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<button type="button" class="button button-primary" id="hac-services-save-order"><?php esc_html_e( 'Guardar orden', 'wp-headless-api-core' ); ?></button>
				<div class="hac-services-order__notice" id="hac-services-order-notice" aria-live="polite"></div>
			</div>
		</div>
		<script>
		jQuery(function($){
			var $list=$('#hac-services-order-list'),$button=$('#hac-services-save-order'),$notice=$('#hac-services-order-notice');
			$list.sortable({handle:'.hac-services-order__handle',axis:'y'});
			$button.on('click',function(){
				var ids=$list.children().map(function(){return $(this).data('id');}).get();
				$button.prop('disabled',true);$notice.text('<?php echo esc_js( __( 'Guardando…', 'wp-headless-api-core' ) ); ?>');
				$.post(ajaxurl,{action:'headless_services_save_order',nonce:'<?php echo esc_js( $nonce ); ?>',ids:ids}).done(function(response){
					$notice.text(response&&response.success?'<?php echo esc_js( __( 'Orden guardado.', 'wp-headless-api-core' ) ); ?>':(response&&response.data&&response.data.message?response.data.message:'<?php echo esc_js( __( 'No se pudo guardar el orden.', 'wp-headless-api-core' ) ); ?>'));
				}).fail(function(){ $notice.text('<?php echo esc_js( __( 'No se pudo guardar el orden.', 'wp-headless-api-core' ) ); ?>'); }).always(function(){ $button.prop('disabled',false); });
			});
		});
		</script>
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
