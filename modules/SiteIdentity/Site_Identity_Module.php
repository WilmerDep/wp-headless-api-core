<?php
/**
 * Site Identity module bootstrap.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\SiteIdentity;

use HeadlessApiCore\Revalidation\Revalidation_Client;

defined( 'ABSPATH' ) || exit;

final class Site_Identity_Module {
	/** @var bool */
	private static $booted = false;

	/** Register public native identity contract and revalidation hooks. */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		$serializer   = new Site_Identity_Serializer();
		$controller   = new Site_Identity_Controller( $serializer );
		$revalidation = new Site_Identity_Revalidation( new Revalidation_Client() );

		$controller->register();
		$revalidation->register();
	}
}
