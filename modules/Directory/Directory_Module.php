<?php
/**
 * Directory module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

use HeadlessApiCore\Revalidation\Revalidation_Client;

defined( 'ABSPATH' ) || exit;

final class Directory_Module {
	/**
	 * Register Directory model, admin workspace, REST API, ordering, importer
	 * and realtime cache invalidation.
	 *
	 * @return void
	 */
	public static function boot() {
		$post_type = new Directory_Post_Type();
		$post_type->register();

		$serializer = new Directory_Serializer();

		$order = new Directory_Order();
		$order->register();

		$importer = new Directory_Importer();
		$importer->register();

		$admin = new Directory_Admin( $serializer );
		$admin->register();

		$controller = new Directory_Controller( $serializer );
		$controller->register();

		$revalidation_client = new Revalidation_Client();
		$revalidation        = new Directory_Revalidation( $revalidation_client );
		$revalidation->register();
	}
}
