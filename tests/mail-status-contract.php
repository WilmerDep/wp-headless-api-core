<?php
/**
 * Isolated Mail capability/status contract regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['mail_options'] = array();
	$GLOBALS['registered_mail_route'] = null;

	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['mail_options'] ) ? $GLOBALS['mail_options'][ $name ] : $default;
	}

	function update_option( $name, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['mail_options'][ $name ] = $value;
		return true;
	}

	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function is_email( $email ) {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	function register_rest_route( $namespace, $route, $args ) {
		$GLOBALS['registered_mail_route'] = array( $namespace, $route, $args );
		return true;
	}

	class WP_REST_Request {
		private $route;
		public function __construct( $route = '' ) {
			$this->route = $route;
		}
		public function get_route() {
			return $this->route;
		}
	}

	class WP_REST_Response {
		public $data;
		public $status;
		public $headers = array();
		public function __construct( $data, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function header( $name, $value ) {
			$this->headers[ $name ] = $value;
		}
	}

	class WP_REST_Server {
		const READABLE = 'GET';
	}
}

namespace HeadlessApiCore\Core {
	class Plugin {
		const REST_NAMESPACE = 'headless-core/v1';
	}
}

namespace HeadlessApiCore\Licensing {
	class License_Policy {
		const CAPABILITY_PUBLIC_CONTENT = 'public_content';
	}
	class License_Gate {
		public static function evaluate( $capability, $entitlement = null ) {
			unset( $capability, $entitlement );
			return array( 'allowed' => true, 'status' => 'active' );
		}
	}
}

namespace HeadlessApiCore\Modules\Mail {
	require_once dirname( __DIR__ ) . '/modules/Mail/Mail_Settings.php';
	require_once dirname( __DIR__ ) . '/modules/Mail/Mail_Controller.php';

	$settings = new Mail_Settings();
	$status   = $settings->public_status();
	if ( 'disabled' !== $status['status'] || $status['ready'] || $status['configured'] ) {
		fwrite( STDERR, "Disabled mail transport must not report ready/configured.\n" );
		exit( 1 );
	}

	$GLOBALS['mail_options'][ Mail_Settings::OPTION_NAME ] = array(
		'enabled'    => true,
		'host'       => 'mail.example.test',
		'port'       => 465,
		'encryption' => 'ssl',
		'auth'       => true,
		'username'   => 'forms@example.test',
		'password'   => '',
		'from_email' => 'forms@example.test',
		'from_name'  => 'Example Forms',
		'force_from' => true,
	);

	$status = $settings->public_status();
	if ( 'incomplete' !== $status['status'] || $status['ready'] || $status['configured'] ) {
		fwrite( STDERR, "Authenticated SMTP without a password must report incomplete.\n" );
		exit( 1 );
	}

	$GLOBALS['mail_options'][ Mail_Settings::OPTION_NAME ]['password'] = 'super-secret-value';
	$settings->record_test( true );
	$status = $settings->public_status();

	if (
		1 !== $status['schemaVersion'] ||
		'smtp' !== $status['transport'] ||
		'ready' !== $status['status'] ||
		! $status['enabled'] ||
		! $status['configured'] ||
		! $status['ready'] ||
		! $status['capabilities']['send'] ||
		empty( $status['lastTest']['success'] ) ||
		empty( $status['lastTest']['testedAt'] )
	) {
		fwrite( STDERR, "Configured SMTP must expose the stable ready capability contract.\n" );
		exit( 1 );
	}

	$encoded = json_encode( $status );
	foreach ( array( 'super-secret-value', 'mail.example.test', 'forms@example.test', 'Example Forms' ) as $secret ) {
		if ( false !== strpos( $encoded, $secret ) ) {
			fwrite( STDERR, "Public mail status must not expose SMTP connection details or identities.\n" );
			exit( 1 );
		}
	}

	$controller = new Mail_Controller( $settings );
	$controller->register_routes();
	$route = $GLOBALS['registered_mail_route'];
	if ( 'headless-core/v1' !== $route[0] || '/mail/status' !== $route[1] ) {
		fwrite( STDERR, "Mail status route contract changed unexpectedly.\n" );
		exit( 1 );
	}

	$response = $controller->get_status();
	if ( ! ( $response instanceof \WP_REST_Response ) || 200 !== $response->status || empty( $response->data['ready'] ) ) {
		fwrite( STDERR, "Mail status controller must return the public readiness contract.\n" );
		exit( 1 );
	}

	$controller->prevent_http_cache( $response, new \WP_REST_Server(), new \WP_REST_Request( '/headless-core/v1/mail/status' ) );
	if ( 'no-store, no-cache, must-revalidate, max-age=0' !== ( $response->headers['Cache-Control'] ?? null ) ) {
		fwrite( STDERR, "Mail status endpoint must advertise no-store cache policy.\n" );
		exit( 1 );
	}

	echo "Mail status contract test passed.\n";
}
