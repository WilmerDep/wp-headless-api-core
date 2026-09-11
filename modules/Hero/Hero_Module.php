<?php
/**
 * Hero module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

use HeadlessApiCore\Revalidation\Revalidation_Client;

defined( 'ABSPATH' ) || exit;

final class Hero_Module {
	/**
	 * Register Hero model, editorial controls, public REST surface and realtime
	 * cache invalidation notifications.
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

		$revalidation_client = new Revalidation_Client();
		$revalidation        = new Hero_Revalidation( $revalidation_client );
		$revalidation->register();
	}
}
