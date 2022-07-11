<?php

namespace Ainsys\Connector\Master;

/**
 * AINSYS connector core.
 *
 * @class          AINSYS connector settings
 * @version        1.0.0
 * @author         AINSYS
 */
class Settings implements Hooked {

	use Is_Singleton;


	static $nonce_title = 'ansys_admin_menu_nonce';

	// AINSYS log table name
	static $ainsys_log_table = 'ainsys_log';
	static $ainsys_entities_settings = 'ainsys_entitis_settings';
	static $do_log_transactions;

	/**
	 * Class init
	 *
	 * @return
	 */
	public function init_hooks() {
		// Generate log until time settings
		$currant_date = date( "Y-m-d H:i:s" );
		$limit_date   = (int) Settings::get_option( 'log_until_certain_time' ) ? date( "Y-m-d H:i:s", Settings::get_option( 'log_until_certain_time' ) ) : '';

		if ( ( ! (int) Settings::get_option( 'log_until_certain_time' ) || $currant_date < $limit_date )
		     && (int) self::get_option( 'do_log_transactions' )
		) {
			self::$do_log_transactions = 1;
		} else {
			self::$do_log_transactions = 0;
		}

		register_activation_hook( AINSYS_CONNECTOR_PLUGIN, array( __CLASS__, 'activation' ) );
		register_deactivation_hook( AINSYS_CONNECTOR_PLUGIN, array( __CLASS__, 'deactivation' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', [ __CLASS__, 'add_ansys_admin_menu' ] );
			add_filter( 'plugin_action_links_ainsys_connector/ainsys_connector.php', [
				__CLASS__,
				'generate_lincks_to_plugin_bar'
			] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );
		}
		echo get_option( 'plugin_error' );

		return;
	}

	///////////////////////////////////////

	/**
	 * Get options value by name
	 *
	 * @param $name
	 *
	 * @return mixed|void
	 */
	public static function get_option( $name ) {
		return get_option( self::get_option_name( $name ) );
	}

	/**
	 * Get full options name
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public static function get_option_name( $name ) {
		return self::get_plugin_name() . '_' . $name;
	}

	//////////////////////////////

	/**
	 * Get plugin uniq name to setting
	 *
	 * @return string
	 */
	public static function get_plugin_name() {
		return strtolower( str_replace( '\\', '_', __NAMESPACE__ ) );
	}

	/**
	 * Enqueue admin styles and scripts
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts() {

		wp_enqueue_script( 'ainsys_connector_admin_handle', plugins_url( 'assets/js/ainsys_connector_admin.js', AINSYS_CONNECTOR_PLUGIN ), array( 'jquery' ), '2.0.0', true );

		if ( false !== strpos( $_GET["page"] ?? '', 'ainsys-connector-master' ) ) {
			//wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_style( 'ainsys_connector_style_handle', plugins_url( "assets/css/ainsys_connector_style.css", AINSYS_CONNECTOR_PLUGIN ) );
			wp_enqueue_style( 'font-awesome_style_handle', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" );

			wp_enqueue_script( 'ainsys_connector_admin_handle', plugins_url( 'assets/js/ainsys_connector_admin.js', AINSYS_CONNECTOR_PLUGIN ), array( 'jquery' ), '2.0.0', true );
			wp_localize_script( 'ainsys_connector_admin_handle', 'ainsys_connector_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::$nonce_title ),
			) );
		}

		return;
	}

	/**
	 * Install tables
	 *
	 * @return
	 */
	public static function activation() {
		global $wpdb;

		update_option( self::get_plugin_name(), AINSYS_CONNECTOR_VERSION );

		flush_rewrite_rules();

		$wpdb->hide_errors();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( self::get_schema() );

		update_option( self::get_plugin_name() . '_db_version', AINSYS_CONNECTOR_VERSION );

		return;
	}

	/**
	 * Get Table schema.
	 *
	 * @return string
	 */
	private static function get_schema() {
		global $wpdb;
		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		/*
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
		 * As of WordPress 4.2, however, we moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 *
		 * This may cause duplicate index notices in logs due to https://core.trac.wordpress.org/ticket/34870 but dropping
		 * indexes first causes too much load on some servers/larger DB.
		 */

		$table_log              = $wpdb->prefix . self::$ainsys_log_table;
		$table_entitis_settings = $wpdb->prefix . self::$ainsys_entities_settings;

		$tables = "
            CREATE TABLE {$table_log} (
                `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `object_id` bigint NOT NULL,
                `request_action` varchar(100) NOT NULL,
                `request_data` text DEFAULT NULL,
                `serrver_responce` text DEFAULT NULL,
                `incoming_call` smallint NOT NULL,
                `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (log_id),
                KEY object_id (object_id)
            ) $collate;

            CREATE TABLE {$table_entitis_settings} (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `entiti` text DEFAULT NULL,
                `setting_name` text DEFAULT NULL,
                `setting_key` text DEFAULT NULL,
                `value` text DEFAULT NULL,
                `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (`id`)
            ) $collate;
		";

		return $tables;
	}

	/**
	 * Remove logs, settings etc.
	 *
	 * @return
	 */
	public static function deactivation() {
		if ( (int) self::get_option( 'full_uninstall' ) ) {
			global $wpdb;
			$wpdb->query( sprintf( "DROP TABLE IF EXISTS %s",
				$wpdb->prefix . self::$ainsys_log_table ) );

			delete_option( self::get_setting_name( 'ansys_api_key' ) );
			delete_option( self::get_setting_name( 'handshake_url' ) );
			delete_option( self::get_setting_name( 'webhook_url' ) );
			delete_option( self::get_setting_name( 'connectors' ) );
			delete_option( self::get_setting_name( 'server' ) );
			delete_option( self::get_setting_name( 'workspace' ) );
			delete_option( self::get_setting_name( 'hook_url' ) );
			delete_option( self::get_setting_name( 'backup_email' ) );
			delete_option( self::get_setting_name( 'do_log_transactions' ) );
			delete_option( self::get_setting_name( 'log_until_certain_time' ) );
			delete_option( self::get_setting_name( 'display_debug' ) );
			delete_option( self::get_setting_name( 'full_uninstall' ) );
			delete_option( self::get_plugin_name() );
			delete_option( self::get_plugin_name() . '_db_version' );

			delete_option( self::get_setting_name( 'debug_log' ) );

			delete_option( 'ainsys-webhook_url' );
		}

		return;
	}

	/**
	 * Get full options name
	 *
	 * @param $name
	 *
	 * @return string
	 */
	public static function get_setting_name( $name ) {
		return self::get_plugin_name() . '_' . $name;
	}

	/**
	 * Truncate log table.
	 *
	 * @return
	 */
	static function truncate_log_table() {
		global $wpdb;
		$sql = "TRUNCATE TABLE " . $wpdb->prefix . self::$ainsys_log_table;
		$wpdb->query( $sql );

		return;
	}

	/**
	 * Get options value by name
	 *
	 * @param $name
	 *
	 * @return mixed|void
	 */
	public static function set_option( $name, $value ) {
		return update_option( self::get_option_name( $name ), $value );
	}

	/**
	 * Register setting page in menu
	 *
	 */
	public static function add_ansys_admin_menu() {
		add_options_page(
			__( 'AINSYS connector integration', AINSYS_CONNECTOR_TEXTDOMAIN ),
			__( 'AINSYS connector', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'administrator',
			__FILE__,
			[ __CLASS__, 'include_setting_page' ]
		);

		add_action( 'admin_init', [ __CLASS__, 'register_options' ] );
	}

	/**
	 * Get saved email or admin email
	 *
	 * @return bool|mixed|void
	 */
	public static function get_backup_email() {
		if ( ! empty( self::get_option( 'backup_email' ) ) ) {
			return self::get_option( 'backup_email' );
		}

		if ( ! empty( get_option( 'admin_email' ) ) ) {
			return get_option( 'admin_email' );
		}

		return false;
	}

	/**
	 * Include settings page
	 *
	 */
	public static function include_setting_page() {
		include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/View.php';
	}

	/**
	 * Register options
	 *
	 */
	public static function register_options() {
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'ansys_api_key' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'handshake_url' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'webhook_url' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'server' ), [ 'default' => 'https://user-api.ainsys.com/' ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'sys_id' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'connectors' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'workspace' ), [ 'default' => 14 ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'hook_url' ), [
			__CLASS__,
			'generate_hook_url'
		] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'backup_email' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'do_log_transactions' ), [ 'default' => 1 ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'log_until_certain_time' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'display_debug' ), [ 'default' => 0 ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'full_uninstall' ), [ 'default' => 0 ] );

		/*  DEBUG   */
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'debug_log' ) );
	}

	/**
	 * Generate hook
	 *
	 * @return string
	 */
	public static function generate_hook_url() {
		return site_url( '/?ainsys_webhook=' . Webhook_Listener::$request_token, 'https' );
	}

	/**
	 * Add links to settings and ainsys portal
	 *
	 * @param $links
	 *
	 * @return mixed
	 */
	public static function generate_lincks_to_plugin_bar( $links ) {
		$settings_url = esc_url( add_query_arg(
			'page',
			'ainsys_connector%2Fincludes%2Fainsys_settings.php',
			get_admin_url() . 'options-general.php'
		) );

		$settings_link = '<a href="' . $settings_url . '">' . __( 'Settings' ) . '</a>';
		$plugin_link   = '<a target="_blank" href="https://app.ainsys.com/en/settings/workspaces">AINSYS dashboard</a>';

		array_push( $links, $settings_link, $plugin_link );

		return $links;
	}

	/**
	 * Generate list of entitis
	 *
	 * @return array
	 */
	static function get_entitis() {
		/// Get Wordpress pre installed enteties
		$entities = array(
			'user'     => __( 'User / fields', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'comments' => __( 'Comments / fields', AINSYS_CONNECTOR_TEXTDOMAIN )
		);

		/// Get Woocommerce enteties
		if ( Settings::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$entities['order']   = __( 'Order / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
			$entities['product'] = __( 'Product / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
			if ( wc_coupons_enabled() ) {
				$entities['coupons'] = __( 'Coupons / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
			}
		}

		return apply_filters( 'ainsys_get_entities_list', $entities );
	}

	/**
	 * Is plugin active
	 *
	 * @param string $plugin
	 *
	 * @return bool
	 */
	public static function is_plugin_active( $plugin ) {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );
	}

	/**
	 * Generate fields for USER entity
	 *
	 * @return array
	 */
	static function get_user_fields() {
		$prepered_fields = array(
			"ID"                   => [
				"nice_name" => __( "{ID}", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_login"           => [
				"nice_name" => __( "User login", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_nicename"        => [
				"nice_name" => __( "Readable name", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_email"           => [
				"nice_name" => __( "User mail", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress",
				"children"  => [
					"primary"   => [
						"nice_name" => __( "Main email", AINSYS_CONNECTOR_TEXTDOMAIN ),
						"api"       => "wordpress"
					],
					"secondary" => [
						"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
						"api"       => "wordpress"
					]
				]
			],
			"user_url"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_registered"      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_activation_key"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_status"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"display_name"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"first_name"           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"last_name"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"nickname"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"nice_name"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"rich_editing"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"syntax_highlighting"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"comment_shortcuts"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"admin_color"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"use_ssl"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"show_admin_bar_front" => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"locale"               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			]
		);

		$extra_fields = apply_filters( 'ainsys_prepare_extra_user_fields', array() );

		return array_merge( $prepered_fields, $extra_fields );
	}

	/**
	 * Generate costom ACF fields for entity
	 *
	 * @param array $acf_fields
	 *
	 * @return array
	 */
	static function generate_extra_fields_for_entity( $acf_fields ) {
		if ( empty( $acf_fields ) ) {
			return array();
		}

		$prepered_fields = [];
		if ( ! empty( $acf_fields ) ) {
			foreach ( $acf_fields as $selector => $settings ) {
				$prepered_fields[ $settings["key"] ] = [
					"nice_name"   => $settings["label"] ?? '',
					"description" => $settings["instructions"] ?? '',
					"api"         => $settings["api"] ?? "ACF",
					"read"        => 0,
					"write"       => 0,
					"required"    => $settings["required"] ?? '',
					"sample"      => $settings["placeholder"] ?? '',
					"data_type"   => $settings["type"] ?? ''
				];
			}
		}

		return $prepered_fields;
	}

	/**
	 * Generate fields for COMMENTS entity
	 *
	 * @return array
	 */
	static function get_comments_fields() {
		$prepered_fields = array(
			'comment_ID'           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_post_ID'      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author'       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author_email' => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author_url'   => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author_IP'    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_date'         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_date_gmt'     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_content'      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_karma'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_approved'     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_agent'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_type'         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_parent'       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'user_id'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'children'             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'populated_children'   => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'post_fields'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
		);

		$extra_fields = apply_filters( 'ainsys_prepare_extra_comment_fields', array() );

		return array_merge( $prepered_fields, $extra_fields );
	}

	/**
	 * Generate fields for USER entity
	 *
	 * @return array
	 */
	static function get_product_fields() {
		return array(
			"title"              => [
				"nice_name"   => __( 'Title', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"         => "woocommerce",
				"description" => "Product title"
			],
			"id"                 => [
				"nice_name" => __( '{ID}', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"created_at"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"updated_at"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"type"               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"status"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"downloadable"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"virtual"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"permalink"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"sku"                => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"price"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"regular_price"      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"sale_price"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"price_html"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"taxable"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"tax_status"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"tax_class"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"managing_stock"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"stock_quantity"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"in_stock"           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"backorders_allowed" => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"backordered"        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"sold_individually"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"purchaseable"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"featured"           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"visible"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"catalog_visibility" => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"on_sale"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"weight"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"dimensions"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_required"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_taxable"   => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_class"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_class_id"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"nice_name"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"short_nice_name"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"reviews_allowed"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"average_rating"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"rating_count"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"related_ids"        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"upsell_ids"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"cross_sell_ids"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"categories"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"tags"               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			//"images"
			"featured_src"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			//"attributes"
			//"downloads"
			"download_limit"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"download_expiry"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			//"download_type"
			//"purchase_note"
			//"total_sales"
		);
	}

	/**
	 * Generate fields for ORDER entity
	 *
	 * @return array
	 */
	static function get_order_fields() {
		$prepered_fields = [
			"id"                   => [
				"nice_name" => __( '{ID}', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"currency"             => [
				"nice_name" => __( 'Currency', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"customer_id"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"payment_method_title" => [
				"nice_name" => __( "Payment", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"date"                 => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"referer"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"hostname"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"user_ip"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"products"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			]
		];

		$order_fields = WC()->checkout->get_checkout_fields();

		foreach ( $order_fields as $category => $fields ) {
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field_slug => $settings ) {
					$prepered_fields[ $field_slug ] = [
						"nice_name"   => $settings["label"] ?? '',
						"description" => $settings["label"] ?? '',
						"api"         => "woocommerce",
						"required"    => isset( $settings["required"] ) && $settings["required"] ? 1 : 0,
						"sample"      => isset( $settings["placeholder"] ) ? $settings["placeholder"] : ''
					];
				}
			} else {
				$prepered_fields[ $category ] = [
					"api" => "woocommerce",
				];
			}
		}

		$order_saved_settings = HTML::get_saved_entity_settings_from_db( ' WHERE entiti="order" AND setting_key="extra_field"', false );
		$order_extra_fields   = [];
		if ( ! empty( $order_saved_settings ) ) {
			foreach ( $order_saved_settings as $saved_setting ) {
				//preg_match('/(?<cat>\S+)_/', $saved_setting["setting_name"], $matches);
				$order_extra_fields[ $saved_setting["setting_name"] ]        = maybe_unserialize( $saved_setting["value"] );
				$order_extra_fields[ $saved_setting["setting_name"] ]['api'] = 'mixed';
			}
		}
		$prepered_fields = array_merge( $prepered_fields, self::generate_extra_fields_for_entity( $order_extra_fields ) );

		return $prepered_fields;
	}

	/**
	 * Generate fields for ACF entity
	 *
	 * @return array
	 */
	static function get_acf_fields() {
		$get_one_post = get_posts( array(
			'numberposts' => 1,
			'post_type'   => 'post'
		) );
		$post_id      = isset( $get_one_post[0]->ID ) ? $get_one_post[0]->ID : 0;
		$acf_group    = $acf_group = get_field_objects( $post_id );

		return self::generate_extra_fields_for_entity( $acf_group );
	}

	static function get_coupons_fields() {
		return array(
			'code'                        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'discount_type'               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'amount'                      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'date_expires'                => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'individual_use'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'product_ids'                 => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'excluded_product_ids'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'usage_limit'                 => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'usage_limit_per_user'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'limit_usage_to_x_items'      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'free_shipping'               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'product_categories'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'excluded_product_categories' => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'exclude_sale_items'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'minimum_amount'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'maximum_amount'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'email_restrictions'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
		);
	}

	/**
	 * Generate list of settings for entity field with default values
	 * $entiti param used for altering settins depending on entity
	 *
	 * @param string $entiti
	 *
	 * @return array
	 */
	static function get_entities_settings( $entiti = '' ) {
		return array(
			'id'          => array(
				'nice_name' => __( 'Id', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '',
				'type'      => 'constant',
			),
			'api'         => array(
				'nice_name' => __( 'API', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => array(
					'woocommerce' => '',
					'wordpress'   => '',
					'ACF'         => '',
				),
				'type'      => 'constant',
			),
			'read'        => array(
				'nice_name' => __( 'Read', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '1',
				'type'      => 'bool'
			),
			'write'       => array(
				'nice_name' => __( 'Write', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '0',
				'type'      => 'bool'
			),
			'required'    => array(
				'nice_name' => __( 'Required', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '0',
				'type'      => 'bool'
			),
			'unique'      => array(
				'nice_name' => __( 'Unique', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '0',
				'type'      => 'bool'
			),
			'data_type'   => array(
				'nice_name' => __( 'Data type', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => array(
					'string' => '1',
					'int'    => '',
					'bool'   => '',
					'mixed'  => ''
				),
				'type'      => $entiti === 'acf' ? 'constant' : 'select'
			),
			'description' => array(
				'nice_name' => __( 'Description', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '',
				'type'      => 'string'
			),
			'sample'      => array(
				'nice_name' => __( 'Sample', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '',
				'type'      => 'string'
			)
		);
	}
}
