<?php

namespace Ainsys\Connector\Master;


class HTML {

	use Is_Singleton;

	/**
	 * Generate entities HTML placeholder.
	 *
	 * @return string
	 */
	static function generate_entities_html() {

		$entities_html = $collapsed = $collapsed_text = $first_active = $inner_fields_header = '';

		$entitis_list = Settings::get_entitis();

		$properties = Settings::get_entities_settings();

		foreach ( $properties as $item => $settings ) {
			$checker_property    = $settings['type'] === 'bool' || $item === 'api' ? 'small_property' : '';
			$inner_fields_header .= '<div class="properties_field_title ' . $checker_property . '">' . $settings['nice_name'] . '</div>';
		}

		foreach ( $entitis_list as $entiti => $title ) {

			$properties = Settings::get_entities_settings( $entiti );

			$entities_html .= '<div class="entities_block">';

			$get_fields_function = 'get_' . $entiti . '_fields';
			$section_fields      = Settings::$get_fields_function();

			if ( ! empty( $section_fields ) ) {
				$collapsed      = $collapsed ? ' ' : ' active';
				$collapsed_text = $collapsed_text ? 'expand' : 'collapse';
				$entities_html  .= '<div class="entiti_data ' . $entiti . '_data' . $collapsed . '"> ';

				$entities_html .= '<div class="entiti_block_header"><div class="entiti_title">' . $title . '</div>'
				                  . $inner_fields_header . '<a class="button expand_entiti_contaner">'
				                  . $collapsed_text . '</a></div>';
				foreach ( $section_fields as $field_slug => $field_content ) {
					$first_active          = $first_active ? ' ' : ' active';
					$field_name            = empty( $field_content["nice_name"] ) ? $field_slug : $field_content["nice_name"];
					$entiti_saved_settings = array_merge( $field_content, self::get_saved_entity_settings_from_db( ' WHERE entiti="' . $entiti . '" AND setting_name="' . $field_slug . '"' ) );

					if ( ! empty( $field_content["children"] ) ) {

						$data_fields = 'data-seting_name="' . esc_html( $field_slug ) . '" data-entiti="' . esc_html( $entiti ) . '"';
						foreach ( $properties as $name => $prop_val ) {
							$prop_val_out = $name === 'id' ? $field_slug : self::get_property( $name, $prop_val, $entiti_saved_settings );
							$data_fields  .= 'data-' . $name . '="' . esc_html( $prop_val_out ) . '" ';
						}
						$entities_html .= '<div id="' . $field_slug . '" class="entities_field multiple_filds ' . $first_active . '" ' .
						                  $data_fields . '><div class="entities_field_header"><i class="fa fa-sort-desc" aria-hidden="true"></i>' . $field_name . '</div>'
						                  . self::generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) .
						                  '<i class="fa fa-floppy-o"></i><div class="loader_dual_ring"></div></div>';

						foreach ( $field_content["children"] as $inner_field_slug => $inner_field_content ) {
							$field_name            = empty( $inner_field_content["description"] ) ? $inner_field_slug : $inner_field_content["discription"];
							$field_slug_inner      = $field_slug . '_' . $inner_field_slug;
							$entiti_saved_settings = array_merge( $field_content, self::get_saved_entity_settings_from_db( ' WHERE entiti="' . $entiti . '" AND setting_name="' . $field_slug_inner . '"' ) );

							$data_fields = 'data-seting_name="' . esc_html( $field_slug ) . '" data-entiti="' . esc_html( $entiti ) . '"';
							foreach ( $properties as $name => $prop_val ) {
								$prop_val_out = $name === 'id' ? $field_slug_inner : self::get_property( $name, $prop_val, $entiti_saved_settings );
								$data_fields  .= 'data-' . $name . '="' . esc_html( $prop_val_out ) . '" ';
							}
							$entities_html .= '<div id="' . $entiti . '_' . $inner_field_slug . '" class="entities_field multiple_filds_children ' . $first_active . '" ' .
							                  $data_fields . '><div class="entities_field_header"><i class="fa fa-angle-right" aria-hidden="true"></i>' . $field_name . '</div>'
							                  . self::generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) .
							                  '<i class="fa fa-floppy-o"></i><div class="loader_dual_ring"></div></div>';
						}
					} else {
						$data_fields = 'data-seting_name="' . esc_html( $field_slug ) . '" data-entiti="' . esc_html( $entiti ) . '"';
						foreach ( $properties as $name => $prop_val ) {
							$prop_val_out = self::get_property( $name, $prop_val, $entiti_saved_settings );
							$data_fields  .= 'data-' . $name . '="' . esc_html( $prop_val_out ) . '" ';
						}
						$entities_html .= '<div id="' . $field_slug . '" class="entities_field ' . $first_active . '" ' . $data_fields . '><div class="entities_field_header">' . $field_name . '</div>'
						                  . self::generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) .
						                  '<i class="fa fa-floppy-o"></i><div class="loader_dual_ring"></div></div>';
					}
				}
				/// close //// div class="entiti_data"
				$entities_html .= '</div>';
			}
			/// close //// div class="entities_block"
			$entities_html .= '</div>';
		}

		return '<div class="entitis_table">
                ' . $entities_html .
		       '</div>';

	}

	/**
	 * Get property from array.
	 *
	 * @return string
	 */
	static function get_property( $name, $prop_val, $entiti_saved_settings ) {
		if ( is_array( $prop_val['default'] ) ) {
			return isset( $entiti_saved_settings[ strtolower( $name ) ] ) ? $entiti_saved_settings[ strtolower( $name ) ] : array_search( '1', $prop_val['default'] );
		}

		return isset( $entiti_saved_settings[ strtolower( $name ) ] ) ?
			$entiti_saved_settings[ strtolower( $name ) ] : $prop_val['default'];
	}

	/**
	 * Generate properties for entity field.
	 *
	 * @return string
	 */
	static function generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) {

		$inner_fields = '';
		if ( empty( $properties ) ) {
			return '';
		}

		foreach ( $properties as $item => $settings ) {
			$checker_property = $settings['type'] === 'bool' || $item === 'api' ? 'small_property' : '';
			$inner_fields     .= '<div class="properties_field ' . $checker_property . '">';
			$field_value      = $item === 'id' ? $field_slug : self::get_property( $item, $settings, $entiti_saved_settings );
			switch ( $settings['type'] ) {
				case 'constant':
					$field_value  = $field_value ? $field_value : '<i>' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</i>';
					$inner_fields .= $item === 'api' ? '<div class="entiti_settings_value constant ' . $field_value . '"></div>' : '<div class="entiti_settings_value constant">' . $field_value . '</div>';
					break;
				case 'bool':
					$checked      = (int) $field_value ? 'checked="" value="1"' : ' value="0"';
					$checked_text = (int) $field_value ? __( 'On', AINSYS_CONNECTOR_TEXTDOMAIN ) : __( 'Off', AINSYS_CONNECTOR_TEXTDOMAIN );
					$inner_fields .= '<input type="checkbox"  class="editor_mode entiti_settings_value " id="' . $item . '" ' . $checked . '/> ';
					$inner_fields .= '<div class="entiti_settings_value">' . $checked_text . '</div> ';
					break;
				case 'int':
					$inner_fields .= '<input size="10" type="text"  class="editor_mode entiti_settings_value" id="' . $item . '" value="' . $field_value . '"/> ';
					$field_value  = $field_value ? $field_value : '<i>' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</i>';
					$inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
					break;
				case 'select':
					$inner_fields .= '<select id="' . $item . '" class="editor_mode entiti_settings_value" name="' . $item . '">';
					$state_text   = '';
					foreach ( $settings["default"] as $option => $state ) {
						$selected     = $option === $field_value ? 'selected="selected"' : '';
						$state_text   = $option === $field_value ? $option : $state_text;
						$inner_fields .= '<option size="10" value="' . $option . '" ' . $selected . '>' . $option . '</option>';
					}
					$inner_fields .= '</select>';
					$inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
					break;
				default:
					$field_length = $item === 'description' ? 20 : 8;
					$inner_fields .= '<input size="' . $field_length . '" type="text" class="editor_mode entiti_settings_value" id="' . $item . '" value="' . $field_value . '"/>';
					$field_value  = $field_value ? $field_value : '<i>' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</i>';
					$inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
			}
			/// close //// div class="properties_field"
			$inner_fields .= '</div>';
		}

		return $inner_fields;
	}

	/**
	 * Get entiti field settings from DB.
	 *
	 * @param string $where
	 * @param bool $single
	 *
	 * @return array
	 */
	static function get_saved_entity_settings_from_db( $where = '', $single = true ) {
		global $wpdb;
		$query   = "SELECT * 
        FROM " . $wpdb->prefix . Settings::$ainsys_entities_settings . $where;
		$resoult = $wpdb->get_results( $query, ARRAY_A );
		if ( isset( $resoult[0]["value"] ) && $single ) {
			$keys = array_column( $resoult, 'setting_key' );
			if ( count( $resoult ) > 1 && isset( array_flip( $keys )['saved_field'] ) ) {
				$saved_settins_id = array_flip( $keys )['saved_field'];
				$data             = maybe_unserialize( $resoult[ $saved_settins_id ]["value"] );
				$data['id']       = $resoult[ $saved_settins_id ]["id"] ?? 0;
			} else {
				$data       = maybe_unserialize( $resoult[0]["value"] );
				$data['id'] = $resoult[0]["id"] ?? 0;
			}
		} else {
			$data = $resoult;
		}

		return $data ?? array();
	}

	/**
	 * Generate server data transactions HTML.
	 *
	 * @return string
	 */
	static function generate_log_html( $where = '' ) {

		global $wpdb;

		$log_html        = '<div id="connection_log"><table class="form-table">';
		$log_html_body   = '';
		$log_html_header = '';
		$query           = "SELECT * 
        FROM " . $wpdb->prefix . Settings::$ainsys_log_table . $where;
		$output          = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $output ) ) {
			return '<div class="empty_tab"><h3>' . __( 'No transactions to display', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</h3></div>';
		}

		foreach ( $output as $item ) {
			$log_html_body .= '<tr valign="top">';
			$header_full   = empty( $log_html_header ) ? true : false;
			foreach ( $item as $name => $value ) {
				$log_html_header .= $header_full ? '<th>' . strtoupper( str_replace( '_', ' ', $name ) ) . '</th>' : '';
				$log_html_body   .= '<td class="' . $name . '">';
				if ( $name === 'incoming_call' ) {
					$value = (int) $value === 0 ? 'No' : 'Yes';
				}
				if ( $name === 'request_data' ) {
					$value = maybe_unserialize( $value );
					if ( empty( $value["request_data"] ) ) {
						$log_html_body .= $value ? '<div class="gray_header">' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</div>' : $value;
						continue;
					}
					if ( is_array( $value ) ) {
						if ( count( $value["request_data"] ) > 2 ) {
							$log_html_body .= '<div class="request_data_contaner"> <a class="button expand_data_contaner">more</a>';
						}
						foreach ( $value["request_data"] as $title => $param ) {
							if ( $title === "products" && ! empty( $param ) ) {
								foreach ( $param as $prod_id => $product ) {
									$log_html_body .= '</br> <strong>Prod# ' . $prod_id . '</strong>';
									foreach ( $product as $param_title => $poduct_param ) {
										if ( is_array( $poduct_param ) ) {
											continue;
										}
										$log_html_body .= '<div><span class="gray_header">' . $param_title . ' : </span>' . maybe_serialize( $poduct_param ) . '</div>';
									}
								}
							} else {
								$log_html_body .= '<div><span class="gray_header">' . $title . ' : </span>' . maybe_serialize( $param ) . '</div>';
							}
						}
						$log_html_body .= '</div>';
					}
				} else {
					$log_html_body .= $value;
				}
				$log_html_body .= '</td>';
			}
			$log_html_body .= '</tr>';
		}
		$log_html .= '<thead><tr>' . $log_html_header . '</tr></thead>' . $log_html_body . '</table> </div>';

		return $log_html;
	}

	/**
	 * Generate debug log HTML.
	 *
	 * @return string
	 */
	static function generate_debug_log() {

		if ( ! (int) Settings::get_option( 'display_debug' ) ) {
			return;
		}

		$html = '
        <div style="color: grey; padding-top: 20px">
        !!Debug info!!
            <ul>
                <li>' . 'connector #' . Settings::get_option( 'connectors' ) . '</li>
                <li>' . 'handshake_url - ' . Settings::get_option( 'handshake_url' ) . '</li>
                <li>' . 'webhook_url - ' . Settings::get_option( 'webhook_url' ) . '</li>
                <li>' . 'debug_log - ' . Settings::get_option( 'debug_log' ) . '</li>
            </ul>
        </div>';

		return $html;
	}
}