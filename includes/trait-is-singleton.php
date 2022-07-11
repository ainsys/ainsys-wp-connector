<?php
/**
 * Plugin_Common trait.
 *
 * @package ainsys-connector-master
 */

namespace Ainsys\Connector\Master;

trait Is_Singleton {

	/**
	 * Instance for singleton.
	 *
	 * @var static|null
	 */
	protected static $instance = null;

	/**
	 * Singleton instance getter.
	 *
	 * @return static
	 */
	public static function get_instance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

}
