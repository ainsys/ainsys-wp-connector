<?php

namespace Ainsys\Connector\Master;

use \Exception;
use \WC_Order;
use \WP_Error;

/**
 * AINSYS connector core.
 *
 * @class          AINSYS connector core
 * @version        1.0.0
 * @author         AINSYS
 */
class Core implements Hooked {

	use Is_Singleton;

	static $notices = [];

	/**
	 * Hooks init to WP.
	 *
	 */
	public function init_hooks() {
		add_action( 'wp_ajax_remove_ainsys_integration', array( __CLASS__, 'remove_ainsys_integration' ) );

		add_action( 'wp_ajax_save_entiti_settings', array( __CLASS__, 'save_entiti_settings' ) );

		add_action( 'wp_ajax_reload_log_html', array( __CLASS__, 'reload_log_html' ) );
		add_action( 'wp_ajax_toggle_logging', array( __CLASS__, 'toggle_logging' ) );
		add_action( 'wp_ajax_clear_log', array( __CLASS__, 'clear_log' ) );

		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'ainsys_new_order_processed' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'send_order_status_update_to_ainsys' ) );

		add_action( 'user_register', array( __CLASS__, 'ainsys_new_user_processed' ), 10, 2 );
		add_action( 'profile_update', array( __CLASS__, 'send_user_details_update_to_ainsys' ), 10, 3 );

		add_action( 'woocommerce_update_product', array( __CLASS__, 'send_update_product_to_ainsys' ), 10, 2 );

		add_action( 'comment_post', array( __CLASS__, 'send_new_comment_to_ainsys' ), 10, 3 );
		add_action( 'edit_comment', array( __CLASS__, 'send_update_comment_to_ainsys' ), 10, 2 );

		add_action( 'plugins_loaded', array( __CLASS__, 'register_events_and_settings' ) );

		add_action( 'admin_notices', [ __CLASS__, 'admin_notices' ] );
	}

	static function admin_notices( $message, $status = 'success' ) {
		if ( self::$notices ) {
			foreach ( self::$notices as $notice ) {
				?>
				<div class="notice notice-<?php echo esc_attr( $notice['status'] ); ?>" is-dismissible>
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Remove ainsys integration information
	 *
	 * @return
	 */
	static function remove_ainsys_integration() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], Settings::$nonce_title ) ) {
			Settings::set_option( 'connectors', '' );
			Settings::set_option( 'ansys_api_key', '' );
			Settings::set_option( 'handshake_url', '' );
			Settings::set_option( 'webhook_url', '' );
			Settings::set_option( 'debug_log', '' );

			delete_option( 'ainsys-webhook_url' );
		}

		return;
	}

	/**
	 * Regenerate log HTML
	 *
	 */
	static function save_entiti_settings() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], Settings::$nonce_title ) ) {
			$fields      = $_POST;
			$entiti      = isset( $_POST["entiti"] ) ? $_POST["entiti"] : '';
			$seting_name = $_POST["seting_name"] ? $_POST["seting_name"] : '';
			if ( ! $entiti && ! $seting_name ) {
				echo false;
				die();
			}

			$fields = self::sanitise_fields_to_save( $fields );

			global $wpdb;
			$entiti_saved_settings = HTML::get_saved_entity_settings_from_db( ' WHERE entiti="' . $entiti . '" setting_key="saved_field" AND setting_name="' . $seting_name . '"' );
			$response              = '';
			if ( empty( $entiti_saved_settings ) ) {
				$response      = $wpdb->insert( $wpdb->prefix . Settings::$ainsys_entities_settings,
					array(
						'entiti'       => $entiti,
						'setting_name' => $seting_name,
						'setting_key'  => 'saved_field',
						'value'        => serialize( $fields )
					)
				);
				$field_data_id = $wpdb->insert_id;
			} else {
				$response      = $wpdb->update( $wpdb->prefix . Settings::$ainsys_entities_settings,
					array( 'value' => serialize( $fields ) ),
					array( 'id' => $entiti_saved_settings["id"] )
				);
				$field_data_id = $entiti_saved_settings["id"];
			}

			$request_action = 'field/' . $entiti . '/' . $seting_name;

			$fields = apply_filters( 'ainsys_update_entiti_fields', $fields );

			$request_data = array(
				'entity'  => [
					'id' => $field_data_id,
				],
				'action'  => $request_action,
				'payload' => $fields
			);

			try {
				$server_responce = self::curl_exec_func( $request_data );
			} catch ( Exception $e ) {
				$server_responce = 'Error: ' . $e->getMessage();
			}

			self::save_log_information( (int) $field_data_id, $request_action, serialize( $request_data ), serialize( $server_responce ), 0 );

			echo $field_data_id ?? 0;
			die();
		}
		echo false;
		die();
	}

	static function sanitise_fields_to_save( $fields ) {
		// clear empty fields
//        foreach ($fields as $field => $val){
//            if (empty($val))
//                unset($fields[$field]);
//        }
		unset( $fields["action"], $fields["entiti"], $fields["nonce"], $fields["seting_name"], $fields["id"] );

		/// exclude 'constant' variables
		foreach ( Settings::get_entities_settings() as $item => $setting ) {
			if ( isset( $fields[ $item ] ) && $setting["type"] === 'constant' ) {
				unset( $fields[ $item ] );
			}
		}

		return $fields;
	}

	/**
	 * Curl connect and get data.
	 *
	 * @param array $post_fields
	 * @param string $url
	 *
	 * @return string
	 */
	public static function curl_exec_func( $post_fields = '', $url = '' ) {
		$url = $url ?: (string) get_option( 'ansys_connector_woocommerce_ansys_api_key' );

		if ( ! $url ) {
			/// Save curl requests for debug
			Settings::set_option( 'debug_log', Settings::get_option( 'debug_log' ) . 'cURL Error: No url provided<br>' );

			return new WP_Error( 'Отсутствует url подключения' );
		}

		//$key = ainsys_settings::get_option('ansys_api_key');

//		$curl = curl_init();
//
//		curl_setopt_array( $curl, [
//			CURLOPT_URL            => $url,
//			CURLOPT_RETURNTRANSFER => true,
//			CURLOPT_ENCODING       => "",
//			CURLOPT_MAXREDIRS      => 10,
//			CURLOPT_TIMEOUT        => 30,
//			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
//			CURLOPT_HTTPHEADER     => [
//				//"Authorization: Bearer " .$key,
//				"Content-Type: application/json"
//			],
//		] );
//
//
//		/// Switch to POST if post fields specified
//		if ( $post_fields ) {
//			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, "POST" );
//			curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $post_fields ) );
//		}
//
//		$response = curl_exec( $curl );
//		$err      = curl_error( $curl );
//
//
//		curl_close( $curl );

		//$response = $err ? "cURL Error #:" . $err : $response;

		$response = wp_remote_post( $url, array(
			'timeout'     => 30,
			'redirection' => 10,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array( 'content-type' => 'application/json' ),
			'body'        => wp_json_encode( $post_fields, 256 ),
			'cookies'     => array(),
			'sslverify'   => false
		) );

		/// Save curl requests for debug
		self::log( $response );


		return $response;
	}

	/**
	 * Log any errors.
	 *
	 * @param string $log The log message.
	 */
	static public function log( $log ) {
		Settings::set_option( 'debug_log', Settings::get_option( 'debug_log' ) . $log . '<br>' );
	}

	/**
	 * Save each uptate transactions to log
	 *
	 * @param int $object_id
	 * @param string $request_action
	 * @param string $request_data
	 * @param string $serrver_responce
	 * @param int $incoming_call
	 *
	 * @return string
	 */
	public static function save_log_information( $object_id, $request_action, $request_data, $serrver_responce = '', $incoming_call = 0 ) {
		global $wpdb;

		if ( ! Settings::$do_log_transactions ) {
			return false;
		}

		$responce = $wpdb->insert( $wpdb->prefix . Settings::$ainsys_log_table,
			array(
				'object_id'        => $object_id,
				'request_action'   => $request_action,
				'request_data'     => $request_data,
				'serrver_responce' => $serrver_responce,
				'incoming_call'    => $incoming_call
			)
		);

		return $responce;
	}

	/**
	 * Regenerate log HTML
	 *
	 */
	static function reload_log_html() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], Settings::$nonce_title ) ) {
			echo HTML::generate_log_html();
		}
		die();
	}

	/**
	 * Toggle logging on/of. Set up time till log will be saved if $_POST["time"] specified
	 *
	 */
	static function toggle_logging() {
		if ( isset( $_POST["command"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], Settings::$nonce_title ) ) {
			/// Set time till log will be saved, 0 if infinity
			if ( isset( $_POST["time"] ) ) {
				if ( (int) $_POST["time"] > 0 ) {
					$current_date_time = date( "Y-m-d H:i:s" );
					Settings::set_option( 'log_until_certain_time', strtotime( $current_date_time . '+' . $_POST["time"] . ' hours' ) );
				} else {
					Settings::set_option( 'log_until_certain_time', 0 );
				}
			}
			if ( $_POST['command'] === 'start_loging' ) {
				Settings::set_option( 'do_log_transactions', 1 );
			} else {
				Settings::set_option( 'do_log_transactions', 0 );
			}
			echo $_POST['command'] === 'start_loging' ? '#stop_loging' : '#start_loging';
		}
		die();
	}

	/**
	 * Clear log DB
	 *
	 */
	static function clear_log() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], Settings::$nonce_title ) ) {
			Settings::truncate_log_table();
			echo HTML::generate_log_html();
		}
		die();
	}

	/**
	 * We send an updated WP comment details to AINSYS
	 *
	 * @param int $comment_id
	 * @param object $data
	 *
	 * @return
	 */
	static function send_new_comment_to_ainsys( $comment_id, $comment_approved, $data ) {
		$request_action = 'CREATE';

		$fields = apply_filters( 'ainsys_new_comment_fields', self::prepare_comment_data( $comment_id, $data ), $data );

		$request_data = array(
			'object_id'      => $comment_id,
			'request_action' => $request_action,
			'request_data'   => $fields
		);

		try {
			$server_responce = self::curl_exec_func( $request_data );
		} catch ( Exception $e ) {
			$server_responce = 'Error: ' . $e->getMessage();
		}

		self::save_log_information( $comment_id, $request_action, serialize( $request_data ), serialize( $server_responce ), 0 );

		return;
	}

	/**
	 * Prepare WP comment data. Add ACF fields if we have
	 *
	 * @param int $comment_id
	 * @param array $data
	 *
	 * @return array
	 */
	static function prepare_comment_data( $comment_id, $data ) {
		$data['id'] = $comment_id;
		/// Get ACF fields
		$acf_fields = apply_filters( 'ainsys_prepare_extra_comment_data', array(), $comment_id );

		return array_merge( $data, $acf_fields );
	}

	/**
	 * We send an updated WP comment details to AINSYS
	 *
	 * @param int $comment_id
	 * @param array $data
	 *
	 * @return
	 */
	static function send_update_comment_to_ainsys( $comment_id, $data ) {
		$request_action = 'UPDATE';

		$fields = apply_filters( 'ainsys_update_comment_fields', self::prepare_comment_data( $comment_id, $data ), $data );

		$request_data = array(
			'object_id'      => $comment_id,
			'request_action' => $request_action,
			'request_data'   => $fields
		);

		try {
			$server_responce = self::curl_exec_func( $request_data );
		} catch ( Exception $e ) {
			$server_responce = 'Error: ' . $e->getMessage();
		}
		self::save_log_information( $comment_id, $request_action, serialize( $request_data ), serialize( $server_responce ), 0 );

		return;
	}

	/**
	 * We send an updated WC product details to AINSYS
	 *
	 * @param int $product_id
	 * @param object $product
	 *
	 * @return
	 */
	static function send_update_product_to_ainsys( $product_id, $product ) {
		$request_action = 'UPDATE';

		$fields = apply_filters( 'ainsys_update_product_fields', self::prepare_single_product( $product ), $product );

		$request_data = array(
			'entity'  => [
				'id'   => $product_id,
				'name' => 'product'
			],
			'action'  => $request_action,
			'payload' => $fields
		);


		try {
			$server_responce = self::curl_exec_func( $request_data );
		} catch ( Exception $e ) {
			$server_responce = 'Error: ' . $e->getMessage();
		}

		self::save_log_information( $product_id, $request_action, serialize( $request_data ), serialize( $server_responce ), 0 );

		return;
	}

	/**
	 * Preparing WC product fields
	 *
	 * @param object $product
	 *
	 * @return array
	 */
	private static function prepare_single_product( $product ) {
		if ( empty( $product ) ) {
			return array();
		}

		return array(
			'title'              => $product->get_name(),
			'id'                 => $product->get_id(),
			'created_at'         => (array) $product->get_date_created(),
			'updated_at'         => (array) $product->get_date_modified(),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'downloadable'       => $product->is_downloadable(),
			'virtual'            => $product->is_virtual(),
			'permalink'          => $product->get_permalink(),
			'sku'                => $product->get_sku(),
			'price'              => wc_format_decimal( $product->get_price(), 2 ),
			'regular_price'      => wc_format_decimal( $product->get_regular_price(), 2 ),
			'sale_price'         => $product->get_sale_price() ? wc_format_decimal( $product->get_sale_price(), 2 ) : null,
			'price_html'         => $product->get_price_html(),
			'taxable'            => $product->is_taxable(),
			'tax_status'         => $product->get_tax_status(),
			'tax_class'          => $product->get_tax_class(),
			'managing_stock'     => $product->managing_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'in_stock'           => $product->is_in_stock(),
			'backorders_allowed' => $product->backorders_allowed(),
			'backordered'        => $product->is_on_backorder(),
			'sold_individually'  => $product->is_sold_individually(),
			'purchaseable'       => $product->is_purchasable(),
			'featured'           => $product->is_featured(),
			'visible'            => $product->is_visible(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'on_sale'            => $product->is_on_sale(),
			'weight'             => $product->get_weight() ? wc_format_decimal( $product->get_weight(), 2 ) : null,
			'dimensions'         => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
				'unit'   => get_option( 'woocommerce_dimension_unit' ),
			),
			'shipping_required'  => $product->needs_shipping(),
			'shipping_taxable'   => $product->is_shipping_taxable(),
			'shipping_class'     => $product->get_shipping_class(),
			'shipping_class_id'  => ( 0 !== $product->get_shipping_class_id() ) ? $product->get_shipping_class_id() : null,
			'description'        => apply_filters( 'the_content', $product->get_description() ),
			'short_description'  => apply_filters( 'woocommerce_short_description', $product->get_short_description() ),
			'reviews_allowed'    => $product->get_reviews_allowed(),
			'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
			'rating_count'       => $product->get_rating_count(),
			'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
			'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
			'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
			'categories'         => wc_get_object_terms( $product->get_id(), 'product_cat', 'name' ),
			'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ),
			//'images'             => $this->get_images( $product ),
			'featured_src'       => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) ),
			//'attributes'         => $this->get_attributes( $product ),
			//'downloads'          => $this->get_downloads( $product ),
			'download_limit'     => $product->get_download_limit(),
			'download_expiry'    => $product->get_download_expiry()
			//'download_type'      => 'standard',
			//'purchase_note'      => apply_filters( 'the_content', $product->get_purchase_note() )
			//'total_sales'        => $product->get_total_sales(),
		);
	}

	/**
	 * We send an updated user details to AINSYS
	 *
	 * @param int $user_id
	 * @param array $old_user_data
	 * @param array $userdata
	 *
	 * @return
	 */
	static function send_user_details_update_to_ainsys( $user_id, $old_user_data, $userdata ) {
		$request_action = 'UPDATE';

		$fields = apply_filters( 'ainsys_user_details_update_fields', self::prepare_user_data( $user_id, $userdata ), $userdata );

		$request_data = array(
			'entity'  => [
				'id'   => $user_id,
				'name' => 'user'
			],
			'action'  => $request_action,
			'payload' => $fields
		);

		try {
			$server_responce = self::curl_exec_func( $request_data );
		} catch ( Exception $e ) {
			$server_responce = 'Error: ' . $e->getMessage();
		}

		self::save_log_information( $user_id, $request_action, serialize( $request_data ), serialize( $server_responce ), 0 );

		return;
	}

	/**
	 * Prepare WP user data. Add ACF fields if we have
	 *
	 * @param int $user_id
	 * @param array $data
	 *
	 * @return array
	 */
	static function prepare_user_data( $user_id, $data ) {
		//$data['id'] = $user_id;
		/// Get ACF fields
		$acf_fields = apply_filters( 'ainsys_prepare_extra_user_data', array(), $user_id );

		return array_merge( $data, $acf_fields );
	}

	/**
	 * We send a new user details to AINSYS
	 *
	 * @param int $user_id
	 * @param array $userdata
	 *
	 * @return
	 */
	static function ainsys_new_user_processed( $user_id, $userdata ) {
		$request_action = 'CREATE';

		$fields = apply_filters( 'ainsys_new_user_fields', self::prepare_user_data( $user_id, $userdata ), $userdata );

		$request_data = array(
			'entity'  => [
				'id'   => $user_id,
				'name' => 'user'
			],
			'action'  => $request_action,
			'payload' => $fields
		);

		try {
			$server_responce = self::curl_exec_func( $request_data );
		} catch ( Exception $e ) {
			$server_responce = 'Error: ' . $e->getMessage();
		}

		self::save_log_information( $user_id, $request_action, serialize( $request_data ), serialize( $server_responce ), 0 );

		return;
	}

	/**
	 * We send a new order to AINSYS
	 *
	 * @param int $order_id
	 *
	 * @return
	 */
	static function ainsys_new_order_processed( $order_id = 0 ) {
		if ( ! $order_id ) {
			return false;
		}

		$request_action = 'CREATE';

		$order = new WC_Order( $order_id );
		$data  = $order->get_data();
		if ( empty( $data ) ) {
			return false;
		}

		//self::save_log_information($order_id, 'settings dump', serialize($data), '', 0);

		// Prepare order data
		$fields   = self::prepareFields( $data );
		$utm_data = self::get_utm_fields();

		//Prepare products
		if ( isset( $data['line_items'] ) && ! empty( $data['line_items'] ) ) {
			$products = self::prepare_products( $data['line_items'] );
		} else {
			$products = [];
		}

		$fields_filtered = apply_filters( 'ainsys_new_order_fields', $fields, $order );

		self::sanitize_aditional_order_fields( array_diff( $fields_filtered, $fields ), $data['id'] );

		$order_data = array(
			'entity'  => [
				'id'   => $order_id,
				'name' => 'order'
			],
			'action'  => $request_action,
			'payload' => array_merge( $fields_filtered, $utm_data, [ 'products' => $products ] )
		);

		try {
			$server_responce = self::curl_exec_func( $order_data );
		} catch ( Exception $e ) {
			$server_responce = 'Error: ' . $e->getMessage();
		}

		self::save_log_information( $order_id, $request_action, serialize( $order_data ), serialize( $server_responce ), 0 );

		return;
	}

	/**
	 * Preparing order fields
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	private static function prepareFields( $data = [] ) {
		$all_fields = WC()->checkout->get_checkout_fields();

		$prepare_data = [];
		if ( ! empty( $data['id'] ) ) {
			$prepare_data['id'] = $data['id'];
		}

		if ( ! empty( $data['currency'] ) ) {
			$prepare_data['currency'] = $data['currency'];
		}

		if ( ! empty( $data['customer_id'] ) ) {
			$prepare_data['customer_id'] = $data['customer_id'];
		}

		if ( ! empty( $data['shipping_total'] ) ) {
			$prepare_data['shipping_total'] = $data['shipping_total'];
		}

		if ( ! empty( $data['payment_method_title'] ) ) {
			$prepare_data['payment_method_title'] = $data['payment_method_title'];
		}

		if ( ! empty( $data['transaction_id'] ) ) {
			$prepare_data['transaction_id'] = $data['transaction_id'];
		}

		if ( ! empty( $data['customer_note'] ) ) {
			$prepare_data['customer_note'] = $data['customer_note'];
		}


		$prepare_data['date'] = date( "Y-m-d H:i:s" );

		// get applaed cupons
		$coupons = WC()->cart->get_coupons();
		if ( ! empty( $coupons ) ) {
			foreach ( $coupons as $coupon ) {
				$prepare_data[ 'coupon_' . $coupon->get_code() ] = wc_format_decimal( $coupon->get_amount(), 2 ) . ' ' . $coupon->get_discount_type();
			}
		}

		//billing
		if ( ! empty( $data['billing'] ) ) {
			foreach ( $data['billing'] as $billing_key => $billing_value ) {
				if ( ! empty( $billing_value ) ) {
					$prepare_data[ 'billing_' . $billing_key ] = $billing_value;
				}
				unset( $all_fields["billing"][ 'billing_' . $billing_key ] );
			}
		}

		/// search for custom fields
		if ( ! empty( $all_fields["billing"] ) ) {
			$prepare_data = array_merge( $prepare_data, self::sanitize_aditional_order_fields( $all_fields["billing"], $data['id'] ) );
		}

		//shipping
		if ( ! empty( $data['shipping'] ) ) {
			foreach ( $data['shipping'] as $shipping_key => $shipping_value ) {
				if ( ! empty( $shipping_value ) ) {
					$prepare_data[ 'shipping_' . $shipping_key ] = $shipping_value;
				}
				unset( $all_fields["shipping"][ 'shipping_' . $shipping_key ] );
			}
		}
		/// search for custom fields
		if ( ! empty( $all_fields["shipping"] ) ) {
			$prepare_data = array_merge( $prepare_data, self::sanitize_aditional_order_fields( $all_fields["shipping"], $data['id'] ) );
		}

		return $prepare_data;
	}

	/**
	 * Sanitize aditional order fields from currnt order and save them to order entity
	 *
	 * @param array $aditional_fields
	 * @param string $prefix
	 *
	 * @return array
	 */
	static function sanitize_aditional_order_fields( $aditional_fields, $order_id ) {
		global $wpdb;
		$prepare_data = [];
		foreach ( $aditional_fields as $field_name => $fields ) {
			$field_value = get_post_meta( $order_id, '_' . $field_name, true );
			if ( ! empty( $field_value ) ) {
				//$field_slug = empty(self::translit($field["label"])) ? $field_name : $prefix . self::translit($field["label"]);
				$prepare_data[ $field_name ] = $field_value;

				/// Saving Order field to DB
				$entiti_saved_settings = HTML::get_saved_entity_settings_from_db( ' WHERE entiti="order" AND setting_key="extra_field" AND setting_name="' . $field_name . '"' );
				$responce              = '';
				if ( empty( $entiti_saved_settings ) ) {
					$responce      = $wpdb->insert( $wpdb->prefix . Settings::$ainsys_entities_settings,
						array(
							'entiti'       => 'order',
							'setting_name' => $field_name,
							'setting_key'  => 'extra_field',
							'value'        => serialize( $fields )
						)
					);
					$field_data_id = $wpdb->insert_id;

					/// Save new field to log
					self::save_log_information( $field_data_id, $field_name, 'order_cstom_field_saved', '', 0 );
				} else {
					$responce = $wpdb->update( $wpdb->prefix . Settings::$ainsys_entities_settings,
						array( 'value' => serialize( $fields ) ),
						array( 'id' => $entiti_saved_settings["id"] )
					);
				}
			}
		}

		return $prepare_data;
	}

	/**
	 * Grab UTM fields
	 *
	 * @return array
	 */
	public static function get_utm_fields() {
		$data = [];
		if ( ! empty( utm_hendler::get_referer_url() ) ) {
			$data['REFERER'] = utm_hendler::get_referer_url();
		}

		if ( ! empty( utm_hendler::get_my_host_name() ) ) {
			$data['HOSTNAME'] = utm_hendler::get_my_host_name();
		}

		if ( ! empty( utm_hendler::get_my_ip() ) ) {
			$data['USER_IP'] = utm_hendler::get_my_ip();
		}

		if ( ! empty( utm_hendler::get_roistat() ) ) {
			$data['ROISTAT_VISIT_ID'] = utm_hendler::get_roistat();
		}

		return $data;
	}

	/**
	 * Preparing WC order products
	 *
	 * @param object $products
	 *
	 * @return array
	 */
	private static function prepare_products( $products = [] ) {
		$prepare_data = [];
		if ( empty( $products ) ) {
			return $prepare_data;
		}

		foreach ( $products as $item_id => $item ) {
			$product       = $item->get_product();
			$regular_price = $product->get_regular_price();
			$price         = $product->get_price();
			$sku           = $product->get_sku();
			$product_id    = $product->get_id();

			$prepare_data[ $item_id ]['name']     = $item->get_name();
			$prepare_data[ $item_id ]['quantity'] = $item->get_quantity();
			$prepare_data[ $item_id ]['price']    = $price;
			$prepare_data[ $item_id ]['id']       = $product_id;
			if ( ! empty( $sku ) ) {
				$prepare_data[ $item_id ]['sku'] = $sku;
			}

			//If discounted
			if ( $price != $regular_price ) {
				$prepare_data[ $item_id ]['discount_type_id'] = 1;
				$prepare_data[ $item_id ]['discount_sum']     = $regular_price - $price;
			}
		}

		return $prepare_data;
	}

	/**
	 * We send an updated order status to AINSYS
	 *
	 * @param int $order_id
	 *
	 * @return
	 */
	static function send_order_status_update_to_ainsys( int $order_id ) {
		$host = false;
		if ( ! empty( $_SERVER['SERVER_NAME'] ) ) {
			$host = $_SERVER['SERVER_NAME'];
		}

		$request_action = 'update/order';

		$order  = new WC_Order( $order_id );
		$status = $order->get_status() ?? false;

		$order_data = array(
			'object_id'      => $order_id,
			'request_action' => $request_action,
			'request_data'   => array(
				'status'   => strtoupper( trim( $status ) ),
				'hostname' => $host
			)
		);

		try {
			$server_responce = self::curl_exec_func( $order_data );
		} catch ( Exception $e ) {
			$server_responce = 'Error: ' . $e->getMessage();
		}

		self::save_log_information( $order_id, $request_action, serialize( $order_data ), serialize( $server_responce ), 0 );

		return;
	}

	/**
	 * Handshake with server, get AINSYS integration
	 *
	 */
	static function register_events_and_settings() {
		//$key = ainsys_settings::get_option('ansys_api_key');
		$webhook_url = Settings::get_option( 'ansys_connector_woocommerce_ansys_api_key' ); //https://user-api.ainsys.com/api/v0/workspace-management/workspaces/13/connectors/144/handshake/5ec1a0c99d428601ce42b407ae9c675e0836a8ba591c8ca6e2a2cf5563d97ff0/

		if ( ! empty( $webhook_url ) && empty( get_option( 'ainsys-webhook_url' ) ) ) {
			//new connector
			$webhook_call  = self::curl_exec_func( [ 'hook_url' => Settings::get_option( 'ansys_connector_woocommerce_ansys_api_key' ) ], $key );
			$webhook_array = ! empty( $webhook_call ) ? json_decode( $webhook_call ) : '';
			if ( ! empty( $webhook_call ) && isset( $webhook_array->webhook_url ) ) {
				Settings::set_option( 'webhook_url', $webhook_array->webhook_url );
			}

			// old connector
			//          $connectors = ainsys_settings::get_option('connectors');
//            if (empty($connectors)){
//                $server_url = empty(ainsys_settings::get_option('server')) ? 'https://user-api.ainsys.com/' : ainsys_settings::get_option('server');
//                $workspace = empty(ainsys_settings::get_option('workspace')) ? 14 : ainsys_settings::get_option('workspace');
//                $url = $server_url . 'api/v0/workspace-management/workspaces/' . $workspace . '/connectors/';
//                $sys_id = empty((int)ainsys_settings::get_option('sys_id')) ? 3 : (int)ainsys_settings::get_option('sys_id');
//                $post_fields = array(
//                    "name" => 'string',
//                    "system" => $sys_id,
//                    "workspace" => 14,
//                    "created_by" => 0);
//                $connectors_responce = self::curl_exec_func( $post_fields, $url );
//                $connectors_array = !empty($connectors_responce) ? json_decode($connectors_responce) : '';
//                if ( !empty($connectors_array) && isset($connectors_array->id) ){
//                    ainsys_settings::set_option('connectors', $connectors_array->id);
//                    $url = $server_url . 'api/v0/workspace-management/workspaces/'. $workspace . '/connectors/'. $connectors_array->id . '/handshake-url/';
//                    $url_responce = self::curl_exec_func('', $url );
//                    $url_array = !empty($url_responce) ? json_decode($url_responce) : '';
//                    if ( !empty($url_array) && isset($url_array->url) ){
//                        ainsys_settings::set_option('handshake_url', $url_array->url);
//                        $webhook_call = self::curl_exec_func( ['webhook_url' => ainsys_settings::get_option('hook_url')], $url_array->url );
//                        $webhook_array = !empty($webhook_call) ? json_decode($webhook_call) : '';
//                        if (! empty($webhook_call) && isset($webhook_array->webhook_url)){
//                            ainsys_settings::set_option('webhook_url', $webhook_array->webhook_url);
//                        }
//                    }
//                }
//            }
		}
	}

	/**
	 * Check if AINSYS integration active
	 *
	 * @param string $actions
	 *
	 * @return string[]
	 */
	public static function is_ainsys_integration_active( $actions = '' ) {
		$webhook_url = get_option( 'ansys_connector_woocommerce_ansys_api_key' );

//		if ( ! empty( $webhook_url ) && ! empty( get_option( 'ainsys-webhook_url' ) ) ) {
//			return array( 'status' => 'success' );
//		}
//
//		$request_to_ainsys = wp_remote_post( $webhook_url, [
//			'sslverify' => false,
//			'body'      => [
//				'webhook_url' => get_option( 'ansys_connector_woocommerce_hook_url' )
//			]
//		] );

//		if ( is_wp_error( $request_to_ainsys ) ) {
//			return array( 'status' => 'none' );
//		}

//		$parsed_response = json_decode( $request_to_ainsys['body'] );

		if ( $webhook_url ) {
			self::add_admin_notice( 'Соединение с сервером Ainsys установлено. Webhook_url получен.' );

			return array( 'status' => 'success' );
		}

		return array( 'status' => 'none' );
	}

	static function add_admin_notice( $message, $status = 'success' ) {
		self::$notices[] = [
			'message' => $message,
			'status'  => $status
		];
	}

	/**
	 * Translation of russian letters into latin.
	 *
	 * @param string $string String to covert.
	 *
	 * @return string converted string.
	 */
	function translit( $string ) {
		$string = (string) $string;
		$string = trim( $string );
		$string = function_exists( 'mb_strtolower' ) ? mb_strtolower( $string ) : strtolower( $string );
		$string = strtr( $string, array(
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'д' => 'd',
			'е' => 'e',
			'ё' => 'e',
			'ж' => 'j',
			'з' => 'z',
			'и' => 'i',
			'й' => 'y',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'h',
			'ц' => 'c',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'shch',
			'ы' => 'y',
			'э' => 'e',
			'ю' => 'yu',
			'я' => 'ya',
			'ъ' => '',
			'ь' => '',
			' ' => '_'
		) );

		return $string;
	}

}
