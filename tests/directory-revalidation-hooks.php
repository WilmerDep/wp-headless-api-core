<?php
/**
 * Isolated regression test for Directory lifecycle hook registration.
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

namespace HeadlessApiCore\Modules\Directory {
	$GLOBALS['directory_registered_hooks'] = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $callback );
		$GLOBALS['directory_registered_hooks'][ $hook ] = array(
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}

	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Revalidation.php';

	$lifecycle = new Directory_Revalidation( new \HeadlessApiCore\Revalidation\Revalidation_Client() );
	$lifecycle->register();

	$expected = array(
		'transition_post_status'                         => array( 10, 3 ),
		'post_updated'                                   => array( 10, 3 ),
		'save_post_headless_person'                      => array( 30, 3 ),
		'added_post_meta'                                => array( 10, 4 ),
		'updated_post_meta'                              => array( 10, 4 ),
		'deleted_post_meta'                              => array( 10, 4 ),
		'set_object_terms'                               => array( 10, 6 ),
		'created_headless_directory_group'               => array( 10, 3 ),
		'edited_headless_directory_group'                => array( 10, 3 ),
		'delete_headless_directory_group'                => array( 10, 4 ),
		'before_delete_post'                             => array( 10, 2 ),
		'headless_api_core_directory_bulk_start'         => array( 10, 1 ),
		'headless_api_core_directory_bulk_end'           => array( 10, 1 ),
		'headless_api_core_directory_collection_changed' => array( 10, 1 ),
		'shutdown'                                       => array( PHP_INT_MAX, 1 ),
	);

	foreach ( $expected as $hook => $settings ) {
		if ( ! isset( $GLOBALS['directory_registered_hooks'][ $hook ] ) ) {
			fwrite( STDERR, 'Missing Directory lifecycle hook: ' . $hook . "\n" );
			exit( 1 );
		}
		if ( $settings[0] !== $GLOBALS['directory_registered_hooks'][ $hook ]['priority'] || $settings[1] !== $GLOBALS['directory_registered_hooks'][ $hook ]['accepted_args'] ) {
			fwrite( STDERR, 'Unexpected Directory lifecycle hook settings: ' . $hook . "\n" );
			exit( 1 );
		}
	}

	if ( count( $expected ) !== count( $GLOBALS['directory_registered_hooks'] ) ) {
		fwrite( STDERR, "Unexpected extra Directory lifecycle hooks registered.\n" );
		exit( 1 );
	}

	echo "Directory revalidation hook registration test passed.\n";
}
