<?php
/**
 * SMTP settings and WordPress mail integration.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Mail;

defined( 'ABSPATH' ) || exit;

final class Mail_Settings {
	const OPTION_NAME = 'headless_api_core_mail';

	/**
	 * Return persisted settings with optional wp-config.php overrides.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$defaults = array(
			'enabled'    => false,
			'host'       => '',
			'port'       => 587,
			'encryption' => 'tls',
			'auth'       => true,
			'username'   => '',
			'password'   => '',
			'from_email' => '',
			'from_name'  => '',
			'force_from' => true,
		);

		$stored   = get_option( self::OPTION_NAME, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );

		$constant_map = array(
			'HEADLESS_SMTP_ENABLED'     => 'enabled',
			'HEADLESS_SMTP_HOST'        => 'host',
			'HEADLESS_SMTP_PORT'        => 'port',
			'HEADLESS_SMTP_ENCRYPTION'  => 'encryption',
			'HEADLESS_SMTP_AUTH'        => 'auth',
			'HEADLESS_SMTP_USER'        => 'username',
			'HEADLESS_SMTP_PASSWORD'    => 'password',
			'HEADLESS_SMTP_FROM_EMAIL'  => 'from_email',
			'HEADLESS_SMTP_FROM_NAME'   => 'from_name',
			'HEADLESS_SMTP_FORCE_FROM'  => 'force_from',
		);

		foreach ( $constant_map as $constant => $key ) {
			if ( defined( $constant ) ) {
				$settings[ $key ] = constant( $constant );
			}
		}

		$settings['enabled']    = (bool) $settings['enabled'];
		$settings['auth']       = (bool) $settings['auth'];
		$settings['force_from'] = (bool) $settings['force_from'];
		$settings['port']       = max( 1, min( 65535, absint( $settings['port'] ) ) );
		$settings['encryption'] = in_array( $settings['encryption'], array( 'tls', 'ssl', 'none' ), true ) ? $settings['encryption'] : 'tls';

		return $settings;
	}

	/**
	 * Sanitize and persist admin settings.
	 *
	 * @param array<string,mixed> $input Raw request values.
	 * @return array<string,mixed>
	 */
	public function save( array $input ) {
		$current = get_option( self::OPTION_NAME, array() );
		$current = is_array( $current ) ? $current : array();

		$password = isset( $input['password'] ) ? (string) $input['password'] : '';
		if ( '' === $password && isset( $current['password'] ) ) {
			$password = (string) $current['password'];
		}

		$encryption = isset( $input['encryption'] ) ? sanitize_key( $input['encryption'] ) : 'tls';
		if ( ! in_array( $encryption, array( 'tls', 'ssl', 'none' ), true ) ) {
			$encryption = 'tls';
		}

		$settings = array(
			'enabled'    => ! empty( $input['enabled'] ),
			'host'       => isset( $input['host'] ) ? sanitize_text_field( $input['host'] ) : '',
			'port'       => isset( $input['port'] ) ? max( 1, min( 65535, absint( $input['port'] ) ) ) : 587,
			'encryption' => $encryption,
			'auth'       => ! empty( $input['auth'] ),
			'username'   => isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '',
			'password'   => $password,
			'from_email' => isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '',
			'from_name'  => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
			'force_from' => ! empty( $input['force_from'] ),
		);

		update_option( self::OPTION_NAME, $settings, false );
		return $settings;
	}

	/**
	 * Configure WordPress' bundled PHPMailer instance.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( $phpmailer ) {
		$settings = $this->get();
		if ( empty( $settings['enabled'] ) || '' === $settings['host'] ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['host'];
		$phpmailer->Port       = (int) $settings['port'];
		$phpmailer->SMTPAuth   = (bool) $settings['auth'];
		$phpmailer->Username   = $settings['username'];
		$phpmailer->Password   = $settings['password'];
		$phpmailer->SMTPDebug  = 0;

		if ( 'none' === $settings['encryption'] ) {
			$phpmailer->SMTPSecure = '';
			$phpmailer->SMTPAutoTLS = false;
		} else {
			$phpmailer->SMTPSecure = $settings['encryption'];
		}
	}

	/** Force configured sender when enabled. */
	public function filter_from_email( $email ) {
		$settings = $this->get();
		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['force_from'] ) && is_email( $settings['from_email'] ) ) {
			return $settings['from_email'];
		}
		return $email;
	}

	/** Force configured sender name when enabled. */
	public function filter_from_name( $name ) {
		$settings = $this->get();
		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['force_from'] ) && '' !== $settings['from_name'] ) {
			return $settings['from_name'];
		}
		return $name;
	}

	/** Whether a setting is controlled by wp-config.php. */
	public function is_constant_controlled( $key ) {
		$map = array(
			'enabled'    => 'HEADLESS_SMTP_ENABLED',
			'host'       => 'HEADLESS_SMTP_HOST',
			'port'       => 'HEADLESS_SMTP_PORT',
			'encryption' => 'HEADLESS_SMTP_ENCRYPTION',
			'auth'       => 'HEADLESS_SMTP_AUTH',
			'username'   => 'HEADLESS_SMTP_USER',
			'password'   => 'HEADLESS_SMTP_PASSWORD',
			'from_email' => 'HEADLESS_SMTP_FROM_EMAIL',
			'from_name'  => 'HEADLESS_SMTP_FROM_NAME',
			'force_from' => 'HEADLESS_SMTP_FORCE_FROM',
		);

		return isset( $map[ $key ] ) && defined( $map[ $key ] );
	}
}
