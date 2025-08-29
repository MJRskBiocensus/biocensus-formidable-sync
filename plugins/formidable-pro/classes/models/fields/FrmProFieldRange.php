<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 3.0
 */
class FrmProFieldRange extends FrmFieldType {
	use FrmProFieldTypeTrait;

	/**
	 * @var string
	 * @since 3.0
	 */
	protected $type = 'range';

	/**
	 * @var bool
	 */
	protected $array_allowed = false;

	protected function field_settings_for_type() {
		$settings = array(
			'invalid' => true,
			'range'   => true,
			'prefix'  => true,
			'format'  => true,
		);

		FrmProFieldsHelper::fill_default_field_display( $settings );
		return $settings;
	}

	/**
	 * Returns true if the min/max values should be shown under the input.
	 *
	 * The min/max values are shown as long as the range field has a Before or After input value.
	 * This is shown regardless of the show_slider_range option for backward compatibility.
	 *
	 * @since 6.20 A new show_slider_range option was added. If this is on, the range can be shown as well.
	 *
	 * @return bool
	 */
	private function get_slider_checked_value() {
		$has_unit          = ! empty( FrmField::get_option( $this->field, 'prepend' ) ) || ! empty( FrmField::get_option( $this->field, 'append' ) );
		$show_slider_range = FrmField::get_option( $this->field, 'show_slider_range' );
		return '' === $show_slider_range ? $has_unit : $show_slider_range;
	}

	/**
	 * @since 5.4.3
	 *
	 * @param array $args - Includes 'field', 'display', and 'values'
	 * @return void
	 */
	public function show_primary_options( $args ) {
		$field = $args['field'];

		$show_range_checked = $this->get_slider_checked_value();
		$value_position     = $this->get_value_position();
		include FrmProAppHelper::plugin_path() . '/classes/views/frmpro-fields/back-end/slider-options.php';
	}

	/**
	 * Returns the position of the slider value.
	 *
	 * For backward compatibility, if the field does not have before/after input values, the value will be shown
	 * at the bottom center. It would otherwise be shown at the bottom left.
	 *
	 * @since 6.20
	 *
	 * @return string
	 */
	private function get_value_position() {
		$value_position = FrmField::get_option( $this->field, 'value_position' );
		if ( '' === $value_position ) {
			$has_unit = ! empty( FrmField::get_option( $this->field, 'prepend' ) ) || ! empty( FrmField::get_option( $this->field, 'append' ) );
			return $has_unit ? 'top-left' : 'bottom-center';
		}

		return $value_position;
	}

	protected function builder_text_field( $name = '' ) {
		if ( is_object( $this->field ) ) {
			$min  = FrmField::get_option( $this->field, 'minnum' );
			$max  = FrmField::get_option( $this->field, 'maxnum' );
			$step = FrmField::get_option( $this->field, 'step' );
		} else {
			$min  = 0;
			$max  = 100;
			$step = 1;
		}

		$default_value        = $this->get_default_value( $min, $max );
		$value_position       = $this->get_value_position();
		$show_value_at_bottom = strpos( $value_position, 'bottom' ) !== false;
		$output               = '<div>' . $this->output_selected_value( $default_value, true ) . '</div>';
		$output               = $this->add_range_value_position_class( $output );

		$input  = '<div class="frm_range_container">';
		if ( ! $show_value_at_bottom ) {
			$input .= $output;
		}
		$input .= '<input type="range" name="' . esc_attr( $this->html_name( $name ) ) . '" id="' . esc_attr( $this->html_id() ) . '" value="' . esc_attr( $default_value ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" />';
		$input .= $this->output_min_max_value( true );
		if ( $show_value_at_bottom ) {
			$input .= $output;
		}
		$input .= '</div>';

		return $input;
	}

	/**
	 * Reset the default value if it's out of range
	 *
	 * @since 3.0.06
	 */
	private function get_default_value( $min, $max ) {
		$default_value = $this->get_field_column( 'default_value' );
		$out_of_range  = $default_value < $min || $default_value > $max;
		if ( $default_value !== '' && $out_of_range ) {
			$default_value = '';
		}
		return $default_value;
	}

	protected function extra_field_opts() {
		return array(
			'minnum'            => 0,
			'maxnum'            => 100,
			'step'              => 1,
			'show_slider_range' => '',
			'value_position'    => '',
		);
	}

	/**
	 * @since 6.20
	 *
	 * {@inheritdoc}
	 */
	protected function add_min_max( $args, &$input_html ) {
		$this->add_formatted_min_max( $args, $input_html );
	}

	public function front_field_input( $args, $shortcode_atts ) {
		$input_html = $this->get_field_input_html_hook( $this->field );
		$this->add_aria_description( $args, $input_html );
		if ( is_callable( array( $this, 'add_min_max' ) ) ) {
			$this->add_min_max( $args, $input_html );
		}

		$default = $this->get_field_column( 'default_value' );
		if ( is_object( $this->field ) ) {
			$field = $this->field;
		} else {
			$field = FrmField::getOne( $this->field_id );
		}
		$default = apply_filters( 'frm_get_default_value', $default, $field, true );

		$output = $this->output_selected_value( $default );
		$output = apply_filters( 'frm_range_output', $output, array( 'field' => $this->field ) );

		$input  = '<div class="frm_range_container">';

		$value_position       = $this->get_value_position();
		$show_value_at_bottom = strpos( $value_position, 'bottom' ) !== false;
		$output               = '<div>' . $output . '</div>';
		$output               = $this->add_range_value_position_class( $output );
		if ( ! $show_value_at_bottom ) {
			$input .= $output;
		}

		$this->adjust_value_if_field_is_hidden( $field );

		$frmval = '' === $this->field['default_value'] ? 'data-frmval=""' : '';
		$input .= '<input type="range" id="' . esc_attr( $args['html_id'] ) . '" name="' . esc_attr( $args['field_name'] ) . '" value="' . esc_attr( $this->field['value'] ) . '" ' . $frmval . ' data-frmrange ' . $input_html . '/>';
		$input .= $this->output_min_max_value();
		if ( $show_value_at_bottom ) {
			$input .= $output;
		}
		$input .= '</div>';

		return $input;
	}

	/**
	 * @since 6.20
	 *
	 * @param string $output
	 * @return string
	 */
	private function add_range_value_position_class( $output ) {
		$value_position = $this->get_value_position();
		$class          = 'frm-text-';
		$class         .= $value_position ? str_replace( array( 'top-', 'bottom-' ), '', $value_position ) : 'left';
		$output         = str_replace( '<div', '<div class="' . esc_attr( $class ) . '"', $output );
		return $output;
	}

	/**
	 * If a slider is conditional, the calculated value should be 0.
	 * When the field is conditionally shown its default value will be restored.
	 *
	 * @param object $field
	 */
	private function adjust_value_if_field_is_hidden( $field ) {
		// phpcs:ignore
		if ( empty( $_POST ) ) {
			return;
		}
		// phpcs:ignore
		$values = wp_unslash( $_POST );
		if ( FrmProFieldsHelper::is_field_hidden( $field, $values ) ) {
			$this->field['value']             = 0;
			$_POST['item_meta'][ $field->id ] = 0;
		}
	}

	/**
	 * @since 4.03.05
	 *
	 * @param bool $is_builder
	 * @return string
	 */
	private function output_selected_value( $default, $is_builder = false ) {
		$value = FrmField::get_option( $this->field, 'value' );

		$starting_value = '' === $value || false === $value ? $default : $value;
		$starting_value = $this->get_mid_value( $starting_value );

		$is_currency = ! empty( $this->field->field_options['is_currency'] )
			|| isset( $this->field->field_options['format'] ) && FrmProCurrencyHelper::is_currency_format( $this->field->field_options['format'] );

		if ( $is_currency ) {
			$starting_value = FrmProCurrencyHelper::maybe_format_currency( $starting_value, $this->field, array() );
		}

		$num  = '<span class="frm_range_value">' . esc_html( $starting_value ) . '</span>';
		$pre  = $this->format_unit( 'prepend', $is_builder );
		$unit = $this->format_unit( 'append', $is_builder );

		return $pre . $num . $unit;
	}

	/**
	 * Get the middle value so the label isn't alone.
	 *
	 * @since 4.06
	 */
	private function get_mid_value( $value ) {
		if ( $value !== '' && $value !== false ) {
			return $value;
		}

		$defaults = $this->extra_field_opts();
		$min      = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, FrmField::get_option( $this->field, 'minnum' ) );
		$max      = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, FrmField::get_option( $this->field, 'maxnum' ) );

		if ( ! is_numeric( $min ) ) {
			$min = $defaults['minnum'];
		}

		if ( ! is_numeric( $max ) ) {
			$max = $defaults['maxnum'];
		}

		$mid = ( $max - $min ) / 2 + $min;

		if ( is_int( $mid ) ) {
			return $mid;
		}

		$step = FrmField::get_option( $this->field, 'step' );
		if ( ! $step || ! is_numeric( $step ) ) {
			// Avoid division by zero or division by non-numeric string.
			$step = $defaults['step'];
		}

		return round( $mid / $step ) * $step;
	}

	/**
	 * Ranges will show the min and max values under the input when a "Before Input" or "After Input" value is set.
	 *
	 * @since 4.05
	 *
	 * @param bool $is_builder
	 * @return string
	 */
	private function output_min_max_value( $is_builder = false ) {
		$pre  = $this->format_unit( 'prepend', $is_builder );
		$unit = $this->format_unit( 'append', $is_builder );

		$show_slider_setting = $this->get_slider_checked_value();

		if ( ! $show_slider_setting ) {
			return '';
		}

		$min = FrmField::get_option( $this->field, 'minnum' );
		$max = FrmField::get_option( $this->field, 'maxnum' );

		if ( FrmField::get_option( $this->field, 'is_currency' ) || FrmProCurrencyHelper::is_currency_format( FrmField::get_option( $this->field, 'format' ) ) ) {
			$min = FrmProCurrencyHelper::maybe_format_currency( $min, $this->field, array() );
			$max = FrmProCurrencyHelper::maybe_format_currency( $max, $this->field, array() );
		}

		$min     = $pre . esc_html( $min ) . $unit;
		$max     = $pre . esc_html( $max ) . $unit;
		$output  = '<div class="frm_description">';
		$output .= '<span class="frm_range_min">' . $min . '</span>';
		$output .= '<span class="frm_range_max">' . $max . '</span>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * @since 4.05
	 *
	 * @param string $setting
	 * @param bool   $is_builder
	 * @return string
	 */
	private function format_unit( $setting, $is_builder = false ) {
		$unit   = FrmField::get_option( $this->field, $setting );
		$output = '';

		if ( ! empty( $unit ) ) {
			$output = '<span class="frm_range_unit"' . ( $is_builder ? ' id="range_unit_' . esc_attr( $this->get_field_column( 'id' ) ) . '"' : '' ) . '>' . esc_html( $unit ) . '</span>';
		}
		return $output;
	}

	/**
	 * @since 4.0.04
	 */
	public function sanitize_value( &$value ) {
		FrmAppHelper::sanitize_value( 'sanitize_text_field', $value );
	}
}
