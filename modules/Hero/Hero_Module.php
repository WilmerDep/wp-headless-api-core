<?php
/**
 * Hero module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

defined( 'ABSPATH' ) || exit;

final class Hero_Module {
	/**
	 * Register Hero model, editorial controls and public REST surface.
	 *
	 * @return void
	 */
	public static function boot() {
		$post_type = new Hero_Post_Type();
		$post_type->register();

		$admin = new Hero_Admin();
		$admin->register();

		$serializer = new Hero_Serializer();
		$controller = new Hero_Controller( $serializer );
		$controller->register();
	}
}
