<?php
/**
 * Services module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use HeadlessApiCore\Revalidation\Revalidation_Client;

defined( 'ABSPATH' ) || exit;

final class Services_Module {
	/** Register Services model, admin workspace, import, REST API, ordering and revalidation. */
	public static function boot() {
		$post_type = new Services_Post_Type();
		$post_type->register();

		$serializer = new Services_Serializer();

		$order = new Services_Order();
		$order->register();

		$importer = new Services_Importer();
		$importer->register();

		$admin = new Services_Admin();
		$admin->register();

		$controller = new Services_Controller( $serializer );
		$controller->register();

		$revalidation_client = new Revalidation_Client();
		$revalidation        = new Services_Revalidation( $revalidation_client );
		$revalidation->register();
	}
}
