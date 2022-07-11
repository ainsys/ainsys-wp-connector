<?php

namespace Ainsys\Connector\Master;

defined( 'ABSPATH' ) || die();

class Plugin implements Hooked {
	use Plugin_Common;


	/**
	 * Key is __FILE__ of respective plugin and value is fully qualified
	 * with namespace Class name of plugin to be instantiated.
	 * @var array
	 */
	public $child_plugin_classes = array();

	public $child_plugins = array();

	/**
	 * Plugin constructor.
	 *
	 * @param string $plugin_file_path
	 */
	private function __construct( $plugin_file_path ) {

		$this->init_plugin_metadata( $plugin_file_path );

		/**
		 * Inject here all components needed for plugin.
		 * It's good to follow same logic in child plugins if it has multiple classes which share functionality
		 * among the plugin.
		 */
		$this->components['settings']    = Settings::get_instance();
		$this->components['html']        = HTML::get_instance();
		$this->components['core']        = Core::get_instance();
		$this->components['utm_handler'] = UTM_Handler::get_instance();
		$this->components['webhooks']    = Webhook_Listener::get_instance();


	}

	/**
	 * @return Plugin
	 */
	public function init_hooks() {
		register_activation_hook( $this->plugin_file_name_path, array( $this, 'activate' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_action( 'plugins_loaded', array( $this, 'load_child_plugins' ) );
		/*
		 * Initialize hooks for all inner plugin's components.
		 */
		foreach ( $this->components as $component ) {
			if ( $component instanceof Hooked ) {
				$component->init_hooks();
			}
		}

		return $this;

	}


	public function load_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), AINSYS_CONNECTOR_TEXTDOMAIN );
		unload_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN );
		load_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN, WP_LANG_DIR . '/plugins/ainsys_connector-' . $locale . '.mo' );
		load_plugin_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN, false, dirname( $this->plugin_file_name_path ) . '/languages/' );
	}


	public function load_child_plugins() {
		/**
		 * After all core components are loaded, we can load child plugins.
		 * To use this properly in child plugin add key as __FILE__ of plugin for proper initialization,
		 * and fully qualified class name with namespace to instantiate a plugin.
		 * Our plugins are all singletons with get_instance(__FILE__) method used for instantiation.
		 */
		$this->child_plugin_classes = apply_filters( 'ainsys_child_plugins_to_be_loaded', array() );

		foreach ( $this->child_plugin_classes as $child_plugin_file => $child_plugin_class_name ) {
			if ( class_exists( $child_plugin_class_name ) && method_exists( $child_plugin_class_name, 'get_instance' ) ) {
				/**
				 *  We pass into creation of child plugin instances the __FILE__ of such plugin and $this as it's reference to master plugin.
				 */
				$this->child_plugins[ $child_plugin_class_name ] = $child_plugin_class_name::get_instance( $child_plugin_file, $this );
			}
		}

		// now lets init their hooks as well.

		foreach ( $this->child_plugins as $child_plugin ) {
			if ( $child_plugin instanceof Hooked ) {
				$child_plugin->init_hooks();
			}
		}

		// now our child plugins got linked to WP.
	}

	/**
	 * Action for plugin activation.
	 */
	public function activate() {
		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'activate' ) ) {
				$component->activate();
			}
		}
	}


	/**
	 * Action on plugin deactivation.
	 * Cleans up everything.
	 */
	public function deactivate() {

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'deactivate' ) ) {
				$component->deactivate();
			}
		}
	}

	/**
	 * Is plugin active
	 *
	 * @param string $plugin
	 *
	 * @return bool
	 */
	public function is_plugin_active( $plugin ) {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );
	}


}