<?php
/**
 * Administrative test delivery for reusable mail templates.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use HeadlessApiCore\Modules\Mail\Mail_Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Mail_Template_Test {
	const ACTION       = 'headless_mail_template_test';
	const NONCE_ACTION = 'headless_mail_template_test_send';
	const NONCE_NAME   = 'headless_mail_template_test_nonce';
	const RESULT_KEY   = 'headless_mail_template_test_result_';

	/** @var Mail_Settings */
	private $mail_settings;

	/** @var Mail_Template_Renderer */
	private $renderer;

	public function __construct( Mail_Settings $mail_settings, Mail_Template_Renderer $renderer ) {
		$this->mail_settings = $mail_settings;
		$this->renderer      = $renderer;
	}

	/** Register the template test box and its protected admin action. */
	public function register() {
		add_action( 'add_meta_boxes_' . Forms_Post_Type::TEMPLATE_POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/** Add the test delivery box next to the normal publish controls. */
	public function add_meta_box() {
		add_meta_box(
			'headless-mail-template-test',
			__( 'Correo de prueba', 'wp-headless-api-core' ),
			array( $this, 'render' ),
			Forms_Post_Type::TEMPLATE_POST_TYPE,
			'side',
			'default'
		);
	}

	/** Render a small, explicit test-delivery workflow. */
	public function render( WP_Post $post ) {
		$result = get_transient( self::RESULT_KEY . get_current_user_id() );
		if ( is_array( $result ) && isset( $result['templateId'] ) && (int) $result['templateId'] === (int) $post->ID ) {
			delete_transient( self::RESULT_KEY . get_current_user_id() );
			$class = ! empty( $result['success'] ) ? 'notice notice-success inline' : 'notice notice-error inline';
			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( isset( $result['message'] ) ? $result['message'] : '' )
			);
		}

		$user       = wp_get_current_user();
		$recipient  = $user && is_email( $user->user_email ) ? $user->user_email : get_option( 'admin_email' );
		$forms      = get_posts(
			array(
				'post_type'      => Forms_Post_Type::FORM_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => array( 'title' => 'ASC', 'ID' => 'ASC' ),
			)
		);
		$is_ready   = $this->mail_settings->is_ready();
		$is_publish = 'publish' === $post->post_status;
		?>
		<p><?php esc_html_e( 'Envía la versión guardada de esta plantilla usando datos de ejemplo. No expone ni modifica las credenciales SMTP.', 'wp-headless-api-core' ); ?></p>
		<?php if ( ! $is_publish ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Guarda y publica la plantilla antes de enviar una prueba.', 'wp-headless-api-core' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! $is_ready ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Mail Core no está listo para enviar.', 'wp-headless-api-core' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="template_id" value="<?php echo esc_attr( $post->ID ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<p>
				<label for="headless-mail-test-recipient"><strong><?php esc_html_e( 'Destinatario', 'wp-headless-api-core' ); ?></strong></label>
				<input id="headless-mail-test-recipient" class="widefat" type="email" name="recipient" value="<?php echo esc_attr( $recipient ); ?>" required>
			</p>
			<p>
				<label for="headless-mail-test-form"><strong><?php esc_html_e( 'Datos de ejemplo', 'wp-headless-api-core' ); ?></strong></label>
				<select id="headless-mail-test-form" class="widefat" name="form_id">
					<option value="0"><?php esc_html_e( 'Ejemplo genérico', 'wp-headless-api-core' ); ?></option>
					<?php foreach ( $forms as $form ) : ?>
						<option value="<?php echo esc_attr( $form->ID ); ?>"><?php echo esc_html( get_the_title( $form ) . ' — ' . $form->post_status ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php submit_button( __( 'Enviar prueba', 'wp-headless-api-core' ), 'secondary', 'submit', false, array( 'disabled' => ( ! $is_ready || ! $is_publish ) ? 'disabled' : false ) ); ?>
		</form>
		<?php
	}

	/** Handle the protected test-send request. */
	public function handle() {
		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		if ( ! $template_id || ! current_user_can( 'edit_post', $template_id ) ) {
			wp_die( esc_html__( 'No tienes permiso para probar esta plantilla.', 'wp-headless-api-core' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$template = get_post( $template_id );
		if ( ! $template || Forms_Post_Type::TEMPLATE_POST_TYPE !== $template->post_type ) {
			$this->finish( $template_id, false, __( 'La plantilla no existe.', 'wp-headless-api-core' ) );
		}
		if ( 'publish' !== $template->post_status ) {
			$this->finish( $template_id, false, __( 'Guarda y publica la plantilla antes de enviar una prueba.', 'wp-headless-api-core' ) );
		}

		$recipient = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
		if ( ! is_email( $recipient ) ) {
			$this->finish( $template_id, false, __( 'Introduce un destinatario válido.', 'wp-headless-api-core' ) );
		}
		if ( ! $this->mail_settings->is_ready() ) {
			$this->finish( $template_id, false, __( 'Mail Core no está listo para enviar.', 'wp-headless-api-core' ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$sample  = $this->sample_context( $form_id );
		if ( is_wp_error( $sample ) ) {
			$this->finish( $template_id, false, $sample->get_error_message() );
		}

		$rendered = $this->renderer->render(
			$template->post_name,
			$sample['form'],
			$sample['values'],
			$sample['fields']
		);
		if ( is_wp_error( $rendered ) ) {
			$this->finish( $template_id, false, $rendered->get_error_message() );
		}

		$alt_body = isset( $rendered['text'] ) ? (string) $rendered['text'] : '';
		$alt_hook = static function ( $phpmailer ) use ( $alt_body ) {
			if ( '' !== $alt_body ) {
				$phpmailer->AltBody = $alt_body;
			}
		};
		add_action( 'phpmailer_init', $alt_hook, 99 );
		$sent = wp_mail(
			$recipient,
			'[PRUEBA] ' . (string) $rendered['subject'],
			(string) $rendered['html'],
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
		remove_action( 'phpmailer_init', $alt_hook, 99 );

		if ( ! $sent ) {
			$this->finish( $template_id, false, __( 'WordPress no pudo entregar el correo de prueba.', 'wp-headless-api-core' ) );
		}

		$this->finish(
			$template_id,
			true,
			sprintf( __( 'Correo de prueba enviado a %s.', 'wp-headless-api-core' ), $recipient )
		);
	}

	/** Build test values from one saved form or a generic fallback contract. */
	private function sample_context( $form_id ) {
		if ( $form_id ) {
			$form_post = get_post( $form_id );
			if ( ! $form_post || Forms_Post_Type::FORM_POST_TYPE !== $form_post->post_type || ! current_user_can( 'edit_post', $form_id ) ) {
				return new \WP_Error( 'invalid_test_form', __( 'El formulario seleccionado no está disponible para la prueba.', 'wp-headless-api-core' ) );
			}
			$fields = Forms_Schema::normalize_fields( get_post_meta( $form_id, Forms_Post_Type::META_FIELDS, true ) );
			$form   = array(
				'id'             => (int) $form_post->ID,
				'slug'           => $form_post->post_name,
				'title'          => get_the_title( $form_post ),
				'submissionId'   => 'TEST-' . strtoupper( substr( md5( (string) $form_post->ID ), 0, 8 ) ),
				'submissionDate' => current_time( 'c' ),
			);
			return array(
				'form'   => $form,
				'fields' => $fields,
				'values' => $this->sample_values( $fields ),
			);
		}

		$fields = array(
			array( 'name' => 'fullName', 'label' => __( 'Nombre', 'wp-headless-api-core' ), 'type' => 'text' ),
			array( 'name' => 'email', 'label' => __( 'Correo', 'wp-headless-api-core' ), 'type' => 'email' ),
			array( 'name' => 'subject', 'label' => __( 'Asunto', 'wp-headless-api-core' ), 'type' => 'text' ),
			array( 'name' => 'message', 'label' => __( 'Mensaje', 'wp-headless-api-core' ), 'type' => 'textarea' ),
		);
		return array(
			'form' => array(
				'id'             => 0,
				'slug'           => 'formulario-prueba',
				'title'          => __( 'Formulario de prueba', 'wp-headless-api-core' ),
				'submissionId'   => 'TEST-00000001',
				'submissionDate' => current_time( 'c' ),
			),
			'fields' => $fields,
			'values' => array(
				'fullName' => 'Ana Pérez',
				'email'    => 'ana.perez@example.org',
				'subject'  => __( 'Consulta de prueba', 'wp-headless-api-core' ),
				'message'  => __( 'Este es un mensaje de prueba generado por Forms Core.', 'wp-headless-api-core' ),
			),
		);
	}

	/** Produce safe representative values while preserving exact field names. */
	private function sample_values( array $fields ) {
		$values = array();
		foreach ( $fields as $field ) {
			$name = isset( $field['name'] ) ? Forms_Schema::sanitize_field_name( $field['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$values[ $name ] = $this->sample_value( $field, $name );
		}
		return $values;
	}

	/** Build one deterministic sample value from type, options and common semantics. */
	private function sample_value( array $field, $name ) {
		if ( isset( $field['defaultValue'] ) && '' !== (string) $field['defaultValue'] ) {
			return $field['defaultValue'];
		}
		if ( ! empty( $field['options'] ) && isset( $field['options'][0]['value'] ) ) {
			return 'checkbox' === $field['type'] ? array( (string) $field['options'][0]['value'] ) : (string) $field['options'][0]['value'];
		}

		$semantic = strtolower( $name );
		$known    = array(
			'fullname'        => 'Ana Pérez',
			'firstname'       => 'Ana',
			'lastname'        => 'Pérez',
			'email'           => 'ana.perez@example.org',
			'subject'         => __( 'Consulta de prueba', 'wp-headless-api-core' ),
			'message'         => __( 'Este es un mensaje de prueba generado por Forms Core.', 'wp-headless-api-core' ),
			'phone'           => '+1 809 555 0101',
			'mobile'          => '+1 829 555 0102',
			'document'        => '001-0000000-1',
			'nss'             => '12345678901',
			'address'         => 'Dirección de ejemplo 123',
			'sector'          => 'Sector de ejemplo',
			'province'        => 'distrito-nacional',
			'service'         => 'servicio-ejemplo',
			'specialtysubtype'=> 'subtipo-ejemplo',
			'doctor'          => 'medico-ejemplo',
			'shift'           => 'manana',
			'rankstatus'      => 'civil',
			'experiencerating'=> 5,
		);
		if ( array_key_exists( $semantic, $known ) ) {
			return $known[ $semantic ];
		}

		$type = isset( $field['type'] ) ? $field['type'] : 'text';
		switch ( $type ) {
			case 'email':
				return 'persona@example.org';
			case 'tel':
				return '+1 809 555 0101';
			case 'number':
			case 'rating':
				return 5;
			case 'date':
				return false !== strpos( $semantic, 'birth' ) ? '1990-01-15' : gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
			case 'time':
				return '09:00';
			case 'checkbox':
				return true;
			case 'textarea':
				return __( 'Contenido de prueba para revisar el diseño del correo.', 'wp-headless-api-core' );
			default:
				return __( 'Valor de prueba', 'wp-headless-api-core' );
		}
	}

	/** Persist a short per-user result and return to the template editor. */
	private function finish( $template_id, $success, $message ) {
		set_transient(
			self::RESULT_KEY . get_current_user_id(),
			array(
				'templateId' => (int) $template_id,
				'success'    => (bool) $success,
				'message'    => sanitize_text_field( $message ),
			),
			60
		);
		wp_safe_redirect( get_edit_post_link( $template_id, 'raw' ) );
		exit;
	}
}
