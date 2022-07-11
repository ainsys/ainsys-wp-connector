<?php
/**
 * Autoloader for Plugin's classes.
 *
 * @package ainsys-connector;
 */

namespace Ainsys\Connector;

// Important - this is autoloader for the whole family of plugins, so it has root namespace set above.

if ( ! function_exists( __NAMESPACE__ . '\autoloader' ) ) {

	/**
	 * Autoloader function.
	 *
	 * @param string $class_name Fully qualified class string to load.
	 *
	 * @return void
	 */
	function autoloader( $class_name ) {

		if ( false !== strpos( $class_name, __NAMESPACE__ ) ) {

			$no_namespace_class_name = str_replace( __NAMESPACE__.'\\', '', $class_name );
			// new WPCS compliant approach.
			$parts = explode( '\\', strtolower( str_replace( '_', '-', $no_namespace_class_name ) ) );

			if ( count( $parts ) < 1 ) {
				return;
			}

			$family_slug = 'ainsys-connector';
			$plugin_slug = array_shift( $parts );

			$plugin_dir = $family_slug . '-' . $plugin_slug;
			$plugin_dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_dir;

			$class_file          = array_pop( $parts );
			$class_file_name     = 'class-' . $class_file . '.php';
			$trait_file_name     = 'trait-' . $class_file . '.php';
			$interface_file_name = 'interface-' . $class_file . '.php';



			$relative_path_part = implode( DIRECTORY_SEPARATOR, $parts );
			if ( count( $parts ) ) {
				$relative_path_part .= DIRECTORY_SEPARATOR;
			}

			$files_paths = array(
				/* first try to load class as class in includes subdir */
				$plugin_dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $relative_path_part . $class_file_name,
				/* try to load class as trait in `includes` subdir of plugin and its nested dirs */
				$plugin_dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $relative_path_part . $trait_file_name,
				$plugin_dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $relative_path_part . $interface_file_name,

//				/* then try to load class as class in direct subdir of plugin */
//				$plugin_dir . DIRECTORY_SEPARATOR . $relative_path_part . $class_file_name,
//				/* same for traits */
//				$plugin_dir . DIRECTORY_SEPARATOR . $relative_path_part . $trait_file_name,
//				$plugin_dir . DIRECTORY_SEPARATOR . $relative_path_part . $interface_file_name,
			);

			foreach ( $files_paths as &$file_path ) {
				if ( is_readable( $file_path ) ) {

					require_once $file_path;

					return;
				}
			}

		}

	}


	spl_autoload_register( __NAMESPACE__ . '\autoloader' );
}
