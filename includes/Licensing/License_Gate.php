<?php
/**
 * Central licensing gate for module capability decisions.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Gate {
	/**
	 * Evaluate a licensed capability using trust, state, policy and entitlement.
	 *
	 * @param string      $capability  License_Policy capability class.
	 * @param string|null $entitlement Optional entitlement slug.
	 * @return array<string,mixed>
	 */
	public static function evaluate( $capability, $entitlement = null ) {
		$verification = License_Manager::verify_stored();
		$trusted      = ! empty( $verification['trusted'] );
		$state        = self::resolve_state( $verification );

		$has_entitlement = true;
		if ( null !== $entitlement && '' !== (string) $entitlement ) {
			$has_entitlement = $trusted && License_Entitlements::has( (string) $entitlement );
		}

		$decision = License_Policy::evaluate( $state, $capability, $has_entitlement );

		$decision['trusted']     = $trusted;
		$decision['operational'] = ! empty( $verification['operational'] );
		$decision['status']      = $state;
		$decision['entitlement'] = null !== $entitlement ? (string) $entitlement : null;

		return $decision;
	}

	/**
	 * Convenience check for callers that only need a boolean.
	 *
	 * @param string      $capability  Capability class.
	 * @param string|null $entitlement Optional entitlement slug.
	 * @return bool
	 */
	public static function allows( $capability, $entitlement = null ) {
		$decision = self::evaluate( $capability, $entitlement );
		return ! empty( $decision['allowed'] );
	}

	/**
	 * Resolve the canonical policy state from a verification result.
	 *
	 * A cryptographically untrusted token is always `untrusted`, regardless of
	 * any status-shaped data that may exist elsewhere in storage.
	 *
	 * @param array<string,mixed> $verification Verification result.
	 * @return string
	 */
	private static function resolve_state( array $verification ) {
		if ( empty( $verification['trusted'] ) ) {
			return License_Policy::STATE_UNTRUSTED;
		}

		$status = isset( $verification['status'] ) ? (string) $verification['status'] : '';
		if ( in_array(
			$status,
			array(
				License_Policy::STATE_ACTIVE,
				License_Policy::STATE_GRACE,
				License_Policy::STATE_EXPIRED,
				License_Policy::STATE_SUSPENDED,
				License_Policy::STATE_REVOKED,
			),
			true
		) ) {
			return $status;
		}

		return License_Policy::STATE_UNTRUSTED;
	}
}
