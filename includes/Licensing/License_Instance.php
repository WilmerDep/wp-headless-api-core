<?php
/**
 * Stable instance identity for one WordPress installation.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Instance {
	const OPTION_KEY = 'headless_api_core_license_instance_id';

	/**
	 * Return the persistent UUID for this installation, creating it once if needed.
	 *
	 * @return string
	 */
	public static function get() {
		$instance_id = get_option( self::OPTION_KEY, '' );
		if ( is_string( $instance_id ) && '' !== $instance_id ) {
			return $instance_id;
		}

		$instance_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : self::fallback_uuid4();
		add_option( self::OPTION_KEY, $instance_id, '', false );

		$stored = get_option( self::OPTION_KEY, $instance_id );
		return is_string( $stored ) && '' !== $stored ? $stored : $instance_id;
	}

	/**
	 * Lightweight UUIDv4 fallback for isolated tests / unusual runtimes.
	 *
	 * @return string
	 */
	private static function fallback_uuid4() {
		$data = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );

		return vsprintf(
			'%s%s-%s-%s-%s-%s%s%s',
			str_split( bin2hex( $data ), 4 )
		);
	}
}
