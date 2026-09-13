<?php
/**
 * Directory drag-and-drop ordering persistence.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

defined( 'ABSPATH' ) || exit;

final class Directory_Order {
	const NONCE_ACTION = 'headless_directory_order';

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_headless_directory_save_person_order', array( $this, 'save_person_order' ) );
		add_action( 'wp_ajax_headless_directory_save_group_order', array( $this, 'save_group_order' ) );
	}

	/**
	 * Save global or per-group person order.
	 *
	 * @return void
	 */
	public function save_person_order() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para ordenar el directorio.', 'wp-headless-api-core' ) ), 403 );
		}

		$ids      = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		$group_id = isset( $_POST['groupId'] ) ? absint( wp_unslash( $_POST['groupId'] ) ) : 0;
		$ids      = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No se recibieron personas para ordenar.', 'wp-headless-api-core' ) ), 400 );
		}

		if ( $group_id > 0 ) {
			$term = get_term( $group_id, Directory_Post_Type::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				wp_send_json_error( array( 'message' => __( 'El grupo seleccionado no es válido.', 'wp-headless-api-core' ) ), 400 );
			}
		}

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || Directory_Post_Type::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'La lista contiene una persona no válida o sin permisos.', 'wp-headless-api-core' ) ), 400 );
			}

			if ( $group_id > 0 && ! has_term( $group_id, Directory_Post_Type::TAXONOMY, $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Una de las personas ya no pertenece al grupo seleccionado.', 'wp-headless-api-core' ) ), 409 );
			}
		}

		do_action( 'headless_api_core_directory_bulk_start', 'order' );

		foreach ( $ids as $index => $post_id ) {
			$position = (int) $index;

			if ( $group_id > 0 ) {
				$map                         = get_post_meta( $post_id, Directory_Post_Type::META_GROUP_ORDER, true );
				$map                         = Directory_Post_Type::sanitize_group_order_map( $map );
				$map[ (string) $group_id ] = $position;
				update_post_meta( $post_id, Directory_Post_Type::META_GROUP_ORDER, $map );
				continue;
			}

			wp_update_post(
				array(
					'ID'         => $post_id,
					'menu_order' => $position,
				)
			);
		}

		do_action( 'headless_api_core_directory_bulk_end', 'order' );
		do_action( 'headless_api_core_directory_collection_changed', 'order' );

		wp_send_json_success(
			array(
				'message' => __( 'Orden guardado.', 'wp-headless-api-core' ),
				'count'   => count( $ids ),
			)
		);
	}

	/**
	 * Save public group order.
	 *
	 * @return void
	 */
	public function save_group_order() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para ordenar grupos.', 'wp-headless-api-core' ) ), 403 );
		}

		$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No se recibieron grupos para ordenar.', 'wp-headless-api-core' ) ), 400 );
		}

		foreach ( $ids as $term_id ) {
			$term = get_term( $term_id, Directory_Post_Type::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				wp_send_json_error( array( 'message' => __( 'La lista contiene un grupo no válido.', 'wp-headless-api-core' ) ), 400 );
			}
		}

		do_action( 'headless_api_core_directory_bulk_start', 'group_order' );

		foreach ( $ids as $index => $term_id ) {
			update_term_meta( $term_id, Directory_Post_Type::TERM_META_ORDER, (int) $index );
		}

		do_action( 'headless_api_core_directory_bulk_end', 'group_order' );
		do_action( 'headless_api_core_directory_collection_changed', 'group_order' );

		wp_send_json_success(
			array(
				'message' => __( 'Orden de grupos guardado.', 'wp-headless-api-core' ),
				'count'   => count( $ids ),
			)
		);
	}
}
