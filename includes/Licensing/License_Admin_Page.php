<?php
/**
 * Licensing settings screen and admin actions.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Admin_Page {
	const PAGE_SLUG = 'headless-api-core-license';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_headless_api_core_license_activate', array( __CLASS__, 'handle_activate' ) );
		add_action( 'admin_post_headless_api_core_license_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_post_headless_api_core_license_deactivate', array( __CLASS__, 'handle_deactivate' ) );
	}

	public static function register_page() {
		add_options_page(
			'Headless API Core — Licencia',
			'Headless API Core',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$verification = License_Manager::verify_stored();
		$state        = License_Storage::get();
		$license      = isset( $state['license'] ) && is_array( $state['license'] ) ? $state['license'] : array();
		$status       = isset( $verification['status'] ) ? (string) $verification['status'] : 'untrusted';
		$trusted      = ! empty( $verification['trusted'] );
		$operational  = ! empty( $verification['operational'] );
		$instance_id  = License_Instance::get();
		$host         = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$domain       = is_string( $host ) ? strtolower( $host ) : '';
		$result       = isset( $_GET['license_result'] ) ? sanitize_key( wp_unslash( $_GET['license_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action       = isset( $_GET['license_action'] ) ? sanitize_key( wp_unslash( $_GET['license_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result_code  = isset( $_GET['license_code'] ) ? sanitize_text_field( wp_unslash( $_GET['license_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$operation    = self::build_operation_notice( $result, $action, $result_code );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Headless API Core — Licencia', 'wp-headless-api-core' ); ?></h1>
			<p><?php esc_html_e( 'Gestiona la activación de esta instalación. La clave de licencia nunca se guarda en texto plano.', 'wp-headless-api-core' ); ?></p>

			<?php if ( null !== $operation ) : ?>
				<?php
				$class = 'notice notice-info';
				if ( 'success' === $operation['level'] ) {
					$class = 'notice notice-success is-dismissible';
				} elseif ( 'warning' === $operation['level'] ) {
					$class = 'notice notice-warning';
				} elseif ( 'error' === $operation['level'] ) {
					$class = 'notice notice-error';
				}
				?>
				<div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $operation['message'] ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:760px;margin:20px 0;">
				<tbody>
					<tr><th><?php esc_html_e( 'Estado', 'wp-headless-api-core' ); ?></th><td><?php echo esc_html( $status ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Token confiable', 'wp-headless-api-core' ); ?></th><td><?php echo esc_html( $trusted ? 'Sí' : 'No' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Operativo', 'wp-headless-api-core' ); ?></th><td><?php echo esc_html( $operational ? 'Sí' : 'No' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Dominio', 'wp-headless-api-core' ); ?></th><td><code><?php echo esc_html( $domain ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'ID de instalación', 'wp-headless-api-core' ); ?></th><td><code><?php echo esc_html( $instance_id ); ?></code></td></tr>
					<?php if ( isset( $license['plan'] ) ) : ?><tr><th><?php esc_html_e( 'Plan', 'wp-headless-api-core' ); ?></th><td><?php echo esc_html( (string) $license['plan'] ); ?></td></tr><?php endif; ?>
					<?php if ( isset( $license['expiresAt'] ) ) : ?><tr><th><?php esc_html_e( 'Expira', 'wp-headless-api-core' ); ?></th><td><?php echo esc_html( (string) $license['expiresAt'] ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! $trusted ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px;">
					<input type="hidden" name="action" value="headless_api_core_license_activate" />
					<?php wp_nonce_field( 'headless_api_core_license_activate' ); ?>
					<label for="headless-api-core-license-key"><strong><?php esc_html_e( 'Clave de licencia', 'wp-headless-api-core' ); ?></strong></label>
					<input id="headless-api-core-license-key" name="license_key" type="text" class="regular-text" autocomplete="off" required />
					<?php submit_button( __( 'Activar licencia', 'wp-headless-api-core' ) ); ?>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
					<input type="hidden" name="action" value="headless_api_core_license_refresh" />
					<?php wp_nonce_field( 'headless_api_core_license_refresh' ); ?>
					<?php submit_button( __( 'Revalidar ahora', 'wp-headless-api-core' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="headless_api_core_license_deactivate" />
					<?php wp_nonce_field( 'headless_api_core_license_deactivate' ); ?>
					<?php submit_button( __( 'Desactivar licencia', 'wp-headless-api-core' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build concise administrator-facing feedback for the last explicit license action.
	 *
	 * @param string $result Result class from the redirect query string.
	 * @param string $action activate|refresh|deactivate.
	 * @param string $code   Preferred server or local error code.
	 * @return array<string,string>|null
	 */
	public static function build_operation_notice( $result, $action, $code = '' ) {
		$result = strtolower( trim( (string) $result ) );
		$action = strtolower( trim( (string) $action ) );
		$code   = strtoupper( trim( (string) $code ) );

		if ( 'success' === $result ) {
			$messages = array(
				'activate'   => 'La licencia se activó correctamente en esta instalación.',
				'refresh'    => 'La licencia se revalidó correctamente con el servidor y el estado local quedó actualizado.',
				'deactivate' => 'La licencia se desactivó correctamente en esta instalación. Puedes volver a activarla cuando lo necesites.',
			);
			return array(
				'level'   => 'success',
				'message' => isset( $messages[ $action ] ) ? $messages[ $action ] : 'La operación de licencia se completó correctamente.',
			);
		}

		if ( 'error' !== $result ) {
			return null;
		}

		$known = array(
			'LICENSE_SUSPENDED' => array(
				'level'   => 'warning',
				'message' => 'La revalidación se completó: el servidor informó que la licencia está suspendida. Reactívala en el panel de licencias y vuelve a revalidar esta instalación.',
			),
			'LICENSE_EXPIRED' => array(
				'level'   => 'warning',
				'message' => 'La licencia está vencida. Renueva la licencia y vuelve a revalidar esta instalación.',
			),
			'LICENSE_REVOKED' => array(
				'level'   => 'error',
				'message' => 'La licencia fue revocada y ya no puede recuperarse desde esta instalación. Asigna o emite una nueva licencia para volver a habilitar las capacidades licenciadas.',
			),
			'ENTITLEMENT_REQUIRED' => array(
				'level'   => 'warning',
				'message' => 'La licencia es válida, pero no incluye una capacidad necesaria para esta operación. Revisa el plan o los módulos asignados.',
			),
			'DOMAIN_NOT_ALLOWED' => array(
				'level'   => 'error',
				'message' => 'Este dominio no está autorizado por la licencia. Revisa los dominios permitidos en el panel de licencias.',
			),
			'INSTANCE_MISMATCH' => array(
				'level'   => 'error',
				'message' => 'La licencia está asociada a otra instalación. Revisa la activación registrada antes de continuar.',
			),
			'MAX_ACTIVATIONS_REACHED' => array(
				'level'   => 'error',
				'message' => 'La licencia alcanzó el máximo de activaciones permitidas. Libera una activación o amplía el límite desde el panel de licencias.',
			),
			'ACTIVATION_NOT_FOUND' => array(
				'level'   => 'warning',
				'message' => 'La activación de esta instalación ya no está registrada en el servidor. Revisa la licencia antes de intentar activarla nuevamente.',
			),
			'LICENSE_NOT_FOUND' => array(
				'level'   => 'error',
				'message' => 'La licencia ya no existe en el servicio de licencias. Debes asignar o emitir una nueva licencia.',
			),
			'LICENSING_MISSING_TOKEN' => array(
				'level'   => 'warning',
				'message' => 'Esta instalación no tiene un token de licencia guardado para revalidar.',
			),
			'LICENSING_VERIFICATION_FAILED' => array(
				'level'   => 'error',
				'message' => 'El token recibido no superó la verificación de seguridad. No se guardó como una licencia confiable.',
			),
			'LICENSING_INVALID_RESPONSE' => array(
				'level'   => 'error',
				'message' => 'El servicio de licencias respondió de forma inesperada. Inténtalo nuevamente en unos momentos.',
			),
			'LICENSING_HTTP_ERROR' => array(
				'level'   => 'error',
				'message' => 'El servicio de licencias rechazó la operación. Revisa el estado de la licencia y vuelve a intentarlo.',
			),
		);

		if ( isset( $known[ $code ] ) ) {
			return $known[ $code ];
		}

		return array(
			'level'   => 'error',
			'message' => 'No se pudo completar la operación de licencia. Inténtalo nuevamente; si el problema continúa, revisa la conexión con el servicio de licencias.',
		);
	}

	public static function handle_activate() {
		self::authorize( 'headless_api_core_license_activate' );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( '' === $key ) {
			self::redirect_with_result( new \WP_Error( 'licensing_missing_key', 'License key is required.' ), 'activate' );
		}
		self::redirect_with_result( License_Manager::activate( $key ), 'activate' );
	}

	public static function handle_refresh() {
		self::authorize( 'headless_api_core_license_refresh' );
		self::redirect_with_result( License_Manager::refresh(), 'refresh' );
	}

	public static function handle_deactivate() {
		self::authorize( 'headless_api_core_license_deactivate' );
		self::redirect_with_result( License_Manager::deactivate(), 'deactivate' );
	}

	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this license.', 'wp-headless-api-core' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Prefer a specific server lifecycle code over the generic HTTP wrapper code.
	 *
	 * @param \WP_Error $error Licensing error.
	 * @return string
	 */
	private static function display_error_code( $error ) {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['response'] ) && is_array( $data['response'] ) ) {
			$response_code = isset( $data['response']['code'] ) && is_string( $data['response']['code'] )
				? trim( $data['response']['code'] )
				: '';
			if ( '' !== $response_code ) {
				return strtoupper( $response_code );
			}
		}
		return strtoupper( (string) $error->get_error_code() );
	}

	private static function redirect_with_result( $result, $action ) {
		$args = array(
			'page'           => self::PAGE_SLUG,
			'license_action' => sanitize_key( $action ),
		);
		if ( is_wp_error( $result ) ) {
			$args['license_result'] = 'error';
			$args['license_code']   = self::display_error_code( $result );
		} else {
			$args['license_result'] = 'success';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}
}
