<?php
/**
 * Guided Forms Core and Mail Templates admin workspace.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Forms_Admin {
	const FORM_NONCE_ACTION     = 'headless_form_save';
	const FORM_NONCE_NAME       = 'headless_form_nonce';
	const TEMPLATE_NONCE_ACTION = 'headless_mail_template_save';
	const TEMPLATE_NONCE_NAME   = 'headless_mail_template_nonce';

	/** Register editor hooks. */
	public function register() {
		add_action( 'add_meta_boxes_' . Forms_Post_Type::FORM_POST_TYPE, array( $this, 'add_form_meta_box' ) );
		add_action( 'add_meta_boxes_' . Forms_Post_Type::TEMPLATE_POST_TYPE, array( $this, 'add_template_meta_box' ) );
		add_action( 'save_post_' . Forms_Post_Type::FORM_POST_TYPE, array( $this, 'save_form' ), 10, 2 );
		add_action( 'save_post_' . Forms_Post_Type::TEMPLATE_POST_TYPE, array( $this, 'save_template' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** Replace default editor chrome with the guided form workspace. */
	public function add_form_meta_box() {
		add_meta_box(
			'headless-form-editor',
			__( 'Configuración del formulario', 'wp-headless-api-core' ),
			array( $this, 'render_form' ),
			Forms_Post_Type::FORM_POST_TYPE,
			'normal',
			'high'
		);
	}

	/** Add the reusable email-template editor. */
	public function add_template_meta_box() {
		add_meta_box(
			'headless-mail-template-editor',
			__( 'Diseño de correo', 'wp-headless-api-core' ),
			array( $this, 'render_template' ),
			Forms_Post_Type::TEMPLATE_POST_TYPE,
			'normal',
			'high'
		);
	}

	/** Load only on Forms/Mail Templates screens. */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( Forms_Post_Type::FORM_POST_TYPE, Forms_Post_Type::TEMPLATE_POST_TYPE ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'headless-directory-admin', plugins_url( 'assets/admin/directory.css', HEADLESS_API_CORE_FILE ), array(), HEADLESS_API_CORE_VERSION );
		wp_enqueue_style( 'headless-forms-admin', plugins_url( 'assets/admin/forms.css', HEADLESS_API_CORE_FILE ), array( 'headless-directory-admin' ), HEADLESS_API_CORE_VERSION );
		wp_enqueue_script( 'headless-forms-admin', plugins_url( 'assets/admin/forms.js', HEADLESS_API_CORE_FILE ), array( 'jquery', 'jquery-ui-sortable' ), HEADLESS_API_CORE_VERSION, true );

		wp_localize_script(
			'headless-forms-admin',
			'HeadlessFormsAdmin',
			array(
				'fieldTypes' => $this->field_type_labels(),
				'templates'  => $this->template_options(),
				'strings'    => array(
					'untitledSection' => __( 'Nueva sección', 'wp-headless-api-core' ),
					'untitledField'   => __( 'Nuevo campo', 'wp-headless-api-core' ),
					'untitledNotice'  => __( 'Nueva notificación', 'wp-headless-api-core' ),
					'confirmRemove'   => __( '¿Quitar este elemento?', 'wp-headless-api-core' ),
					'noSection'       => __( 'Sin sección', 'wp-headless-api-core' ),
				),
			)
		);
	}

	/** Main form builder UI. */
	public function render_form( WP_Post $post ) {
		wp_nonce_field( self::FORM_NONCE_ACTION, self::FORM_NONCE_NAME );

		$sections      = Forms_Schema::normalize_sections( get_post_meta( $post->ID, Forms_Post_Type::META_SECTIONS, true ) );
		$fields        = Forms_Schema::normalize_fields( get_post_meta( $post->ID, Forms_Post_Type::META_FIELDS, true ) );
		$notifications = Forms_Schema::normalize_notifications( get_post_meta( $post->ID, Forms_Post_Type::META_NOTIFICATIONS, true ) );
		$anti_spam     = Forms_Schema::normalize_anti_spam( get_post_meta( $post->ID, Forms_Post_Type::META_ANTI_SPAM, true ) );
		$rate_limit    = Forms_Schema::normalize_rate_limit( get_post_meta( $post->ID, Forms_Post_Type::META_RATE_LIMIT, true ) );
		$enabled_raw   = get_post_meta( $post->ID, Forms_Post_Type::META_ENABLED, true );
		$enabled       = '' === $enabled_raw ? true : (bool) $enabled_raw;
		?>
		<div class="headless-directory-editor headless-forms-editor">
			<div class="headless-directory-intro">
				<strong><?php esc_html_e( 'Define el contrato del formulario sin acoplarlo al frontend.', 'wp-headless-api-core' ); ?></strong>
				<span><?php esc_html_e( 'El Consumer decide cómo se ve; Forms Core controla campos, validación, disponibilidad, seguridad y notificaciones.', 'wp-headless-api-core' ); ?></span>
			</div>

			<div class="headless-directory-editor-grid">
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'General', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Nombre público, estado y mensajes de la experiencia.', 'wp-headless-api-core' ); ?></p></div><span class="headless-directory-badge <?php echo $enabled ? 'is-active' : 'is-optional'; ?>"><?php echo esc_html( $enabled ? __( 'Activo', 'wp-headless-api-core' ) : __( 'Inactivo', 'wp-headless-api-core' ) ); ?></span></div>
					<div class="headless-forms-toggle-row"><label><input type="checkbox" name="headless_form_enabled" value="1" <?php checked( $enabled ); ?>> <strong><?php esc_html_e( 'Formulario habilitado', 'wp-headless-api-core' ); ?></strong></label><p><?php esc_html_e( 'Solo los formularios publicados y habilitados se exponen por REST.', 'wp-headless-api-core' ); ?></p></div>
					<div class="headless-directory-field"><label for="headless_form_description"><?php esc_html_e( 'Descripción', 'wp-headless-api-core' ); ?></label><textarea id="headless_form_description" class="widefat" name="headless_form_description" rows="3"><?php echo esc_textarea( get_post_meta( $post->ID, Forms_Post_Type::META_DESCRIPTION, true ) ); ?></textarea></div>
					<div class="headless-forms-grid-2">
						<div class="headless-directory-field"><label for="headless_form_submit_label"><?php esc_html_e( 'Texto del botón', 'wp-headless-api-core' ); ?></label><input id="headless_form_submit_label" class="widefat" type="text" name="headless_form_submit_label" value="<?php echo esc_attr( get_post_meta( $post->ID, Forms_Post_Type::META_SUBMIT_LABEL, true ) ?: __( 'Enviar', 'wp-headless-api-core' ) ); ?>"></div>
						<div class="headless-directory-field"><label><?php esc_html_e( 'Schema', 'wp-headless-api-core' ); ?></label><input class="widefat" type="text" value="v<?php echo esc_attr( Forms_Schema::SCHEMA_VERSION ); ?>" readonly></div>
					</div>
					<div class="headless-directory-field"><label for="headless_form_success_message"><?php esc_html_e( 'Mensaje de éxito', 'wp-headless-api-core' ); ?></label><input id="headless_form_success_message" class="widefat" type="text" name="headless_form_success_message" value="<?php echo esc_attr( get_post_meta( $post->ID, Forms_Post_Type::META_SUCCESS_MESSAGE, true ) ); ?>"></div>
					<div class="headless-directory-field"><label for="headless_form_error_message"><?php esc_html_e( 'Mensaje de error', 'wp-headless-api-core' ); ?></label><input id="headless_form_error_message" class="widefat" type="text" name="headless_form_error_message" value="<?php echo esc_attr( get_post_meta( $post->ID, Forms_Post_Type::META_ERROR_MESSAGE, true ) ); ?>"></div>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced headless-forms-builder-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Secciones', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Agrupa campos por pasos o bloques editoriales. Puedes reordenarlas.', 'wp-headless-api-core' ); ?></p></div><button type="button" class="button button-secondary" data-forms-add="section"><?php esc_html_e( 'Añadir sección', 'wp-headless-api-core' ); ?></button></div>
					<input type="hidden" name="headless_form_sections_json" data-forms-json="sections" value="<?php echo esc_attr( wp_json_encode( $sections ) ); ?>">
					<div class="headless-forms-builder" data-forms-builder="sections"></div>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced headless-forms-builder-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Campos', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'El ancho usa una cuadrícula de 12: 12 = completo, 6 = dos columnas, 4 = tres columnas.', 'wp-headless-api-core' ); ?></p></div><button type="button" class="button button-primary" data-forms-add="field"><?php esc_html_e( 'Añadir campo', 'wp-headless-api-core' ); ?></button></div>
					<input type="hidden" name="headless_form_fields_json" data-forms-json="fields" value="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>">
					<div class="headless-forms-builder" data-forms-builder="fields"></div>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced headless-forms-builder-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Notificaciones', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Cada envío puede disparar uno o varios correos usando plantillas reutilizables.', 'wp-headless-api-core' ); ?></p></div><button type="button" class="button" data-forms-add="notification"><?php esc_html_e( 'Añadir notificación', 'wp-headless-api-core' ); ?></button></div>
					<input type="hidden" name="headless_form_notifications_json" data-forms-json="notifications" value="<?php echo esc_attr( wp_json_encode( $notifications ) ); ?>">
					<div class="headless-forms-builder" data-forms-builder="notifications"></div>
					<p class="description"><?php esc_html_e( 'Puedes usar correos estáticos o {{field.email}} como destinatario. Reply-To debe ser el nombre exacto de un campo email.', 'wp-headless-api-core' ); ?></p>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Seguridad y abuso', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Protecciones genéricas para formularios públicos headless.', 'wp-headless-api-core' ); ?></p></div><span class="headless-directory-badge is-active"><?php esc_html_e( 'Recomendado', 'wp-headless-api-core' ); ?></span></div>
					<div class="headless-forms-grid-2">
						<div class="headless-forms-toggle-row"><label><input type="checkbox" name="headless_form_honeypot" value="1" <?php checked( ! empty( $anti_spam['honeypot'] ) ); ?>> <strong><?php esc_html_e( 'Honeypot', 'wp-headless-api-core' ); ?></strong></label><input class="widefat" type="text" name="headless_form_honeypot_field" value="<?php echo esc_attr( $anti_spam['honeypotField'] ); ?>" placeholder="website"></div>
						<div class="headless-forms-toggle-row"><label><input type="checkbox" name="headless_form_rate_enabled" value="1" <?php checked( ! empty( $rate_limit['enabled'] ) ); ?>> <strong><?php esc_html_e( 'Rate limit', 'wp-headless-api-core' ); ?></strong></label><p><?php esc_html_e( 'Se aplica por formulario + IP hash.', 'wp-headless-api-core' ); ?></p></div>
					</div>
					<div class="headless-forms-grid-3">
						<div class="headless-directory-field"><label><?php esc_html_e( 'Máx. payload (bytes)', 'wp-headless-api-core' ); ?></label><input class="widefat" type="number" min="4096" max="262144" name="headless_form_max_payload" value="<?php echo esc_attr( $anti_spam['maxPayload'] ); ?>"></div>
						<div class="headless-directory-field"><label><?php esc_html_e( 'Envíos permitidos', 'wp-headless-api-core' ); ?></label><input class="widefat" type="number" min="1" max="100" name="headless_form_rate_max" value="<?php echo esc_attr( $rate_limit['max'] ); ?>"></div>
						<div class="headless-directory-field"><label><?php esc_html_e( 'Ventana (segundos)', 'wp-headless-api-core' ); ?></label><input class="widefat" type="number" min="60" max="86400" name="headless_form_rate_window" value="<?php echo esc_attr( $rate_limit['window'] ); ?>"></div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	/** Mail Template visual + HTML editor. */
	public function render_template( WP_Post $post ) {
		wp_nonce_field( self::TEMPLATE_NONCE_ACTION, self::TEMPLATE_NONCE_NAME );
		$mode   = get_post_meta( $post->ID, Forms_Post_Type::META_TEMPLATE_MODE, true ) ?: 'visual';
		$visual = Forms_Schema::decode_json( get_post_meta( $post->ID, Forms_Post_Type::META_TEMPLATE_VISUAL, true ), array() );
		$visual = wp_parse_args(
			$visual,
			array(
				'logoUrl'          => '',
				'outerBackground'  => '#f5f8fb',
				'headerBackground' => '#06477f',
				'labelColor'       => '#123f68',
				'valueColor'       => '#344f67',
				'separatorColor'   => '#e8eef3',
				'eyebrow'          => '{{form.name}}',
				'heading'          => __( 'Nueva solicitud', 'wp-headless-api-core' ),
				'intro'            => '',
				'footer'           => '',
			)
		);
		?>
		<div class="headless-directory-editor headless-mail-template-editor">
			<div class="headless-directory-intro"><strong><?php esc_html_e( 'Crea correos reutilizables sin duplicar HTML por formulario.', 'wp-headless-api-core' ); ?></strong><span><?php esc_html_e( 'Modo Visual genera HTML compatible; modo HTML permite una plantilla avanzada sanitizada.', 'wp-headless-api-core' ); ?></span></div>
			<div class="headless-directory-editor-grid">
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Mensaje', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Asunto, preheader y modo de edición.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Descripción interna', 'wp-headless-api-core' ); ?></label><textarea class="widefat" name="headless_template_description" rows="2"><?php echo esc_textarea( get_post_meta( $post->ID, Forms_Post_Type::META_TEMPLATE_DESCRIPTION, true ) ); ?></textarea></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Asunto por defecto', 'wp-headless-api-core' ); ?></label><input class="widefat" name="headless_template_subject" type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, Forms_Post_Type::META_TEMPLATE_SUBJECT, true ) ); ?>" placeholder="Nueva solicitud — {{field.fullName}}"></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Preheader', 'wp-headless-api-core' ); ?></label><input class="widefat" name="headless_template_preheader" type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, Forms_Post_Type::META_TEMPLATE_PREHEADER, true ) ); ?>"></div>
					<div class="headless-forms-mode-switch"><label><input type="radio" name="headless_template_mode" value="visual" <?php checked( $mode, 'visual' ); ?>> <?php esc_html_e( 'Visual', 'wp-headless-api-core' ); ?></label><label><input type="radio" name="headless_template_mode" value="html" <?php checked( $mode, 'html' ); ?>> <?php esc_html_e( 'HTML avanzado', 'wp-headless-api-core' ); ?></label></div>
				</section>

				<section class="headless-directory-section" data-template-panel="visual">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Diseño visual', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Basado en el patrón institucional validado, pero completamente reutilizable.', 'wp-headless-api-core' ); ?></p></div><span class="headless-directory-badge is-active"><?php esc_html_e( 'Visual', 'wp-headless-api-core' ); ?></span></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Logo', 'wp-headless-api-core' ); ?></label><div class="headless-forms-media-row"><input class="widefat" data-template-setting="logoUrl" name="headless_template_logo_url" type="url" value="<?php echo esc_attr( $visual['logoUrl'] ); ?>"><button type="button" class="button" data-template-media><?php esc_html_e( 'Seleccionar', 'wp-headless-api-core' ); ?></button></div></div>
					<div class="headless-forms-color-grid">
						<?php $this->render_color_field( 'outerBackground', __( 'Fondo exterior', 'wp-headless-api-core' ), $visual['outerBackground'] ); ?>
						<?php $this->render_color_field( 'headerBackground', __( 'Encabezado', 'wp-headless-api-core' ), $visual['headerBackground'] ); ?>
						<?php $this->render_color_field( 'labelColor', __( 'Etiquetas', 'wp-headless-api-core' ), $visual['labelColor'] ); ?>
						<?php $this->render_color_field( 'valueColor', __( 'Valores', 'wp-headless-api-core' ), $visual['valueColor'] ); ?>
						<?php $this->render_color_field( 'separatorColor', __( 'Separadores', 'wp-headless-api-core' ), $visual['separatorColor'] ); ?>
					</div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Eyebrow', 'wp-headless-api-core' ); ?></label><input class="widefat" data-template-setting="eyebrow" name="headless_template_eyebrow" type="text" value="<?php echo esc_attr( $visual['eyebrow'] ); ?>"></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Título', 'wp-headless-api-core' ); ?></label><input class="widefat" data-template-setting="heading" name="headless_template_heading" type="text" value="<?php echo esc_attr( $visual['heading'] ); ?>"></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Introducción', 'wp-headless-api-core' ); ?></label><textarea class="widefat" data-template-setting="intro" name="headless_template_intro" rows="3"><?php echo esc_textarea( $visual['intro'] ); ?></textarea></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Pie', 'wp-headless-api-core' ); ?></label><textarea class="widefat" data-template-setting="footer" name="headless_template_footer" rows="3"><?php echo esc_textarea( $visual['footer'] ); ?></textarea></div>
				</section>

				<section class="headless-directory-section" data-template-panel="html">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'HTML avanzado', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Para diseños personalizados. El HTML se sanitiza antes de almacenarse y enviarse.', 'wp-headless-api-core' ); ?></p></div><span class="headless-directory-badge is-optional">HTML</span></div>
					<textarea class="widefat headless-forms-code" name="headless_template_html" rows="18" spellcheck="false"><?php echo esc_textarea( get_post_meta( $post->ID, Forms_Post_Type::META_TEMPLATE_HTML, true ) ); ?></textarea>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Variables y fallback', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Las variables de campo preservan exactamente el name del formulario, incluido camelCase.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-forms-token-list"><code>{{site.name}}</code><code>{{site.url}}</code><code>{{form.name}}</code><code>{{form.slug}}</code><code>{{submission.id}}</code><code>{{submission.date}}</code><code>{{field.email}}</code><code>{{field.fullName}}</code><code>{{form.fields}}</code></div>
					<div class="headless-directory-field"><label><?php esc_html_e( 'Versión de texto', 'wp-headless-api-core' ); ?></label><textarea class="widefat" name="headless_template_text" rows="8"><?php echo esc_textarea( get_post_meta( $post->ID, Forms_Post_Type::META_TEMPLATE_TEXT, true ) ); ?></textarea><p class="description"><?php esc_html_e( 'Si queda vacía, se genera automáticamente desde los campos enviados.', 'wp-headless-api-core' ); ?></p></div>
				</section>

				<section class="headless-directory-section headless-forms-preview-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Vista previa', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Previsualización orientativa del modo Visual. El envío de prueba se añadirá en el siguiente gate.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-forms-template-preview" data-template-preview></div>
				</section>
			</div>
		</div>
		<?php
	}

	/** Persist form builder state. */
	public function save_form( $post_id, WP_Post $post ) {
		if ( ! $this->can_save( $post_id, self::FORM_NONCE_NAME, self::FORM_NONCE_ACTION ) ) {
			return;
		}

		update_post_meta( $post_id, Forms_Post_Type::META_ENABLED, isset( $_POST['headless_form_enabled'] ) );
		update_post_meta( $post_id, Forms_Post_Type::META_SCHEMA_VERSION, Forms_Schema::SCHEMA_VERSION );
		update_post_meta( $post_id, Forms_Post_Type::META_DESCRIPTION, sanitize_textarea_field( wp_unslash( $_POST['headless_form_description'] ?? '' ) ) );
		update_post_meta( $post_id, Forms_Post_Type::META_SUBMIT_LABEL, sanitize_text_field( wp_unslash( $_POST['headless_form_submit_label'] ?? '' ) ) );
		update_post_meta( $post_id, Forms_Post_Type::META_SUCCESS_MESSAGE, sanitize_text_field( wp_unslash( $_POST['headless_form_success_message'] ?? '' ) ) );
		update_post_meta( $post_id, Forms_Post_Type::META_ERROR_MESSAGE, sanitize_text_field( wp_unslash( $_POST['headless_form_error_message'] ?? '' ) ) );

		$sections      = Forms_Schema::normalize_sections( wp_unslash( $_POST['headless_form_sections_json'] ?? '[]' ) );
		$fields        = Forms_Schema::normalize_fields( wp_unslash( $_POST['headless_form_fields_json'] ?? '[]' ) );
		$notifications = Forms_Schema::normalize_notifications( wp_unslash( $_POST['headless_form_notifications_json'] ?? '[]' ) );
		$anti_spam     = Forms_Schema::normalize_anti_spam(
			array(
				'honeypot'      => isset( $_POST['headless_form_honeypot'] ),
				'honeypotField' => wp_unslash( $_POST['headless_form_honeypot_field'] ?? 'website' ),
				'maxPayload'    => absint( $_POST['headless_form_max_payload'] ?? 65536 ),
			)
		);
		$rate_limit = Forms_Schema::normalize_rate_limit(
			array(
				'enabled' => isset( $_POST['headless_form_rate_enabled'] ),
				'max'     => absint( $_POST['headless_form_rate_max'] ?? 5 ),
				'window'  => absint( $_POST['headless_form_rate_window'] ?? 900 ),
			)
		);

		update_post_meta( $post_id, Forms_Post_Type::META_SECTIONS, wp_json_encode( $sections ) );
		update_post_meta( $post_id, Forms_Post_Type::META_FIELDS, wp_json_encode( $fields ) );
		update_post_meta( $post_id, Forms_Post_Type::META_NOTIFICATIONS, wp_json_encode( $notifications ) );
		update_post_meta( $post_id, Forms_Post_Type::META_ANTI_SPAM, wp_json_encode( $anti_spam ) );
		update_post_meta( $post_id, Forms_Post_Type::META_RATE_LIMIT, wp_json_encode( $rate_limit ) );

		unset( $post );
	}

	/** Persist reusable mail-template state. */
	public function save_template( $post_id, WP_Post $post ) {
		if ( ! $this->can_save( $post_id, self::TEMPLATE_NONCE_NAME, self::TEMPLATE_NONCE_ACTION ) ) {
			return;
		}

		$mode = isset( $_POST['headless_template_mode'] ) ? sanitize_key( wp_unslash( $_POST['headless_template_mode'] ) ) : 'visual';
		$mode = in_array( $mode, array( 'visual', 'html' ), true ) ? $mode : 'visual';
		$visual = array(
			'logoUrl'          => esc_url_raw( wp_unslash( $_POST['headless_template_logo_url'] ?? '' ), array( 'http', 'https' ) ),
			'outerBackground'  => $this->sanitize_color( $_POST['headless_template_outerBackground'] ?? '#f5f8fb', '#f5f8fb' ),
			'headerBackground' => $this->sanitize_color( $_POST['headless_template_headerBackground'] ?? '#06477f', '#06477f' ),
			'labelColor'       => $this->sanitize_color( $_POST['headless_template_labelColor'] ?? '#123f68', '#123f68' ),
			'valueColor'       => $this->sanitize_color( $_POST['headless_template_valueColor'] ?? '#344f67', '#344f67' ),
			'separatorColor'   => $this->sanitize_color( $_POST['headless_template_separatorColor'] ?? '#e8eef3', '#e8eef3' ),
			'eyebrow'          => sanitize_text_field( wp_unslash( $_POST['headless_template_eyebrow'] ?? '' ) ),
			'heading'          => sanitize_text_field( wp_unslash( $_POST['headless_template_heading'] ?? '' ) ),
			'intro'            => sanitize_textarea_field( wp_unslash( $_POST['headless_template_intro'] ?? '' ) ),
			'footer'           => sanitize_textarea_field( wp_unslash( $_POST['headless_template_footer'] ?? '' ) ),
		);

		update_post_meta( $post_id, Forms_Post_Type::META_TEMPLATE_DESCRIPTION, sanitize_textarea_field( wp_unslash( $_POST['headless_template_description'] ?? '' ) ) );
		update_post_meta( $post_id, Forms_Post_Type::META_TEMPLATE_SUBJECT, sanitize_text_field( wp_unslash( $_POST['headless_template_subject'] ?? '' ) ) );
		update_post_meta( $post_id, Forms_Post_Type::META_TEMPLATE_PREHEADER, sanitize_text_field( wp_unslash( $_POST['headless_template_preheader'] ?? '' ) ) );
		update_post_meta( $post_id, Forms_Post_Type::META_TEMPLATE_MODE, $mode );
		update_post_meta( $post_id, Forms_Post_Type::META_TEMPLATE_VISUAL, wp_json_encode( $visual ) );
		update_post_meta( $post_id, Forms_Post_Type::META_TEMPLATE_HTML, wp_kses_post( wp_unslash( $_POST['headless_template_html'] ?? '' ) ) );
		update_post_meta( $post_id, Forms_Post_Type::META_TEMPLATE_TEXT, sanitize_textarea_field( wp_unslash( $_POST['headless_template_text'] ?? '' ) ) );

		unset( $post );
	}

	/** Render one color input using the exact saved key. */
	private function render_color_field( $key, $label, $value ) {
		?><div class="headless-directory-field headless-forms-color"><label><?php echo esc_html( $label ); ?></label><div><input type="color" data-template-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><input class="widefat" type="text" name="headless_template_<?php echo esc_attr( $key ); ?>" data-template-color-text="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"></div></div><?php
	}

	/** Human labels for the client-side builder. */
	private function field_type_labels() {
		return array(
			'text'     => __( 'Texto', 'wp-headless-api-core' ),
			'email'    => __( 'Correo', 'wp-headless-api-core' ),
			'tel'      => __( 'Teléfono', 'wp-headless-api-core' ),
			'number'   => __( 'Número', 'wp-headless-api-core' ),
			'textarea' => __( 'Texto largo', 'wp-headless-api-core' ),
			'select'   => __( 'Select', 'wp-headless-api-core' ),
			'radio'    => __( 'Radio', 'wp-headless-api-core' ),
			'checkbox' => __( 'Checkbox', 'wp-headless-api-core' ),
			'date'     => __( 'Fecha', 'wp-headless-api-core' ),
			'time'     => __( 'Hora', 'wp-headless-api-core' ),
			'hidden'   => __( 'Oculto', 'wp-headless-api-core' ),
			'rating'   => __( 'Valoración', 'wp-headless-api-core' ),
		);
	}

	/** List published templates for notification selectors. */
	private function template_options() {
		$posts = get_posts(
			array(
				'post_type'      => Forms_Post_Type::TEMPLATE_POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$options = array();
		foreach ( $posts as $template ) {
			$options[] = array(
				'value' => $template->post_name,
				'label' => get_the_title( $template ) ?: $template->post_name,
			);
		}
		return $options;
	}

	/** Standard save guard. */
	private function can_save( $post_id, $nonce_name, $nonce_action ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_action ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	private function sanitize_color( $value, $fallback ) {
		$value = sanitize_hex_color( wp_unslash( $value ) );
		return $value ?: $fallback;
	}
}
