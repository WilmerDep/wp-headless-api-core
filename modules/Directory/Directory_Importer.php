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
	const SPREADSHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	const OFFICE_REL_NS  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

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
			$warnings[] = __( 'Ya existe una persona con este external_id. Usa modo actualizar para modificarla.', 'wp-headless-api-core' );
		}

		$groups = $this->normalize_group_names( isset( $row['groups'] ) ? $row['groups'] : '' );

		return array(
			'row'         => (int) $row_number,
			'external_id' => $external_id,
			'name'        => $name,
			'role'        => sanitize_text_field( isset( $row['role'] ) ? $row['role'] : '' ),
			'joined_at'   => $joined_at,
			'phone'       => Directory_Post_Type::sanitize_phone( isset( $row['phone'] ) ? $row['phone'] : '' ),
			'email'       => $email,
			'summary'     => sanitize_textarea_field( isset( $row['summary'] ) ? $row['summary'] : '' ),
			'groups'      => $groups,
			'status'      => $status,
			'order'       => isset( $row['order'] ) && '' !== $row['order'] ? (int) $row['order'] : 0,
			'image_url'   => $image_url,
			'image_alt'   => sanitize_text_field( isset( $row['image_alt'] ) ? $row['image_alt'] : '' ),
			'warnings'    => $warnings,
			'errors'      => $errors,
		);
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

		$sheet_xml  = $zip->getFromName( $sheet_path );
		$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		$shared     = false !== $shared_xml ? $this->parse_shared_strings( $shared_xml ) : array();
		$zip->close();

		if ( false === $sheet_xml ) {
			return new WP_Error( 'headless_directory_xlsx_sheet_read', __( 'No fue posible leer la hoja del XLSX.', 'wp-headless-api-core' ) );
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $sheet_xml );
		if ( false === $xml ) {
			return new WP_Error( 'headless_directory_xlsx_xml', __( 'La hoja XLSX contiene XML inválido.', 'wp-headless-api-core' ) );
		}

		$xml->registerXPathNamespace( 'm', self::SPREADSHEET_NS );
		$row_nodes = $xml->xpath( '/m:worksheet/m:sheetData/m:row' );
		if ( false === $row_nodes || empty( $row_nodes ) ) {
			return new WP_Error( 'headless_directory_xlsx_data', __( 'La hoja XLSX no contiene datos.', 'wp-headless-api-core' ) );
		}

		$rows = array();
		foreach ( $row_nodes as $row_node ) {
			$row_node->registerXPathNamespace( 'm', self::SPREADSHEET_NS );
			$cell_nodes = $row_node->xpath( './m:c' );
			$row = array();

			foreach ( (array) $cell_nodes as $cell ) {
				$attrs = $cell->attributes();
				$ref   = isset( $attrs['r'] ) ? (string) $attrs['r'] : '';
				$col   = $this->column_index_from_ref( $ref );
				$type  = isset( $attrs['t'] ) ? (string) $attrs['t'] : '';
				$value = '';

				$cell->registerXPathNamespace( 'm', self::SPREADSHEET_NS );
				if ( 'inlineStr' === $type ) {
					$text_nodes = $cell->xpath( './m:is//m:t' );
					foreach ( (array) $text_nodes as $text_node ) {
						$value .= (string) $text_node;
					}
				} else {
					$value_nodes = $cell->xpath( './m:v' );
					if ( ! empty( $value_nodes ) ) {
						$value = (string) $value_nodes[0];
						if ( 's' === $type ) {
							$shared_index = (int) $value;
							$value = isset( $shared[ $shared_index ] ) ? $shared[ $shared_index ] : '';
						}
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
	 * Resolve the first worksheet path using deterministic XLSX fallbacks.
	 *
	 * Excel producers differ in namespace prefixes and relationship target forms.
	 * Prefer the conventional sheet1 path, then resolve workbook relationships,
	 * and finally fall back to the first worksheet XML entry in the ZIP.
	 *
	 * @param ZipArchive $zip XLSX archive.
	 * @return string
	 */
	private function first_sheet_path( ZipArchive $zip ) {
		if ( false !== $zip->locateName( 'xl/worksheets/sheet1.xml', ZipArchive::FL_NOCASE ) ) {
			return 'xl/worksheets/sheet1.xml';
		}

		$workbook_xml = $zip->getFromName( 'xl/workbook.xml' );
		$rels_xml     = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
		if ( false !== $workbook_xml && false !== $rels_xml ) {
			libxml_use_internal_errors( true );
			$workbook = simplexml_load_string( $workbook_xml );
			$rels     = simplexml_load_string( $rels_xml );

			if ( false !== $workbook && false !== $rels ) {
				$workbook->registerXPathNamespace( 'm', self::SPREADSHEET_NS );
				$workbook->registerXPathNamespace( 'r', self::OFFICE_REL_NS );
				$sheet_nodes = $workbook->xpath( '/m:workbook/m:sheets/m:sheet[1]' );

				if ( ! empty( $sheet_nodes ) ) {
					$sheet_attrs = $sheet_nodes[0]->attributes( self::OFFICE_REL_NS );
					$rid = isset( $sheet_attrs['id'] ) ? (string) $sheet_attrs['id'] : '';

					if ( '' !== $rid ) {
						$rels->registerXPathNamespace( 'pr', self::PACKAGE_REL_NS );
						$relationship_nodes = $rels->xpath( '/pr:Relationships/pr:Relationship' );
						foreach ( (array) $relationship_nodes as $relationship ) {
							$attrs = $relationship->attributes();
							if ( ! isset( $attrs['Id'], $attrs['Target'] ) || $rid !== (string) $attrs['Id'] ) {
								continue;
							}

							$candidate = $this->normalize_sheet_target( (string) $attrs['Target'] );
							if ( '' !== $candidate && false !== $zip->locateName( $candidate, ZipArchive::FL_NOCASE ) ) {
								return $candidate;
							}
						}
					}
				}
			}
		}

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( ! is_string( $name ) ) {
				continue;
			}
			if ( preg_match( '#^xl/worksheets/sheet[^/]*\.xml$#i', $name ) ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Normalize an XLSX workbook relationship target to a ZIP path.
	 *
	 * @param string $target Relationship target.
	 * @return string
	 */
	private function normalize_sheet_target( $target ) {
		$target = str_replace( '\\', '/', trim( (string) $target ) );
		if ( '' === $target || 0 === strpos( $target, 'http://' ) || 0 === strpos( $target, 'https://' ) ) {
			return '';
		}

		if ( 0 === strpos( $target, '/' ) ) {
			$path = ltrim( $target, '/' );
		} elseif ( 0 === strpos( $target, 'xl/' ) ) {
			$path = $target;
		} else {
			$path = 'xl/' . $target;
		}

		$segments = array();
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				array_pop( $segments );
				continue;
			}
			$segments[] = $segment;
		}

		return implode( '/', $segments );
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

		$xml->registerXPathNamespace( 'm', self::SPREADSHEET_NS );
		$items = $xml->xpath( '/m:sst/m:si' );
		$strings = array();

		foreach ( (array) $items as $item ) {
			$item->registerXPathNamespace( 'm', self::SPREADSHEET_NS );
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
	 * Parse CSV rows.
	 *
	 * @param string $path File path.
	 * @return array|WP_Error
	 */
	private function parse_csv( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( false === $handle ) {
			return new WP_Error( 'headless_directory_csv_open', __( 'No fue posible leer el CSV.', 'wp-headless-api-core' ) );
		}

		$rows = array();
		while ( false !== ( $row = fgetcsv( $handle ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fgetcsv
			if ( empty( $rows ) && isset( $row[0] ) ) {
				$row[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $row[0] );
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return $rows;
	}

	/**
	 * Remaining importer helpers.
	 *
	 * The implementation below is unchanged from the current Directory candidate.
	 */
	private function normalize_header( $header ) {
		return sanitize_key( strtolower( trim( (string) $header ) ) );
	}

	private function row_is_empty( array $row ) {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		return true;
	}

	private function normalize_date_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( is_numeric( $value ) ) {
			$serial = (float) $value;
			if ( $serial > 0 ) {
				$timestamp = (int) round( ( $serial - 25569 ) * DAY_IN_SECONDS );
				return gmdate( 'Y-m-d', $timestamp );
			}
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}

	private function normalize_group_names( $value ) {
		$parts = preg_split( '/[|,;]+/', (string) $value );
		$groups = array();
		foreach ( (array) $parts as $part ) {
			$name = sanitize_text_field( trim( $part ) );
			if ( '' !== $name ) {
				$groups[ strtolower( $name ) ] = $name;
			}
		}
		return array_values( $groups );
	}

	private function find_by_external_id( $external_id ) {
		$external_id = Directory_Post_Type::sanitize_external_id( $external_id );
		if ( '' === $external_id ) {
			return 0;
		}
		$posts = get_posts( array(
			'post_type'      => Directory_Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => Directory_Post_Type::META_EXTERNAL_ID,
			'meta_value'     => $external_id,
			'no_found_rows'  => true,
		) );
		return empty( $posts ) ? 0 : (int) $posts[0];
	}

	private function import_row( array $row, $mode, $create_groups, $import_images ) {
		$existing_id = '' !== $row['external_id'] ? $this->find_by_external_id( $row['external_id'] ) : 0;
		if ( $existing_id && 'create_only' === $mode ) {
			return array( 'status' => 'skipped', 'message' => __( 'Ya existe external_id; fila omitida en modo crear solamente.', 'wp-headless-api-core' ) );
		}

		$postarr = array(
			'post_type'   => Directory_Post_Type::POST_TYPE,
			'post_title'  => $row['name'],
			'post_status' => $row['status'],
			'menu_order'  => (int) $row['order'],
		);
		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$post_id = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array( 'status' => 'failed', 'message' => is_wp_error( $post_id ) ? $post_id->get_error_message() : __( 'No fue posible guardar la persona.', 'wp-headless-api-core' ) );
		}

		$meta = array(
			Directory_Post_Type::META_ROLE        => $row['role'],
			Directory_Post_Type::META_JOINED_AT   => $row['joined_at'],
			Directory_Post_Type::META_PHONE       => $row['phone'],
			Directory_Post_Type::META_EMAIL       => $row['email'],
			Directory_Post_Type::META_SUMMARY     => $row['summary'],
			Directory_Post_Type::META_EXTERNAL_ID => $row['external_id'],
			Directory_Post_Type::META_IMAGE_ALT   => $row['image_alt'],
		);
		foreach ( $meta as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		$term_ids = array();
		foreach ( $row['groups'] as $group_name ) {
			$term = term_exists( $group_name, Directory_Post_Type::TAXONOMY );
			if ( ! $term && $create_groups ) {
				$term = wp_insert_term( $group_name, Directory_Post_Type::TAXONOMY );
			}
			if ( is_wp_error( $term ) || ! $term ) {
				continue;
			}
			$term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		}
		wp_set_object_terms( $post_id, $term_ids, Directory_Post_Type::TAXONOMY, false );

		if ( $import_images && ! empty( $row['image_url'] ) ) {
			$this->sideload_portrait( $post_id, $row['image_url'], $row['image_alt'] );
		}

		return array(
			'status'  => $existing_id ? 'updated' : 'created',
			'message' => $existing_id ? __( 'Persona actualizada.', 'wp-headless-api-core' ) : __( 'Persona creada.', 'wp-headless-api-core' ),
		);
	}

	private function sideload_portrait( $post_id, $url, $alt ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 15, false );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		if ( filesize( $tmp ) > self::MAX_IMAGE_BYTES ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'headless_directory_image_size', __( 'La imagen remota supera el límite de 6 MB.', 'wp-headless-api-core' ) );
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$name = $path ? basename( $path ) : 'directory-image.jpg';
		$file = array( 'name' => sanitize_file_name( $name ), 'tmp_name' => $tmp );
		$attachment_id = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attachment_id;
		}
		set_post_thumbnail( $post_id, $attachment_id );
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}
		return $attachment_id;
	}

	private function transient_key( $token ) {
		return 'headless_dir_imp_' . substr( md5( $token ), 0, 20 );
	}

	private function column_index_from_ref( $ref ) {
		if ( ! preg_match( '/^([A-Z]+)/i', (string) $ref, $matches ) ) {
			return 0;
		}
		$letters = strtoupper( $matches[1] );
		$index = 0;
		for ( $i = 0, $len = strlen( $letters ); $i < $len; $i++ ) {
			$index = ( $index * 26 ) + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $index - 1 );
	}

	private function build_template_xlsx( $path ) {
		$headers = array( 'external_id', 'name', 'role', 'joined_at', 'phone', 'email', 'summary', 'groups', 'status', 'order', 'image_url', 'image_alt' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Directory" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );

		$cells = array();
		foreach ( $headers as $column => $header ) {
			$letters = $this->column_letters( $column + 1 );
			$cells[] = '<c r="' . $letters . '1" t="inlineStr"><is><t>' . htmlspecialchars( $header, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>';
		}
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">' . implode( '', $cells ) . '</row></sheetData></worksheet>';
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
		$zip->close();
		return true;
	}

	private function column_letters( $number ) {
		$letters = '';
		while ( $number > 0 ) {
			$number--;
			$letters = chr( 65 + ( $number % 26 ) ) . $letters;
			$number = (int) floor( $number / 26 );
		}
		return $letters;
	}
}
