<?php
/**
 * Isolated regression test for News lifecycle hook registration.
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

namespace HeadlessApiCore\Modules\News {
	$GLOBALS['headless_registered_hooks'] = array();

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $callback );
		$GLOBALS['headless_registered_hooks'][ $hook ] = array(
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}

	require_once dirname( __DIR__ ) . '/modules/News/News_Revalidation.php';

	$lifecycle = new News_Revalidation( new \HeadlessApiCore\Revalidation\Revalidation_Client() );
	$lifecycle->register();

	$expected = array(
		'transition_post_status' => 3,
		'post_updated'           => 3,
		'save_post_post'         => 3,
		'set_object_terms'       => 6,
		'added_post_meta'        => 4,
		'updated_post_meta'      => 4,
		'deleted_post_meta'      => 4,
		'before_delete_post'     => 2,
		'shutdown'               => 1,
	);

	foreach ( $expected as $hook => $accepted_args ) {
		if ( ! isset( $GLOBALS['headless_registered_hooks'][ $hook ] ) ) {
			fwrite( STDERR, 'Missing News lifecycle hook: ' . $hook . "\n" );
			exit( 1 );
		}

		if ( $accepted_args !== $GLOBALS['headless_registered_hooks'][ $hook ]['accepted_args'] ) {
			fwrite( STDERR, 'Unexpected accepted args for lifecycle hook: ' . $hook . "\n" );
			exit( 1 );
		}
	}

	if ( count( $expected ) !== count( $GLOBALS['headless_registered_hooks'] ) ) {
		fwrite( STDERR, "Unexpected extra lifecycle hooks registered.\n" );
		exit( 1 );
	}

	echo "News revalidation hook registration test passed.\n";
}
