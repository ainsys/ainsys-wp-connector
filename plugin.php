<?php

namespace Ainsys\Connector\Master;


/**
 * @link              https://github.com/ainsys/ainsys-wp-connector-plugin
 * @since             1.0.0
 * @package           ainsys-connector
 *
 * @wordpress-plugin
 * Plugin Name:       AINSYS connector
 * Plugin URI: https://app.ainsys.com/
 * Description: AINSYS connector master.
 * Version:           3.0.0-MD
 * Author:            AINSYS
 * Author URI:        https://app.ainsys.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     Ainsysconnector
 * Domain Path:     /languages
 */

defined( 'ABSPATH' ) || die();

define( 'AINSYS_CONNECTOR_BASENAME', plugin_basename( __FILE__ ) );
define( 'AINSYS_CONNECTOR_VERSION', '3.0' );
define( 'AINSYS_CONNECTOR_PLUGIN', __FILE__ );
define( 'AINSYS_CONNECTOR_PLUGIN_DIR', untrailingslashit( dirname( AINSYS_CONNECTOR_PLUGIN ) ) );
define( 'AINSYS_CONNECTOR_TEXTDOMAIN', 'ainsys_connector' );
define( 'AINSYS_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );


if ( version_compare( PHP_VERSION, '7.2.0' ) < 0 ) {

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	deactivate_plugins( plugin_basename( __FILE__ ) );

	add_action( 'admin_notices', function () {
		$class    = 'notice notice-error is-dismissible';
		$message1 = __( 'Upgrade your PHP version. Minimum version - 7.2+. Your PHP version ', AINSYS_CONNECTOR_TEXTDOMAIN );
		$message2 = __( '! If you don\'t know how to upgrade PHP version, just ask in your hosting provider! If you can\'t upgrade - delete this plugin!', AINSYS_CONNECTOR_TEXTDOMAIN );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message1 . PHP_VERSION . $message2 ) );
	} );

}


require_once __DIR__ . '/autoloader.php';

( Plugin::get_instance( __FILE__ ) )->init_hooks();