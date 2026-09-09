<?php
/**
 * News module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\News;

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
	}
}
