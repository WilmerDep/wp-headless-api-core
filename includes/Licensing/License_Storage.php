<?php
/**
 * Persistence boundary for licensing state.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Storage {
	const OPTION_KEY = 'headless_api_core_license_state';

	/**
	 * Read persisted licensing state.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$value = get_option( self::OPTION_KEY, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Persist licensing state without autoloading the token on every request.
	 *
	 * @param array<string,mixed> $state State payload.
	 * @return void
	 */
	public static function set( array $state ) {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $state, '', false );
			return;
		}

		update_option( self::OPTION_KEY, $state, false );
	}

	/**
	 * Clear all persisted licensing state, while preserving instance identity.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
	}
}
