<?php
/**
 * Core plugin bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Core;

use HeadlessApiCore\Modules\Hero\Hero_Module;
use HeadlessApiCore\Modules\News\News_Module;
use HeadlessApiCore\Rest\Health_Controller;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/**
	 * REST API namespace for the v1 contract.
	 */
	const REST_NAMESPACE = 'headless-core/v1';

	/**
	 * Prevent duplicate bootstrapping.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Bootstrap the plugin modules available in this version.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		$health_controller = new Health_Controller();
		$health_controller->register();

		News_Module::boot();
		Hero_Module::boot();
	}
}
