<?php
/**
 * Isolated regression test for controlled News licensing enforcement.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_REST_Request {
		private $params;
		private $route;

		public function __construct( array $params = array(), $route = '' ) {
			$this->params = $params;
			$this->route  = $route;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
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

		public function header( $key, $value ) {
			$this->headers[ $key ] = $value;
		}
	}

	class WP_Error {}

	class WP_REST_Server {
		const READABLE = 'GET';
	}

	class WP_Query {
		public $posts = array();
		public $found_posts = 0;
		public $max_num_pages = 0;

		public function __construct( array $args ) {
			unset( $args );
			$GLOBALS['news_query_count']++;
		}
	}

	$GLOBALS['news_query_count'] = 0;
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
		public static $decision = array(
			'allowed' => true,
			'code'    => null,
			'status'  => 'active',
		);

		public static function evaluate( $capability, $entitlement = null ) {
			if ( License_Policy::CAPABILITY_PUBLIC_CONTENT !== $capability || 'news' !== $entitlement ) {
				throw new \RuntimeException( 'News must evaluate the public_content/news licensing gate.' );
			}

			return self::$decision;
		}
	}
}

namespace HeadlessApiCore\Modules\News {
	class News_Serializer {
		public function summary( $post ) {
			unset( $post );
			return array();
		}

		public function detail( $post ) {
			unset( $post );
			return array();
		}
	}

	function get_post_status( $post ) {
		return $post->post_status;
	}

	function __( $value, $domain ) {
		unset( $domain );
		return $value;
	}

	require_once dirname( __DIR__ ) . '/modules/News/News_Controller.php';

	use HeadlessApiCore\Licensing\License_Gate;

	function assert_case( $condition, $message ) {
		if ( ! $condition ) {
			fwrite( STDERR, $message . "\n" );
			exit( 1 );
		}
	}

	$controller = new News_Controller( new News_Serializer() );
	$request    = new \WP_REST_Request(
		array(
			'page'     => 1,
			'per_page' => 12,
			'order'    => 'desc',
			'orderby'  => 'date',
		)
	);

	License_Gate::$decision = array(
		'allowed' => true,
		'code'    => null,
		'status'  => 'active',
	);
	$GLOBALS['news_query_count'] = 0;
	$active = $controller->get_items( $request );
	assert_case( $active instanceof \WP_REST_Response && 200 === $active->status, 'Active licensed News should remain public.' );
	assert_case( 1 === $GLOBALS['news_query_count'], 'Allowed News must execute its editorial query.' );

	License_Gate::$decision = array(
		'allowed' => true,
		'code'    => null,
		'status'  => 'grace_period',
	);
	$GLOBALS['news_query_count'] = 0;
	$grace = $controller->get_items( $request );
	assert_case( 200 === $grace->status, 'News must remain public during grace period.' );
	assert_case( 1 === $GLOBALS['news_query_count'], 'Grace-period News must still query content.' );

	License_Gate::$decision = array(
		'allowed' => false,
		'code'    => 'LICENSE_RENEWAL_REQUIRED',
		'status'  => 'expired',
	);
	$GLOBALS['news_query_count'] = 0;
	$expired = $controller->get_items( $request );
	assert_case( 403 === $expired->status, 'Expired News must return an explicit 403 licensing response.' );
	assert_case( 'LICENSE_RENEWAL_REQUIRED' === ( $expired->data['code'] ?? null ), 'Expired News must expose the renewal-required code.' );
	assert_case( 'news' === ( $expired->data['module'] ?? null ) && 'expired' === ( $expired->data['status'] ?? null ), 'Expired News response must identify module and state.' );
	assert_case( 0 === $GLOBALS['news_query_count'], 'Restricted News must not query or expose editorial content.' );

	License_Gate::$decision = array(
		'allowed' => false,
		'code'    => 'ENTITLEMENT_REQUIRED',
		'status'  => 'active',
	);
	$GLOBALS['news_query_count'] = 0;
	$entitlement = $controller->get_items( $request );
	assert_case( 403 === $entitlement->status && 'ENTITLEMENT_REQUIRED' === ( $entitlement->data['code'] ?? null ), 'Missing News entitlement must be explicit.' );
	assert_case( 0 === $GLOBALS['news_query_count'], 'Missing entitlement must not query News content.' );

	License_Gate::$decision = array(
		'allowed' => false,
		'code'    => 'LICENSE_VERIFICATION_REQUIRED',
		'status'  => 'untrusted',
	);
	$GLOBALS['news_query_count'] = 0;
	$untrusted = $controller->get_items( $request );
	assert_case( 403 === $untrusted->status && 'LICENSE_VERIFICATION_REQUIRED' === ( $untrusted->data['code'] ?? null ), 'Untrusted News state must fail closed explicitly.' );
	assert_case( 0 === $GLOBALS['news_query_count'], 'Untrusted News state must not query content.' );

	$controller->prevent_news_http_cache(
		$expired,
		new \WP_REST_Server(),
		new \WP_REST_Request( array(), '/headless-core/v1/news' )
	);
	assert_case( 'no-store, no-cache, must-revalidate, max-age=0' === ( $expired->headers['Cache-Control'] ?? null ), 'Restricted licensing responses must remain no-store.' );

	echo "News licensing enforcement test passed.\n";
}
