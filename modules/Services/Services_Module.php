<?php
/**
 * Services module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

defined( 'ABSPATH' ) || exit;

final class Services_Module {
	/** Register Services model, admin workspace and REST API. */
	public static function boot() {
		$post_type = new Services_Post_Type();
		$post_type->register();

		$serializer = new Services_Serializer();

		$admin = new Services_Admin();
		$admin->register();

		$controller = new Services_Controller( $serializer );
		$controller->register();
	}
}
