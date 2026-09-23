<?php
/**
 * WordPress admin notices for licensing lifecycle state.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Admin_Notice {
	/**
	 * Register admin-only licensing UX.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render a persistent status notice for administrators when attention is needed.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = self::build_notice( License_Manager::verify_stored() );
		if ( null === $notice ) {
			return;
		}

		$class = 'error' === $notice['level'] ? 'notice notice-error' : 'notice notice-warning';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><strong><?php esc_html_e( 'Headless API Core — Licencia', 'wp-headless-api-core' ); ?></strong></p>
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Convert verification state into administrator-facing copy.
	 *
	 * This method is intentionally deterministic and free from WordPress I/O so
	 * the lifecycle UX can be regression-tested independently from wp-admin.
	 *
	 * @param array<string,mixed> $verification Current verification result.
	 * @return array<string,string>|null
	 */
	public static function build_notice( array $verification ) {
		$decision = License_Gate::decide(
			$verification,
			License_Policy::CAPABILITY_ADMINISTRATIVE,
			true,
			null
		);

		$state = isset( $decision['status'] ) ? (string) $decision['status'] : License_Policy::STATE_UNTRUSTED;
		$code  = isset( $verification['code'] ) ? (string) $verification['code'] : '';

		if ( License_Policy::STATE_ACTIVE === $state && ! empty( $verification['operational'] ) ) {
			return null;
		}

		if ( License_Policy::STATE_GRACE === $state ) {
			return array(
				'level'   => 'warning',
				'code'    => 'LICENSE_GRACE_PERIOD',
				'message' => 'La licencia está en período de gracia. El sitio continúa funcionando normalmente, pero conviene renovarla antes de que termine este período.',
			);
		}

		if ( License_Policy::STATE_OFFLINE_EXCEEDED === $state ) {
			return array(
				'level'   => 'warning',
				'code'    => 'LICENSE_REVALIDATION_REQUIRED',
				'message' => 'La licencia sigue siendo auténtica, pero necesita volver a validarse con el servicio de licencias. El contenido público Headless permanece limitado hasta completar la revalidación.',
			);
		}

		if ( License_Policy::STATE_EXPIRED === $state ) {
			return array(
				'level'   => 'warning',
				'code'    => 'LICENSE_RENEWAL_REQUIRED',
				'message' => 'La licencia ha vencido. Puedes seguir administrando, importando y preparando contenido en WordPress, pero las capacidades públicas Headless permanecen limitadas hasta renovar.',
			);
		}

		if ( License_Policy::STATE_SUSPENDED === $state ) {
			return array(
				'level'   => 'warning',
				'code'    => 'LICENSE_SUSPENDED',
				'message' => 'La licencia está suspendida. La administración de contenido sigue disponible, pero las capacidades públicas Headless están limitadas mientras se resuelve el estado de la licencia.',
			);
		}

		if ( License_Policy::STATE_REVOKED === $state ) {
			return array(
				'level'   => 'error',
				'code'    => 'LICENSE_REVOKED',
				'message' => 'La licencia fue revocada. Los datos y la administración de WordPress permanecen accesibles para recuperación y gestión, pero las capacidades licenciadas están bloqueadas.',
			);
		}

		$message = 'No se pudo verificar la licencia de Headless API Core. La administración de WordPress permanece disponible, pero las capacidades públicas licenciadas están bloqueadas hasta restablecer una verificación válida.';
		if ( 'TOKEN_MISSING' === $code ) {
			$message = 'Headless API Core todavía no tiene una licencia verificada en esta instalación. Puedes administrar el contenido en WordPress, pero las capacidades públicas licenciadas permanecen bloqueadas hasta activar una licencia.';
		} elseif ( 'VERIFICATION_UNAVAILABLE' === $code ) {
			$message = 'Este servidor no puede verificar firmas Ed25519 mediante Sodium. La administración de WordPress permanece disponible, pero las capacidades públicas licenciadas están bloqueadas por seguridad.';
		}

		return array(
			'level'   => 'error',
			'code'    => '' !== $code ? $code : 'LICENSE_VERIFICATION_REQUIRED',
			'message' => $message,
		);
	}
}
