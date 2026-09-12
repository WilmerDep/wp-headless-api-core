<?php
/**
 * Directory spreadsheet import service.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

use WP_Error;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

final class Directory_Importer {
	const NONCE_ACTION   = 'headless_directory_import';
	const TRANSIENT_TTL  = 1800;
	const MAX_FILE_BYTES = 10485760;
	const MAX_IMAGE_BYTES = 6291456;

	/**
	 * Register import/download actions.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_headless_directory_download_template', array( $this, 'download_template' ) );
		add_action( 'wp_ajax_headless_directory_preview_import', array( $this, 'preview_import' ) );
		add_action( 'wp_ajax_headless_directory_import_batch', array( $this, 'import_batch' ) );
	}

	/**
	 * Download a minimal XLSX template with supported columns.
	 *
	 * @return void
	 */
	public function download_template() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para descargar esta plantilla.', 'wp-headless-api-core' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'El servidor no tiene disponible ZipArchive, necesario para generar la plantilla XLSX.', 'wp-headless-api-core' ) );
		}

		$path = wp_tempnam( 'directory-template.xlsx' );
		if ( ! $path || ! $this->build_template_xlsx( $path ) ) {
			wp_die( esc_html__( 'No fue posible generar la plantilla Excel.', 'wp-headless-api-core' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="headless-directory-template.xlsx"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		exit;
	}

	/**
	 * Parse and validate a spreadsheet without writing WordPress content.
	 *
	 * @return void
	 */
	public function preview_import() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para importar personas.', 'wp-headless-api-core' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Selecciona un archivo Excel o CSV.', 'wp-headless-api-core' ) ), 400 );
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'WordPress no pudo recibir el archivo.', 'wp-headless-api-core' ) ), 400 );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > self::MAX_FILE_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'El archivo supera el límite de 10 MB o está vacío.', 'wp-headless-api-core' ) ), 400 );
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Formato no soportado. Usa .xlsx o .csv.', 'wp-headless-api-core' ) ), 400 );
		}

		$rows = 'csv' === $ext ? $this->parse_csv( $file['tmp_name'] ) : $this->parse_xlsx( $file['tmp_name'] );
		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( array( 'message' => $rows->get_error_message() ), 400 );
		}

		$normalized = $this->normalize_rows( $rows );
		if ( is_wp_error( $normalized ) ) {
			wp_send_json_error( array( 'message' => $normalized->get_error_message() ), 400 );
		}

		$token = strtolower( wp_generate_password( 24, false, false ) );
		$key   = $this->transient_key( $token );
		set_transient(
			$key,
			array(
				'rows'    => $normalized['rows'],
				'summary' => $normalized['summary'],
			),
			self::TRANSIENT_TTL
		);

		$preview_rows = array_slice( $normalized['rows'], 0, 100 );

		wp_send_json_success(
			array(
				'token'        => $token,
				'summary'      => $normalized['summary'],
				'rows'         => $preview_rows,
				'previewLimit' => 100,
				'totalRows'    => count( $normalized['rows'] ),
			)
		);
	}

	/**
	 * Import one bounded batch from a validated preview token.
	 *
	 * @return void
	 */
	public function import_batch() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para importar personas.', 'wp-headless-api-core' ) ), 403 );
		}

		$token         = isset( $_POST['token'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) wp_unslash( $_POST['token'] ) ) ) : '';
		$offset        = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
		$batch_size    = isset( $_POST['batchSize'] ) ? min( 25, max( 1, absint( wp_unslash( $_POST['batchSize'] ) ) ) ) : 10;
		$mode          = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create_only';
		$create_groups = ! empty( $_POST['createGroups'] ) && current_user_can( 'manage_categories' );
		$import_images = ! empty( $_POST['importImages'] ) && current_user_can( 'upload_files' );

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

			$outcome = $this->import_row( $row, $mode, $create_groups, $import_images );
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
				'offset'     => $next_offset,
				'total'      => $total,
				'done'       => $done,
				'batchResult'=> $result,
			)
		);
	}

	/**
	 * Normalize parsed rows and attach row-level diagnostics.
	 *
	 * @param array $rows Raw rows; first row is header.
	 * @return array|WP_Error
	 */
	private function normalize_rows( array $rows ) {
		if ( count( $rows ) < 2 ) {
			return new WP_Error( 'headless_directory_import_empty', __( 'El archivo no contiene filas de datos.', 'wp-headless-api-core' ) );
		}

		$headers = array_map( array( $this, 'normalize_header' ), $rows[0] );
		if ( ! in_array( 'name', $headers, true ) ) {
			return new WP_Error( 'headless_directory_import_name_missing', __( 'Falta la columna obligatoria "name".', 'wp-headless-api-core' ) );
		}

		$known = array( 'external_id', 'name', 'role', 'joined_at', 'phone', 'email', 'summary', 'groups', 'status', 'order', 'image_url', 'image_alt' );
		$normalized = array();
		$summary = array( 'ready' => 0, 'warning' => 0, 'error' => 0 );

		for ( $index = 1; $index < count( $rows ); $index++ ) {
			$raw = $rows[ $index ];
			if ( $this->row_is_empty( $raw ) ) {
				continue;
			}

			$assoc = array();
			foreach ( $headers as $column => $header ) {
				if ( '' === $header || ! in_array( $header, $known, true ) ) {
					continue;
				}
				$assoc[ $header ] = isset( $raw[ $column ] ) ? trim( (string) $raw[ $column ] ) : '';
			}

			$item = $this->normalize_row( $assoc, $index + 1 );
			$normalized[] = $item;

			if ( ! empty( $item['errors'] ) ) {
				++$summary['error'];
			} elseif ( ! empty( $item['warnings'] ) ) {
				++$summary['warning'];
			} else {
				++$summary['ready'];
			}
		}

		return array( 'rows' => $normalized, 'summary' => $summary );
	}

	/**
	 * Normalize one import row.
	 *
	 * @param array $row        Row map.
	 * @param int   $row_number Human row number.
	 * @return array
	 */
	private function normalize_row( array $row, $row_number ) {
		$name        = sanitize_text_field( isset( $row['name'] ) ? $row['name'] : '' );
		$external_id = Directory_Post_Type::sanitize_external_id( isset( $row['external_id'] ) ? $row['external_id'] : '' );
		$joined_at   = $this->normalize_date_value( isset( $row['joined_at'] ) ? $row['joined_at'] : '' );
		$email_raw   = isset( $row['email'] ) ? $row['email'] : '';
		$email       = Directory_Post_Type::sanitize_email_value( $email_raw );
		$status      = sanitize_key( isset( $row['status'] ) ? $row['status'] : '' );
		$status      = '' === $status ? 'draft' : $status;
		$allowed_statuses = array( 'draft', 'publish', 'pending', 'private' );

		$errors   = array();
		$warnings = array();

		if ( '' === $name ) {
			$errors[] = __( 'El nombre es obligatorio.', 'wp-headless-api-core' );
		}
		if ( '' !== trim( (string) ( isset( $row['joined_at'] ) ? $row['joined_at'] : '' ) ) && '' === $joined_at ) {
			$errors[] = __( 'La fecha joined_at no es válida.', 'wp-headless-api-core' );
		}
		if ( '' !== trim( (string) $email_raw ) && '' === $email ) {
			$errors[] = __( 'El email no es válido.', 'wp-headless-api-core' );
		}
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$errors[] = __( 'El estado debe ser draft, publish, pending o private.', 'wp-headless-api-core' );
			$status = 'draft';
		}

		$image_url = isset( $row['image_url'] ) ? esc_url_raw( $row['image_url'], array( 'http', 'https' ) ) : '';
		if ( '' !== trim( (string) ( isset( $row['image_url'] ) ? $row['image_url'] : '' ) ) && '' === $image_url ) {
			$warnings[] = __( 'La URL de imagen no es válida y será omitida.', 'wp-headless-api-core' );
		}

		if ( '' === $external_id ) {
			$warnings[] = __( 'Sin external_id: una importación futura no podrá identificar esta persona de forma segura.', 'wp-headless-api-core' );
		} elseif ( $this->find_by_external_id( $external_id ) ) {
			$warnings[] = __( 'Ya existe una persona con este external_id.', 'wp-headless-api-core' );
		}

		$groups = array();
		$groups_raw = isset( $row['groups'] ) ? trim( (string) $row['groups'] ) : '';
		if ( '' !== $groups_raw ) {
			foreach ( preg_split( '/\s*[|;]\s*/', $groups_raw ) as $group ) {
				$group = sanitize_text_field( $group );
				if ( '' !== $group ) {
					$groups[] = $group;
				}
		}
		}

		$order = isset( $row['order'] ) && '' !== trim( (string) $row['order'] ) ? max( 0, (int) $row['order'] ) : 0;

		return array(
			'row'         => (int) $row_number,
			'external_id' => $external_id,
			'name'        => $name,
			'role'        => sanitize_text_field( isset( $row['role'] ) ? $row['role'] : '' ),
			'joined_at'   => $joined_at,
			'phone'       => sanitize_text_field( isset( $row['phone'] ) ? $row['phone'] : '' ),
			'email'       => $email,
			'summary'     => sanitize_textarea_field( isset( $row['summary'] ) ? $row['summary'] : '' ),
			'groups'      => array_values( array_unique( $groups ) ),
			'status'      => $status,
			'order'       => $order,
			'image_url'   => $image_url,
			'image_alt'   => sanitize_text_field( isset( $row['image_alt'] ) ? $row['image_alt'] : '' ),
			'errors'      => $errors,
			'warnings'    => $warnings,
		);
	}

	/**
	 * Create/update one person from a normalized row.
	 *
	 * @param array  $row           Normalized row.
	 * @param string $mode          Import mode.
	 * @param bool   $create_groups Whether unknown groups may be created.
	 * @param bool   $import_images Whether image_url may be sideloaded.
	 * @return array
	 */
	private function import_row( array $row, $mode, $create_groups, $import_images ) {
		$existing_id = '' !== $row['external_id'] ? $this->find_by_external_id( $row['external_id'] ) : 0;

		if ( $existing_id && 'create_only' === $mode ) {
			return array( 'status' => 'skipped', 'message' => __( 'Ya existe el external_id; modo crear solamente.', 'wp-headless-api-core' ) );
		}

		$postarr = array(
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

		$term_ids = $this->resolve_groups( $row['groups'], $create_groups );
		if ( is_wp_error( $term_ids ) ) {
			return array( 'status' => 'failed', 'message' => $term_ids->get_error_message() );
		}
		wp_set_object_terms( $post_id, $term_ids, Directory_Post_Type::TAXONOMY, false );

		$image_warning = '';
		if ( $import_images && '' !== $row['image_url'] ) {
			$image_id = $this->sideload_image( $row['image_url'], $post_id, $row['name'] );
			if ( is_wp_error( $image_id ) ) {
				$image_warning = ' ' . sprintf( __( 'Imagen omitida: %s', 'wp-headless-api-core' ), $image_id->get_error_message() );
			} elseif ( $image_id > 0 ) {
				set_post_thumbnail( $post_id, $image_id );
				if ( '' !== $row['image_alt'] ) {
					update_post_meta( $image_id, '_wp_attachment_image_alt', $row['image_alt'] );
				}
			}
		}

		return array(
			'status'  => $action,
			'message' => sprintf( __( 'Persona %s correctamente.%s', 'wp-headless-api-core' ), 'created' === $action ? __( 'creada', 'wp-headless-api-core' ) : __( 'actualizada', 'wp-headless-api-core' ), $image_warning ),
		);
	}

	/**
	 * Resolve group names/slugs to term IDs.
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
				return new WP_Error( 'headless_directory_unknown_group', sprintf( __( 'El grupo "%s" no existe.', 'wp-headless-api-core' ), $label ) );
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
	 * Find one existing person by the private external import ID.
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
	 * Safely sideload one remote image attachment.
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
		if ( $length > self::MAX_IMAGE_BYTES ) {
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
	 * Parse CSV into a row matrix.
	 *
	 * @param string $path Temp path.
	 * @return array|WP_Error
	 */
	private function parse_csv( $path ) {
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( ! $handle ) {
			return new WP_Error( 'headless_directory_csv_open', __( 'No fue posible abrir el CSV.', 'wp-headless-api-core' ) );
		}

		$rows = array();
		while ( false !== ( $row = fgetcsv( $handle ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		return $rows;
	}

	/**
	 * Parse the first worksheet from an XLSX package without executing formulas.
	 *
	 * @param string $path Temp path.
	 * @return array|WP_Error
	 */
	private function parse_xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'headless_directory_xlsx_support', __( 'El servidor necesita ZipArchive y SimpleXML para importar XLSX.', 'wp-headless-api-core' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'headless_directory_xlsx_open', __( 'El archivo XLSX no es válido o está dañado.', 'wp-headless-api-core' ) );
		}

		$sheet_path = $this->first_sheet_path( $zip );
		if ( '' === $sheet_path ) {
			$zip->close();
			return new WP_Error( 'headless_directory_xlsx_sheet', __( 'No se encontró una hoja de cálculo válida.', 'wp-headless-api-core' ) );
		}

		$sheet_xml = $zip->getFromName( $sheet_path );
		$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		$shared = false !== $shared_xml ? $this->parse_shared_strings( $shared_xml ) : array();
		$zip->close();

		if ( false === $sheet_xml ) {
			return new WP_Error( 'headless_directory_xlsx_sheet_read', __( 'No fue posible leer la hoja del XLSX.', 'wp-headless-api-core' ) );
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $sheet_xml );
		if ( false === $xml ) {
			return new WP_Error( 'headless_directory_xlsx_xml', __( 'La hoja XLSX contiene XML inválido.', 'wp-headless-api-core' ) );
		}

		$ns = $xml->getNamespaces( true );
		$main = isset( $ns[''] ) ? $xml->children( $ns[''] ) : $xml;
		$rows = array();

		if ( ! isset( $main->sheetData ) ) {
			return new WP_Error( 'headless_directory_xlsx_data', __( 'La hoja XLSX no contiene datos.', 'wp-headless-api-core' ) );
		}

		foreach ( $main->sheetData->row as $row_node ) {
			$row = array();
			foreach ( $row_node->c as $cell ) {
				$attrs = $cell->attributes();
				$ref   = isset( $attrs['r'] ) ? (string) $attrs['r'] : '';
				$col   = $this->column_index_from_ref( $ref );
				$type  = isset( $attrs['t'] ) ? (string) $attrs['t'] : '';
				$value = '';

				if ( 'inlineStr' === $type && isset( $cell->is->t ) ) {
					$value = (string) $cell->is->t;
				} elseif ( isset( $cell->v ) ) {
					$value = (string) $cell->v;
					if ( 's' === $type ) {
						$shared_index = (int) $value;
						$value = isset( $shared[ $shared_index ] ) ? $shared[ $shared_index ] : '';
					}
				}

				$row[ $col ] = $value;
			}

			if ( ! empty( $row ) ) {
				$max = max( array_keys( $row ) );
				for ( $i = 0; $i <= $max; $i++ ) {
					if ( ! array_key_exists( $i, $row ) ) {
						$row[ $i ] = '';
					}
				}
				ksort( $row );
				$rows[] = array_values( $row );
			}
		}

		return $rows;
	}

	/**
	 * Resolve the first worksheet path using workbook relationships.
	 *
	 * @param ZipArchive $zip XLSX archive.
	 * @return string
	 */
	private function first_sheet_path( ZipArchive $zip ) {
		$workbook_xml = $zip->getFromName( 'xl/workbook.xml' );
		$rels_xml     = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
		if ( false === $workbook_xml || false === $rels_xml ) {
			return $zip->locateName( 'xl/worksheets/sheet1.xml' ) !== false ? 'xl/worksheets/sheet1.xml' : '';
		}

		libxml_use_internal_errors( true );
		$workbook = simplexml_load_string( $workbook_xml );
		$rels     = simplexml_load_string( $rels_xml );
		if ( false === $workbook || false === $rels ) {
			return '';
		}

		$w_ns = $workbook->getNamespaces( true );
		$main = isset( $w_ns[''] ) ? $workbook->children( $w_ns[''] ) : $workbook;
		if ( empty( $main->sheets->sheet[0] ) ) {
			return '';
		}

		$sheet_attrs = $main->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
		$rid = isset( $sheet_attrs['id'] ) ? (string) $sheet_attrs['id'] : '';
		if ( '' === $rid ) {
			return '';
		}

		$r_ns  = $rels->getNamespaces( true );
		$rmain = isset( $r_ns[''] ) ? $rels->children( $r_ns[''] ) : $rels;
		foreach ( $rmain->Relationship as $relationship ) {
			$attrs = $relationship->attributes();
			if ( isset( $attrs['Id'], $attrs['Target'] ) && $rid === (string) $attrs['Id'] ) {
				$target = ltrim( (string) $attrs['Target'], '/' );
				return 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;
			}
		}

		return '';
	}

	/**
	 * Parse shared strings including rich-text runs.
	 *
	 * @param string $xml_string SharedStrings XML.
	 * @return array
	 */
	private function parse_shared_strings( $xml_string ) {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_string );
		if ( false === $xml ) {
			return array();
		}

		$ns = $xml->getNamespaces( true );
		$uri = isset( $ns[''] ) ? $ns[''] : 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
		$xml->registerXPathNamespace( 'm', $uri );
		$items = $xml->xpath( '//m:si' );
		$strings = array();

		foreach ( (array) $items as $item ) {
			$item->registerXPathNamespace( 'm', $uri );
			$text_nodes = $item->xpath( './/m:t' );
			$text = '';
			foreach ( (array) $text_nodes as $node ) {
				$text .= (string) $node;
			}
			$strings[] = $text;
		}

		return $strings;
	}

	/**
	 * Convert an Excel cell reference to zero-based column index.
	 *
	 * @param string $ref Cell reference e.g. AA12.
	 * @return int
	 */
	private function column_index_from_ref( $ref ) {
		preg_match( '/^([A-Z]+)/i', $ref, $matches );
		$letters = isset( $matches[1] ) ? strtoupper( $matches[1] ) : 'A';
		$index = 0;
		for ( $i = 0; $i < strlen( $letters ); $i++ ) {
			$index = ( $index * 26 ) + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $index - 1 );
	}

	/**
	 * Generate a minimal standards-compliant XLSX template.
	 *
	 * @param string $path Destination path.
	 * @return bool
	 */
	private function build_template_xlsx( $path ) {
		$headers = array( 'external_id', 'name', 'role', 'joined_at', 'phone', 'email', 'summary', 'groups', 'status', 'order', 'image_url', 'image_alt' );
		$example = array( 'EMP-001', 'Dra. Ejemplo', 'Directora', '2026-09-11', '(809) 555-0000', 'persona@example.org', 'Resumen breve', 'Directores|Médicos', 'draft', '0', 'https://example.org/retrato.jpg', 'Retrato institucional' );

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Directory" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="1"><xf xfId="0"/></cellXfs></styleSheet>' );

		$rows_xml = $this->xlsx_row_xml( 1, $headers ) . $this->xlsx_row_xml( 2, $example );
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $rows_xml . '</sheetData></worksheet>';
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
		$zip->close();
		return true;
	}

	/**
	 * Build one inline-string XLSX row.
	 *
	 * @param int   $row_number Row number.
	 * @param array $values     Cell values.
	 * @return string
	 */
	private function xlsx_row_xml( $row_number, array $values ) {
		$cells = '';
		foreach ( array_values( $values ) as $index => $value ) {
			$ref = $this->column_letters( $index + 1 ) . $row_number;
			$cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' ) . '</t></is></c>';
		}
		return '<row r="' . (int) $row_number . '">' . $cells . '</row>';
	}

	/**
	 * Convert one-based column number to Excel letters.
	 *
	 * @param int $number Column number.
	 * @return string
	 */
	private function column_letters( $number ) {
		$letters = '';
		while ( $number > 0 ) {
			$number--;
			$letters = chr( 65 + ( $number % 26 ) ) . $letters;
			$number = (int) floor( $number / 26 );
		}
		return $letters;
	}

	/**
	 * Normalize a supported spreadsheet header.
	 *
	 * @param mixed $header Raw header.
	 * @return string
	 */
	private function normalize_header( $header ) {
		$header = strtolower( trim( remove_accents( (string) $header ) ) );
		$header = preg_replace( '/[^a-z0-9]+/', '_', $header );
		return trim( (string) $header, '_' );
	}

	/**
	 * Normalize YYYY-MM-DD, dd/mm/YYYY or Excel numeric serial.
	 *
	 * @param mixed $value Raw date.
	 * @return string
	 */
	private function normalize_date_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$strict = Directory_Post_Type::sanitize_date( $value );
		if ( '' !== $strict ) {
			return $strict;
		}

		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $matches ) ) {
			$candidate = sprintf( '%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1] );
			return Directory_Post_Type::sanitize_date( $candidate );
		}

		if ( is_numeric( $value ) ) {
			$serial = (float) $value;
			if ( $serial > 0 && $serial < 100000 ) {
				$timestamp = (int) round( ( $serial - 25569 ) * DAY_IN_SECONDS );
				return gmdate( 'Y-m-d', $timestamp );
			}
		}

		return '';
	}

	/**
	 * Determine whether a row is completely empty.
	 *
	 * @param array $row Row values.
	 * @return bool
	 */
	private function row_is_empty( array $row ) {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Per-user transient key.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private function transient_key( $token ) {
		return 'headless_directory_import_' . get_current_user_id() . '_' . substr( $token, 0, 32 );
	}
}
