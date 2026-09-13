<?php
/**
 * Directory import batch guard.
 *
 * Owns the final write phase for CSV imports so a failed row cannot leave a
 * partially-created person and existing Media Library files are reused before
 * any remote download is attempted.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

use WP_Error;

 defined( 'ABSPATH' ) || exit;

final class Directory_Import_Batch {
	/**
	 * Register before Directory_Importer::import_batch().
	 *
	 * wp_send_json_* terminates the AJAX request, so the legacy priority-10
	 * handler never runs once this priority-1 handler has completed.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_headless_directory_import_batch', array( $this, 'import_batch' ), 1 );
	}

	/**
	 * Import one validated bounded batch.
	 *
	 * @return void
	 */
	public function import_batch() {
		check_ajax_referer( Directory_Importer::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para importar personas.', 'wp-headless-api-core' ) ), 403 );
		}

		$token           = isset( $_POST['token'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) wp_unslash( $_POST['token'] ) ) ) : '';
		$offset          = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
		$batch_size      = isset( $_POST['batchSize'] ) ? min( 25, max( 1, absint( wp_unslash( $_POST['batchSize'] ) ) ) ) : 10;
		$mode            = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create_only';
		$create_groups   = ! empty( $_POST['createGroups'] ) && current_user_can( 'manage_categories' );
		$download_images = ! empty( $_POST['importImages'] ) && current_user_can( 'upload_files' );

		if ( ! in_array( $mode, array( 'create_only', 'upsert' ), true ) ) {
			$mode = 'create_only';
		}

		$data = get_transient( $this->transient_key( $token ) );
		if ( ! is_array( $data ) || empty( $data['rows'] ) || ! is_array( $data['rows'] ) ) {
			wp_send_json_error( array( 'message' => __( 'La vista previa expiró. Vuelve a validar el archivo.', 'wp-headless-api-core' ) ), 410 );
		}

		$rows  = $data['rows'];
		$total = count( $rows );
		$slice = array_slice( $rows, $offset, $batch_size );

		$result = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'details' => array(),
		);

		do_action( 'headless_api_core_directory_bulk_start', 'import' );

		foreach ( $slice as $row ) {
			if ( ! empty( $row['errors'] ) ) {
				++$result['skipped'];
				$result['details'][] = array(
					'row'     => (int) $row['row'],
					'status'  => 'skipped',
					'message' => implode( ' ', $row['errors'] ),
				);
				continue;
			}

			$outcome = $this->import_row( $row, $mode, $create_groups, $download_images );
			$status  = isset( $outcome['status'] ) ? $outcome['status'] : 'failed';

			if ( isset( $result[ $status ] ) ) {
				++$result[ $status ];
			} else {
				++$result['failed'];
			}

			$result['details'][] = array(
				'row'     => (int) $row['row'],
				'status'  => $status,
				'message' => isset( $outcome['message'] ) ? $outcome['message'] : '',
			);
		}

		do_action( 'headless_api_core_directory_bulk_end', 'import' );
		do_action( 'headless_api_core_directory_collection_changed', 'import' );

		$next_offset = min( $total, $offset + count( $slice ) );
		$done        = $next_offset >= $total;

		if ( $done ) {
			delete_transient( $this->transient_key( $token ) );
		}

		wp_send_json_success(
			array(
				'offset'      => $next_offset,
				'total'       => $total,
				'done'        => $done,
				'batchResult' => $result,
			)
		);
	}

	/**
	 * Create or update one person without leaving a failed partial record.
	 *
	 * Unknown groups are resolved before the person is written. If a later hard
	 * failure occurs, a newly-created person is removed and an existing person is
	 * restored from its snapshot.
	 *
	 * @param array  $row             Normalized row.
	 * @param string $mode            create_only|upsert.
	 * @param bool   $create_groups   Whether missing groups may be created.
	 * @param bool   $download_images Whether a missing media file may be downloaded.
	 * @return array
	 */
	private function import_row( array $row, $mode, $create_groups, $download_images ) {
		$existing_id = '' !== $row['external_id'] ? $this->find_by_external_id( $row['external_id'] ) : 0;

		if ( $existing_id && 'create_only' === $mode ) {
			return array( 'status' => 'skipped', 'message' => __( 'Ya existe el external_id; modo crear solamente.', 'wp-headless-api-core' ) );
		}

		// Resolve every group before touching the person. This prevents the exact
		// partial-record failure found during real CMS QA.
		$term_ids = $this->resolve_groups( $row['groups'], $create_groups );
		if ( is_wp_error( $term_ids ) ) {
			return array( 'status' => 'failed', 'message' => $term_ids->get_error_message() );
		}

		$snapshot = $existing_id && 'upsert' === $mode ? $this->snapshot_person( $existing_id ) : null;
		$postarr  = array(
			'post_type'   => Directory_Post_Type::POST_TYPE,
			'post_title'  => $row['name'],
			'post_status' => $row['status'],
			'menu_order'  => (int) $row['order'],
		);

		if ( $existing_id && 'upsert' === $mode ) {
			$postarr['ID'] = $existing_id;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
			$action        = 'updated';
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return array( 'status' => 'failed', 'message' => $post_id->get_error_message() );
		}

		update_post_meta( $post_id, Directory_Post_Type::META_ROLE, $row['role'] );
		update_post_meta( $post_id, Directory_Post_Type::META_JOINED_AT, $row['joined_at'] );
		update_post_meta( $post_id, Directory_Post_Type::META_PHONE, $row['phone'] );
		update_post_meta( $post_id, Directory_Post_Type::META_EMAIL, $row['email'] );
		update_post_meta( $post_id, Directory_Post_Type::META_SUMMARY, $row['summary'] );
		update_post_meta( $post_id, Directory_Post_Type::META_ALT, $row['image_alt'] );
		if ( '' !== $row['external_id'] ) {
			update_post_meta( $post_id, Directory_Post_Type::META_EXTERNAL_ID, $row['external_id'] );
		}

		$assigned = wp_set_object_terms( $post_id, $term_ids, Directory_Post_Type::TAXONOMY, false );
		if ( is_wp_error( $assigned ) ) {
			$this->rollback_person( $post_id, $action, $snapshot );
			return array( 'status' => 'failed', 'message' => $assigned->get_error_message() );
		}

		$image_note = '';
		if ( '' !== $row['image_url'] ) {
			$image = $this->resolve_portrait( $row['image_url'], $post_id, $row['name'], $download_images );
			if ( is_wp_error( $image ) ) {
				$image_note = ' ' . sprintf( __( 'Imagen omitida: %s', 'wp-headless-api-core' ), $image->get_error_message() );
			} elseif ( ! empty( $image['id'] ) ) {
				$image_id = (int) $image['id'];
				set_post_thumbnail( $post_id, $image_id );
				if ( '' !== $row['image_alt'] ) {
					update_post_meta( $image_id, '_wp_attachment_image_alt', $row['image_alt'] );
				}
				$image_note = 'existing' === $image['source']
					? ' ' . __( 'Retrato reutilizado desde Medios.', 'wp-headless-api-core' )
					: ' ' . __( 'Retrato descargado e importado.', 'wp-headless-api-core' );
			}
		}

		return array(
			'status'  => $action,
			'message' => sprintf(
				__( 'Persona %s correctamente.%s', 'wp-headless-api-core' ),
				'created' === $action ? __( 'creada', 'wp-headless-api-core' ) : __( 'actualizada', 'wp-headless-api-core' ),
				$image_note
			),
		);
	}

	/**
	 * Resolve group labels to IDs before writing the person.
	 *
	 * @param array $groups        Group labels.
	 * @param bool  $create_groups Whether creation is allowed.
	 * @return array|WP_Error
	 */
	private function resolve_groups( array $groups, $create_groups ) {
		$ids = array();

		foreach ( $groups as $label ) {
			$term = get_term_by( 'slug', sanitize_title( $label ), Directory_Post_Type::TAXONOMY );
			if ( ! $term ) {
				$term = get_term_by( 'name', $label, Directory_Post_Type::TAXONOMY );
			}

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
				continue;
			}

			if ( ! $create_groups ) {
				return new WP_Error(
					'headless_directory_unknown_group',
					sprintf( __( 'El grupo "%s" no existe.', 'wp-headless-api-core' ), $label )
				);
			}

			$created = wp_insert_term( $label, Directory_Post_Type::TAXONOMY );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$ids[] = (int) $created['term_id'];
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Reuse an existing attachment by upload-relative path before downloading.
	 *
	 * @param string $url             Source image URL from CSV.
	 * @param int    $post_id         Person post ID.
	 * @param string $desc            Attachment description.
	 * @param bool   $download_images Whether remote sideload is allowed as fallback.
	 * @return array|WP_Error
	 */
	private function resolve_portrait( $url, $post_id, $desc, $download_images ) {
		$existing_id = $this->find_existing_attachment( $url );
		if ( $existing_id > 0 ) {
			return array( 'id' => $existing_id, 'source' => 'existing' );
		}

		if ( ! $download_images ) {
			return new WP_Error(
				'headless_directory_image_not_found',
				__( 'No se encontró la imagen en Medios y la descarga está desactivada.', 'wp-headless-api-core' )
			);
		}

		$image_id = $this->sideload_image( $url, $post_id, $desc );
		if ( is_wp_error( $image_id ) ) {
			return $image_id;
		}

		return array( 'id' => (int) $image_id, 'source' => 'downloaded' );
	}

	/**
	 * Find an attachment using the upload-relative file path.
	 *
	 * This intentionally ignores the URL host. A cloned WordPress may move from
	 * example.org to cms.example.org while retaining the same _wp_attached_file.
	 *
	 * @param string $url Image URL.
	 * @return int
	 */
	private function find_existing_attachment( $url ) {
		$relative = $this->upload_relative_path( $url );
		if ( '' !== $relative ) {
			$attachments = get_posts(
				array(
					'post_type'        => 'attachment',
					'post_status'      => 'inherit',
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'meta_query'       => array(
						array(
							'key'   => '_wp_attached_file',
							'value' => $relative,
						),
					),
				)
			);

			if ( ! empty( $attachments ) ) {
				return (int) $attachments[0];
			}
		}

		// Useful for same-host URLs and custom upload setups.
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$fallback = (int) attachment_url_to_postid( $url );
			if ( $fallback > 0 ) {
				return $fallback;
			}
		}

		return 0;
	}

	/**
	 * Extract the value WordPress stores in _wp_attached_file from an image URL.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	private function upload_relative_path( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$path = rawurldecode( $path );
		$upload = wp_upload_dir( null, false );
		$base_path = isset( $upload['baseurl'] ) ? wp_parse_url( $upload['baseurl'], PHP_URL_PATH ) : '';
		$markers = array_filter(
			array(
				is_string( $base_path ) ? trailingslashit( $base_path ) : '',
				'/wp-content/uploads/',
			)
		);

		foreach ( array_unique( $markers ) as $marker ) {
			$position = strpos( $path, $marker );
			if ( false !== $position ) {
				return ltrim( substr( $path, $position + strlen( $marker ) ), '/' );
			}
		}

		return '';
	}

	/**
	 * Safely sideload a remote image only when reuse was not possible.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Person post ID.
	 * @param string $desc    Attachment description.
	 * @return int|WP_Error
	 */
	private function sideload_image( $url, $post_id, $desc ) {
		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'headless_directory_image_url', __( 'URL remota no permitida.', 'wp-headless-api-core' ) );
		}

		$head = wp_safe_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
			)
		);
		if ( is_wp_error( $head ) ) {
			return $head;
		}

		$length = (int) wp_remote_retrieve_header( $head, 'content-length' );
		$type   = strtolower( (string) wp_remote_retrieve_header( $head, 'content-type' ) );
		if ( $length > Directory_Importer::MAX_IMAGE_BYTES ) {
			return new WP_Error( 'headless_directory_image_size', __( 'La imagen remota supera 6 MB.', 'wp-headless-api-core' ) );
		}
		if ( '' !== $type && 0 !== strpos( $type, 'image/' ) ) {
			return new WP_Error( 'headless_directory_image_type', __( 'La URL remota no apunta a una imagen.', 'wp-headless-api-core' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$id = media_sideload_image( $url, $post_id, $desc, 'id' );
		return is_wp_error( $id ) ? $id : (int) $id;
	}

	/**
	 * Snapshot an existing record so a hard post-write failure can be reverted.
	 *
	 * @param int $post_id Person post ID.
	 * @return array
	 */
	private function snapshot_person( $post_id ) {
		$post = get_post( $post_id );
		$terms = wp_get_object_terms( $post_id, Directory_Post_Type::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$meta_keys = array(
			Directory_Post_Type::META_ROLE,
			Directory_Post_Type::META_JOINED_AT,
			Directory_Post_Type::META_PHONE,
			Directory_Post_Type::META_EMAIL,
			Directory_Post_Type::META_SUMMARY,
			Directory_Post_Type::META_ALT,
			Directory_Post_Type::META_EXTERNAL_ID,
		);
		$meta = array();
		foreach ( $meta_keys as $key ) {
			$meta[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return array(
			'post'      => $post,
			'meta'      => $meta,
			'terms'     => array_map( 'intval', (array) $terms ),
			'thumbnail' => (int) get_post_thumbnail_id( $post_id ),
		);
	}

	/**
	 * Remove a failed new row or restore an existing row snapshot.
	 *
	 * @param int        $post_id  Person post ID.
	 * @param string     $action   created|updated.
	 * @param array|null $snapshot Existing record snapshot.
	 * @return void
	 */
	private function rollback_person( $post_id, $action, $snapshot ) {
		if ( 'created' === $action ) {
			wp_delete_post( $post_id, true );
			return;
		}

		if ( ! is_array( $snapshot ) || empty( $snapshot['post'] ) ) {
			return;
		}

		$old = $snapshot['post'];
		wp_update_post(
			wp_slash(
				array(
					'ID'          => $post_id,
					'post_title'  => $old->post_title,
					'post_status' => $old->post_status,
					'menu_order'  => (int) $old->menu_order,
				)
			)
		);

		foreach ( $snapshot['meta'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		wp_set_object_terms( $post_id, $snapshot['terms'], Directory_Post_Type::TAXONOMY, false );

		if ( ! empty( $snapshot['thumbnail'] ) ) {
			set_post_thumbnail( $post_id, (int) $snapshot['thumbnail'] );
		} else {
			delete_post_thumbnail( $post_id );
		}
	}

	/**
	 * Find an existing person by private external ID.
	 *
	 * @param string $external_id Import key.
	 * @return int
	 */
	private function find_by_external_id( $external_id ) {
		$external_id = Directory_Post_Type::sanitize_external_id( $external_id );
		if ( '' === $external_id ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'        => Directory_Post_Type::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array(
					array(
						'key'   => Directory_Post_Type::META_EXTERNAL_ID,
						'value' => $external_id,
					),
				),
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Per-user transient key compatible with Directory_Importer preview tokens.
	 *
	 * @param string $token Preview token.
	 * @return string
	 */
	private function transient_key( $token ) {
		return 'headless_directory_import_' . get_current_user_id() . '_' . substr( $token, 0, 32 );
	}
}
