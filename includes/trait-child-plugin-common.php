<?php
/**
 * Child_Plugin_Common trait.
 *
 * @package ainsys-connector-master
 */

namespace Ainsys\Connector\Master;

/**
 * Class must have these fields:
 *      protected static $instance = null; - because Is_Singleton used.
 *      public  $version;
 *      protected $plugin_file_name_path = __FILE__;
 */
trait Child_Plugin_Common {

	use Is_Singleton;

	/**
	 * Reference to master plugin in child plugins.
	 * @var Plugin
	 */
	public $master_plugin;

	/**
	 * Version of plugin from metadata.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Path of Plugin file.
	 *
	 * @var string
	 */
	public $plugin_file_name_path;

	/**
	 * Plugin's directory.
	 *
	 * @var string
	 */
	public $plugin_dir_path;

	/**
	 * Plugin's directory URL.
	 *
	 * @var string
	 */
	public $plugin_dir_url;

	/**
	 * Inits plugin's metadata for class based plugin.
	 *
	 * @param string $plugin_file_path Path of plugin's file.
	 */
	private function init_plugin_metadata( $plugin_file_path ) {

		$this->plugin_file_name_path = $plugin_file_path;
		$this->plugin_dir_path       = plugin_dir_path( $this->plugin_file_name_path );
		$this->plugin_dir_url        = plugin_dir_url( $this->plugin_file_name_path );
		$plugin_data                 = get_file_data( $this->plugin_file_name_path, array( 'Version' => 'Version' ), 'plugin' );
		$this->version               = $plugin_data['Version'] ?? '1.0';

	}


	/**
	 * Returns base plugin name based on it's main Namespace - useful for localize script variable names.
	 *
	 * @return string
	 */
	public static function get_short_name() {

		$namespace_parts = explode( '\\', static::class );

		return $namespace_parts[0] . $namespace_parts[1] ?? '';

	}

	/**
	 * Singleton instance getter.
	 * It requires __FILE__ path of plugin passed in upon initial instantiation.
	 * If you need to get already initialized at plugins loaded stage instance, just ommit this param, it will be ignored anyway.
	 *
	 * @param string $plugin_file_path File path of plugin.
	 * @param Plugin $master_plugin Reference to master plugin to be accessible inside child plugin, for ease of access.
	 *
	 * @return static
	 */
	public static function get_instance( $plugin_file_path = '', $master_plugin = null ) {
		if ( is_null( static::$instance ) ) {
			if ( empty( $plugin_file_path ) ) {
				_doing_it_wrong( esc_attr( static::class ), 'Class ' . esc_attr( static::class ) . ' should be instantiated through get_instance( __FILE__ )', 1 );
			}
			static::$instance = new static( $plugin_file_path, $master_plugin );
		}

		return static::$instance;
	}

}
