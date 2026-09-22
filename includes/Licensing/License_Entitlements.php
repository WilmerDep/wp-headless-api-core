<?php
/**
 * Entitlement facade for licensed plugin features.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Entitlements {
	/**
	 * Determine whether a verified license grants an entitlement.
	 *
	 * @param string $entitlement Entitlement slug.
	 * @return bool
	 */
	public static function has( $entitlement ) {
		$state = License_Storage::get();
		if ( empty( $state['verified'] ) || empty( $state['payload'] ) || ! is_array( $state['payload'] ) ) {
			return false;
		}

		$entitlements = isset( $state['payload']['entitlements'] ) && is_array( $state['payload']['entitlements'] )
			? $state['payload']['entitlements']
			: array();

		return in_array( (string) $entitlement, $entitlements, true );
	}
}
