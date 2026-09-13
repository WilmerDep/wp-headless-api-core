<?php
/**
 * Services CSV importer with preview, upsert and media reuse.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Services_Importer {
	const NONCE_ACTION   = 'headless_services_import';
	const TRANSIENT_TTL  = 1800;
	const MAX_FILE_BYTES = 10485760;
	const MAX_IMAGE_BYTES = 6291456;

	public function register() {
		add_action( 'admin_post_headless_services_download_template', array( $this, 'download_template' ) );
		add_action( 'wp_ajax_headless_services_preview_import', array( $this, 'preview_import' ) );
		add_action( 'wp_ajax_headless_services_import_batch', array( $this, 'import_batch' ) );
	}

	public function download_template() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para descargar esta plantilla.', 'wp-headless-api-core' ), 403 );
		}
		check_admin_referer( self::NONCE_ACTION );
		$headers = array( 'external_id','title','description','audience','department','requirements','procedure','schedule','cost','duration','channel','phone','email','address','status','order','image_url','image_alt' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="headless-services-template.csv"' );
		echo "\xEF\xBB\xBF";
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $headers );
		fputcsv( $out, array( 'HOS-SRV-001','Servicio de ejemplo','Descripción pública','Público objetivo','Departamento responsable','Requisito 1 | Requisito 2','Procedimiento','Lunes a Viernes','0.00','30 minutos','Presencial','(809) 000-0000','servicio@example.org','Dirección','draft','0','https://example.org/imagen.jpg','Imagen del servicio' ) );
		fclose( $out );
		exit;
	}

	public function preview_import() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para importar servicios.', 'wp-headless-api-core' ) ), 403 );
		}
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Selecciona un archivo CSV.', 'wp-headless-api-core' ) ), 400 );
		}
		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || $size <= 0 || $size > self::MAX_FILE_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'WordPress no pudo recibir el archivo o supera 10 MB.', 'wp-headless-api-core' ) ), 400 );
		}
		if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Formato no soportado. Usa .csv.', 'wp-headless-api-core' ) ), 400 );
		}
		$rows = $this->parse_csv( $file['tmp_name'] );
		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( array( 'message' => $rows->get_error_message() ), 400 );
		}
		$normalized = $this->normalize_rows( $rows );
		if ( is_wp_error( $normalized ) ) {
			wp_send_json_error( array( 'message' => $normalized->get_error_message() ), 400 );
		}
		$token = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( $this->transient_key( $token ), $normalized, self::TRANSIENT_TTL );
		wp_send_json_success( array( 'token' => $token, 'summary' => $normalized['summary'], 'rows' => array_slice( $normalized['rows'], 0, 100 ), 'previewLimit' => 100, 'totalRows' => count( $normalized['rows'] ) ) );
	}

	public function import_batch() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para importar servicios.', 'wp-headless-api-core' ) ), 403 );
		}
		$token      = isset( $_POST['token'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) wp_unslash( $_POST['token'] ) ) ) : '';
		$offset     = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0;
		$batch_size = isset( $_POST['batchSize'] ) ? min( 25, max( 1, absint( wp_unslash( $_POST['batchSize'] ) ) ) ) : 10;
		$mode       = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create_only';
		$images     = ! empty( $_POST['importImages'] ) && current_user_can( 'upload_files' );
		if ( ! in_array( $mode, array( 'create_only', 'upsert' ), true ) ) { $mode = 'create_only'; }
		$data = get_transient( $this->transient_key( $token ) );
		if ( ! is_array( $data ) || empty( $data['rows'] ) ) {
			wp_send_json_error( array( 'message' => __( 'La vista previa expiró. Valida nuevamente el CSV.', 'wp-headless-api-core' ) ), 410 );
		}
		$rows = $data['rows']; $slice = array_slice( $rows, $offset, $batch_size );
		$result = array( 'created'=>0,'updated'=>0,'skipped'=>0,'failed'=>0,'details'=>array() );
		do_action( 'headless_api_core_services_bulk_start', 'import' );
		foreach ( $slice as $row ) {
			if ( ! empty( $row['errors'] ) ) { ++$result['skipped']; $result['details'][] = array( 'row'=>$row['row'],'status'=>'skipped','message'=>implode( ' ', $row['errors'] ) ); continue; }
			$outcome = $this->import_row( $row, $mode, $images );
			$status = isset( $outcome['status'] ) ? $outcome['status'] : 'failed';
			isset( $result[ $status ] ) ? ++$result[ $status ] : ++$result['failed'];
			$result['details'][] = array( 'row'=>$row['row'],'status'=>$status,'message'=>isset( $outcome['message'] ) ? $outcome['message'] : '' );
		}
		do_action( 'headless_api_core_services_bulk_end', 'import' );
		do_action( 'headless_api_core_services_collection_changed', 'import' );
		$next = min( count( $rows ), $offset + count( $slice ) ); $done = $next >= count( $rows );
		if ( $done ) { delete_transient( $this->transient_key( $token ) ); }
		wp_send_json_success( array( 'offset'=>$next,'total'=>count( $rows ),'done'=>$done,'batchResult'=>$result ) );
	}

	private function parse_csv( $path ) {
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) { return new WP_Error( 'services_csv_open', __( 'No fue posible leer el CSV.', 'wp-headless-api-core' ) ); }
		$rows = array();
		while ( ( $row = fgetcsv( $handle ) ) !== false ) { if ( 0 === count( $rows ) && isset( $row[0] ) ) { $row[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $row[0] ); } $rows[] = $row; }
		fclose( $handle );
		return $rows;
	}

	private function normalize_rows( array $rows ) {
		if ( count( $rows ) < 2 ) { return new WP_Error( 'services_import_empty', __( 'El archivo no contiene filas de datos.', 'wp-headless-api-core' ) ); }
		$headers = array_map( 'sanitize_key', $rows[0] );
		if ( ! in_array( 'title', $headers, true ) ) { return new WP_Error( 'services_import_title_missing', __( 'Falta la columna obligatoria "title".', 'wp-headless-api-core' ) ); }
		$known = array( 'external_id','title','description','audience','department','requirements','procedure','schedule','cost','duration','channel','phone','email','address','status','order','image_url','image_alt' );
		$out = array(); $summary = array( 'ready'=>0,'warning'=>0,'error'=>0 );
		for ( $i=1; $i<count( $rows ); $i++ ) {
			$assoc = array(); $empty = true;
			foreach ( $headers as $c=>$header ) { $value = isset( $rows[$i][$c] ) ? trim( (string) $rows[$i][$c] ) : ''; if ( '' !== $value ) { $empty = false; } if ( in_array( $header, $known, true ) ) { $assoc[$header] = $value; } }
			if ( $empty ) { continue; }
			$item = $this->normalize_row( $assoc, $i+1 ); $out[] = $item;
			! empty( $item['errors'] ) ? ++$summary['error'] : ( ! empty( $item['warnings'] ) ? ++$summary['warning'] : ++$summary['ready'] );
		}
		return array( 'rows'=>$out,'summary'=>$summary );
	}

	private function normalize_row( array $row, $number ) {
		$title = sanitize_text_field( isset( $row['title'] ) ? $row['title'] : '' );
		$external = Services_Post_Type::sanitize_external_id( isset( $row['external_id'] ) ? $row['external_id'] : '' );
		$email_raw = isset( $row['email'] ) ? $row['email'] : ''; $email = Services_Post_Type::sanitize_email_value( $email_raw );
		$status = sanitize_key( isset( $row['status'] ) ? $row['status'] : 'draft' ); if ( '' === $status ) { $status='draft'; }
		$errors=array(); $warnings=array();
		if ( '' === $title ) { $errors[] = __( 'El título es obligatorio.', 'wp-headless-api-core' ); }
		if ( ! in_array( $status, array( 'draft','publish','pending','private' ), true ) ) { $errors[] = __( 'Estado no válido.', 'wp-headless-api-core' ); $status='draft'; }
		if ( '' !== trim( $email_raw ) && '' === $email ) { $errors[] = __( 'El correo no es válido.', 'wp-headless-api-core' ); }
		if ( '' === $external ) { $warnings[] = __( 'Sin external_id no se podrá hacer upsert seguro.', 'wp-headless-api-core' ); }
		$requirements = preg_split( '/\s*(?:\|\||\||;;)\s*/', isset( $row['requirements'] ) ? $row['requirements'] : '' );
		$requirements = Services_Post_Type::sanitize_requirements( is_array( $requirements ) ? $requirements : array() );
		$image_url = isset( $row['image_url'] ) ? esc_url_raw( $row['image_url'], array( 'http','https' ) ) : '';
		return array(
			'row'=>$number,'external_id'=>$external,'title'=>$title,'description'=>sanitize_textarea_field( isset($row['description'])?$row['description']:'' ),'audience'=>sanitize_textarea_field( isset($row['audience'])?$row['audience']:'' ),'department'=>sanitize_text_field( isset($row['department'])?$row['department']:'' ),'requirements'=>$requirements,'procedure'=>sanitize_textarea_field( isset($row['procedure'])?$row['procedure']:'' ),'schedule'=>sanitize_textarea_field( isset($row['schedule'])?$row['schedule']:'' ),'cost'=>sanitize_text_field( isset($row['cost'])?$row['cost']:'' ),'duration'=>sanitize_text_field( isset($row['duration'])?$row['duration']:'' ),'channel'=>sanitize_text_field( isset($row['channel'])?$row['channel']:'' ),'phone'=>sanitize_text_field( isset($row['phone'])?$row['phone']:'' ),'email'=>$email,'address'=>sanitize_textarea_field( isset($row['address'])?$row['address']:'' ),'status'=>$status,'order'=>max(0,(int)(isset($row['order'])?$row['order']:0)),'image_url'=>$image_url,'image_alt'=>sanitize_text_field( isset($row['image_alt'])?$row['image_alt']:'' ),'errors'=>$errors,'warnings'=>$warnings,
		);
	}

	private function import_row( array $row, $mode, $import_images ) {
		$existing = '' !== $row['external_id'] ? $this->find_by_external_id( $row['external_id'] ) : 0;
		if ( $existing && 'create_only' === $mode ) { return array( 'status'=>'skipped','message'=>__( 'Ya existe un servicio con ese ID externo.', 'wp-headless-api-core' ) ); }
		$postarr = array( 'post_type'=>Services_Post_Type::POST_TYPE,'post_title'=>$row['title'],'post_status'=>$row['status'],'menu_order'=>$row['order'] );
		if ( $existing ) { $postarr['ID']=$existing; $post_id=wp_update_post( $postarr, true ); $status='updated'; } else { $post_id=wp_insert_post( $postarr, true ); $status='created'; }
		if ( is_wp_error( $post_id ) || ! $post_id ) { return array( 'status'=>'failed','message'=>is_wp_error($post_id)?$post_id->get_error_message():__( 'No se pudo guardar el servicio.', 'wp-headless-api-core' ) ); }
		$map = array(
			Services_Post_Type::META_DESCRIPTION=>$row['description'], Services_Post_Type::META_AUDIENCE=>$row['audience'], Services_Post_Type::META_DEPARTMENT=>$row['department'], Services_Post_Type::META_REQUIREMENTS=>$row['requirements'], Services_Post_Type::META_PROCEDURE=>$row['procedure'], Services_Post_Type::META_SCHEDULE=>$row['schedule'], Services_Post_Type::META_COST=>$row['cost'], Services_Post_Type::META_DURATION=>$row['duration'], Services_Post_Type::META_CHANNEL=>$row['channel'], Services_Post_Type::META_PHONE=>$row['phone'], Services_Post_Type::META_EMAIL=>$row['email'], Services_Post_Type::META_ADDRESS=>$row['address'], Services_Post_Type::META_ALT=>$row['image_alt'], Services_Post_Type::META_EXTERNAL_ID=>$row['external_id'],
		);
		foreach ( $map as $key=>$value ) { if ( is_array($value) ? empty($value) : ''===$value ) { delete_post_meta($post_id,$key); } else { update_post_meta($post_id,$key,$value); } }
		if ( $import_images && '' !== $row['image_url'] ) { $attachment_id = $this->resolve_image( $row['image_url'], $row['image_alt'] ); if ( $attachment_id > 0 ) { set_post_thumbnail( $post_id, $attachment_id ); } }
		return array( 'status'=>$status,'message'=>$existing?__( 'Servicio actualizado.', 'wp-headless-api-core' ):__( 'Servicio creado.', 'wp-headless-api-core' ) );
	}

	private function find_by_external_id( $external_id ) {
		$posts = get_posts( array( 'post_type'=>Services_Post_Type::POST_TYPE,'post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>Services_Post_Type::META_EXTERNAL_ID,'meta_value'=>$external_id,'no_found_rows'=>true ) );
		return empty( $posts ) ? 0 : (int) $posts[0];
	}

	private function resolve_image( $url, $alt ) {
		$uploads = wp_get_upload_dir();
		$path = (string) parse_url( $url, PHP_URL_PATH );
		$needle = '/wp-content/uploads/';
		$pos = strpos( $path, $needle );
		if ( false !== $pos ) {
			$relative = ltrim( substr( $path, $pos + strlen( $needle ) ), '/' );
			$ids = get_posts( array( 'post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_wp_attached_file','meta_value'=>$relative,'no_found_rows'=>true ) );
			if ( ! empty( $ids ) ) { if ( '' !== $alt ) { update_post_meta( (int)$ids[0], '_wp_attachment_image_alt', $alt ); } return (int)$ids[0]; }
		}
		if ( ! function_exists( 'media_sideload_image' ) ) { require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; }
		$id = media_sideload_image( $url, 0, $alt, 'id' );
		if ( is_wp_error( $id ) ) { return 0; }
		if ( '' !== $alt ) { update_post_meta( (int)$id, '_wp_attachment_image_alt', $alt ); }
		return (int)$id;
	}

	private function transient_key( $token ) { return 'hac_services_import_' . md5( $token ); }
}
