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
		$result_code  = isset( $_GET['license_code'] ) ? sanitize_key( wp_unslash( $_GET['license_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Headless API Core — Licencia', 'wp-headless-api-core' ); ?></h1>
			<p><?php esc_html_e( 'Gestiona la activación de esta instalación. La clave de licencia nunca se guarda en texto plano.', 'wp-headless-api-core' ); ?></p>

			<?php if ( 'success' === $result ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'La operación de licencia se completó correctamente.', 'wp-headless-api-core' ); ?></p></div>
			<?php elseif ( 'error' === $result ) : ?>
				<div class="notice notice-error"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: licensing error code. */
							__( 'No se pudo completar la operación de licencia. Código: %s', 'wp-headless-api-core' ),
							$result_code ? $result_code : 'licensing_error'
						)
					);
					?>
				</p></div>
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

	public static function handle_activate() {
		self::authorize( 'headless_api_core_license_activate' );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( '' === $key ) {
			self::redirect_with_result( new \WP_Error( 'licensing_missing_key', 'License key is required.' ) );
		}
		self::redirect_with_result( License_Manager::activate( $key ) );
	}

	public static function handle_refresh() {
		self::authorize( 'headless_api_core_license_refresh' );
		self::redirect_with_result( License_Manager::refresh() );
	}

	public static function handle_deactivate() {
		self::authorize( 'headless_api_core_license_deactivate' );
		self::redirect_with_result( License_Manager::deactivate() );
	}

	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this license.', 'wp-headless-api-core' ) );
		}
		check_admin_referer( $action );
	}

	private static function redirect_with_result( $result ) {
		$args = array( 'page' => self::PAGE_SLUG );
		if ( is_wp_error( $result ) ) {
			$args['license_result'] = 'error';
			$args['license_code']   = $result->get_error_code();
		} else {
			$args['license_result'] = 'success';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}
}
