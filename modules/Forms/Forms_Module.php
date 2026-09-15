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

	/** Register the generic forms data model, templates and public contract. */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		$mail_settings = Mail_Module::settings();
		if ( ! $mail_settings ) {
			return;
		}

		$post_type     = new Forms_Post_Type();
		$serializer    = new Forms_Serializer( $mail_settings );
		$validator     = new Forms_Validator();
		$renderer      = new Mail_Template_Renderer();
		$template_test = new Mail_Template_Test( $mail_settings, $renderer );
		$submission    = new Forms_Submission( $mail_settings, $validator, $renderer );
		$controller    = new Forms_Controller( $serializer, $submission );
		$admin         = new Forms_Admin();
		$importer      = new Forms_Package_Importer();
		$import_ui     = new Forms_Import_UI();

		$post_type->register();
		$controller->register();
		$admin->register();
		$template_test->register();
		$importer->register();
		$import_ui->register();
	}
}
