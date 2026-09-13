<?php
/**
 * Isolated regression test for Hero lifecycle hook registration.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
	}
}

namespace HeadlessApiCore\Revalidation {
	class Revalidation_Client {
		public function send( array $payload ) {
			unset( $payload );
			return true;
		}
	}
}

namespace HeadlessApiCore\Modules\Hero {
	$GLOBALS['hero_registered_hooks'] = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $callback );
		$GLOBALS['hero_registered_hooks'][ $hook ] = array(
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}

	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Revalidation.php';

	$lifecycle = new Hero_Revalidation( new \HeadlessApiCore\Revalidation\Revalidation_Client() );
	$lifecycle->register();

	$expected = array(
		'transition_post_status'      => array( 10, 3 ),
		'post_updated'                => array( 10, 3 ),
		'save_post_headless_hero'     => array( 30, 3 ),
		'added_post_meta'             => array( 10, 4 ),
		'updated_post_meta'           => array( 10, 4 ),
		'deleted_post_meta'           => array( 10, 4 ),
		'before_delete_post'          => array( 10, 2 ),
		'shutdown'                    => array( PHP_INT_MAX, 1 ),
	);

	foreach ( $expected as $hook => $settings ) {
		if ( ! isset( $GLOBALS['hero_registered_hooks'][ $hook ] ) ) {
			fwrite( STDERR, 'Missing Hero lifecycle hook: ' . $hook . "\n" );
			exit( 1 );
		}

		if ( $settings[0] !== $GLOBALS['hero_registered_hooks'][ $hook ]['priority'] ) {
			fwrite( STDERR, 'Unexpected priority for Hero lifecycle hook: ' . $hook . "\n" );
			exit( 1 );
		}

		if ( $settings[1] !== $GLOBALS['hero_registered_hooks'][ $hook ]['accepted_args'] ) {
			fwrite( STDERR, 'Unexpected accepted args for Hero lifecycle hook: ' . $hook . "\n" );
			exit( 1 );
		}
	}

	if ( count( $expected ) !== count( $GLOBALS['hero_registered_hooks'] ) ) {
		fwrite( STDERR, "Unexpected extra Hero lifecycle hooks registered.\n" );
		exit( 1 );
	}

	echo "Hero revalidation hook registration test passed.\n";
}
