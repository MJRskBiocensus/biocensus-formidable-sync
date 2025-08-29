<?php
/**
 * Graph block controller
 *
 * @package FrmCharts
 */

/**
 * Class FrmChartsGraphController
 */
class FrmChartsGraphController {

	/**
	 * Track the custom graph ID.
	 *
	 * @var string
	 */
	private static $custom_graph_id = '';

	/**
	 * Loads scripts.
	 */
	public static function load_scripts() {
		wp_localize_script(
			'frm-charts-graph-editor-script',
			'FrmChartsGraph',
			array(
				'frmJsUrl'        => FrmChartsAppHelper::get_formidable_js_url(),
				'defaultSettings' => self::get_defaults(),
				'sizeSettings'    => self::get_size_settings(),
				'dataTypeOptions' => self::get_data_type_options(),
				'menuName'        => FrmAppHelper::get_menu_name(),
				'animationSupport' => method_exists( 'FrmProGraphsController', 'add_animation_options' ),
				'gdSupported'      => FrmChartsGraphImageController::is_gd_supported(),
			)
		);
	}

	/**
	 * Renders graph from block props.
	 *
	 * @param array $block_props Block props.
	 *
	 * @return string
	 */
	public static function render( $block_props ) {
		$shortcode_attrs = self::block_to_shortcode_attrs( $block_props );
		$shortcode       = '[frm-graph ';
		foreach ( $shortcode_attrs as $key => $value ) {
			if ( in_array( $key, array( 'title', 'x_title', 'y_title', 'tooltip_label' ), true ) ) {
				// $value cannot be escaped or apostrophes will be encoded as HTML entities.
				$shortcode .= esc_attr( $key ) . '="' . $value . '" ';
			} else {
				$shortcode .= esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
			}
		}
		$shortcode      .= ']';
		$graph_content = do_shortcode( $shortcode );
		return '<div ' . get_block_wrapper_attributes() . '>' . $graph_content . '</div>';
	}

	/**
	 * Adds a single block attribute to shortcode attributes.
	 *
	 * @param string $key Attribute key.
	 * @param string $value Attribute value.
	 * @param array  $shortcode_attrs Shortcode attributes.
	 */
	private static function add_shortcode_attr( $key, $value, &$shortcode_attrs ) {
		if ( 'curve_type' === $key ) {
			if ( $value ) {
				$value = 'function';
			}
		} elseif ( in_array( $key, self::get_size_settings(), true ) ) {
			$value = str_replace( 'px', '', $value );
		}

		$shortcode_attrs[ $key ] = is_array( $value ) ? implode( ',', $value ) : $value;
	}

	/**
	 * Converts block props to shortcode attributes.
	 *
	 * @param array $block_props Block props.
	 * @return array
	 */
	private static function block_to_shortcode_attrs( $block_props ) {
		$shortcode_attrs = array();
		$extract_attrs   = array( 'filters', 'xAxisProps', 'yAxisProps', 'displayProps', 'legendProps' );
		$animation_props = array( 'animate', 'animation_duration', 'animation_easing' );

		self::set_chart_area_attr( $block_props );

		foreach ( $block_props as $key => $value ) {
			if ( empty( $block_props['animate'] ) && in_array( $key, $animation_props, true ) ) {
				continue;
			}

			if ( '' === $value ) {
				continue;
			}

			if ( in_array( $key, array( 'formFields', 'className' ), true ) ) {
				continue;
			}

			if ( 'dataOverTime' === $key ) {
				if ( $value ) {
					self::add_shortcode_attr( 'include_zero', 1, $shortcode_attrs );
				}
				continue;
			}

			if ( ! in_array( $key, $extract_attrs, true ) ) {
				self::add_shortcode_attr( $key, $value, $shortcode_attrs );
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			foreach ( $value as $option ) {
				if ( ! empty( $option['field'] ) ) {
					$option['name'] = str_replace( '{field}', $option['field'], $option['name'] );
				}
				self::add_shortcode_attr( $option['name'], $option['value'], $shortcode_attrs );
			}
		}

		if ( ! empty( $shortcode_attrs['fields'] ) ) {
			unset( $shortcode_attrs['form'] );
		}

		return $shortcode_attrs;
	}

	/**
	 * Sets chart_area attribute.
	 *
	 * @param array $block_props Block props.
	 */
	private static function set_chart_area_attr( &$block_props ) {
		if ( empty( $block_props['displayProps'] ) ) {
			return;
		}

		$value = array();
		foreach ( $block_props['displayProps'] as $index => $option ) {
			if ( 0 !== strpos( $option['name'], 'chart_area_' ) ) {
				continue;
			}

			$value[] = sprintf(
				'%1$s:%2$s',
				str_replace( 'chart_area_', '', $option['name'] ),
				str_replace( 'px', '', $option['value'] ) // Remove 'px' when using pixel.
			);

			unset( $block_props['displayProps'][ $index ] );
		}

		if ( $value ) {
			$block_props['displayProps'][] = array(
				'name'  => 'chart_area',
				'value' => implode( ',', $value ),
			);
		}
	}

	/**
	 * Gets graph default settings.
	 *
	 * @return array
	 */
	private static function get_defaults() {
		if ( is_callable( array( 'FrmProGraphsController', 'get_graph_defaults' ) ) ) {
			return FrmProGraphsController::get_graph_defaults();
		}
		return array(); // TODO: add some default values.
	}

	/**
	 * Sets custom graph ID to be used when generating graph.
	 *
	 * @param string $id Graph ID.
	 */
	public static function set_custom_graph_id( $id ) {
		self::$custom_graph_id = $id;
	}

	/**
	 * Changes graph ID when generating graph.
	 *
	 * @param string $graph_id Graph ID.
	 * @return string
	 */
	public static function change_graph_id( $graph_id ) {
		if ( self::$custom_graph_id ) {
			return self::$custom_graph_id;
		}
		return $graph_id;
	}

	/**
	 * Gets all size setting names.
	 *
	 * @return string[]
	 */
	private static function get_size_settings() {
		return array(
			'width',
			'height',
			'title_size',
			'x_title_size',
			'x_labels_size',
			'y_title_size',
			'y_labels_size',
			'legend_size',
		);
	}

	/**
	 * Gets data type options.
	 *
	 * @since 1.0.1
	 *
	 * @return array
	 */
	private static function get_data_type_options() {
		if ( method_exists( 'FrmProGraphsController', 'get_data_type_options' ) ) {
			return FrmProGraphsController::get_data_type_options();
		}

		return array(
			'count'   => __( 'The number of entries', 'frm-charts' ),
			'total'   => __( 'Add the field values together', 'frm-charts' ),
			'average' => __( 'Average the totaled field values', 'frm-charts' ),
		);
	}
}
