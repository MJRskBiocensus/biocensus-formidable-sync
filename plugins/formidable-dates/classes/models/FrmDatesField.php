<?php
class FrmDatesField extends FrmProFieldDate {

	public static function get_field_type_class( $classname, $field_type ) {
		if ( 'date' == $field_type ) {
			$classname = __CLASS__;
		}

		return $classname;
	}

	/**
	 * Unset field value if it's a disabled value.
	 *
	 * @param array $field
	 * @return array
	 */
	public static function remove_default_if_disabled( $field ) {
		if ( 'date' !== $field['type'] || empty( $field['value'] ) ) {
			return $field;
		}

		$default_date = self::convert_date_from_settings_format_to_db( $field['value'] );
		if ( ! $default_date ) {
			return $field;
		}

		if ( ! empty( $field['excepted_dates'] ) && in_array( $default_date, $field['excepted_dates'], true ) ) {
			return $field;
		}

		if ( ! empty( $field['blackout_dates'] ) && in_array( $default_date, $field['blackout_dates'], true ) ) {
			$field['value'] = '';
			return $field;
		}

		if ( ! self::all_days_of_the_week_are_allowed( $field ) && ! in_array( (int) gmdate( 'w', strtotime( $default_date ) ), $field['days_of_the_week'], true ) ) {
			$field['value'] = '';
			return $field;
		}

		$min_date = FrmDatesAppHelper::get_field_min_max_date( $field, 'minimum' );
		$max_date = FrmDatesAppHelper::get_field_min_max_date( $field, 'maximum' );

		if ( ( $min_date && ( $min_date > $default_date ) ) || ( $max_date && $max_date < $default_date ) ) {
			$field['value'] = '';
		}

		return $field;
	}

	/**
	 * Check if any days of the week are disabled.
	 *
	 * @since 1.05
	 *
	 * @param stdClass|array $field
	 * @return bool
	 */
	private static function all_days_of_the_week_are_allowed( $field ) {
		if ( is_object( $field ) ) {
			return empty( $field->field_options['days_of_the_week'] ) || 7 === count( $field->field_options['days_of_the_week'] );
		}

		return empty( $field['days_of_the_week'] ) || 7 === count( $field['days_of_the_week'] );
	}

	public function __construct( $field = '', $type = '' ) {
		parent::__construct( $field, $type );

		if ( ! FrmDatesCalculationHelper::is_formidable_supported() ) {
			return;
		}

		if ( is_array( $this->field ) && ! empty( $this->field['date_calc'] ) ) {
			$this->convert_date_calc_to_calc( $this->field );
		} elseif ( is_object( $this->field ) && ! empty( $this->field->field_options['date_calc'] ) ) {
			$this->convert_date_calc_to_calc( $this->field->field_options );
		}
	}

	/**
	 * Converts date calculation settings to field calculation to reuse the calculation code.
	 *
	 * @since 2.0
	 *
	 * @param array $field_array Field array or field options array.
	 * @return void
	 */
	private function convert_date_calc_to_calc( &$field_array ) {
		$field_array['calc_type'] = 'date';
		$field_array['calc']      = trim( $field_array['date_calc'] );

		if ( false === strpos( $field_array['calc'], '[' ) ) {
			// Convert fixed date from Y-m-d to datepicker date format.
			$field_array['calc'] = FrmProAppHelper::maybe_convert_from_db_date( $field_array['calc'] );
		}

		if ( ! empty( $field_array['date_calc_diff'] ) ) {
			$field_array['calc'] .= '+';
			$field_array['calc'] .= trim( $field_array['date_calc_diff'] );
		}
	}

	protected function field_settings_for_type() {
		$settings              = parent::field_settings_for_type();
		$settings['date_calc'] = true;
		return $settings;
	}

	/**
	 * Validates a date field.
	 *
	 * @param array $args Field values.
	 *
	 * @return array.
	 */
	public function validate( $args ) {
		$errors = parent::validate( $args );
		if ( $errors ) {
			return $errors;
		}

		$errors = isset( $args['errors'] ) ? $args['errors'] : array();

		if ( empty( $args['value'] ) || ! empty( $errors[ 'field' . $args['id'] ] ) ) {
			return $errors;
		}

		if ( date_create_from_format( 'Y-m-d', $args['value'] ) ) {
			$entry_date = $args['value'];
		} else {
			$entry_date = self::convert_date_from_settings_format_to_db( $args['value'] );
		}

		if ( ! empty( $this->field->field_options['excepted_dates'] ) && in_array( $entry_date, $this->field->field_options['excepted_dates'], true ) ) {
			return $errors;
		}

		if ( ! empty( $this->field->field_options['blackout_dates'] ) && in_array( $entry_date, $this->field->field_options['blackout_dates'], true ) ) {
			$errors[ 'field' . $args['id'] ] = __( 'The date selected is not allowed. Please select another day.', 'frmdates' );
			return $errors;
		}

		$min_date = FrmDatesAppHelper::get_field_min_max_date( $this->field->field_options, 'minimum' );
		$max_date = FrmDatesAppHelper::get_field_min_max_date( $this->field->field_options, 'maximum' );

		if ( $min_date && ( $min_date > $entry_date ) ) {
			/* translators: %s: Minimum date */
			$errors[ 'field' . $args['id'] ] = sprintf( __( 'Date cannot be before %s', 'frmdates' ), date_i18n( 'F j, Y', strtotime( $min_date ) ) );
			return $errors;
		}

		if ( $max_date && ( $max_date < $entry_date ) ) {
			/* translators: %s: Maximum date */
			$errors[ 'field' . $args['id'] ] = sprintf( __( 'Date cannot be after %s', 'frmdates' ), date_i18n( 'F j, Y', strtotime( $max_date ) ) );
			return $errors;
		}

		if ( ! self::all_days_of_the_week_are_allowed( $this->field ) && ! in_array( (int) gmdate( 'w', strtotime( $entry_date ) ), $this->field->field_options['days_of_the_week'], true ) ) {
			$errors[ 'field' . $args['id'] ] = __( 'The date selected is not allowed. Please select another day.', 'frmdates' );
			return $errors;
		}

		return $errors;
	}

	public static function convert_date_from_settings_format_to_db( $date ) {
		$frmpro_settings = FrmProAppHelper::get_settings();
		$date_format     = $frmpro_settings->date_format;
		return FrmProAppHelper::convert_date( $date, $date_format, 'Y-m-d' );
	}

	public static function sanitize_field_options( $values ) {
		if ( ! empty( $values['field_options']['days_of_the_week'] ) ) {
			$values['field_options']['days_of_the_week'] = array_unique( array_map( 'absint', $values['field_options']['days_of_the_week'] ) );
		} else {
			$values['field_options']['days_of_the_week'] = array( 0, 1, 2, 3, 4, 5, 6 );
		}

		if ( ! empty( $values['field_options']['blackout_dates'] ) ) {
			asort( $values['field_options']['blackout_dates'] );
		}

		if ( 7 === count( $values['field_options']['days_of_the_week'] ) ) {
			$values['field_options']['excepted_dates'] = array();
		} elseif ( ! empty( $values['field_options']['excepted_dates'] ) ) {
			asort( $values['field_options']['excepted_dates'] );
		}

		// Validate threshold conditions and values.
		foreach ( array( 'minimum_date', 'maximum_date' ) as $date_type ) {
			if ( empty( $values['field_options'][ $date_type . '_cond' ] ) ) {
				continue;
			}

			$condition = $values['field_options'][ $date_type . '_cond' ];
			$value     = isset( $values['field_options'][ $date_type . '_val' ] ) ? $values['field_options'][ $date_type . '_val' ] : '';

			if ( ! in_array( $condition, array( '', 'date', 'today' ) ) && substr( $condition, 0, 6 ) != 'field_' ) {
				$values['field_options'][ $date_type . '_cond' ] = '';
				$values['field_options'][ $date_type . '_val' ]  = '';
			}
		}

		return $values;
	}

	public static function field_has_custom_opts( $field ) {
		$display_inline    = FrmField::get_option( $field, 'display_inline' );
		$days              = FrmField::get_option( $field, 'days_of_the_week' );
		$blackout_dates    = FrmField::get_option( $field, 'blackout_dates' );
		$minimum_date_cond = FrmField::get_option( $field, 'minimum_date_cond' );
		$maximum_date_cond = FrmField::get_option( $field, 'maximum_date_cond' );

		$custom_days = is_array( $days ) && ! empty( $days ) && 7 > count( $days );

		return ( $custom_days || $display_inline || $blackout_dates || $minimum_date_cond || $maximum_date_cond );
	}

	public function extra_field_opts() {
		$new_options = array(
			'days_of_the_week'  => array( 0, 1, 2, 3, 4, 5, 6 ),
			'blackout_dates'    => array(),
			'excepted_dates'    => array(),
			'display_inline'    => false,
			'minimum_date_cond' => '',
			'minimum_date_val'  => '',
			'maximum_date_cond' => '',
			'maximum_date_val'  => '',
			'date_calc'         => '',
			'date_calc_diff'    => '',
		);

		return array_merge( parent::extra_field_opts(), $new_options );
	}

	public function front_field_input( $args, $shortcode_atts ) {
		FrmDatesAppController::enqueue_scripts();

		if ( ! self::field_has_custom_opts( $this->field ) ) {
			return parent::front_field_input( $args, $shortcode_atts );
		}

		$display_inline = FrmField::get_option( $this->field, 'display_inline' );
		if ( $display_inline ) {
			if ( isset( $this->field['original_default'] ) ) {
				$shortcode_atts = shortcode_parse_atts( trim( $this->field['original_default'], '[]' ) );
				if ( isset( $shortcode_atts['format'] ) ) {
					$from_format = $shortcode_atts['format'];
				}
			}
			if ( empty( $from_format ) ) {
				$from_format = 'db';
			}

			$date = self::maybe_convert_date_to_mysql_format( $this->field['value'], $from_format );

			$html = '<input type="hidden" name="' . esc_attr( $args['field_name'] ) . '" value="' . esc_attr( $date ) . '" id="' . esc_attr( $args['html_id'] ) . '_alt" ';
			$html .= FrmFieldsController::input_html( $this->field, false );
			$html .= ' />';
			$html .= '<div id="' . esc_attr( $args['html_id'] ) . '" class="frm_date_inline"></div>';
		} else {
			$html = parent::front_field_input( $args, $shortcode_atts );
		}

		return $html;
	}

	/**
	 * Convert a date to Y-m-d if it is not already.
	 *
	 * @param string $date
	 * @param string $from_format
	 *
	 * @return string
	 */
	public static function maybe_convert_date_to_mysql_format( $date, $from_format ) {
		if ( date_create_from_format( 'Y-m-d', $date ) ) {
			// if date is already in Y-m-d format, return it.
			return $date;
		}

		return FrmProAppHelper::convert_date( $date, $from_format, 'Y-m-d' );
	}

	/**
	 * Loads field scripts.
	 *
	 * @since 2.0
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	protected function load_field_scripts( $args ) {
		if ( ! FrmField::is_read_only( $this->field ) || ! empty( $this->field['date_calc'] ) ) {
			global $frm_vars;
			if ( ! isset( $frm_vars['datepicker_loaded'] ) || ! is_array( $frm_vars['datepicker_loaded'] ) ) {
				$frm_vars['datepicker_loaded'] = array();
			}

			if ( ! isset( $frm_vars['datepicker_loaded'][ $args['html_id'] ] ) ) {
				$static_html_id = $this->html_id();
				if ( $args['html_id'] != $static_html_id ) {
					// User wildcard for repeating fields.
					$frm_vars['datepicker_loaded'][ '^' . $static_html_id ] = true;
				} else {
					$frm_vars['datepicker_loaded'][ $args['html_id'] ] = true;
				}
			}

			$entry_id = isset( $frm_vars['editing_entry'] ) ? $frm_vars['editing_entry'] : 0;
			FrmProFieldsHelper::set_field_js( $this->field, $entry_id );
		}
	}
}
