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
	 * Determine whether a cryptographically trusted license grants an entitlement.
	 *
	 * Entitlements belong to the signed payload. They remain readable when the
	 * token is no longer operational so central policy can distinguish ordinary
	 * expiration from an untrusted token. This does not itself grant access to a
	 * module; callers must still pass the entitlement decision through
	 * License_Policy.
	 *
	 * @param string $entitlement Entitlement slug.
	 * @return bool
	 */
	public static function has( $entitlement ) {
		$state   = License_Storage::get();
		$trusted = ! empty( $state['trusted'] );

		// Compatibility with state written by the first Contract v1 implementation.
		if ( ! array_key_exists( 'trusted', $state ) && ! empty( $state['verified'] ) ) {
			$trusted = true;
		}

		if ( ! $trusted || empty( $state['payload'] ) || ! is_array( $state['payload'] ) ) {
			return false;
		}

		$entitlements = isset( $state['payload']['entitlements'] ) && is_array( $state['payload']['entitlements'] )
			? $state['payload']['entitlements']
			: array();

		return in_array( (string) $entitlement, $entitlements, true );
	}
}
