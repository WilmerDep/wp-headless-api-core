<?php
/**
 * Central licensing enforcement policy.
 *
 * This class contains no WordPress I/O. It translates a trusted license state,
 * capability class, and entitlement decision into one consistent enforcement
 * result that modules can consume later through a shared gate.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Policy {
	const CAPABILITY_ADMINISTRATIVE       = 'administrative';
	const CAPABILITY_PUBLIC_CONTENT       = 'public_content';
	const CAPABILITY_CRITICAL_TRANSACTION = 'critical_transactional';
	const CAPABILITY_PREMIUM              = 'premium_capability';

	const STATE_ACTIVE           = 'active';
	const STATE_GRACE            = 'grace_period';
	const STATE_EXPIRED          = 'expired';
	const STATE_SUSPENDED        = 'suspended';
	const STATE_REVOKED          = 'revoked';
	const STATE_OFFLINE_EXCEEDED = 'offline_tolerance_exceeded';
	const STATE_UNTRUSTED        = 'untrusted';

	/**
	 * Evaluate whether a capability should be available.
	 *
	 * `has_entitlement` is deliberately supplied by the caller so this pure
	 * policy remains independent from token storage and verification details.
	 * Administrative capabilities intentionally ignore entitlements.
	 *
	 * @param string $state             Canonical policy state.
	 * @param string $capability        Capability class.
	 * @param bool   $has_entitlement   Whether the trusted token grants the feature.
	 * @return array<string,mixed>
	 */
	public static function evaluate( $state, $capability, $has_entitlement = true ) {
		$state      = self::normalize_state( $state );
		$capability = self::normalize_capability( $capability );

		if ( self::CAPABILITY_ADMINISTRATIVE === $capability ) {
			return self::decision( true, 'allow', null, self::admin_notice_level( $state ) );
		}

		if ( self::STATE_UNTRUSTED === $state ) {
			return self::decision( false, 'block', 'LICENSE_VERIFICATION_REQUIRED', 'error' );
		}

		if ( self::STATE_REVOKED === $state ) {
			return self::decision( false, 'block', 'LICENSE_REVOKED', 'error' );
		}

		if ( ! $has_entitlement ) {
			return self::decision( false, 'restrict', 'ENTITLEMENT_REQUIRED', 'warning' );
		}

		if ( self::CAPABILITY_CRITICAL_TRANSACTION === $capability ) {
			if ( in_array(
				$state,
				array(
					self::STATE_ACTIVE,
					self::STATE_GRACE,
					self::STATE_EXPIRED,
					self::STATE_SUSPENDED,
					self::STATE_OFFLINE_EXCEEDED,
				),
				true
			) ) {
				$notice = self::STATE_ACTIVE === $state ? null : 'warning';
				return self::decision( true, 'allow', null, $notice );
			}

			return self::decision( false, 'block', 'LICENSE_VERIFICATION_REQUIRED', 'error' );
		}

		if ( in_array( $capability, array( self::CAPABILITY_PUBLIC_CONTENT, self::CAPABILITY_PREMIUM ), true ) ) {
			if ( self::STATE_ACTIVE === $state ) {
				return self::decision( true, 'allow', null, null );
			}

			if ( self::STATE_GRACE === $state ) {
				return self::decision( true, 'allow', null, 'warning' );
			}

			if ( self::STATE_OFFLINE_EXCEEDED === $state ) {
				return self::decision( false, 'restrict', 'LICENSE_REVALIDATION_REQUIRED', 'warning' );
			}

			if ( self::STATE_SUSPENDED === $state ) {
				return self::decision( false, 'restrict', 'LICENSE_SUSPENDED', 'warning' );
			}

			if ( self::STATE_EXPIRED === $state ) {
				return self::decision( false, 'restrict', 'LICENSE_RENEWAL_REQUIRED', 'warning' );
			}
		}

		return self::decision( false, 'block', 'LICENSE_POLICY_UNRESOLVED', 'error' );
	}

	/** @return array<string,mixed> */
	private static function decision( $allowed, $mode, $code, $notice_level ) {
		return array(
			'allowed'     => (bool) $allowed,
			'mode'        => (string) $mode,
			'code'        => $code,
			'noticeLevel' => $notice_level,
		);
	}

	/** @return string */
	private static function normalize_state( $state ) {
		$state = (string) $state;
		$known = array(
			self::STATE_ACTIVE,
			self::STATE_GRACE,
			self::STATE_EXPIRED,
			self::STATE_SUSPENDED,
			self::STATE_REVOKED,
			self::STATE_OFFLINE_EXCEEDED,
			self::STATE_UNTRUSTED,
		);

		return in_array( $state, $known, true ) ? $state : self::STATE_UNTRUSTED;
	}

	/** @return string */
	private static function normalize_capability( $capability ) {
		$capability = (string) $capability;
		$known      = array(
			self::CAPABILITY_ADMINISTRATIVE,
			self::CAPABILITY_PUBLIC_CONTENT,
			self::CAPABILITY_CRITICAL_TRANSACTION,
			self::CAPABILITY_PREMIUM,
		);

		return in_array( $capability, $known, true ) ? $capability : '';
	}

	/** @return string|null */
	private static function admin_notice_level( $state ) {
		if ( self::STATE_ACTIVE === $state ) {
			return null;
		}

		if ( in_array(
			$state,
			array(
				self::STATE_GRACE,
				self::STATE_EXPIRED,
				self::STATE_SUSPENDED,
				self::STATE_OFFLINE_EXCEEDED,
			),
			true
		) ) {
			return 'warning';
		}

		return 'error';
	}
}
