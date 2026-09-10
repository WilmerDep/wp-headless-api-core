<?php
/**
 * Isolated regression test that News provider remains public-only.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
		public $ID = 1;
		public $post_status = 'publish';
		public $post_password = '';
	}

	class WP_REST_Request {
		private $params;

		public function __construct( array $params ) {
			$this->params = $params;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}
	}

	class WP_REST_Response {
		public $data;
		public $status;

		public function __construct( $data, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
	}

	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code, $message, $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
	}

	class WP_REST_Server {
		const READABLE = 'GET';
	}

	class WP_Query {
		public $posts = array();
		public $found_posts = 0;
		public $max_num_pages = 0;

		public function __construct( array $args ) {
			$GLOBALS['news_query_args'] = $args;
		}
	}

	$GLOBALS['news_detail_post'] = null;
}

namespace HeadlessApiCore\Core {
	class Plugin {
		const REST_NAMESPACE = 'headless-core/v1';
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
			return array( 'ok' => true );
		}
	}

	function get_page_by_path( $slug, $output, $post_type ) {
		unset( $slug, $output, $post_type );
		return $GLOBALS['news_detail_post'];
	}

	function get_post_status( $post ) {
		return $post->post_status;
	}

	function __( $value, $domain ) {
		unset( $domain );
		return $value;
	}

	require_once dirname( __DIR__ ) . '/modules/News/News_Controller.php';

	$controller = new News_Controller( new News_Serializer() );
	$request    = new \WP_REST_Request(
		array(
			'page'     => 1,
			'per_page' => 12,
			'order'    => 'desc',
			'orderby'  => 'date',
		)
	);

	$controller->get_items( $request );
	$args = $GLOBALS['news_query_args'];

	if ( 'publish' !== ( $args['post_status'] ?? null ) || false !== ( $args['has_password'] ?? null ) ) {
		fwrite( STDERR, "News collection must query only published, non-password-protected posts.\n" );
		exit( 1 );
	}

	foreach ( array( 'draft', 'pending', 'private', 'trash', 'future' ) as $status ) {
		$post              = new \WP_Post();
		$post->post_status = $status;
		$GLOBALS['news_detail_post'] = $post;
		$result = $controller->get_item( new \WP_REST_Request( array( 'slug' => 'test' ) ) );

		if ( ! ( $result instanceof \WP_Error ) || 'headless_core_news_not_found' !== $result->code || 404 !== ( $result->data['status'] ?? null ) ) {
			fwrite( STDERR, 'News detail exposed non-public status: ' . $status . "\n" );
			exit( 1 );
		}
	}

	$protected                = new \WP_Post();
	$protected->post_status   = 'publish';
	$protected->post_password = 'secret';
	$GLOBALS['news_detail_post'] = $protected;
	$result = $controller->get_item( new \WP_REST_Request( array( 'slug' => 'protected' ) ) );

	if ( ! ( $result instanceof \WP_Error ) || 'headless_core_news_not_found' !== $result->code ) {
		fwrite( STDERR, "Password-protected News detail must remain hidden.\n" );
		exit( 1 );
	}

	$public                    = new \WP_Post();
	$public->post_status       = 'publish';
	$public->post_password     = '';
	$GLOBALS['news_detail_post'] = $public;
	$result = $controller->get_item( new \WP_REST_Request( array( 'slug' => 'public' ) ) );

	if ( ! ( $result instanceof \WP_REST_Response ) || 200 !== $result->status ) {
		fwrite( STDERR, "Published public News detail should remain available.\n" );
		exit( 1 );
	}

	echo "News public visibility test passed.\n";
}
