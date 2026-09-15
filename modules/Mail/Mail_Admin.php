<?php
/**
 * SMTP admin settings and delivery test.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Mail;

defined( 'ABSPATH' ) || exit;

final class Mail_Admin {
	const PAGE_SLUG = 'headless-api-core-mail';
	const SAVE_ACTION = 'headless_mail_save';
	const TEST_ACTION = 'headless_mail_test';

	/** @var Mail_Settings */
	private $settings;

	/** @var string */
	private $last_error = '';

	public function __construct( Mail_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'send_test' ) );
		add_action( 'wp_mail_failed', array( $this, 'capture_error' ) );
	}

	public function register_page() {
		add_options_page(
			__( 'Headless SMTP', 'wp-headless-api-core' ),
			__( 'Headless SMTP', 'wp-headless-api-core' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		wp_enqueue_style( 'headless-directory-admin', plugins_url( 'assets/admin/directory.css', HEADLESS_API_CORE_FILE ), array(), HEADLESS_API_CORE_VERSION );
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'wp-headless-api-core' ) );
		}
		check_admin_referer( self::SAVE_ACTION );
		$input = isset( $_POST['mail'] ) && is_array( $_POST['mail'] ) ? wp_unslash( $_POST['mail'] ) : array();
		$this->settings->save( $input );
		$this->redirect_with_notice( 'saved' );
	}

	public function send_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'wp-headless-api-core' ) );
		}
		check_admin_referer( self::TEST_ACTION );

		$recipient = isset( $_POST['test_recipient'] ) ? sanitize_email( wp_unslash( $_POST['test_recipient'] ) ) : '';
		if ( ! is_email( $recipient ) ) {
			$this->store_test_result( false, __( 'El correo de prueba no es válido.', 'wp-headless-api-core' ), false );
			$this->redirect_with_notice( 'tested' );
		}

		$this->last_error = '';
		$sent = wp_mail(
			$recipient,
			__( 'Prueba SMTP — Headless API Core', 'wp-headless-api-core' ),
			__( "Este mensaje confirma que WordPress pudo entregar un correo mediante la configuración SMTP de Headless API Core.\n\nSi recibiste este mensaje, la prueba fue exitosa.", 'wp-headless-api-core' )
		);

		$message = $sent
			? sprintf( __( 'Correo de prueba enviado a %s.', 'wp-headless-api-core' ), $recipient )
			: ( $this->last_error ? $this->last_error : __( 'WordPress no pudo enviar el correo de prueba.', 'wp-headless-api-core' ) );

		$this->store_test_result( (bool) $sent, $message, true );
		$this->redirect_with_notice( 'tested' );
	}

	public function capture_error( $error ) {
		if ( is_wp_error( $error ) ) {
			$this->last_error = $error->get_error_message();
		}
	}

	private function store_test_result( $success, $message, $record_transport = true ) {
		set_transient(
			'headless_mail_test_' . get_current_user_id(),
			array( 'success' => (bool) $success, 'message' => sanitize_text_field( $message ) ),
			MINUTE_IN_SECONDS
		);

		if ( $record_transport ) {
			$this->settings->record_test( (bool) $success );
		}
	}

	private function redirect_with_notice( $notice ) {
		$url = add_query_arg(
			array( 'page' => self::PAGE_SLUG, 'headless_mail_notice' => sanitize_key( $notice ) ),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$values = $this->settings->get();
		$test   = get_transient( 'headless_mail_test_' . get_current_user_id() );
		if ( false !== $test ) {
			delete_transient( 'headless_mail_test_' . get_current_user_id() );
		}
		$notice = isset( $_GET['headless_mail_notice'] ) ? sanitize_key( wp_unslash( $_GET['headless_mail_notice'] ) ) : '';
		?>
		<div class="wrap headless-directory-editor">
			<h1><?php esc_html_e( 'Correo / SMTP', 'wp-headless-api-core' ); ?></h1>
			<div class="headless-directory-intro">
				<strong><?php esc_html_e( 'Entrega de correo reutilizable para todo el plugin.', 'wp-headless-api-core' ); ?></strong>
				<span><?php esc_html_e( 'Configura WordPress para enviar mediante SMTP sin acoplar formularios o módulos a un proveedor específico.', 'wp-headless-api-core' ); ?></span>
			</div>

			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuración SMTP guardada.', 'wp-headless-api-core' ); ?></p></div>
			<?php endif; ?>
			<?php if ( is_array( $test ) ) : ?>
				<div class="notice <?php echo ! empty( $test['success'] ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $test['message'] ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<?php wp_nonce_field( self::SAVE_ACTION ); ?>

				<div class="headless-directory-editor-grid">
					<section class="headless-directory-section">
						<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Servidor SMTP', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Conexión utilizada por wp_mail() y por los módulos futuros del plugin.', 'wp-headless-api-core' ); ?></p></div></div>
						<p><label><input type="checkbox" name="mail[enabled]" value="1" <?php checked( ! empty( $values['enabled'] ) ); ?> <?php disabled( $this->settings->is_constant_controlled( 'enabled' ) ); ?>> <?php esc_html_e( 'Activar SMTP', 'wp-headless-api-core' ); ?></label></p>
						<table class="form-table" role="presentation"><tbody>
						<tr><th><label for="headless-smtp-host"><?php esc_html_e( 'Host', 'wp-headless-api-core' ); ?></label></th><td><input id="headless-smtp-host" class="regular-text" type="text" name="mail[host]" value="<?php echo esc_attr( $values['host'] ); ?>" <?php disabled( $this->settings->is_constant_controlled( 'host' ) ); ?>></td></tr>
						<tr><th><label for="headless-smtp-port"><?php esc_html_e( 'Puerto', 'wp-headless-api-core' ); ?></label></th><td><input id="headless-smtp-port" class="small-text" type="number" min="1" max="65535" name="mail[port]" value="<?php echo esc_attr( $values['port'] ); ?>" <?php disabled( $this->settings->is_constant_controlled( 'port' ) ); ?>></td></tr>
						<tr><th><label for="headless-smtp-encryption"><?php esc_html_e( 'Cifrado', 'wp-headless-api-core' ); ?></label></th><td><select id="headless-smtp-encryption" name="mail[encryption]" <?php disabled( $this->settings->is_constant_controlled( 'encryption' ) ); ?>><option value="tls" <?php selected( $values['encryption'], 'tls' ); ?>>TLS</option><option value="ssl" <?php selected( $values['encryption'], 'ssl' ); ?>>SSL</option><option value="none" <?php selected( $values['encryption'], 'none' ); ?>><?php esc_html_e( 'Ninguno', 'wp-headless-api-core' ); ?></option></select></td></tr>
						<tr><th><?php esc_html_e( 'Autenticación', 'wp-headless-api-core' ); ?></th><td><label><input type="checkbox" name="mail[auth]" value="1" <?php checked( ! empty( $values['auth'] ) ); ?> <?php disabled( $this->settings->is_constant_controlled( 'auth' ) ); ?>> <?php esc_html_e( 'El servidor requiere usuario y contraseña', 'wp-headless-api-core' ); ?></label></td></tr>
						<tr><th><label for="headless-smtp-user"><?php esc_html_e( 'Usuario', 'wp-headless-api-core' ); ?></label></th><td><input id="headless-smtp-user" class="regular-text" type="text" autocomplete="off" name="mail[username]" value="<?php echo esc_attr( $values['username'] ); ?>" <?php disabled( $this->settings->is_constant_controlled( 'username' ) ); ?>></td></tr>
						<tr><th><label for="headless-smtp-password"><?php esc_html_e( 'Contraseña', 'wp-headless-api-core' ); ?></label></th><td><input id="headless-smtp-password" class="regular-text" type="password" autocomplete="new-password" name="mail[password]" value="" placeholder="<?php echo ! empty( $values['password'] ) ? esc_attr__( 'Configurada — deja vacío para conservarla', 'wp-headless-api-core' ) : ''; ?>" <?php disabled( $this->settings->is_constant_controlled( 'password' ) ); ?>><p class="description"><?php echo ! empty( $values['password'] ) ? esc_html__( 'La contraseña está guardada. Por seguridad no se vuelve a mostrar; deja este campo vacío para conservarla.', 'wp-headless-api-core' ) : esc_html__( 'Para mayor seguridad puedes definir las credenciales mediante constantes en wp-config.php.', 'wp-headless-api-core' ); ?></p></td></tr>
						</tbody></table>
					</section>

					<section class="headless-directory-section">
						<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Remitente', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Identidad que verán los destinatarios.', 'wp-headless-api-core' ); ?></p></div></div>
						<table class="form-table" role="presentation"><tbody>
						<tr><th><label for="headless-smtp-from-email"><?php esc_html_e( 'From Email', 'wp-headless-api-core' ); ?></label></th><td><input id="headless-smtp-from-email" class="regular-text" type="email" name="mail[from_email]" value="<?php echo esc_attr( $values['from_email'] ); ?>" <?php disabled( $this->settings->is_constant_controlled( 'from_email' ) ); ?>></td></tr>
						<tr><th><label for="headless-smtp-from-name"><?php esc_html_e( 'From Name', 'wp-headless-api-core' ); ?></label></th><td><input id="headless-smtp-from-name" class="regular-text" type="text" name="mail[from_name]" value="<?php echo esc_attr( $values['from_name'] ); ?>" <?php disabled( $this->settings->is_constant_controlled( 'from_name' ) ); ?>></td></tr>
						<tr><th><?php esc_html_e( 'Forzar remitente', 'wp-headless-api-core' ); ?></th><td><label><input type="checkbox" name="mail[force_from]" value="1" <?php checked( ! empty( $values['force_from'] ) ); ?> <?php disabled( $this->settings->is_constant_controlled( 'force_from' ) ); ?>> <?php esc_html_e( 'Aplicar este remitente a wp_mail()', 'wp-headless-api-core' ); ?></label></td></tr>
						</tbody></table>
					</section>
				</div>

				<?php submit_button( __( 'Guardar configuración', 'wp-headless-api-core' ) ); ?>
			</form>

			<section class="headless-directory-section" style="max-width:900px;margin-top:24px;">
				<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Correo de prueba', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Valida la configuración con Gmail, Google Workspace o el correo corporativo antes de publicar.', 'wp-headless-api-core' ); ?></p></div></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>">
					<?php wp_nonce_field( self::TEST_ACTION ); ?>
					<p><label for="headless-smtp-test-recipient"><strong><?php esc_html_e( 'Destinatario', 'wp-headless-api-core' ); ?></strong></label></p>
					<p><input id="headless-smtp-test-recipient" class="regular-text" type="email" name="test_recipient" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" required></p>
					<?php submit_button( __( 'Enviar correo de prueba', 'wp-headless-api-core' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>
		</div>
		<?php
	}
}
