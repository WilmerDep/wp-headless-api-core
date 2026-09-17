<?php
/**
 * Native WordPress Site Identity changes -> generic Consumer revalidation.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\SiteIdentity;

use HeadlessApiCore\Revalidation\Revalidation_Client;

defined( 'ABSPATH' ) || exit;

final class Site_Identity_Revalidation {
	/** @var Revalidation_Client */
	private $client;

	/** @var array<string,bool> */
	private $changes = array();

	/** @var bool */
	private $flushing = false;

	public function __construct( Revalidation_Client $client ) {
		$this->client = $client;
	}

	/** Observe native WordPress options without introducing theme-specific storage. */
	public function register() {
		add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );
		add_action( 'added_option', array( $this, 'on_added_option' ), 10, 2 );
		add_action( 'deleted_option', array( $this, 'on_deleted_option' ), 10, 1 );
		add_action( 'shutdown', array( $this, 'flush' ), PHP_INT_MAX );
	}

	public function on_updated_option( $option, $old_value, $value ) {
		$change = $this->change_for_option( $option, $old_value, $value );
		if ( $change ) {
			$this->changes[ $change ] = true;
		}
	}

	public function on_added_option( $option, $value ) {
		$change = $this->change_for_option( $option, null, $value );
		if ( $change ) {
			$this->changes[ $change ] = true;
		}
	}

	public function on_deleted_option( $option ) {
		$change = $this->change_for_deleted_option( $option );
		if ( $change ) {
			$this->changes[ $change ] = true;
		}
	}

	/** Send one compact event after WordPress has committed all option changes. */
	public function flush() {
		if ( $this->flushing || empty( $this->changes ) ) {
			return;
		}

		$this->flushing = true;
		$changes        = array_keys( $this->changes );
		sort( $changes );
		$this->changes = array();

		$this->client->send(
			array(
				'resource' => 'site_identity',
				'event'    => 'identity_updated',
				'changes'  => $changes,
			)
		);

		$this->flushing = false;
	}

	/** Map relevant core options to Consumer-facing identity concerns. */
	private function change_for_option( $option, $old_value, $value ) {
		$option = (string) $option;
		$map    = array(
			'blogname'        => 'name',
			'blogdescription' => 'description',
			'home'            => 'url',
			'siteurl'         => 'url',
			'WPLANG'          => 'language',
			'site_icon'       => 'favicon',
			'site_logo'       => 'logo',
		);

		if ( isset( $map[ $option ] ) ) {
			return $old_value === $value ? '' : $map[ $option ];
		}

		if ( 0 === strpos( $option, 'theme_mods_' ) ) {
			$old_logo = is_array( $old_value ) && isset( $old_value['custom_logo'] ) ? absint( $old_value['custom_logo'] ) : 0;
			$new_logo = is_array( $value ) && isset( $value['custom_logo'] ) ? absint( $value['custom_logo'] ) : 0;
			return $old_logo !== $new_logo ? 'logo' : '';
		}

		return '';
	}

	private function change_for_deleted_option( $option ) {
		$option = (string) $option;
		if ( 'site_icon' === $option ) {
			return 'favicon';
		}
		if ( 'site_logo' === $option || 0 === strpos( $option, 'theme_mods_' ) ) {
			return 'logo';
		}
		if ( in_array( $option, array( 'blogname', 'blogdescription', 'home', 'siteurl', 'WPLANG' ), true ) ) {
			return 'site';
		}
		return '';
	}
}
