<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmProCurrencyHelper {

	/**
	 * @since 4.04
	 */
	public static function get_currency( $form ) {
		$settings      = FrmProAppHelper::get_settings();
		$currency_code = trim( $settings->currency );

		if ( ! $currency_code ) {
			$currency_code = 'USD';
		}

		$currency = self::get_currencies( $currency_code );

		if ( $settings->use_custom_currency_format ) {
			$currency = wp_parse_args(
				array(
					'thousand_separator' => $settings->thousand_separator,
					'decimal_separator'  => $settings->decimal_separator,
					'decimals'           => $settings->decimals,
				),
				$currency
			);
		}

		/**
		 * Allow custom code to change the currency for different currencies per form.
		 *
		 * @since 4.04
		 * @param array      $currency  The currency information.
		 * @param int|object $form      The ID of the form or the form object.
		 */
		$currency = apply_filters( 'frm_currency', $currency, $form );

		return $currency;
	}

	/**
	 * If the currency is needed for this form, add it to the global.
	 * This is later included in the footer.
	 *
	 * @since 4.04
	 */
	public static function add_currency_to_global( $form_id ) {
		global $frm_vars;
		if ( ! isset( $frm_vars['currency'] ) ) {
			$frm_vars['currency'] = array();
		}

		if ( ! isset( $frm_vars['currency'][ $form_id ] ) ) {
			$frm_vars['currency'][ $form_id ] = self::normalize_decimal_separators( self::get_currency( $form_id ) );
		}
	}

	/**
	 * Avoid blank decimal separators causing calculated values to be multiplied by 100.
	 *
	 * @since 5.0.16
	 *
	 * @param array $currency
	 * @return array
	 */
	private static function normalize_decimal_separators( $currency ) {
		$currency['decimal_separator'] = trim( $currency['decimal_separator'] );
		if ( ! $currency['decimal_separator'] && 0 === (int) $currency['decimals'] ) {
			$currency['decimal_separator'] = '.';
		}
		return $currency;
	}

	/**
	 * This function is triggered on the frm_display_value hook
	 * which is run any time the value is displayed.
	 *
	 * @since 4.06.01
	 *
	 * @param array|string $value
	 * @param array|object $field
	 * @param array $atts
	 *
	 * @return string
	 */
	public static function maybe_format_currency( $value, $field, $atts ) {
		if ( is_array( $value ) || $value === '' || ! preg_match( '/\d/', $value ) ) {
			return $value;
		}

		$format      = isset( $atts['format'] ) ? $atts['format'] : '';
		$is_currency = FrmField::get_option( $field, 'is_currency' ) || self::is_currency_format( FrmField::get_option( $field, 'format' ) ) || 'currency' === $format;

		if ( ! $is_currency || $format === 'number' ) {
			return $value;
		}

		$form_id  = is_object( $field ) ? $field->form_id : $field['form_id'];
		$currency = self::get_currency_for_field( $field );
		$currency = self::apply_shortcode_atts( $currency, $atts );
		$words    = explode( ' ', $value );

		if ( count( $words ) === 1 ) {
			return self::format_amount_for_currency( $form_id, $value, $currency );
		}

		foreach ( $words as &$word ) {
			if ( preg_match( '/^\d+(\.\d+)?$/', $word ) ) {
				$word = self::format_amount_for_currency( $form_id, $word, $currency );
			}
		}

		return implode( ' ', $words );
	}

	/**
	 * Maybe overwrite currency settings with shortcode attributes.
	 *
	 * @since 6.22.1
	 *
	 * @param array $currency
	 * @param array $atts
	 * @return array
	 */
	public static function apply_shortcode_atts( $currency, $atts ) {
		// Left side represents the shortcode option key.
		// Right side represents the currency key.
		$map = array(
			'decimal'       => 'decimals',
			'dec_point'     => 'decimal_separator',
			'thousands_sep' => 'thousand_separator',
			'symbol_left'   => 'symbol_left',
			'symbol_right'  => 'symbol_right',
		);

		$args = array();
		foreach ( $map as $key => $value ) {
			if ( isset( $atts[ $key ] ) ) {
				$args[ $value ] = $atts[ $key ];
			}
		}

		return wp_parse_args( $args, $currency );
	}

	/**
	 * Maybe get the custom currency for a field.
	 *
	 * @since 5.5.4 This was moved from self::maybe_format_currency.
	 *
	 * @param array|object $field The field data.
	 * @return array|null An array is only returned for a custom currency.
	 */
	private static function get_currency_for_field( $field ) {
		if ( ! FrmField::get_option( $field, 'custom_currency' ) && ! self::is_currency_format( FrmField::get_option( $field, 'format' ) ) ) {
			// There is a is_null check in self::format_amount_for_currency that will call self::get_currency to resolve this.
			return null;
		}

		return self::get_custom_currency( is_object( $field ) ? $field->field_options : $field );
	}

	/**
	 * @since 5.0.16
	 *
	 * @param array $field_options
	 * @return array
	 */
	public static function get_custom_currency( $field_options ) {
		if (
			'currency' === FrmField::get_option( $field_options, 'format' ) &&
			'1' === FrmField::get_option( $field_options, 'use_global_currency' ) &&
			! FrmField::get_option( $field_options, 'custom_currency' )
		) {
			return self::get_global_currency( $field_options );
		}

		$defaults       = FrmProFieldsHelper::get_default_field_opts();
		$decimal_option = self::get_decimal_setting_key( $field_options );

		// There is no setting for custom symbol padding, so use the currency setting.
		$currency = self::get_currency( FrmField::get_option( $field_options, 'form_id' ) );

		return array(
			'thousand_separator' => isset( $field_options['custom_thousand_separator'] ) ? $field_options['custom_thousand_separator'] : $defaults['custom_thousand_separator'],
			'decimal_separator'  => isset( $field_options['custom_decimal_separator'] ) ? $field_options['custom_decimal_separator'] : $defaults['custom_decimal_separator'],
			'decimals'           => (int) ( isset( $field_options[ $decimal_option ] ) ? $field_options[ $decimal_option ] : $defaults['custom_decimals'] ),
			'symbol_left'        => isset( $field_options['custom_symbol_left'] ) ? $field_options['custom_symbol_left'] : $defaults['custom_symbol_left'],
			'symbol_right'       => isset( $field_options['custom_symbol_right'] ) ? $field_options['custom_symbol_right'] : $defaults['custom_symbol_right'],
			'symbol_padding'     => $currency['symbol_padding'] ?? '',
		);
	}

	/**
	 * Get currency settings based on global usage.
	 *
	 * @since 6.19
	 *
	 * @param array $field_options Field options.
	 * @return array Currency settings.
	 */
	public static function get_global_currency( $field_options ) {
		$currency                 = self::get_currency( FrmField::get_option( $field_options, 'form_id' ) );
		$currency['symbol_left']  = html_entity_decode( $currency['symbol_left'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$currency['symbol_right'] = html_entity_decode( $currency['symbol_right'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return wp_array_slice_assoc(
			$currency,
			array( 'thousand_separator', 'decimal_separator', 'decimals', 'symbol_left', 'symbol_right', 'symbol_padding' )
		);
	}

	/**
	 * @since 4.04
	 *
	 * @param int|object   $form   Form object or ID.
	 * @param float|string $amount The string could contain the currency symbol.
	 * @param array|null   $currency
	 * @return float|string
	 */
	public static function format_amount_for_currency( $form = null, $amount = 0, $currency = null ) {
		if ( null === $form ) {
			return $amount;
		}

		if ( is_null( $currency ) ) {
			$currency = self::get_currency( $form );
		}

		if ( is_string( $amount ) ) {
			$amount = floatval( self::prepare_price( $amount, $currency ) );
		}

		$amount = number_format( $amount, $currency['decimals'], $currency['decimal_separator'], $currency['thousand_separator'] );

		if ( '' !== $currency['symbol_left'] ) {
			$amount = $currency['symbol_left'] . $currency['symbol_padding'] . $amount;
		}

		if ( '' !== $currency['symbol_right'] ) {
			$amount .= $currency['symbol_padding'] . $currency['symbol_right'];
		}

		return $amount;
	}

	/**
	 * @since 4.04
	 */
	public static function prepare_price( $price, $currency ) {
		$price = trim( $price );
		if ( ! $price ) {
			return 0;
		}

		preg_match_all( '/[\-]*[0-9,.]*\.?\,?[0-9]+/', $price, $matches );
		$price = $matches ? end( $matches[0] ) : 0;
		if ( $price ) {
			$price = self::maybe_use_decimal( $price, $currency );
			$price = str_replace( $currency['decimal_separator'], '.', str_replace( $currency['thousand_separator'], '', $price ) );
		}

		return $price;
	}

	/**
	 * @since 4.04
	 */
	private static function maybe_use_decimal( $amount, $currency ) {
		if ( $currency['thousand_separator'] === '.' ) {
			$amount_parts     = explode( '.', $amount );
			$used_for_decimal = count( $amount_parts ) === 2 && in_array( strlen( $amount_parts[1] ), array( 1, 2 ), true );
			if ( $used_for_decimal ) {
				$amount = str_replace( '.', $currency['decimal_separator'], $amount );
			}
		}
		return $amount;
	}

	/**
	 * Checks if the given format is a valid currency format.
	 *
	 * @since 6.18
	 *
	 * @param string $format_value The format value to check.
	 * @return bool
	 */
	public static function is_currency_format( $format_value ) {
		return is_callable( 'FrmCurrencyHelper::is_currency_format' ) ? FrmCurrencyHelper::is_currency_format( $format_value ) : false;
	}

	/**
	 * @since 4.04
	 */
	public static function get_currencies( $currency = false ) {
		$currencies = is_callable( 'FrmCurrencyHelper::get_currencies' ) ? FrmCurrencyHelper::get_currencies() : array();

		if ( $currency ) {
			$currency = strtoupper( $currency );
			if ( isset( $currencies[ $currency ] ) ) {
				$currencies = $currencies[ $currency ];
			}
		}

		return $currencies;
	}

	/**
	 * Normalizes formatted numbers in a string based on format settings.
	 *
	 * @since 6.18
	 *
	 * @param array|object $field           The field settings containing custom formatting options.
	 * @param string       $formatted_value The input string containing numbers and text.
	 * @param array|null   $config          Configuration settings for number formatting.
	 * @return string The processed string with normalized numbers.
	 */
	public static function normalize_formatted_numbers( $field, $formatted_value, $config = null ) {
		if ( ! $field || ! $formatted_value ) {
			return $formatted_value;
		}

		$config = $config ?? self::get_formatting_config( $field );

		return in_array( FrmField::get_field_type( $field ), array( 'text', 'textarea', 'hidden' ), true )
			? self::unformat_numbers_in_string( $formatted_value, $config )
			: self::unformat_number( $formatted_value, $config );
	}

	/**
	 * Normalizes formatted numbers in an array based on format settings.
	 *
	 * @since 6.20
	 *
	 * @param array|object $field             The field settings containing custom formatting options.
	 * @param array        &$formatted_values Array of formatted number strings to be normalized.
	 * @return void
	 */
	public static function normalize_formatted_number_collection( $field, &$formatted_values ) {
		if ( ! $field || ! is_array( $formatted_values ) ) {
			return;
		}

		$config = self::get_formatting_config( $field );

		foreach ( $formatted_values as &$value ) {
			$value = self::normalize_formatted_numbers( $field, $value, $config );
		}

		unset( $value ); // Break the reference.
	}

	/**
	 * Builds the configuration array for formatting options.
	 *
	 * @since 6.18
	 *
	 * @param array|object $field The field settings containing custom formatting options.
	 * @return array The configuration array with both raw and regex-quoted values.
	 */
	private static function get_formatting_config( $field ) {
		$config             = self::get_custom_currency( is_object( $field ) ? $field->field_options : $field );
		$config['decimals'] = self::get_decimal_setting( $field );

		// Ensure the decimal separator is valid.
		if ( ! is_string( $config['decimal_separator'] ) || $config['decimal_separator'] === '' ) {
			$config['decimal_separator'] = '.';
		}

		// Add regex-ready (quoted) versions to the configuration.
		$config = array_merge(
			$config,
			array(
				'quoted_symbol_left'        => preg_quote( $config['symbol_left'], '/' ),
				'quoted_symbol_right'       => preg_quote( $config['symbol_right'], '/' ),
				'quoted_thousand_separator' => preg_quote( $config['thousand_separator'], '/' ),
				'quoted_decimal_separator'  => preg_quote( $config['decimal_separator'], '/' ),
				'quoted_symbol_padding'     => preg_quote( $config['symbol_padding'], '/' ),
			)
		);

		$config['number_pattern'] = self::build_number_pattern( $config );

		return $config;
	}

	/**
	 * Builds a regex pattern to match custom-formatted numbers using the provided configuration.
	 *
	 * @since 6.18
	 *
	 * @param array $config The configuration array containing formatting options.
	 * @return string The regex pattern for matching numbers.
	 */
	private static function build_number_pattern( $config ) {
		$pattern = '/^';

		// Add left symbol, if provided.
		if ( $config['quoted_symbol_left'] ) {
			$pattern .= '(' . $config['quoted_symbol_left'] . ')?';
			if ( ! empty( $config['symbol_padding'] ) ) {
				$pattern .= '(' . $config['quoted_symbol_padding'] . ')?';
			}
		}

		// Match the integer part with optional thousand separators.
		if ( $config['thousand_separator'] ) {
			if ( $config['thousand_separator'] === ' ' ) {
				// Special handling for space thousand separator
				$pattern .= '\d{1,3}(?:[ ]\d{3})*';
			} else {
				$pattern .= '\d{1,3}(?:' . $config['quoted_thousand_separator'] . '\d{3})*';
			}
		} else {
			$pattern .= '\d+';
		}

		// Append an optional decimal part if decimals are allowed.
		if ( $config['decimals'] > 0 && ! empty( $config['decimal_separator'] ) ) {
			$pattern .= '(?:' . $config['quoted_decimal_separator'] . '\d{1,' . $config['decimals'] . '})?';
		}

		// Add the right symbol, if provided.
		if ( $config['quoted_symbol_right'] ) {
			if ( ! empty( $config['symbol_padding'] ) ) {
				$pattern .= '(' . $config['quoted_symbol_padding'] . ')?';
			}
			$pattern .= '(' . $config['quoted_symbol_right'] . ')?';
		}

		$pattern .= '$/';

		return $pattern;
	}

	/**
	 * Processes the entire formatted string by splitting it into words,
	 * unformatting each word if it represents a formatted number, and
	 * joining them back together.
	 *
	 * @since 6.18
	 *
	 * @param string $formatted_value The input string containing formatted numbers.
	 * @param array  $config          The configuration array containing formatting options.
	 * @return string The processed string with unformatted numbers.
	 */
	private static function unformat_numbers_in_string( $formatted_value, $config ) {
		// Protect space thousand separators before splitting.
		if ( $config['thousand_separator'] === ' ' ) {
			$formatted_value = preg_replace_callback(
				'/(?<!\d)\d{1,3}([ ]\d{3})+(?!\d)/',
				function ( $matches ) {
					return str_replace( ' ', '___TEMP_SPACE___', $matches[0] );
				},
				$formatted_value
			);
		}

		$words = explode( ' ', $formatted_value );

		// Restore spaces in number sequences.
		if ( $config['thousand_separator'] === ' ' ) {
			$words = array_map(
				function ( $word ) {
					return str_replace( '___TEMP_SPACE___', ' ', $word );
				},
				$words
			);
		}

		$words = self::prepare_currency_words( $words, $config );

		// Process each word and unformat it if it represents a number.
		$processed_words = array_map(
			function ( $word ) use ( $config ) {
				return self::unformat_number( $word, $config );
			},
			$words
		);

		return implode( ' ', $processed_words );
	}

	/**
	 * Reconstructs currency values split by space padding in symbols.
	 *
	 * @param array $words Array of exploded words.
	 * @param array $config Currency configuration.
	 * @return array
	 */
	private static function prepare_currency_words( $words, $config ) {
		if ( $config['symbol_padding'] !== ' ' ) {
			return $words;
		}

		$has_symbol_left  = ! empty( $config['symbol_left'] );
		$has_symbol_right = ! empty( $config['symbol_right'] );

		if ( ! $has_symbol_left && ! $has_symbol_right ) {
			return $words;
		}

		// Validate numeric content by checking for digit presence.
		$is_valid_number = function ( $word ) {
			return preg_match( '/\d/', $word );
		};

		$result = array();
		$count  = count( $words );
		$i      = 0;

		while ( $i < $count ) {
			$has_next = $i + 1 < $count;

			$is_left_symbol    = $has_symbol_left && $has_next && $words[ $i ] === $config['symbol_left'];
			$next_is_number    = $has_next && $is_valid_number( $words[ $i + 1 ] );
			$current_is_number = $is_valid_number( $words[ $i ] );
			$is_right_symbol   = $has_symbol_right && $has_next && $words[ $i + 1 ] === $config['symbol_right'];

			// Process left symbol + number pattern with optional right symbol.
			if ( $is_left_symbol && $next_is_number ) {
				// Merge symbol with numeric value.
				$text = $config['symbol_left'] . ' ' . $words[ $i + 1 ];
				$i   += 2;

				$next_available       = $i < $count;
				$next_is_right_symbol = $next_available && $has_symbol_right && $words[ $i ] === $config['symbol_right'];

				if ( $next_is_right_symbol ) {
					$text .= ' ' . $config['symbol_right'];
					$i++;
				}

				$result[] = $text;

				// Process number + right symbol pattern.
			} elseif ( $current_is_number && $is_right_symbol ) {
				// Merge numeric value with symbol.
				$result[] = $words[ $i ] . ' ' . $config['symbol_right'];
				$i       += 2;
			} else {
				// Preserve standalone word.
				$result[] = $words[ $i ];
				$i++;
			}
		}

		return $result;
	}

	/**
	 * Unformats a formatted number string by stripping out formatting characters.
	 *
	 * If the word matches the number pattern, this method removes the currency symbols,
	 * thousand separators, and adjusts the decimal part so that a plain number is returned.
	 *
	 * @since 6.18
	 *
	 * @param string $word   The word to process.
	 * @param array  $config The configuration array containing formatting options and patterns.
	 * @return string The unformatted number if the word represents a formatted number; otherwise, the original word.
	 */
	private static function unformat_number( $word, array $config ) {
		if ( ! preg_match( $config['number_pattern'], $word ) ) {
			return $word;
		}

		$unformatted = $word;

		// Remove currency symbols.
		if ( $config['symbol_left'] ) {
			$unformatted = preg_replace( '/^' . $config['quoted_symbol_left'] . '/', '', $unformatted );
		}
		if ( $config['symbol_right'] ) {
			$unformatted = preg_replace( '/' . $config['quoted_symbol_right'] . '$/', '', $unformatted );
		}

		$unformatted = trim( $unformatted );

		// Remove thousand separators.
		if ( $config['thousand_separator'] ) {
			$unformatted = str_replace( $config['thousand_separator'], '', $unformatted );
		}

		if ( $config['decimals'] === 0 ) {
			// If decimals is 0, remove any decimal part.
			$parts       = explode( ! empty( $config['decimal_separator'] ) ? $config['decimal_separator'] : '.', $unformatted );
			$unformatted = $parts[0];
		} elseif ( ! empty( $config['decimal_separator'] ) ) {
			// Handle the decimal part.
			$parts = explode( $config['decimal_separator'], $unformatted );
			if ( count( $parts ) > 1 ) {
				$integer_part = array_shift( $parts );
				$decimal_part = implode( '', $parts );

				$unformatted  = (int) $decimal_part === 0
					? $integer_part
					: $integer_part . '.' . $decimal_part;
			}
		}

		return (string) $unformatted;
	}

	/**
	 * Currencies only support 0 and 2 decimal places.
	 * Other numbers support other values as well.
	 *
	 * @since 6.18
	 *
	 * @param array|stdClass $field
	 * @return string 'text' or 'select'.
	 */
	public static function get_decimal_setting_type( $field ) {
		if ( self::uses_legacy_decimal_places_calc( $field ) ) {
			return 'text';
		}
		return in_array( FrmField::get_option( $field, 'format' ), array( 'number', 'custom' ), true ) ? 'text' : 'select';
	}

	/**
	 * @since 6.18
	 *
	 * @param array|stdClass $field
	 * @return bool
	 */
	public static function uses_legacy_decimal_places_calc( $field ) {
		$format = FrmField::get_option( $field, 'format' );
		if ( '' !== $format ) {
			return false;
		}

		$calc_type = FrmField::get_option( $field, 'calc_type' );
		if ( $calc_type ) {
			return false;
		}

		$calc = FrmField::get_option( $field, 'calc' );
		if ( ! $calc && '0' !== $calc ) {
			return false;
		}

		$is_currency = FrmField::get_option( $field, 'is_currency' );
		if ( $is_currency ) {
			return false;
		}

		return is_numeric( FrmField::get_option( $field, 'calc_dec' ) );
	}

	/**
	 * Get the settings key to use for the decimal setting.
	 *
	 * @since 6.18
	 *
	 * @param array|stdClass $field
	 * @return string 'calc_dec' or 'custom_decimals'.
	 */
	private static function get_decimal_setting_key( $field ) {
		$decimal_type = self::get_decimal_setting_type( $field );
		return 'text' === $decimal_type ? 'calc_dec' : 'custom_decimals';
	}

	/**
	 * Get the number of decimals to use for formatting.
	 * This may be one of two settings (calc_dec or custom_decimals), based on the format setting.
	 * This is because currency only supports 0 or 2 decimal places, and for backward compatibility.
	 *
	 * @since 6.18
	 *
	 * @param array|stdClass $field
	 * @return int
	 */
	private static function get_decimal_setting( $field ) {
		$key = self::get_decimal_setting_key( $field );
		return (int) FrmField::get_option( $field, $key );
	}
}
