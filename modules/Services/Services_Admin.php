<?php
/**
 * Services guided admin workspace.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Services_Admin {
	const NONCE_ACTION = 'headless_service_profile_save';
	const NONCE_NAME   = 'headless_service_profile_nonce';

	public function register() {
		add_action( 'add_meta_boxes_' . Services_Post_Type::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Services_Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'register_submenus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_submenus() {
		add_submenu_page(
			'edit.php?post_type=' . Services_Post_Type::POST_TYPE,
			__( 'Importar servicios', 'wp-headless-api-core' ),
			__( 'Importar', 'wp-headless-api-core' ),
			'edit_posts',
			'headless-services-import',
			array( $this, 'render_import_page' )
		);
	}

	public function add_meta_boxes() {
		add_meta_box(
			'headless-service-profile',
			__( 'Ficha del servicio', 'wp-headless-api-core' ),
			array( $this, 'render' ),
			Services_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function enqueue( $hook ) {
		$screen = get_current_screen();
		$is_service = $screen && Services_Post_Type::POST_TYPE === $screen->post_type;
		$is_import  = isset( $_GET['page'] ) && 'headless-services-import' === sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( ! $is_service && ! $is_import ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'headless-directory-admin', plugins_url( 'assets/admin/directory.css', HEADLESS_API_CORE_FILE ), array(), HEADLESS_API_CORE_VERSION );
	}

	public function render( WP_Post $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$values = array(
			'description' => get_post_meta( $post->ID, Services_Post_Type::META_DESCRIPTION, true ),
			'audience'    => get_post_meta( $post->ID, Services_Post_Type::META_AUDIENCE, true ),
			'department'  => get_post_meta( $post->ID, Services_Post_Type::META_DEPARTMENT, true ),
			'procedure'   => get_post_meta( $post->ID, Services_Post_Type::META_PROCEDURE, true ),
			'schedule'    => get_post_meta( $post->ID, Services_Post_Type::META_SCHEDULE, true ),
			'cost'        => get_post_meta( $post->ID, Services_Post_Type::META_COST, true ),
			'duration'    => get_post_meta( $post->ID, Services_Post_Type::META_DURATION, true ),
			'channel'     => get_post_meta( $post->ID, Services_Post_Type::META_CHANNEL, true ),
			'phone'       => get_post_meta( $post->ID, Services_Post_Type::META_PHONE, true ),
			'email'       => get_post_meta( $post->ID, Services_Post_Type::META_EMAIL, true ),
			'address'     => get_post_meta( $post->ID, Services_Post_Type::META_ADDRESS, true ),
			'alt'         => get_post_meta( $post->ID, Services_Post_Type::META_ALT, true ),
			'external_id' => get_post_meta( $post->ID, Services_Post_Type::META_EXTERNAL_ID, true ),
		);
		$requirements = Services_Post_Type::sanitize_requirements( get_post_meta( $post->ID, Services_Post_Type::META_REQUIREMENTS, true ) );
		if ( empty( $requirements ) ) { $requirements = array( '' ); }
		?>
		<div class="headless-directory-editor headless-services-editor">
			<div class="headless-directory-intro">
				<strong><?php esc_html_e( 'Completa la ficha pública del servicio.', 'wp-headless-api-core' ); ?></strong>
				<span><?php esc_html_e( 'El título se escribe arriba. Organiza cada bloque por separado para mantener el mismo contrato en WordPress y el frontend.', 'wp-headless-api-core' ); ?></span>
			</div>
			<div class="headless-directory-editor-grid">
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Presentación', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Descripción general y público al que va dirigido.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_description"><?php esc_html_e( 'Descripción del servicio', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_description" class="widefat" name="headless_service_description" rows="7"><?php echo esc_textarea( $values['description'] ); ?></textarea></div>
					<div class="headless-directory-field"><label for="headless_service_audience"><?php esc_html_e( 'A quién va dirigido', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_audience" class="widefat" name="headless_service_audience" rows="5"><?php echo esc_textarea( $values['audience'] ); ?></textarea></div>
				</section>
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Responsable', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Área institucional que ofrece el servicio.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_department"><?php esc_html_e( 'Departamento que lo ofrece', 'wp-headless-api-core' ); ?></label><input id="headless_service_department" class="widefat" name="headless_service_department" type="text" value="<?php echo esc_attr( $values['department'] ); ?>"></div>
					<div class="headless-directory-field"><label for="headless_service_channel"><?php esc_html_e( 'Canal', 'wp-headless-api-core' ); ?></label><input id="headless_service_channel" class="widefat" name="headless_service_channel" type="text" value="<?php echo esc_attr( $values['channel'] ); ?>" placeholder="<?php esc_attr_e( 'Ej.: Presencial', 'wp-headless-api-core' ); ?>"></div>
				</section>
				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Requerimientos o requisitos', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Cada requisito es un renglón independiente y puede reordenarse.', 'wp-headless-api-core' ); ?></p></div></div>
					<ul id="hac-service-requirements" style="margin:0;padding:0;list-style:none">
						<?php foreach ( $requirements as $requirement ) : ?>
							<li class="hac-service-requirement" style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px"><span class="dashicons dashicons-menu hac-service-drag" style="cursor:move;margin-top:8px"></span><textarea class="widefat" name="headless_service_requirements[]" rows="2"><?php echo esc_textarea( $requirement ); ?></textarea><button type="button" class="button-link-delete hac-service-remove" style="margin-top:8px"><?php esc_html_e( 'Quitar', 'wp-headless-api-core' ); ?></button></li>
						<?php endforeach; ?>
					</ul>
					<button type="button" class="button" id="hac-service-add-requirement"><?php esc_html_e( 'Añadir requisito', 'wp-headless-api-core' ); ?></button>
				</section>
				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Procedimiento', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Pasos que debe seguir el usuario para recibir el servicio.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><textarea id="headless_service_procedure" class="widefat" name="headless_service_procedure" rows="7"><?php echo esc_textarea( $values['procedure'] ); ?></textarea></div>
				</section>
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Disponibilidad', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Horario, costo y tiempo estimado.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_schedule"><?php esc_html_e( 'Horario', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_schedule" class="widefat" name="headless_service_schedule" rows="4"><?php echo esc_textarea( $values['schedule'] ); ?></textarea></div>
					<div class="headless-directory-field"><label for="headless_service_cost"><?php esc_html_e( 'Costo', 'wp-headless-api-core' ); ?></label><input id="headless_service_cost" class="widefat" name="headless_service_cost" type="text" value="<?php echo esc_attr( $values['cost'] ); ?>"></div>
					<div class="headless-directory-field"><label for="headless_service_duration"><?php esc_html_e( 'Tiempo estimado', 'wp-headless-api-core' ); ?></label><input id="headless_service_duration" class="widefat" name="headless_service_duration" type="text" value="<?php echo esc_attr( $values['duration'] ); ?>"></div>
				</section>
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Contacto público', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Información visible para el usuario final.', 'wp-headless-api-core' ); ?></p></div><span class="headless-directory-badge is-optional"><?php esc_html_e( 'Opcional', 'wp-headless-api-core' ); ?></span></div>
					<div class="headless-directory-field"><label for="headless_service_phone"><?php esc_html_e( 'Teléfono', 'wp-headless-api-core' ); ?></label><input id="headless_service_phone" class="widefat" name="headless_service_phone" type="text" value="<?php echo esc_attr( $values['phone'] ); ?>"></div>
					<div class="headless-directory-field"><label for="headless_service_email"><?php esc_html_e( 'Correo electrónico', 'wp-headless-api-core' ); ?></label><input id="headless_service_email" class="widefat" name="headless_service_email" type="email" value="<?php echo esc_attr( $values['email'] ); ?>"></div>
					<div class="headless-directory-field"><label for="headless_service_address"><?php esc_html_e( 'Dirección', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_address" class="widefat" name="headless_service_address" rows="4"><?php echo esc_textarea( $values['address'] ); ?></textarea></div>
				</section>
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Accesibilidad', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Describe la imagen principal para lectores de pantalla.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_alt"><?php esc_html_e( 'Texto alternativo de la imagen', 'wp-headless-api-core' ); ?></label><input id="headless_service_alt" class="widefat" name="headless_service_alt" type="text" value="<?php echo esc_attr( $values['alt'] ); ?>"><p class="description"><?php esc_html_e( 'La imagen principal se gestiona desde Imagen destacada.', 'wp-headless-api-core' ); ?></p></div>
				</section>
				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Organización e importación', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'El orden global se gestiona desde Servicios → Ordenar. El ID externo se usa para upsert.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-form-row"><div class="headless-directory-field"><label for="headless_service_order"><?php esc_html_e( 'Orden global', 'wp-headless-api-core' ); ?></label><input id="headless_service_order" class="small-text" type="number" min="0" step="1" name="headless_service_order" value="<?php echo esc_attr( (int) $post->menu_order ); ?>"></div><div class="headless-directory-field"><label for="headless_service_external_id"><?php esc_html_e( 'ID externo de importación', 'wp-headless-api-core' ); ?></label><input id="headless_service_external_id" class="regular-text" name="headless_service_external_id" type="text" value="<?php echo esc_attr( $values['external_id'] ); ?>"><p class="description"><?php esc_html_e( 'Privado. Nunca se expone en la API pública.', 'wp-headless-api-core' ); ?></p></div></div>
				</section>
			</div>
		</div>
		<script>jQuery(function($){var $list=$('#hac-service-requirements');$list.sortable({handle:'.hac-service-drag'});$('#hac-service-add-requirement').on('click',function(){$list.append('<li class="hac-service-requirement" style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px"><span class="dashicons dashicons-menu hac-service-drag" style="cursor:move;margin-top:8px"></span><textarea class="widefat" name="headless_service_requirements[]" rows="2"></textarea><button type="button" class="button-link-delete hac-service-remove" style="margin-top:8px">Quitar</button></li>');});$list.on('click','.hac-service-remove',function(){var $rows=$list.children();if($rows.length===1){$(this).siblings('textarea').val('');return;}$(this).closest('li').remove();});});</script>
		<?php
	}

	public function render_import_page() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( esc_html__( 'No tienes permisos para importar servicios.', 'wp-headless-api-core' ) ); }
		$download = wp_nonce_url( admin_url( 'admin-post.php?action=headless_services_download_template' ), Services_Importer::NONCE_ACTION );
		$nonce = wp_create_nonce( Services_Importer::NONCE_ACTION );
		?>
		<div class="wrap headless-directory-workspace headless-directory-import-page">
			<h1><?php esc_html_e( 'Importar servicios', 'wp-headless-api-core' ); ?></h1>
			<p class="headless-directory-lead"><?php esc_html_e( 'Carga un CSV, valida la vista previa y luego crea o actualiza servicios por ID externo.', 'wp-headless-api-core' ); ?></p>
			<div class="headless-directory-import-layout">
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( '1. Preparar archivo', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Usa la plantilla oficial para mantener las columnas esperadas.', 'wp-headless-api-core' ); ?></p></div></div>
					<a class="button" href="<?php echo esc_url( $download ); ?>"><?php esc_html_e( 'Descargar plantilla CSV', 'wp-headless-api-core' ); ?></a>
				</section>
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( '2. Validar y previsualizar', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Nada se escribe en WordPress durante esta etapa.', 'wp-headless-api-core' ); ?></p></div></div>
					<input type="file" id="hac-services-import-file" accept=".csv,text/csv" />
					<button type="button" class="button button-primary" id="hac-services-preview" style="margin-left:8px"><?php esc_html_e( 'Validar archivo', 'wp-headless-api-core' ); ?></button>
					<div id="hac-services-preview-result" style="margin-top:16px"></div>
				</section>
				<section class="headless-directory-section" id="hac-services-import-step" hidden>
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( '3. Importar', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'La importación se procesa por lotes de hasta 25 filas.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="hac-services-mode"><?php esc_html_e( 'Modo', 'wp-headless-api-core' ); ?></label><select id="hac-services-mode"><option value="create_only"><?php esc_html_e( 'Crear nuevos solamente', 'wp-headless-api-core' ); ?></option><option value="upsert"><?php esc_html_e( 'Crear y actualizar por ID externo', 'wp-headless-api-core' ); ?></option></select></div>
					<label style="display:block;margin:14px 0"><input type="checkbox" id="hac-services-images" <?php disabled( ! current_user_can( 'upload_files' ) ); ?>> <?php esc_html_e( 'Reutilizar/importar image_url como imagen destacada', 'wp-headless-api-core' ); ?></label>
					<button type="button" class="button button-primary" id="hac-services-run"><?php esc_html_e( 'Comenzar importación', 'wp-headless-api-core' ); ?></button>
					<div id="hac-services-progress" style="margin-top:14px"></div>
				</section>
			</div>
		</div>
		<script>
		jQuery(function($){var token='',offset=0,total=0,stats={created:0,updated:0,skipped:0,failed:0};
		$('#hac-services-preview').on('click',function(){var file=$('#hac-services-import-file')[0].files[0];if(!file){alert('Selecciona un CSV.');return;}var fd=new FormData();fd.append('action','headless_services_preview_import');fd.append('nonce','<?php echo esc_js( $nonce ); ?>');fd.append('file',file);$('#hac-services-preview').prop('disabled',true);$('#hac-services-preview-result').text('Validando archivo…');$.ajax({url:ajaxurl,type:'POST',data:fd,processData:false,contentType:false}).done(function(r){if(!r||!r.success){$('#hac-services-preview-result').text(r&&r.data&&r.data.message?r.data.message:'No se pudo validar.');return;}token=r.data.token;total=r.data.totalRows;offset=0;stats={created:0,updated:0,skipped:0,failed:0};var s=r.data.summary;$('#hac-services-preview-result').html('<strong>'+total+' filas detectadas</strong><br>Listas: '+s.ready+' · Con advertencias: '+s.warning+' · Con errores: '+s.error);$('#hac-services-import-step').prop('hidden',false);}).fail(function(x){$('#hac-services-preview-result').text(x.responseJSON&&x.responseJSON.data&&x.responseJSON.data.message?x.responseJSON.data.message:'No se pudo validar.');}).always(function(){$('#hac-services-preview').prop('disabled',false);});});
		$('#hac-services-run').on('click',function(){if(!token)return;$(this).prop('disabled',true);runBatch();});
		function runBatch(){$.post(ajaxurl,{action:'headless_services_import_batch',nonce:'<?php echo esc_js( $nonce ); ?>',token:token,offset:offset,batchSize:25,mode:$('#hac-services-mode').val(),importImages:$('#hac-services-images').is(':checked')?1:0}).done(function(r){if(!r||!r.success){$('#hac-services-progress').text(r&&r.data&&r.data.message?r.data.message:'Error de importación.');$('#hac-services-run').prop('disabled',false);return;}var b=r.data.batchResult;['created','updated','skipped','failed'].forEach(function(k){stats[k]+=b[k]||0;});offset=r.data.offset;var pct=total?Math.round((offset/total)*100):100;$('#hac-services-progress').html('<strong>'+pct+'%</strong> · '+stats.created+' creados · '+stats.updated+' actualizados · '+stats.skipped+' omitidos · '+stats.failed+' fallidos');if(r.data.done){$('#hac-services-run').prop('disabled',false).text('Importación completada');token='';}else{runBatch();}}).fail(function(){$('#hac-services-progress').text('Error de importación.');$('#hac-services-run').prop('disabled',false);});}
		});
		</script>
		<?php
	}

	public function save( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || Services_Post_Type::POST_TYPE !== $post->post_type ) { return; }
		$map = array(
			'headless_service_description'=>array(Services_Post_Type::META_DESCRIPTION,'sanitize_textarea_field'),'headless_service_audience'=>array(Services_Post_Type::META_AUDIENCE,'sanitize_textarea_field'),'headless_service_department'=>array(Services_Post_Type::META_DEPARTMENT,'sanitize_text_field'),'headless_service_procedure'=>array(Services_Post_Type::META_PROCEDURE,'sanitize_textarea_field'),'headless_service_schedule'=>array(Services_Post_Type::META_SCHEDULE,'sanitize_textarea_field'),'headless_service_cost'=>array(Services_Post_Type::META_COST,'sanitize_text_field'),'headless_service_duration'=>array(Services_Post_Type::META_DURATION,'sanitize_text_field'),'headless_service_channel'=>array(Services_Post_Type::META_CHANNEL,'sanitize_text_field'),'headless_service_phone'=>array(Services_Post_Type::META_PHONE,'sanitize_text_field'),'headless_service_email'=>array(Services_Post_Type::META_EMAIL,array(Services_Post_Type::class,'sanitize_email_value')),'headless_service_address'=>array(Services_Post_Type::META_ADDRESS,'sanitize_textarea_field'),'headless_service_alt'=>array(Services_Post_Type::META_ALT,'sanitize_text_field'),'headless_service_external_id'=>array(Services_Post_Type::META_EXTERNAL_ID,array(Services_Post_Type::class,'sanitize_external_id')),
		);
		foreach ( $map as $field=>$config ) { $raw=isset($_POST[$field])?wp_unslash($_POST[$field]):''; $value=call_user_func($config[1],$raw); ''===$value?delete_post_meta($post_id,$config[0]):update_post_meta($post_id,$config[0],$value); }
		$raw_requirements=isset($_POST['headless_service_requirements'])&&is_array($_POST['headless_service_requirements'])?wp_unslash($_POST['headless_service_requirements']):array(); $requirements=Services_Post_Type::sanitize_requirements($raw_requirements); empty($requirements)?delete_post_meta($post_id,Services_Post_Type::META_REQUIREMENTS):update_post_meta($post_id,Services_Post_Type::META_REQUIREMENTS,$requirements);
		$order=isset($_POST['headless_service_order'])?max(0,(int)wp_unslash($_POST['headless_service_order'])):0; if((int)$post->menu_order!==$order){wp_update_post(array('ID'=>$post_id,'menu_order'=>$order));}
	}
}
