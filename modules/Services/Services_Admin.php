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

	/** Prevent recursive menu_order saves. */
	private $saving = false;

	public function register() {
		add_action( 'add_meta_boxes_' . Services_Post_Type::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Services_Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'register_submenus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'manage_' . Services_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'list_columns' ) );
		add_action( 'manage_' . Services_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
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
		remove_meta_box( 'postimagediv', Services_Post_Type::POST_TYPE, 'side' );
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
		$screen     = get_current_screen();
		$is_service = $screen && Services_Post_Type::POST_TYPE === $screen->post_type;
		$is_import  = isset( $_GET['page'] ) && 'headless-services-import' === sanitize_key( wp_unslash( $_GET['page'] ) );
		$is_order   = isset( $_GET['page'] ) && Services_Order::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );

		if ( ! $is_service && ! $is_import && ! $is_order ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'headless-directory-admin', plugins_url( 'assets/admin/directory.css', HEADLESS_API_CORE_FILE ), array(), HEADLESS_API_CORE_VERSION );
		wp_enqueue_script( 'headless-services-admin', plugins_url( 'assets/admin/services.js', HEADLESS_API_CORE_FILE ), array( 'jquery', 'jquery-ui-sortable' ), HEADLESS_API_CORE_VERSION, true );
		wp_localize_script(
			'headless-services-admin',
			'HeadlessServicesAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'orderNonce'  => wp_create_nonce( Services_Order::NONCE_ACTION ),
				'importNonce' => wp_create_nonce( Services_Importer::NONCE_ACTION ),
				'strings'     => array(
					'saving'      => __( 'Guardando…', 'wp-headless-api-core' ),
					'saved'       => __( 'Guardado', 'wp-headless-api-core' ),
					'error'       => __( 'No se pudo guardar.', 'wp-headless-api-core' ),
					'validating'  => __( 'Validando archivo…', 'wp-headless-api-core' ),
					'importing'    => __( 'Importando…', 'wp-headless-api-core' ),
					'chooseImage' => __( 'Seleccionar imagen del servicio', 'wp-headless-api-core' ),
					'useImage'    => __( 'Usar esta imagen', 'wp-headless-api-core' ),
				),
			)
		);
	}

	public function render( WP_Post $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$image_id = (int) get_post_thumbnail_id( $post );
		$values   = array(
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
		if ( empty( $requirements ) ) {
			$requirements = array( '' );
		}
		?>
		<div class="headless-directory-editor headless-services-editor">
			<div class="headless-directory-intro">
				<strong><?php esc_html_e( 'Completa la ficha pública del servicio.', 'wp-headless-api-core' ); ?></strong>
				<span><?php esc_html_e( 'Usamos el mismo sistema editorial de Directory, adaptado al contrato de Services. El título se escribe arriba.', 'wp-headless-api-core' ); ?></span>
			</div>

			<div class="headless-directory-editor-grid">
				<section class="headless-directory-section headless-directory-section--portrait">
					<div class="headless-directory-section-heading">
						<div><h3><?php esc_html_e( 'Imagen principal', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Imagen representativa del servicio para tarjetas y página de detalle.', 'wp-headless-api-core' ); ?></p></div>
						<span class="headless-directory-badge is-optional"><?php esc_html_e( 'Opcional', 'wp-headless-api-core' ); ?></span>
					</div>
					<?php $this->render_image_control( $image_id ); ?>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Presentación', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Descripción general y público al que va dirigido.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_description"><?php esc_html_e( 'Descripción del servicio', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_description" class="widefat" name="headless_service_description" rows="7" placeholder="<?php esc_attr_e( 'Descripción pública del servicio.', 'wp-headless-api-core' ); ?>"><?php echo esc_textarea( $values['description'] ); ?></textarea></div>
					<div class="headless-directory-field"><label for="headless_service_audience"><?php esc_html_e( 'A quién va dirigido', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_audience" class="widefat" name="headless_service_audience" rows="5" placeholder="<?php esc_attr_e( 'Usuarios que pueden acceder al servicio.', 'wp-headless-api-core' ); ?>"><?php echo esc_textarea( $values['audience'] ); ?></textarea></div>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Responsable', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Área institucional y canal de atención.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_department"><?php esc_html_e( 'Departamento que lo ofrece', 'wp-headless-api-core' ); ?></label><input id="headless_service_department" class="widefat" name="headless_service_department" type="text" value="<?php echo esc_attr( $values['department'] ); ?>" placeholder="<?php esc_attr_e( 'Ej.: Subdirección Operativa', 'wp-headless-api-core' ); ?>"></div>
					<div class="headless-directory-field"><label for="headless_service_channel"><?php esc_html_e( 'Canal', 'wp-headless-api-core' ); ?></label><input id="headless_service_channel" class="widefat" name="headless_service_channel" type="text" value="<?php echo esc_attr( $values['channel'] ); ?>" placeholder="<?php esc_attr_e( 'Ej.: Presencial', 'wp-headless-api-core' ); ?>"></div>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Disponibilidad', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Horario, costo y tiempo estimado.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_schedule"><?php esc_html_e( 'Horario', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_schedule" class="widefat" name="headless_service_schedule" rows="4"><?php echo esc_textarea( $values['schedule'] ); ?></textarea></div>
					<div class="headless-directory-field"><label for="headless_service_cost"><?php esc_html_e( 'Costo', 'wp-headless-api-core' ); ?></label><input id="headless_service_cost" class="widefat" name="headless_service_cost" type="text" value="<?php echo esc_attr( $values['cost'] ); ?>" placeholder="<?php esc_attr_e( 'Ej.: 0.00', 'wp-headless-api-core' ); ?>"></div>
					<div class="headless-directory-field"><label for="headless_service_duration"><?php esc_html_e( 'Tiempo estimado', 'wp-headless-api-core' ); ?></label><input id="headless_service_duration" class="widefat" name="headless_service_duration" type="text" value="<?php echo esc_attr( $values['duration'] ); ?>" placeholder="<?php esc_attr_e( 'Ej.: 30 minutos promedio', 'wp-headless-api-core' ); ?>"></div>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Requerimientos o requisitos', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Cada requisito es un renglón independiente. Arrastra para cambiar el orden.', 'wp-headless-api-core' ); ?></p></div></div>
					<ul id="headless-services-requirements" class="headless-services-requirements">
						<?php foreach ( $requirements as $requirement ) : ?>
							<li class="headless-services-requirement-row"><span class="dashicons dashicons-menu headless-directory-drag-handle" aria-hidden="true"></span><textarea class="widefat" name="headless_service_requirements[]" rows="2"><?php echo esc_textarea( $requirement ); ?></textarea><button type="button" class="button-link-delete headless-services-remove-requirement"><?php esc_html_e( 'Quitar', 'wp-headless-api-core' ); ?></button></li>
						<?php endforeach; ?>
					</ul>
					<button type="button" class="button" id="headless-services-add-requirement"><?php esc_html_e( 'Añadir requisito', 'wp-headless-api-core' ); ?></button>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Procedimiento', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Pasos que debe seguir el usuario para recibir el servicio.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_procedure"><?php esc_html_e( 'Procedimientos a seguir', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_procedure" class="widefat" name="headless_service_procedure" rows="7"><?php echo esc_textarea( $values['procedure'] ); ?></textarea></div>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Contacto público', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Solo agrega información que realmente deba mostrarse en el sitio.', 'wp-headless-api-core' ); ?></p></div><span class="headless-directory-badge is-optional"><?php esc_html_e( 'Opcional', 'wp-headless-api-core' ); ?></span></div>
					<div class="headless-directory-field"><label for="headless_service_phone"><?php esc_html_e( 'Teléfono', 'wp-headless-api-core' ); ?></label><input id="headless_service_phone" class="widefat" name="headless_service_phone" type="text" value="<?php echo esc_attr( $values['phone'] ); ?>" placeholder="(809) 555-0000 Ext. 0000"></div>
					<div class="headless-directory-field"><label for="headless_service_email"><?php esc_html_e( 'Correo electrónico', 'wp-headless-api-core' ); ?></label><input id="headless_service_email" class="widefat" name="headless_service_email" type="email" value="<?php echo esc_attr( $values['email'] ); ?>" placeholder="servicio@example.org"></div>
					<div class="headless-directory-field"><label for="headless_service_address"><?php esc_html_e( 'Dirección', 'wp-headless-api-core' ); ?></label><textarea id="headless_service_address" class="widefat" name="headless_service_address" rows="4"><?php echo esc_textarea( $values['address'] ); ?></textarea></div>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Accesibilidad', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Describe brevemente la imagen para lectores de pantalla.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label for="headless_service_alt"><?php esc_html_e( 'Descripción de la imagen', 'wp-headless-api-core' ); ?></label><input id="headless_service_alt" class="widefat" name="headless_service_alt" type="text" value="<?php echo esc_attr( $values['alt'] ); ?>" placeholder="<?php esc_attr_e( 'Ej.: Área de atención del servicio...', 'wp-headless-api-core' ); ?>"><p class="description"><?php esc_html_e( 'Si lo dejas vacío, se utilizará el texto alternativo guardado en Medios.', 'wp-headless-api-core' ); ?></p></div>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Organización', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'El drag & drop es la forma recomendada de ordenar. El ID externo se utiliza para importación y upsert.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-form-row">
						<div class="headless-directory-field"><label for="headless_service_order"><?php esc_html_e( 'Orden global', 'wp-headless-api-core' ); ?></label><input id="headless_service_order" class="small-text" type="number" min="0" step="1" name="headless_service_order" value="<?php echo esc_attr( (int) $post->menu_order ); ?>"></div>
						<div class="headless-directory-field"><label for="headless_service_external_id"><?php esc_html_e( 'ID externo de importación', 'wp-headless-api-core' ); ?></label><input id="headless_service_external_id" class="regular-text" name="headless_service_external_id" type="text" value="<?php echo esc_attr( $values['external_id'] ); ?>" placeholder="SRV-001"><p class="description"><?php esc_html_e( 'Privado. Sirve para actualizar esta ficha de forma segura desde CSV.', 'wp-headless-api-core' ); ?></p></div>
					</div>
				</section>
			</div>
		</div>
		<style>
			.headless-services-requirements{margin:0 0 12px;padding:0;list-style:none;display:grid;gap:10px}.headless-services-requirement-row{display:grid;grid-template-columns:38px minmax(0,1fr) auto;gap:10px;align-items:start;margin:0;padding:10px;border:1px solid #e1e1e1;border-radius:9px;background:#fcfcfc}.headless-services-requirement-row .headless-directory-drag-handle{margin-top:4px}.headless-services-remove-requirement{margin-top:8px}
		</style>
		<?php
	}

	private function render_image_control( $image_id ) {
		$image_id = (int) $image_id;
		$src      = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		$meta     = $image_id > 0 ? wp_get_attachment_metadata( $image_id ) : array();
		$width    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$height   = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		?>
		<div class="headless-directory-image-control">
			<div class="headless-directory-image-meta"><span class="headless-directory-chip"><?php esc_html_e( 'Recomendado: 1200 × 800 px · Horizontal', 'wp-headless-api-core' ); ?></span><span class="headless-directory-chip headless-directory-image-actual" <?php echo $image_id > 0 ? '' : 'hidden'; ?>><?php echo $image_id > 0 ? esc_html( sprintf( 'Actual: %d × %d px', $width, $height ) ) : ''; ?></span></div>
			<div class="headless-directory-image-preview <?php echo $image_id > 0 ? 'has-image' : ''; ?>" style="aspect-ratio:3/2;max-width:430px">
				<div class="headless-directory-image-empty" <?php echo $image_id > 0 ? 'hidden' : ''; ?>><span class="dashicons dashicons-format-image"></span><span><?php esc_html_e( 'Selecciona una imagen', 'wp-headless-api-core' ); ?></span></div>
				<img class="headless-directory-image-preview-image" src="<?php echo esc_url( $src ); ?>" alt="" <?php echo $image_id > 0 ? '' : 'hidden'; ?> />
			</div>
			<input class="headless-services-image-id" type="hidden" name="headless_service_image_id" value="<?php echo esc_attr( $image_id ); ?>" />
			<div class="headless-directory-image-actions"><button type="button" class="button headless-services-select-image" data-empty-label="<?php esc_attr_e( 'Seleccionar imagen', 'wp-headless-api-core' ); ?>" data-selected-label="<?php esc_attr_e( 'Cambiar imagen', 'wp-headless-api-core' ); ?>"><?php echo esc_html( $image_id > 0 ? __( 'Cambiar imagen', 'wp-headless-api-core' ) : __( 'Seleccionar imagen', 'wp-headless-api-core' ) ); ?></button><button type="button" class="button-link-delete headless-services-remove-image" <?php echo $image_id > 0 ? '' : 'hidden'; ?>><?php esc_html_e( 'Quitar imagen', 'wp-headless-api-core' ); ?></button></div>
		</div>
		<?php
	}

	public function save( $post_id, WP_Post $post ) {
		if ( $this->saving || ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$image_id = isset( $_POST['headless_service_image_id'] ) ? absint( wp_unslash( $_POST['headless_service_image_id'] ) ) : 0;
		if ( $image_id > 0 && wp_attachment_is_image( $image_id ) ) {
			set_post_thumbnail( $post_id, $image_id );
		} else {
			delete_post_thumbnail( $post_id );
		}

		$fields = array(
			Services_Post_Type::META_DESCRIPTION => isset( $_POST['headless_service_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['headless_service_description'] ) ) : '',
			Services_Post_Type::META_AUDIENCE    => isset( $_POST['headless_service_audience'] ) ? sanitize_textarea_field( wp_unslash( $_POST['headless_service_audience'] ) ) : '',
			Services_Post_Type::META_DEPARTMENT  => isset( $_POST['headless_service_department'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_service_department'] ) ) : '',
			Services_Post_Type::META_PROCEDURE   => isset( $_POST['headless_service_procedure'] ) ? sanitize_textarea_field( wp_unslash( $_POST['headless_service_procedure'] ) ) : '',
			Services_Post_Type::META_SCHEDULE    => isset( $_POST['headless_service_schedule'] ) ? sanitize_textarea_field( wp_unslash( $_POST['headless_service_schedule'] ) ) : '',
			Services_Post_Type::META_COST        => isset( $_POST['headless_service_cost'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_service_cost'] ) ) : '',
			Services_Post_Type::META_DURATION    => isset( $_POST['headless_service_duration'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_service_duration'] ) ) : '',
			Services_Post_Type::META_CHANNEL     => isset( $_POST['headless_service_channel'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_service_channel'] ) ) : '',
			Services_Post_Type::META_PHONE       => isset( $_POST['headless_service_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_service_phone'] ) ) : '',
			Services_Post_Type::META_EMAIL       => isset( $_POST['headless_service_email'] ) ? Services_Post_Type::sanitize_email_value( wp_unslash( $_POST['headless_service_email'] ) ) : '',
			Services_Post_Type::META_ADDRESS     => isset( $_POST['headless_service_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['headless_service_address'] ) ) : '',
			Services_Post_Type::META_ALT         => isset( $_POST['headless_service_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_service_alt'] ) ) : '',
			Services_Post_Type::META_EXTERNAL_ID => isset( $_POST['headless_service_external_id'] ) ? Services_Post_Type::sanitize_external_id( wp_unslash( $_POST['headless_service_external_id'] ) ) : '',
		);
		foreach ( $fields as $key => $value ) {
			'' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
		}

		$requirements = isset( $_POST['headless_service_requirements'] ) && is_array( $_POST['headless_service_requirements'] ) ? Services_Post_Type::sanitize_requirements( wp_unslash( $_POST['headless_service_requirements'] ) ) : array();
		empty( $requirements ) ? delete_post_meta( $post_id, Services_Post_Type::META_REQUIREMENTS ) : update_post_meta( $post_id, Services_Post_Type::META_REQUIREMENTS, $requirements );

		$order = isset( $_POST['headless_service_order'] ) ? max( 0, (int) wp_unslash( $_POST['headless_service_order'] ) ) : 0;
		if ( (int) $post->menu_order !== $order ) {
			$this->saving = true;
			wp_update_post( array( 'ID' => $post_id, 'menu_order' => $order ) );
			$this->saving = false;
		}
	}

	public function list_columns( $columns ) {
		return array(
			'cb'                  => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'services_image'      => __( 'Imagen', 'wp-headless-api-core' ),
			'title'               => __( 'Servicio', 'wp-headless-api-core' ),
			'services_department' => __( 'Departamento', 'wp-headless-api-core' ),
			'services_channel'    => __( 'Canal', 'wp-headless-api-core' ),
			'services_order'      => __( 'Orden', 'wp-headless-api-core' ),
			'date'                => isset( $columns['date'] ) ? $columns['date'] : __( 'Fecha', 'wp-headless-api-core' ),
		);
	}

	public function render_list_column( $column, $post_id ) {
		if ( 'services_image' === $column ) {
			$image = get_the_post_thumbnail( $post_id, array( 88, 58 ), array( 'class' => 'headless-directory-list-thumb', 'style' => 'width:72px;height:52px' ) );
			echo $image ? wp_kses_post( $image ) : '<span class="headless-directory-no-image">' . esc_html__( 'Sin imagen', 'wp-headless-api-core' ) . '</span>';
			return;
		}
		if ( 'services_department' === $column ) { echo esc_html( (string) get_post_meta( $post_id, Services_Post_Type::META_DEPARTMENT, true ) ); return; }
		if ( 'services_channel' === $column ) { echo esc_html( (string) get_post_meta( $post_id, Services_Post_Type::META_CHANNEL, true ) ); return; }
		if ( 'services_order' === $column ) { echo esc_html( (string) get_post_field( 'menu_order', $post_id ) ); }
	}

	public function render_import_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para importar servicios.', 'wp-headless-api-core' ) );
		}
		$download = wp_nonce_url( admin_url( 'admin-post.php?action=headless_services_download_template' ), Services_Importer::NONCE_ACTION );
		?>
		<div class="wrap headless-directory-workspace headless-directory-import-page">
			<h1><?php esc_html_e( 'Importar servicios', 'wp-headless-api-core' ); ?></h1>
			<p class="headless-directory-lead"><?php esc_html_e( 'Carga muchos servicios desde CSV sin crear cada ficha manualmente. Primero validamos y mostramos una vista previa; nada se guarda hasta que confirmes.', 'wp-headless-api-core' ); ?></p>
			<div class="headless-directory-import-layout">
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( '1. Archivo', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Formato admitido: CSV (.csv).', 'wp-headless-api-core' ); ?></p></div><a class="button" href="<?php echo esc_url( $download ); ?>"><span class="dashicons dashicons-download" style="margin-top:4px"></span> <?php esc_html_e( 'Descargar plantilla CSV', 'wp-headless-api-core' ); ?></a></div>
					<label class="headless-directory-dropzone headless-services-dropzone">
						<span class="dashicons dashicons-upload"></span><strong><?php esc_html_e( 'Arrastra aquí tu CSV', 'wp-headless-api-core' ); ?></strong><span><?php esc_html_e( 'o haz clic para seleccionar .csv', 'wp-headless-api-core' ); ?></span>
						<input class="headless-directory-import-file headless-services-import-file" type="file" accept=".csv,text/csv" />
					</label>
					<div class="headless-directory-import-file-name"></div>
					<button type="button" class="button button-primary headless-services-validate-import" disabled><?php esc_html_e( 'Validar archivo', 'wp-headless-api-core' ); ?></button>
				</section>

				<section class="headless-directory-section headless-services-import-preview" hidden>
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( '2. Vista previa', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Revisa el resultado antes de escribir contenido en WordPress.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-import-kpis"></div>
					<div class="headless-directory-table-wrap"><table class="widefat striped headless-directory-import-preview"><thead><tr><th>#</th><th><?php esc_html_e( 'Servicio', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Departamento', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Requisitos', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Estado', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Validación', 'wp-headless-api-core' ); ?></th></tr></thead><tbody></tbody></table></div>
				</section>

				<section class="headless-directory-section headless-services-import-options" hidden>
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( '3. Importar', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Selecciona el modo y confirma la importación.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-import-controls">
						<div class="headless-directory-field"><label for="headless-services-import-mode"><?php esc_html_e( 'Modo', 'wp-headless-api-core' ); ?></label><select id="headless-services-import-mode"><option value="create_only"><?php esc_html_e( 'Crear nuevos solamente', 'wp-headless-api-core' ); ?></option><option value="upsert"><?php esc_html_e( 'Crear y actualizar por ID externo', 'wp-headless-api-core' ); ?></option></select></div>
						<label class="headless-directory-toggle"><input type="checkbox" class="headless-services-import-images" <?php disabled( ! current_user_can( 'upload_files' ) ); ?> /> <span><?php esc_html_e( 'Importar image_url como imagen principal', 'wp-headless-api-core' ); ?></span></label>
					</div>
					<button type="button" class="button button-primary headless-services-run-import"><?php esc_html_e( 'Comenzar importación', 'wp-headless-api-core' ); ?></button>
					<div class="headless-directory-progress" hidden><div class="headless-directory-progress-bar"><span></span></div><strong class="headless-directory-progress-label">0%</strong></div>
					<div class="headless-directory-import-result" aria-live="polite"></div>
				</section>
			</div>
		</div>
		<?php
	}
}
