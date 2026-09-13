<?php
/**
 * Isolated regression test for Services lifecycle hook registration.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	class WP_Post {}
}

namespace HeadlessApiCore\Revalidation {
	class Revalidation_Client {
		public function send( array $payload ) {
			unset( $payload );
			return true;
		}
	}
}

namespace HeadlessApiCore\Modules\Services {
	$GLOBALS['services_registered_hooks'] = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $callback );
		$GLOBALS['services_registered_hooks'][ $hook ] = array(
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}

	require_once dirname( __DIR__ ) . '/modules/Services/Services_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Services/Services_Revalidation.php';

	$lifecycle = new Services_Revalidation( new \HeadlessApiCore\Revalidation\Revalidation_Client() );
	$lifecycle->register();

	$expected = array(
		'transition_post_status'                          => array( 10, 3 ),
		'post_updated'                                    => array( 10, 3 ),
		'save_post_headless_service'                      => array( 30, 3 ),
		'added_post_meta'                                 => array( 10, 4 ),
		'updated_post_meta'                               => array( 10, 4 ),
		'deleted_post_meta'                               => array( 10, 4 ),
		'before_delete_post'                              => array( 10, 2 ),
		'headless_api_core_services_bulk_start'           => array( 10, 1 ),
		'headless_api_core_services_bulk_end'             => array( 10, 1 ),
		'headless_api_core_services_collection_changed'   => array( 10, 1 ),
		'shutdown'                                        => array( PHP_INT_MAX, 1 ),
	);

	foreach ( $expected as $hook => $settings ) {
		if ( ! isset( $GLOBALS['services_registered_hooks'][ $hook ] ) ) {
			fwrite( STDERR, 'Missing Services lifecycle hook: ' . $hook . "\n" );
			exit( 1 );
		}
		if ( $settings[0] !== $GLOBALS['services_registered_hooks'][ $hook ]['priority'] || $settings[1] !== $GLOBALS['services_registered_hooks'][ $hook ]['accepted_args'] ) {
			fwrite( STDERR, 'Unexpected Services lifecycle hook settings: ' . $hook . "\n" );
			exit( 1 );
		}
	}

	if ( count( $expected ) !== count( $GLOBALS['services_registered_hooks'] ) ) {
		fwrite( STDERR, "Unexpected extra Services lifecycle hooks registered.\n" );
		exit( 1 );
	}

	echo "Services revalidation hook registration test passed.\n";
}
