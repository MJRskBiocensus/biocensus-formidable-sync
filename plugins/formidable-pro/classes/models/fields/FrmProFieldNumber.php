<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 3.0
 */
class FrmProFieldNumber extends FrmFieldNumber {
	use FrmProFieldAutocompleteField;
	use FrmProFieldTypeTrait;

	/**
	 * @var string The input type for the HTML input element.
	 */
	protected $input_type;

	protected function field_settings_for_type() {
		$settings = parent::field_settings_for_type();

		$settings['autopopulate'] = true;
		$settings['calc']         = true;
		$settings['unique']       = true;
		$settings['read_only']    = true;
		$settings['prefix']       = true;
		$settings['autocomplete'] = true;
		$settings['format']       = true;

		FrmProFieldsHelper::fill_default_field_display( $settings );
		return $settings;
	}

	/**
	 * @return array
	 */
	protected function get_filter_keys() {
		return array( 'on', 'off', 'bday-day', 'bday-year', 'postal-code', 'transaction-amount', 'tel-extension' );
	}

	/**
	 * @since 4.05
	 */
	protected function builder_text_field( $name = '' ) {
		$html  = FrmProFieldsHelper::builder_page_prepend( $this->field );
		$field = parent::builder_text_field( $name );
		return str_replace( '[input]', $field, $html );
	}

	/**
	 * @since 6.18
	 *
	 * {@inheritdoc}
	 */
	protected function get_input_class() {
		$class = '';

		if ( 'text' === $this->html5_input_type() ) {
			$class .= ' frm_field_number';
		}

		return $class;
	}

	/**
	 * @since 6.18
	 *
	 * {@inheritdoc}
	 */
	public function validate( $args ) {
		if ( 'number' === $this->html5_input_type() ) {
			return parent::validate( $args );
		}

		$errors = array();

		if ( $args['value'] === '' ) {
			return $errors;
		}

		$value  = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, $args['value'] );
		$minnum = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, FrmField::get_option( $this->field, 'minnum' ) );
		$maxnum = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, FrmField::get_option( $this->field, 'maxnum' ) );

		if ( $minnum !== '' && $value < $minnum ) {
			$errors[ 'field' . $args['id'] ] = __( 'Please select a higher number', 'formidable' );
		} elseif ( $maxnum !== '' && $value > $maxnum ) {
			$errors[ 'field' . $args['id'] ] = __( 'Please select a lower number', 'formidable' );
		}

		if ( is_callable( parent::class . '::validate_step' ) ) {
			$this->validate_step( $errors, $args );
		}

		return $errors;
	}

	/**
	 * @since 6.18
	 *
	 * {@inheritdoc}
	 */
	protected function check_value_is_valid_with_step( $value, $step ) {
		if ( 'number' === $this->html5_input_type() && is_callable( parent::class . '::check_value_is_valid_with_step' ) ) {
			return parent::check_value_is_valid_with_step( $value, $step );
		}

		$value             = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, $value );
		$decimal_separator = FrmField::get_option( $this->field, 'custom_decimal_separator' );

		if ( '.' !== $decimal_separator ) {
			$value = str_replace( $decimal_separator, '.', $value );
		}

		// Count the number of decimals.
		$decimals = max( FrmAppHelper::count_decimals( $value ), FrmAppHelper::count_decimals( $step ) );

		// Convert value and step to int to prevent precision problem.
		$pow   = pow( 10, $decimals );
		$value = intval( $pow * $value );
		$step  = intval( $pow * $step );
		$div   = $value / $step;
		if ( is_int( $div ) ) {
			return 0;
		}

		$div = floor( $div );

		$first_nearest  = $div * $step / $pow;
		$second_nearest = ( $div + 1 ) * $step / $pow;

		if ( '.' !== $decimal_separator ) {
			$first_nearest  = str_replace( '.', $decimal_separator, $first_nearest );
			$second_nearest = str_replace( '.', $decimal_separator, $second_nearest );
		}

		return array( $first_nearest, $second_nearest );
	}

	/**
	 * @since 6.20
	 *
	 * {@inheritdoc}
	 */
	protected function add_min_max( $args, &$input_html ) {
		$this->add_formatted_min_max( $args, $input_html );
	}

	/**
	 * @since 6.18
	 *
	 * {@inheritdoc}
	 */
	public function front_field_input( $args, $shortcode_atts ) {
		$input_html = $this->get_field_input_html_hook( $this->field );
		$this->add_aria_description( $args, $input_html );
		$this->add_extra_html_atts( $args, $input_html );

		if ( 'text' === $this->html5_input_type() ) {
			$this->field['value'] = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, $this->field['value'] );
		}

		$attributes = array(
			'type'  => $this->html5_input_type(),
			'id'    => $args['html_id'],
			'name'  => $args['field_name'],
			'value' => $this->prepare_esc_value(),
		);

		return '<input' . FrmAppHelper::array_to_html_params( $attributes ) . $input_html . ' />';
	}

	/**
	 * @since 6.18
	 *
	 * {@inheritdoc}
	 */
	protected function html5_input_type() {
		if ( $this->input_type ) {
			return $this->input_type;
		}

		$this->input_type =
			FrmProCurrencyHelper::is_currency_format( FrmField::get_option( $this->field, 'format' ) )
			&& ! FrmField::get_option( $this->field, 'calc' )
				? 'text' : 'number';

		return $this->input_type;
	}

	protected function prepare_display_value( $value, $atts ) {

		$new_val = array();
		$value   = array_filter(
			(array) $value,
			/**
			 * @param string $string
			 * @return bool
			 */
			function ( $string ) {
				return strlen( $string ) > 0;
			}
		);

		foreach ( $value as $v ) {
			if ( strpos( $v, $atts['sep'] ) ) {
				$v = explode( $atts['sep'], $v );
			}

			foreach ( (array) $v as $n ) {
				if ( ! isset( $atts['decimal'] ) ) {
					$num             = explode( '.', $n );
					$atts['decimal'] = isset( $num[1] ) ? strlen( $num[1] ) : 0;
				}

				if ( is_numeric( $n ) ) {
					$n = $this->number_format_and_maintain_leading_zeroes( $n, $atts );
				}

				$new_val[] = $n;
			}

			unset( $v );
		}
		$new_val = array_filter(
			$new_val,
			/**
			 * @param string $string
			 * @return bool
			 */
			function ( $string ) {
				return strlen( $string ) > 0;
			}
		);

		return implode( $atts['sep'], $new_val );
	}

	/**
	 * Filter value through number_format a value, but maintain any leading zeros as well
	 *
	 * @param string $number
	 * @param array $atts
	 * @return string
	 */
	private function number_format_and_maintain_leading_zeroes( $number, $atts ) {
		$number_of_leading_zeroes = $this->count_number_of_leading_zeroes( $number );
		$number_formatted_value   = number_format( $number, $atts['decimal'], $atts['dec_point'], $atts['thousands_sep'] );
		return str_repeat( '0', $number_of_leading_zeroes ) . $number_formatted_value;
	}

	/**
	 * @param string $number
	 * @return int
	 */
	private function count_number_of_leading_zeroes( $number ) {
		if ( '' === $number ) {
			return 0;
		}

		$split          = explode( '.', $number );
		$leading_length = $split ? strlen( $split[0] ) : 0;

		if ( ! $leading_length ) {
			return 0;
		}

		$total_length    = strlen( $number );
		$trailing_length = 2 === count( $split ) ? strlen( $split[1] ) + 1 : 0;
		$max_length      = $total_length - $trailing_length;
		$index           = 0;

		while ( $index < $max_length && '0' === $number[ $index ] ) {
			++$index;
		}

		if ( $index === $max_length ) {
			return $max_length - 1;
		}

		return $index;
	}

	protected function fill_default_atts( &$atts ) {
		$defaults = array(
			'dec_point'     => '.',
			'thousands_sep' => '',
			'sep'           => ', ',
		);
		$atts     = wp_parse_args( $atts, $defaults );
	}

	protected function prepare_import_value( $value, $atts ) {
		if ( is_numeric( $value ) ) {
			$value = (string) $value;
		}
		return $value;
	}

	/**
	 * @since 6.18
	 *
	 * {@inheritdoc}
	 */
	public function set_value_before_save( $value ) {
		$input_type = $this->html5_input_type();
		if ( 'number' === $input_type ) {
			if ( ! is_numeric( $value ) ) {
				$value = is_scalar( $value ) ? (float) $value : 0;
			}
		} elseif ( 'text' === $input_type ) {
			$value = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, $value );
		}

		return $value;
	}
}
