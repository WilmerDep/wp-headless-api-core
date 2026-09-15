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
	const LAST_TEST_OPTION = 'headless_api_core_mail_last_test';

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

	/** Whether SMTP delivery is enabled. */
	public function is_enabled() {
		$settings = $this->get();
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Whether the minimum transport configuration is present.
	 *
	 * This intentionally validates capability rather than provider-specific
	 * conventions so future Forms/notifications modules can depend on it.
	 */
	public function is_configured() {
		$settings = $this->get();

		if ( '' === trim( (string) $settings['host'] ) || empty( $settings['port'] ) ) {
			return false;
		}

		if ( ! empty( $settings['auth'] ) ) {
			if ( '' === trim( (string) $settings['username'] ) || '' === (string) $settings['password'] ) {
				return false;
			}
		}

		if ( ! empty( $settings['force_from'] ) && ! is_email( (string) $settings['from_email'] ) ) {
			return false;
		}

		return true;
	}

	/** Whether the transport can be used by dependent modules. */
	public function is_ready() {
		return $this->is_enabled() && $this->is_configured();
	}

	/**
	 * Persist a safe transport-test result for health/status consumers.
	 *
	 * No recipient, host, username, password or raw SMTP error is stored here.
	 *
	 * @param bool $success Whether wp_mail() accepted the test delivery.
	 */
	public function record_test( $success ) {
		update_option(
			self::LAST_TEST_OPTION,
			array(
				'success'   => (bool) $success,
				'tested_at' => time(),
			),
			false
		);
	}

	/** Return the last safe SMTP test result, or null when never tested. */
	public function get_last_test() {
		$stored = get_option( self::LAST_TEST_OPTION, null );
		if ( ! is_array( $stored ) || ! isset( $stored['success'], $stored['tested_at'] ) ) {
			return null;
		}

		$timestamp = absint( $stored['tested_at'] );
		if ( $timestamp < 1 ) {
			return null;
		}

		return array(
			'success'  => (bool) $stored['success'],
			'testedAt' => gmdate( 'c', $timestamp ),
		);
	}

	/**
	 * Safe public status contract for headless consumers.
	 *
	 * Credentials and provider-specific connection details are deliberately
	 * excluded. Consumers only learn whether the mail capability is available.
	 *
	 * @return array<string,mixed>
	 */
	public function public_status() {
		$enabled    = $this->is_enabled();
		$configured = $this->is_configured();
		$ready      = $enabled && $configured;
		$status     = 'ready';

		if ( ! $enabled ) {
			$status = 'disabled';
		} elseif ( ! $configured ) {
			$status = 'incomplete';
		}

		return array(
			'schemaVersion' => 1,
			'transport'     => 'smtp',
			'enabled'       => $enabled,
			'configured'    => $configured,
			'ready'         => $ready,
			'status'        => $status,
			'lastTest'      => $this->get_last_test(),
			'capabilities'  => array(
				'send' => $ready,
			),
		);
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
