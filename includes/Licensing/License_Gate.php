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
	 * Evaluate a licensed capability using the currently stored token.
	 *
	 * @param string      $capability  License_Policy capability class.
	 * @param string|null $entitlement Optional entitlement slug.
	 * @return array<string,mixed>
	 */
	public static function evaluate( $capability, $entitlement = null ) {
		$verification = License_Manager::verify_stored();
		$trusted      = ! empty( $verification['trusted'] );

		$has_entitlement = true;
		if ( null !== $entitlement && '' !== (string) $entitlement ) {
			$has_entitlement = $trusted && License_Entitlements::has( (string) $entitlement );
		}

		return self::decide( $verification, $capability, $has_entitlement, $entitlement );
	}

	/**
	 * Pure decision boundary for an already verified token result.
	 *
	 * This keeps module policy deterministic and directly testable without
	 * bypassing production verification: normal callers should use evaluate().
	 *
	 * @param array<string,mixed> $verification    Verification result.
	 * @param string              $capability      Capability class.
	 * @param bool                $has_entitlement Whether the signed payload grants the feature.
	 * @param string|null         $entitlement     Optional entitlement slug for diagnostics.
	 * @return array<string,mixed>
	 */
	public static function decide( array $verification, $capability, $has_entitlement = true, $entitlement = null ) {
		$trusted = ! empty( $verification['trusted'] );
		$state   = self::resolve_state( $verification );

		if ( ! $trusted ) {
			$has_entitlement = false;
		}

		$decision = License_Policy::evaluate( $state, $capability, (bool) $has_entitlement );

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
	 * A cryptographically untrusted token is always `untrusted`. A trusted token
	 * whose offline tolerance has elapsed is a separate revalidation state: its
	 * signed claims remain trustworthy, but ordinary public/premium capabilities
	 * must not continue indefinitely without contacting the licensing service.
	 *
	 * @param array<string,mixed> $verification Verification result.
	 * @return string
	 */
	private static function resolve_state( array $verification ) {
		if ( empty( $verification['trusted'] ) ) {
			return License_Policy::STATE_UNTRUSTED;
		}

		if ( 'OFFLINE_TOLERANCE_EXCEEDED' === ( $verification['code'] ?? null ) ) {
			return License_Policy::STATE_OFFLINE_EXCEEDED;
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
