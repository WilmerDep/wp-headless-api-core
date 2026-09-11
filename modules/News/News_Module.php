<?php
/**
 * News module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\News;

use HeadlessApiCore\Revalidation\Revalidation_Client;

defined( 'ABSPATH' ) || exit;

final class News_Module {
	/**
	 * Register the module with WordPress.
	 *
	 * @return void
	 */
	public static function boot() {
		$serializer = new News_Serializer();
		$controller = new News_Controller( $serializer );
		$controller->register();

		$revalidation_client = new Revalidation_Client();
		$revalidation        = new News_Revalidation( $revalidation_client );
		$revalidation->register();
	}
}
