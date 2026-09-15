<?php
/**
 * Generic JSON package importer for Forms Core and Mail Templates.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Forms_Package_Importer {
	const PAGE_SLUG        = 'headless-forms-import';
	const NONCE_ACTION     = 'headless_forms_package_import';
	const TRANSIENT_PREFIX = 'hacf_forms_pkg_';
	const RESULT_PREFIX    = 'hacf_forms_pkg_result_';
	const TRANSIENT_TTL    = 1800;
	const MAX_FILE_BYTES   = 2097152;

	/** @var string */
	private $hook_suffix = '';

	/** Register admin workspace and protected actions. */
	public function register() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_headless_forms_package_preview', array( $this, 'preview' ) );
		add_action( 'admin_post_headless_forms_package_import', array( $this, 'import' ) );
		add_action( 'admin_post_headless_forms_package_example', array( $this, 'download_example' ) );
	}

	/** Add the importer under the Forms workspace. */
	public function admin_menu() {
		$this->hook_suffix = (string) add_submenu_page(
			'edit.php?post_type=' . Forms_Post_Type::FORM_POST_TYPE,
			__( 'Importar Forms / Templates', 'wp-headless-api-core' ),
			__( 'Importar paquete', 'wp-headless-api-core' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/** Reuse the approved SIP admin visual language on the import screen. */
	public function enqueue( $hook_suffix ) {
		if ( ! $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'headless-directory-admin', plugins_url( 'assets/admin/directory.css', HEADLESS_API_CORE_FILE ), array(), HEADLESS_API_CORE_VERSION );
		wp_enqueue_style( 'headless-forms-admin', plugins_url( 'assets/admin/forms.css', HEADLESS_API_CORE_FILE ), array( 'headless-directory-admin' ), HEADLESS_API_CORE_VERSION );
	}

	/** Render upload, preview and import controls. */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para importar formularios.', 'wp-headless-api-core' ) );
		}

		$result = get_transient( self::RESULT_PREFIX . get_current_user_id() );
		if ( is_array( $result ) ) {
			delete_transient( self::RESULT_PREFIX . get_current_user_id() );
		}

		$token = isset( $_GET['preview'] ) ? $this->sanitize_token( wp_unslash( $_GET['preview'] ) ) : '';
		$data  = $token ? get_transient( $this->transient_key( $token ) ) : false;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Importar Forms / Mail Templates', 'wp-headless-api-core' ); ?></h1>
			<div class="headless-directory-editor headless-forms-editor">
				<div class="headless-directory-intro">
					<strong><?php esc_html_e( 'Migra contratos completos sin reconstruir formularios campo por campo.', 'wp-headless-api-core' ); ?></strong>
					<span><?php esc_html_e( 'El paquete JSON puede contener plantillas y formularios. Se validan primero y las plantillas se importan antes que los formularios.', 'wp-headless-api-core' ); ?></span>
				</div>

				<?php if ( is_array( $result ) ) : ?>
					<div class="notice <?php echo ! empty( $result['success'] ) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $result['message'] ?? '' ); ?></p></div>
				<?php endif; ?>

				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading">
						<div><h3><?php esc_html_e( '1. Selecciona el paquete', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Formato .json, máximo 2 MB. La vista previa no modifica WordPress.', 'wp-headless-api-core' ); ?></p></div>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=headless_forms_package_example' ), self::NONCE_ACTION ) ); ?>"><?php esc_html_e( 'Descargar ejemplo', 'wp-headless-api-core' ); ?></a>
					</div>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="headless_forms_package_preview">
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<div class="headless-directory-field">
							<label for="headless-forms-package-file"><?php esc_html_e( 'Archivo JSON', 'wp-headless-api-core' ); ?></label>
							<input id="headless-forms-package-file" type="file" name="package" accept="application/json,.json" required>
						</div>
						<?php submit_button( __( 'Validar paquete', 'wp-headless-api-core' ), 'primary', 'submit', false ); ?>
					</form>
				</section>

				<?php if ( is_array( $data ) ) : $this->render_preview( $token, $data ); endif; ?>
			</div>
		</div>
		<?php
	}

	/** Parse and persist a safe preview token. */
	public function preview() {
		$this->guard();
		if ( empty( $_FILES['package'] ) || ! is_array( $_FILES['package'] ) ) {
			$this->redirect_result( false, __( 'Selecciona un archivo JSON.', 'wp-headless-api-core' ) );
		}

		$file = $_FILES['package']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || $size <= 0 || $size > self::MAX_FILE_BYTES ) {
			$this->redirect_result( false, __( 'WordPress no pudo recibir el archivo o supera 2 MB.', 'wp-headless-api-core' ) );
		}
		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			$this->redirect_result( false, __( 'Formato no soportado. Usa un archivo .json.', 'wp-headless-api-core' ) );
		}

		$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			$this->redirect_result( false, __( 'No fue posible leer el archivo.', 'wp-headless-api-core' ) );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
			$this->redirect_result( false, __( 'El JSON no es válido.', 'wp-headless-api-core' ) );
		}

		$normalized = $this->normalize_package( $decoded );
		if ( is_wp_error( $normalized ) ) {
			$this->redirect_result( false, $normalized->get_error_message() );
		}

		$token = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( $this->transient_key( $token ), $normalized, self::TRANSIENT_TTL );
		wp_safe_redirect( $this->page_url( array( 'preview' => $token ) ) );
		exit;
	}

	/** Import a validated package, templates first and forms second. */
	public function import() {
		$this->guard();
		$token = isset( $_POST['token'] ) ? $this->sanitize_token( wp_unslash( $_POST['token'] ) ) : '';
		$mode  = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create_only';
		$mode  = in_array( $mode, array( 'create_only', 'upsert' ), true ) ? $mode : 'create_only';
		$data  = $token ? get_transient( $this->transient_key( $token ) ) : false;

		if ( ! is_array( $data ) ) {
			$this->redirect_result( false, __( 'La vista previa expiró. Valida el paquete nuevamente.', 'wp-headless-api-core' ) );
		}
		if ( ! empty( $data['summary']['errors'] ) ) {
			$this->redirect_result( false, __( 'El paquete contiene errores y no puede importarse.', 'wp-headless-api-core' ) );
		}

		$result = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0 );
		foreach ( $data['templates'] as $template ) {
			$this->count_outcome( $result, $this->import_template( $template, $mode ) );
		}
		foreach ( $data['forms'] as $form ) {
			$this->count_outcome( $result, $this->import_form( $form, $mode ) );
		}

		delete_transient( $this->transient_key( $token ) );
		$success = 0 === $result['failed'];
		$message = sprintf(
			/* translators: 1 created, 2 updated, 3 skipped, 4 failed. */
			__( 'Importación terminada: %1$d creados, %2$d actualizados, %3$d omitidos y %4$d fallidos.', 'wp-headless-api-core' ),
			$result['created'],
			$result['updated'],
			$result['skipped'],
			$result['failed']
		);
		$this->redirect_result( $success, $message );
	}

	/** Download a provider-agnostic package example. */
	public function download_example() {
		$this->guard();
		$example = array(
			'schemaVersion' => Forms_Schema::SCHEMA_VERSION,
			'package'       => array( 'name' => 'Ejemplo institucional', 'version' => '1.0.0' ),
			'templates'     => array(
				array(
					'slug'        => 'notificacion-general',
					'title'       => 'Notificación general',
					'status'      => 'publish',
					'mode'        => 'visual',
					'subject'     => 'Nueva solicitud — {{field.fullName}}',
					'preheader'   => 'Nueva solicitud recibida.',
					'visual'      => array( 'heading' => 'Nueva solicitud', 'eyebrow' => '{{form.name}}' ),
					'textFallback'=> "Nueva solicitud\n\n{{form.fields}}",
				),
			),
			'forms' => array(
				array(
					'slug'           => 'contacto',
					'title'          => 'Contacto',
					'status'         => 'publish',
					'enabled'        => true,
					'submitLabel'    => 'Enviar mensaje',
					'successMessage' => 'Tu mensaje fue enviado correctamente.',
					'errorMessage'   => 'No pudimos enviar tu mensaje.',
					'sections'       => array( array( 'id' => 'principal', 'title' => 'Escríbenos', 'order' => 0 ) ),
					'fields'         => array(
						array( 'id' => 'fullName', 'name' => 'fullName', 'type' => 'text', 'label' => 'Nombre', 'required' => true, 'section' => 'principal', 'width' => 6, 'order' => 0 ),
						array( 'id' => 'email', 'name' => 'email', 'type' => 'email', 'label' => 'Correo', 'required' => true, 'section' => 'principal', 'width' => 6, 'order' => 1 ),
					),
					'notifications' => array( array( 'id' => 'interno', 'enabled' => true, 'template' => 'notificacion-general', 'to' => array( 'equipo@example.org' ), 'replyToField' => 'email' ) ),
					'antiSpam'      => array( 'honeypot' => true, 'honeypotField' => 'website', 'maxPayload' => 65536 ),
					'rateLimit'     => array( 'enabled' => true, 'max' => 5, 'window' => 900 ),
				),
			),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="headless-forms-package-example.json"' );
		echo wp_json_encode( $example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** Render the normalized package preview and final import controls. */
	private function render_preview( $token, array $data ) {
		$summary = $data['summary'];
		?>
		<section class="headless-directory-section headless-directory-section--advanced">
			<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( '2. Vista previa', 'wp-headless-api-core' ); ?></h3><p><?php echo esc_html( $data['package']['name'] ); ?></p></div><span class="headless-directory-badge <?php echo empty( $summary['errors'] ) ? 'is-active' : 'is-optional'; ?>"><?php echo esc_html( empty( $summary['errors'] ) ? __( 'Listo', 'wp-headless-api-core' ) : __( 'Revisar', 'wp-headless-api-core' ) ); ?></span></div>
			<div class="headless-forms-grid-3">
				<div class="headless-directory-field"><label><?php esc_html_e( 'Plantillas', 'wp-headless-api-core' ); ?></label><input class="widefat" readonly value="<?php echo esc_attr( count( $data['templates'] ) ); ?>"></div>
				<div class="headless-directory-field"><label><?php esc_html_e( 'Formularios', 'wp-headless-api-core' ); ?></label><input class="widefat" readonly value="<?php echo esc_attr( count( $data['forms'] ) ); ?>"></div>
				<div class="headless-directory-field"><label><?php esc_html_e( 'Alertas', 'wp-headless-api-core' ); ?></label><input class="widefat" readonly value="<?php echo esc_attr( (int) $summary['warnings'] + (int) $summary['errors'] ); ?>"></div>
			</div>

			<table class="widefat striped" style="margin-top:16px">
				<thead><tr><th><?php esc_html_e( 'Tipo', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Título', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Slug', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Estado', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Validación', 'wp-headless-api-core' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( array_merge( $data['templates'], $data['forms'] ) as $item ) : ?>
					<tr><td><?php echo esc_html( $item['_type'] ); ?></td><td><?php echo esc_html( $item['title'] ); ?></td><td><code><?php echo esc_html( $item['slug'] ); ?></code></td><td><?php echo esc_html( $item['status'] ); ?></td><td><?php echo esc_html( $this->validation_label( $item ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $data['messages'] ) ) : ?>
				<div style="margin-top:16px">
					<?php foreach ( $data['messages'] as $message ) : ?>
						<div class="notice <?php echo 'error' === $message['level'] ? 'notice-error' : 'notice-warning'; ?> inline"><p><?php echo esc_html( $message['message'] ); ?></p></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $summary['errors'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:18px">
					<input type="hidden" name="action" value="headless_forms_package_import">
					<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<div class="headless-directory-field" style="max-width:520px">
						<label for="headless-forms-import-mode"><?php esc_html_e( 'Modo de importación', 'wp-headless-api-core' ); ?></label>
						<select id="headless-forms-import-mode" name="mode" class="widefat"><option value="create_only"><?php esc_html_e( 'Solo crear — no tocar slugs existentes', 'wp-headless-api-core' ); ?></option><option value="upsert"><?php esc_html_e( 'Crear o actualizar por slug', 'wp-headless-api-core' ); ?></option></select>
					</div>
					<?php submit_button( __( 'Importar paquete', 'wp-headless-api-core' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/** Normalize and validate the complete package. */
	private function normalize_package( array $package ) {
		$version = isset( $package['schemaVersion'] ) ? absint( $package['schemaVersion'] ) : 0;
		if ( Forms_Schema::SCHEMA_VERSION !== $version ) {
			return new WP_Error( 'forms_package_schema', sprintf( __( 'schemaVersion debe ser %d.', 'wp-headless-api-core' ), Forms_Schema::SCHEMA_VERSION ) );
		}

		$templates_raw = isset( $package['templates'] ) && is_array( $package['templates'] ) ? $package['templates'] : array();
		$forms_raw     = isset( $package['forms'] ) && is_array( $package['forms'] ) ? $package['forms'] : array();
		if ( empty( $templates_raw ) && empty( $forms_raw ) ) {
			return new WP_Error( 'forms_package_empty', __( 'El paquete no contiene plantillas ni formularios.', 'wp-headless-api-core' ) );
		}

		$out = array(
			'package'   => array(
				'name'    => sanitize_text_field( $package['package']['name'] ?? __( 'Paquete sin nombre', 'wp-headless-api-core' ) ),
				'version' => sanitize_text_field( $package['package']['version'] ?? '' ),
			),
			'templates' => array(),
			'forms'     => array(),
			'messages'  => array(),
			'summary'   => array( 'warnings' => 0, 'errors' => 0 ),
		);

		$seen_templates = array();
		foreach ( $templates_raw as $index => $raw ) {
			$item = $this->normalize_template( is_array( $raw ) ? $raw : array(), $index );
			if ( isset( $seen_templates[ $item['slug'] ] ) && '' !== $item['slug'] ) {
				$item['_errors'][] = __( 'Slug de plantilla duplicado dentro del paquete.', 'wp-headless-api-core' );
			}
			$seen_templates[ $item['slug'] ] = true;
			$out['templates'][] = $item;
		}

		$seen_forms = array();
		foreach ( $forms_raw as $index => $raw ) {
			$item = $this->normalize_form( is_array( $raw ) ? $raw : array(), $index );
			if ( isset( $seen_forms[ $item['slug'] ] ) && '' !== $item['slug'] ) {
				$item['_errors'][] = __( 'Slug de formulario duplicado dentro del paquete.', 'wp-headless-api-core' );
			}
			$seen_forms[ $item['slug'] ] = true;
			$out['forms'][] = $item;
		}

		$package_template_slugs = array_values( array_filter( array_column( $out['templates'], 'slug' ) ) );
		foreach ( $out['forms'] as $form_index => $form ) {
			foreach ( $form['notifications'] as $notification ) {
				$slug = $notification['template'];
				if ( ! $slug ) {
					continue;
				}
				$existing = get_page_by_path( $slug, OBJECT, Forms_Post_Type::TEMPLATE_POST_TYPE );
				if ( ! in_array( $slug, $package_template_slugs, true ) && ! $existing ) {
					$out['forms'][ $form_index ]['_warnings'][] = sprintf( __( 'La plantilla "%s" no está en el paquete ni existe todavía en WordPress.', 'wp-headless-api-core' ), $slug );
				}
			}
		}

		foreach ( array_merge( $out['templates'], $out['forms'] ) as $item ) {
			foreach ( $item['_errors'] as $error ) {
				++$out['summary']['errors'];
				$out['messages'][] = array( 'level' => 'error', 'message' => $item['title'] . ': ' . $error );
			}
			foreach ( $item['_warnings'] as $warning ) {
				++$out['summary']['warnings'];
				$out['messages'][] = array( 'level' => 'warning', 'message' => $item['title'] . ': ' . $warning );
			}
		}

		return $out;
	}

	/** Normalize one reusable template. */
	private function normalize_template( array $raw, $index ) {
		$slug   = sanitize_title( $raw['slug'] ?? '' );
		$title  = sanitize_text_field( $raw['title'] ?? '' );
		$status = $this->sanitize_status( $raw['status'] ?? 'draft' );
		$mode   = sanitize_key( $raw['mode'] ?? 'visual' );
		$mode   = in_array( $mode, array( 'visual', 'html' ), true ) ? $mode : 'visual';
		$errors = array();
		$warnings = array();
		if ( ! $slug ) { $errors[] = __( 'Falta slug.', 'wp-headless-api-core' ); }
		if ( ! $title ) { $errors[] = __( 'Falta título.', 'wp-headless-api-core' ); }
		if ( 'html' === $mode && empty( $raw['html'] ) ) { $warnings[] = __( 'Modo HTML sin contenido HTML.', 'wp-headless-api-core' ); }

		$visual = is_array( $raw['visual'] ?? null ) ? $raw['visual'] : array();
		$visual = array(
			'logoUrl'          => esc_url_raw( $visual['logoUrl'] ?? '', array( 'http', 'https' ) ),
			'outerBackground'  => $this->sanitize_color( $visual['outerBackground'] ?? '#f5f8fb', '#f5f8fb' ),
			'headerBackground' => $this->sanitize_color( $visual['headerBackground'] ?? '#06477f', '#06477f' ),
			'labelColor'       => $this->sanitize_color( $visual['labelColor'] ?? '#123f68', '#123f68' ),
			'valueColor'       => $this->sanitize_color( $visual['valueColor'] ?? '#344f67', '#344f67' ),
			'separatorColor'   => $this->sanitize_color( $visual['separatorColor'] ?? '#e8eef3', '#e8eef3' ),
			'eyebrow'          => sanitize_text_field( $visual['eyebrow'] ?? '{{form.name}}' ),
			'heading'          => sanitize_text_field( $visual['heading'] ?? __( 'Nueva solicitud', 'wp-headless-api-core' ) ),
			'intro'            => sanitize_textarea_field( $visual['intro'] ?? '' ),
			'footer'           => sanitize_textarea_field( $visual['footer'] ?? '' ),
		);

		return array(
			'_type'       => __( 'Plantilla', 'wp-headless-api-core' ),
			'_errors'     => $errors,
			'_warnings'   => $warnings,
			'_index'      => $index,
			'slug'        => $slug,
			'title'       => $title,
			'status'      => $status,
			'description' => sanitize_textarea_field( $raw['description'] ?? '' ),
			'subject'     => sanitize_text_field( $raw['subject'] ?? '' ),
			'preheader'   => sanitize_text_field( $raw['preheader'] ?? '' ),
			'mode'        => $mode,
			'visual'      => $visual,
			'html'        => wp_kses_post( $raw['html'] ?? '' ),
			'textFallback'=> sanitize_textarea_field( $raw['textFallback'] ?? '' ),
		);
	}

	/** Normalize one generic form contract. */
	private function normalize_form( array $raw, $index ) {
		$slug          = sanitize_title( $raw['slug'] ?? '' );
		$title         = sanitize_text_field( $raw['title'] ?? '' );
		$status        = $this->sanitize_status( $raw['status'] ?? 'draft' );
		$sections      = Forms_Schema::normalize_sections( $raw['sections'] ?? array() );
		$fields        = Forms_Schema::normalize_fields( $raw['fields'] ?? array() );
		$notifications = Forms_Schema::normalize_notifications( $raw['notifications'] ?? array() );
		$anti_spam     = Forms_Schema::normalize_anti_spam( $raw['antiSpam'] ?? array() );
		$rate_limit    = Forms_Schema::normalize_rate_limit( $raw['rateLimit'] ?? array() );
		$errors        = array();
		$warnings      = array();
		if ( ! $slug ) { $errors[] = __( 'Falta slug.', 'wp-headless-api-core' ); }
		if ( ! $title ) { $errors[] = __( 'Falta título.', 'wp-headless-api-core' ); }
		if ( empty( $fields ) ) { $warnings[] = __( 'El formulario no tiene campos.', 'wp-headless-api-core' ); }
		if ( empty( $notifications ) ) { $warnings[] = __( 'El formulario no tiene notificaciones configuradas.', 'wp-headless-api-core' ); }

		return array(
			'_type'          => __( 'Formulario', 'wp-headless-api-core' ),
			'_errors'        => $errors,
			'_warnings'      => $warnings,
			'_index'         => $index,
			'slug'           => $slug,
			'title'          => $title,
			'status'         => $status,
			'enabled'        => ! isset( $raw['enabled'] ) || (bool) $raw['enabled'],
			'description'    => sanitize_textarea_field( $raw['description'] ?? '' ),
			'submitLabel'    => sanitize_text_field( $raw['submitLabel'] ?? __( 'Enviar', 'wp-headless-api-core' ) ),
			'successMessage' => sanitize_text_field( $raw['successMessage'] ?? __( 'Tu solicitud fue enviada correctamente.', 'wp-headless-api-core' ) ),
			'errorMessage'   => sanitize_text_field( $raw['errorMessage'] ?? __( 'No pudimos enviar tu solicitud.', 'wp-headless-api-core' ) ),
			'sections'       => $sections,
			'fields'         => $fields,
			'notifications'  => $notifications,
			'antiSpam'       => $anti_spam,
			'rateLimit'      => $rate_limit,
		);
	}

	/** Insert/update one reusable template by slug. */
	private function import_template( array $item, $mode ) {
		$id = $this->upsert_post( Forms_Post_Type::TEMPLATE_POST_TYPE, $item, $mode );
		if ( is_wp_error( $id ) || 'skipped' === $id ) { return $id; }
		update_post_meta( $id, Forms_Post_Type::META_TEMPLATE_DESCRIPTION, $item['description'] );
		update_post_meta( $id, Forms_Post_Type::META_TEMPLATE_SUBJECT, $item['subject'] );
		update_post_meta( $id, Forms_Post_Type::META_TEMPLATE_PREHEADER, $item['preheader'] );
		update_post_meta( $id, Forms_Post_Type::META_TEMPLATE_MODE, $item['mode'] );
		update_post_meta( $id, Forms_Post_Type::META_TEMPLATE_VISUAL, wp_json_encode( $item['visual'] ) );
		update_post_meta( $id, Forms_Post_Type::META_TEMPLATE_HTML, $item['html'] );
		update_post_meta( $id, Forms_Post_Type::META_TEMPLATE_TEXT, $item['textFallback'] );
		return $id['outcome'];
	}

	/** Insert/update one form by slug. */
	private function import_form( array $item, $mode ) {
		$id = $this->upsert_post( Forms_Post_Type::FORM_POST_TYPE, $item, $mode );
		if ( is_wp_error( $id ) || 'skipped' === $id ) { return $id; }
		update_post_meta( $id['id'], Forms_Post_Type::META_ENABLED, $item['enabled'] );
		update_post_meta( $id['id'], Forms_Post_Type::META_SCHEMA_VERSION, Forms_Schema::SCHEMA_VERSION );
		update_post_meta( $id['id'], Forms_Post_Type::META_DESCRIPTION, $item['description'] );
		update_post_meta( $id['id'], Forms_Post_Type::META_SUBMIT_LABEL, $item['submitLabel'] );
		update_post_meta( $id['id'], Forms_Post_Type::META_SUCCESS_MESSAGE, $item['successMessage'] );
		update_post_meta( $id['id'], Forms_Post_Type::META_ERROR_MESSAGE, $item['errorMessage'] );
		update_post_meta( $id['id'], Forms_Post_Type::META_SECTIONS, wp_json_encode( $item['sections'] ) );
		update_post_meta( $id['id'], Forms_Post_Type::META_FIELDS, wp_json_encode( $item['fields'] ) );
		update_post_meta( $id['id'], Forms_Post_Type::META_NOTIFICATIONS, wp_json_encode( $item['notifications'] ) );
		update_post_meta( $id['id'], Forms_Post_Type::META_ANTI_SPAM, wp_json_encode( $item['antiSpam'] ) );
		update_post_meta( $id['id'], Forms_Post_Type::META_RATE_LIMIT, wp_json_encode( $item['rateLimit'] ) );
		return $id['outcome'];
	}

	/** Create or update one editorial entity by stable slug. */
	private function upsert_post( $post_type, array $item, $mode ) {
		$existing = get_page_by_path( $item['slug'], OBJECT, $post_type );
		if ( $existing && 'create_only' === $mode ) {
			return 'skipped';
		}
		$postarr = array(
			'post_type'   => $post_type,
			'post_title'  => $item['title'],
			'post_name'   => $item['slug'],
			'post_status' => $item['status'],
		);
		if ( $existing ) {
			$postarr['ID'] = (int) $existing->ID;
			$id = wp_update_post( wp_slash( $postarr ), true );
			$outcome = 'updated';
		} else {
			$id = wp_insert_post( wp_slash( $postarr ), true );
			$outcome = 'created';
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array( 'id' => (int) $id, 'outcome' => $outcome );
	}

	/** Count importer outcomes without leaking raw errors to the UI. */
	private function count_outcome( array &$result, $outcome ) {
		if ( is_wp_error( $outcome ) ) { ++$result['failed']; return; }
		if ( isset( $result[ $outcome ] ) ) { ++$result[ $outcome ]; return; }
		++$result['failed'];
	}

	private function validation_label( array $item ) {
		if ( ! empty( $item['_errors'] ) ) { return __( 'Error', 'wp-headless-api-core' ); }
		if ( ! empty( $item['_warnings'] ) ) { return __( 'Advertencia', 'wp-headless-api-core' ); }
		return __( 'Listo', 'wp-headless-api-core' );
	}

	private function sanitize_status( $value ) {
		$value = sanitize_key( $value );
		return in_array( $value, array( 'draft', 'publish', 'pending', 'private' ), true ) ? $value : 'draft';
	}

	private function sanitize_color( $value, $fallback ) {
		$value = sanitize_hex_color( (string) $value );
		return $value ?: $fallback;
	}

	private function sanitize_token( $value ) {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $value ) );
	}

	private function transient_key( $token ) {
		return self::TRANSIENT_PREFIX . get_current_user_id() . '_' . $token;
	}

	private function page_url( array $args = array() ) {
		$url = admin_url( 'edit.php?post_type=' . Forms_Post_Type::FORM_POST_TYPE . '&page=' . self::PAGE_SLUG );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	private function guard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para importar formularios.', 'wp-headless-api-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_ACTION );
	}

	private function redirect_result( $success, $message ) {
		set_transient(
			self::RESULT_PREFIX . get_current_user_id(),
			array( 'success' => (bool) $success, 'message' => sanitize_text_field( $message ) ),
			60
		);
		wp_safe_redirect( $this->page_url() );
		exit;
	}
}
