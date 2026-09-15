<?php
/**
 * Forms Core module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use HeadlessApiCore\Modules\Mail\Mail_Module;

defined( 'ABSPATH' ) || exit;

final class Forms_Module {
	/** Prevent duplicate bootstrapping. */
	private static $booted = false;

	/** Register the generic forms data model and public schema contract. */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		$mail_settings = Mail_Module::settings();
		if ( ! $mail_settings ) {
			return;
		}

		$post_type  = new Forms_Post_Type();
		$serializer = new Forms_Serializer( $mail_settings );
		$controller = new Forms_Controller( $serializer );

		$post_type->register();
		$controller->register();
	}
}
