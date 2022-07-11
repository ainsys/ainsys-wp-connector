<?php

namespace Ainsys\Connector\Master;

use \WC_Order;
use \WC_Product;

/**
 * AINSYS webhook listener.
 *
 * @class          AINSYS webhook listener
 * @version        1.0.0
 * @author         AINSYS
 */
class Webhook_Listener implements Hooked {

	use Is_Singleton;

	static $request_token;
	static $request_data;


	public function init_hooks() {
		self::$request_token = sha1( $_SERVER["REMOTE_ADDR"] . $_SERVER["SERVER_NAME"] );
		add_action( 'init', array( __CLASS__, 'webhook_listener' ) );
	}

	/**
	 * Listens WebHooks using a specific param 'ainsys_webhook'.
	 *
	 */
	static function webhook_listener() {
		if ( empty( $_SERVER['QUERY_STRING'] ) ) {
			return;
		}

		parse_str( $_SERVER['QUERY_STRING'], $query_vars );

		if ( ! isset( $query_vars['ainsys_webhook'] ) ) {
			return;
		}

//
//		if ( self::$request_token !== $query_vars['ainsys_webhook'] ) {
//			Core::log( 'Webhook - Token invalid' );
//			http_response_code( 403 );
//			exit;
//		}


		$entityBody         = file_get_contents( 'php://input' );
		$request            = json_decode( $entityBody );
		$request_action_arr = $request->action;

		$object_id = $request->entity->id ?? 0;
		$data      = $request->payload ?? [];

		$entityAction = $request->action;
		$entityType   = $request->entity->name;

		switch ( $entityAction ) {
			case 'CREATE':
			case 'DELETE':
			case 'UPDATE':
				$request_code = 200;
				break;
		}

		switch ( $entityType ) {
			case 'product':
				$response = self::entityProduct( $entityAction, $data, $object_id );
				break;
			case 'user':
				$response = self::entityUser( $entityAction, $data, $object_id );
				break;
			case 'order':
				$response = self::entityOrder( $entityAction, $data, $object_id );
				break;
		}

		/**
		 * TODO: !!! BEWARE - this will lead to endlessly increased wp_options table  in WP which will lead to loading site forever
		 *      the longer it's used the slower site would become, because it will load all of them upon each request in memory!!!
		 *      RECOMMENDED TO COMMENT OUT THIS LINE.
		 */
//		update_option( 'last_query_' . time(), $entityBody );

		wp_send_json( [
			//'id'           => rand( 2, 50 ),
			'entityType'   => $entityType,
			'request_data' => $data,
			'response'     => $response
		], $request_code );

		exit();

//        $query_vars['request_data'] = '{
//   "object_id":"489",
//   "request_action":"update/order",
//   "request_data":{
//      "id":"489",
//      "billing_first_name":"DENIS",
//      "status":"PROCESSING",
//      "hostname":"wp.my"
//   }
//}';
		//$query_vars['request_data'] = '{ "object_id":"489", "request_action":"update/user", "request_data":{ "ID":1, "user_nicename":"denis222", "user_url":"test_url" } }';
		$query_vars['request_data'] = str_replace( "\n", '', stripslashes( $query_vars['request_data'] ) );
		self::$request_data         = isset( $query_vars['request_data'] ) ? (array) json_decode( $query_vars['request_data'] ) : '';

		self::process_request();
	}

	static function entityProduct( $action, $data, $object_id = 0 ) {
		switch ( $action ) {
			case 'add':
				$product_id = wp_insert_post( [ 'post_type' => 'product', 'post_title' => $data['title'] ] );

				return self::REST_update_product( $data, $product_id );
			case 'update':
				return self::REST_update_product( $data, $object_id );
			case 'delete':
				$WC_Product = new WC_Product( $object_id );

				return $WC_Product->delete();
		}
	}

	static function REST_update_product( $data, $object_id ) {
		$data       = (array) $data;
		$data['id'] = $object_id;

		if ( ! wc_get_product( $object_id ) ) {
			return 'Товар не найден';
		}

		$product = new \WC_Product( $data["id"] );

		// Title
		if ( isset( $data['title'] ) ) {
			wp_update_post( array( 'ID' => $product->get_id(), 'post_title' => $data['title'] ) );
		}

		// Title
		if ( isset( $data['content'] ) ) {
			wp_update_post( array( 'ID' => $product->get_id(), 'post_content' => $data['content'] ) );
		}

		// Virtual
		if ( isset( $data['virtual'] ) ) {
			$product->set_virtual( $data['virtual'] );
		}

		// Tax status
		if ( isset( $data['tax_status'] ) ) {
			$product->set_tax_status( wc_clean( $data['tax_status'] ) );
		}

		// Tax Class
		if ( isset( $data['tax_class'] ) ) {
			$product->set_tax_class( wc_clean( $data['tax_class'] ) );
		}

		// Catalog Visibility
		if ( isset( $data['catalog_visibility'] ) ) {
			$product->set_catalog_visibility( wc_clean( $data['catalog_visibility'] ) );
		}

		// Purchase Note
		if ( isset( $data['purchase_note'] ) ) {
			$product->set_purchase_note( wc_clean( $data['purchase_note'] ) );
		}

		// Featured Product
		if ( isset( $data['featured'] ) ) {
			$product->set_featured( $data['featured'] );
		}

		// Shipping data
		//$product = $this->save_product_shipping_data( $product, $data );

		// SKU
		if ( isset( $data['sku'] ) ) {
			$sku     = $product->get_sku();
			$new_sku = wc_clean( $data['sku'] );

			if ( '' == $new_sku ) {
				$product->set_sku( '' );
			} elseif ( $new_sku !== $sku ) {
				if ( ! empty( $new_sku ) ) {
					$unique_sku = wc_product_has_unique_sku( $product->get_id(), $new_sku );
					if ( ! $unique_sku ) {
						throw new \WC_API_Exception( 'woocommerce_api_product_sku_already_exists', __( 'The SKU already exists on another product.', 'woocommerce' ), 400 );
					} else {
						$product->set_sku( $new_sku );
					}
				} else {
					$product->set_sku( '' );
				}
			}
		}


		// Sales and prices.
		if ( in_array( $product->get_type(), array( 'variable', 'grouped' ) ) ) {
			// Variable and grouped products have no prices.
			$product->set_regular_price( '' );
			$product->set_sale_price( '' );
			$product->set_date_on_sale_to( '' );
			$product->set_date_on_sale_from( '' );
			$product->set_price( '' );
		} else {
			// Regular Price.
			if ( isset( $data['regular_price'] ) ) {
				$regular_price = ( '' === $data['regular_price'] ) ? '' : $data['regular_price'];
				$product->set_regular_price( $regular_price );
			}

			// Sale Price.
			if ( isset( $data['sale_price'] ) ) {
				$sale_price = ( '' === $data['sale_price'] ) ? '' : $data['sale_price'];
				$product->set_sale_price( $sale_price );
			}

			if ( isset( $data['sale_price_dates_from'] ) ) {
				$date_from = $data['sale_price_dates_from'];
			} else {
				$date_from = $product->get_date_on_sale_from() ? date( 'Y-m-d', $product->get_date_on_sale_from()->getTimestamp() ) : '';
			}

			if ( isset( $data['sale_price_dates_to'] ) ) {
				$date_to = $data['sale_price_dates_to'];
			} else {
				$date_to = $product->get_date_on_sale_to() ? date( 'Y-m-d', $product->get_date_on_sale_to()->getTimestamp() ) : '';
			}

			if ( $date_to && ! $date_from ) {
				$date_from = strtotime( 'NOW', current_time( 'timestamp', true ) );
			}

			$product->set_date_on_sale_to( $date_to );
			$product->set_date_on_sale_from( $date_from );

			if ( $product->is_on_sale( 'edit' ) ) {
				$product->set_price( $product->get_sale_price( 'edit' ) );
			} else {
				$product->set_price( $product->get_regular_price( 'edit' ) );
			}
		}

		// Product parent ID for groups
		if ( isset( $data['parent_id'] ) ) {
			$product->set_parent_id( absint( $data['parent_id'] ) );
		}

		// Sold Individually
		if ( isset( $data['sold_individually'] ) ) {
			$product->set_sold_individually( true === $data['sold_individually'] ? 'yes' : '' );
		}

		// Stock status
		if ( isset( $data['in_stock'] ) ) {
			$stock_status = ( true === $data['in_stock'] ) ? 'instock' : 'outofstock';
		} else {
			$stock_status = $product->get_stock_status();

			if ( '' === $stock_status ) {
				$stock_status = 'instock';
			}
		}

		// Stock Data
		if ( 'yes' == get_option( 'woocommerce_manage_stock' ) ) {
			// Manage stock
			if ( isset( $data['managing_stock'] ) ) {
				$managing_stock = ( true === $data['managing_stock'] ) ? 'yes' : 'no';
				$product->set_manage_stock( $managing_stock );
			} else {
				$managing_stock = $product->get_manage_stock() ? 'yes' : 'no';
			}

			// Backorders
			if ( isset( $data['backorders'] ) ) {
				if ( 'notify' == $data['backorders'] ) {
					$backorders = 'notify';
				} else {
					$backorders = ( true === $data['backorders'] ) ? 'yes' : 'no';
				}

				$product->set_backorders( $backorders );
			} else {
				$backorders = $product->get_backorders();
			}

			if ( $product->is_type( 'grouped' ) ) {
				$product->set_manage_stock( 'no' );
				$product->set_backorders( 'no' );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( $stock_status );
			} elseif ( $product->is_type( 'external' ) ) {
				$product->set_manage_stock( 'no' );
				$product->set_backorders( 'no' );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( 'instock' );
			} elseif ( 'yes' == $managing_stock ) {
				$product->set_backorders( $backorders );

				// Stock status is always determined by children so sync later.
				if ( ! $product->is_type( 'variable' ) ) {
					$product->set_stock_status( $stock_status );
				}

				// Stock quantity
				if ( isset( $data['stock_quantity'] ) ) {
					$product->set_stock_quantity( wc_stock_amount( $data['stock_quantity'] ) );
				}
			} else {
				// Don't manage stock.
				$product->set_manage_stock( 'no' );
				$product->set_backorders( $backorders );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( $stock_status );
			}
		} elseif ( ! $product->is_type( 'variable' ) ) {
			$product->set_stock_status( $stock_status );
		}

		// Upsells
		if ( isset( $data['upsell_ids'] ) ) {
			$upsells = array();
			$ids     = $data['upsell_ids'];

			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					if ( $id && $id > 0 ) {
						$upsells[] = $id;
					}
				}

				$product->set_upsell_ids( $upsells );
			} else {
				$product->set_upsell_ids( array() );
			}
		}

		// Cross sells
		if ( isset( $data['cross_sell_ids'] ) ) {
			$crosssells = array();
			$ids        = $data['cross_sell_ids'];

			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					if ( $id && $id > 0 ) {
						$crosssells[] = $id;
					}
				}

				$product->set_cross_sell_ids( $crosssells );
			} else {
				$product->set_cross_sell_ids( array() );
			}
		}

		// Product categories
		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			$product->set_category_ids( $data['categories'] );
		}

		// Product tags
		if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
			$product->set_tag_ids( $data['tags'] );
		}

		// Downloadable
		if ( isset( $data['downloadable'] ) ) {
			$is_downloadable = ( true === $data['downloadable'] ) ? 'yes' : 'no';
			$product->set_downloadable( $is_downloadable );
		} else {
			$is_downloadable = $product->get_downloadable() ? 'yes' : 'no';
		}

		// Downloadable options
		if ( 'yes' == $is_downloadable ) {
			// Download limit
			if ( isset( $data['download_limit'] ) ) {
				$product->set_download_limit( $data['download_limit'] );
			}

			// Download expiry
			if ( isset( $data['download_expiry'] ) ) {
				$product->set_download_expiry( $data['download_expiry'] );
			}
		}

		// Product url
		if ( $product->is_type( 'external' ) ) {
			if ( isset( $data['product_url'] ) ) {
				$product->set_product_url( $data['product_url'] );
			}

			if ( isset( $data['button_text'] ) ) {
				$product->set_button_text( $data['button_text'] );
			}
		}

		// Reviews allowed
		if ( isset( $data['reviews_allowed'] ) ) {
			$product->set_reviews_allowed( $data['reviews_allowed'] );
		}

		$product->save();
		$request = [
			'object_id'      => $data["id"],
			'request_action' => 'REST/update/product',
			'request_data'   => self::$request_data['request_data']
		];
		//	Core::save_log_information( $data["id"], 'save product', serialize( $request ), '$serrver_responce', 1 );
	}

	static function entityUser( $action, $data, $object_id = 0 ) {
		$data              = (array) $data;
		$data['user_pass'] = $data['user_pass'] ?? wp_generate_password( 15, true, true );

		switch ( $action ) {
			case 'add':
				$user_id = wp_insert_user( $data );
				if ( ! is_wp_error( $user_id ) ) {
					return $user_id;
				} else {
					return $user_id->get_error_message();
				}
			case 'update':
				return self::REST_update_user( $data, $object_id );
			case 'delete':
				return wp_delete_user( $object_id );
		}
	}

	static function REST_update_user( $data, $object_id = 0 ) {
		$errors = wp_update_user( $data );
		Core::log( 'Webhook - User updated ' . $errors );
		//Core::save_log_information( $data['ID'], 'save user', serialize( $data ), $errors, 1 );
	}

	static function entityOrder( $action, $data, $object_id = 0 ) {
		switch ( $action ) {
			case 'add':
				$order = wc_create_order( $data );
				if ( ! is_wp_error( $order ) ) {
					return $order->get_id();
				} else {
					return $order->get_error_message();
				}

			case 'update':
				if ( ! wc_get_order( $object_id ) ) {
					return 'Заказ не найден';
				}

				return self::REST_update_order( $data, $object_id );
			case 'delete':
				return 'Метод DELETE не реализован';
		}
	}

	static function REST_update_order( $data, $object_id ) {
		$data       = (array) $data;
		$data['id'] = $object_id;
		$order      = new WC_Order( $data["id"] );
		if ( $order ) {
			$fields_prefix = array(
				'shipping' => true,
				'billing'  => true,
			);

			$shipping_fields = array(
				'shipping_method' => true,
				'shipping_total'  => true,
				'shipping_tax'    => true,
			);
			foreach ( $data as $key => $value ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );
					// Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
				} elseif
				( isset( $fields_prefix[ current( explode( '_', $key ) ) ] )
				) {
					if ( ! isset( $shipping_fields[ $key ] ) ) {
						$order->update_meta_data( '_' . $key, $value );
					}
				}
			}

			$order->hold_applied_coupons( $data['billing_email'] );
			$order->set_created_via( 'checkout' );
			$order->set_cart_hash( $cart_hash );
			$order->set_customer_id( $data['customer_id'] );
			$order->set_currency( get_woocommerce_currency() );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_customer_ip_address( isset( $data['USER_IP'] ) ? $data['USER_IP'] : '' );
			$order->set_customer_user_agent( wc_get_user_agent() );
			$order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
			$order->set_payment_method( isset( $available_gateways[ $data['payment_method'] ] ) ? $available_gateways[ $data['payment_method'] ] : $data['payment_method'] );

			$order_id = $order->save();

			return $order_id;
		}
		//Core::save_log_information( (int) self::$request_data['order_id'], 'save order', serialize( self::$request_data['request_data'] ), '$serrver_responce', 1 );
	}

	/**
	 * Processes the Webhook request
	 *
	 * @return void
	 */
	static function process_request() {
		// Retrieve the request's body and parse it as JSON.
		$request_data_from_contents = json_decode( @file_get_contents( 'php://input' ) );

		self::$request_data = empty( $request_data_from_contents ) ? self::$request_data : $request_data_from_contents;

		if ( empty( self::$request_data ) ) {
			Core::log( 'Webhook - No request data' );
			http_response_code( 403 );
			exit;
		}

		if ( self::$request_data ) {
			$actions = explode( '/', self::$request_data["request_action"] );
			switch ( $actions[0] ) {
				case 'update':
					$function = 'REST_' . implode( '_', $actions );
					self::$function( (array) self::$request_data["request_data"] );
					break;
				case 'field':


					break;
				default:
					Core::log( 'Webhook - Action not supported' );
					http_response_code( 403 );
			}
		}

		http_response_code( 200 );
		exit;
	}

	static function REST_update_comment( $data ) {
		$data['comment_ID'] = $data['comment_post_ID'];
		$errors             = wp_update_comment( $data );
		Core::log( 'Webhook - Comment updated ' . $errors );
		//Core::save_log_information( $data['comment_ID'], 'save comment', serialize( $data ), $errors, 1 );
	}
}
