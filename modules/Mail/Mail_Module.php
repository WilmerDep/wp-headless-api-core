<?php
/**
 * Mail module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Mail;

defined( 'ABSPATH' ) || exit;

final class Mail_Module {
	/** @var Mail_Settings|null */
	private static $settings = null;

	public static function boot() {
		if ( null !== self::$settings ) {
			return;
		}

		self::$settings = new Mail_Settings();
		$admin          = new Mail_Admin( self::$settings );

		add_action( 'phpmailer_init', array( self::$settings, 'configure_phpmailer' ) );
		add_filter( 'wp_mail_from', array( self::$settings, 'filter_from_email' ) );
		add_filter( 'wp_mail_from_name', array( self::$settings, 'filter_from_name' ) );
		$admin->register();
	}

	/** Shared mail settings service for future modules such as Forms Core. */
	public static function settings() {
		return self::$settings;
	}
}
