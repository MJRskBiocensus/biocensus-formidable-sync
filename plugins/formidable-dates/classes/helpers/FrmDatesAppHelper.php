<?php
class FrmDatesAppHelper {

	public static $plug_version = '2.1';

	public static function plugin_file() {
		return dirname( dirname( __DIR__ ) ) . '/formidable-dates.php';
	}

	public static function get_path( $path = '/' ) {
		return plugin_dir_path( self::plugin_file() ) . $path;
	}

	public static function get_url( $path = '/' ) {
		return plugins_url( $path, self::plugin_file() );
	}

	public static function get_days_of_the_week( $args = null ) {
		global $wp_locale;

		$week_days  = array();
		$week_start = absint( get_option( 'start_of_week' ) );

		$n = $week_start;
		for ( $i = 0; $i < 7; $i++ ) {
			$week_days[ strval( ( $n + $i ) % 7 ) ] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( ( $n + $i ) % 7 ) );
		}

		return $week_days;
	}

	/**
	 * Gets the minimum and maximum dates for a date field.
	 *
	 * @param array  $field_options
	 * @param string $min_max 'minimum' or 'maximum' strings.
	 *
	 * @return string|false $date.
	 */
	public static function get_field_min_max_date( $field_options, $min_max ) {
		if ( ! isset( $field_options[ $min_max . '_date_val' ] ) || ! isset( $field_options[ $min_max . '_date_cond' ] ) ) {
			return false;
		}

		$limit_date_val = $field_options[ $min_max . '_date_val' ];
		$date_cond      = $field_options[ $min_max . '_date_cond' ];
		$date_format    = 'Y-m-d';

		if ( 'today' === $date_cond || strpos( $date_cond, 'field_' ) !== false ) {
			return self::get_standard_date_string_with_offset( $date_cond, $limit_date_val, $date_format );
		}

		if ( 'date' === $date_cond ) {
			return gmdate( $date_format, strtotime( $limit_date_val ) );
		}

		return false;
	}

	/**
	 * Creates date string that is interpreted by strtotime() function. Ex. today + 3 weeks.
	 *
	 * @param string $date_cond      The date off which the setting is based.
	 * @param string $limit_date_val The value to add/subtract from the $date_cond.
	 * @param string $date_format    The date format used.
	 *
	 * @return string
	 */
	private static function get_standard_date_string_with_offset( $date_cond, $limit_date_val, $date_format ) {
		$date_offset = strtolower( $limit_date_val );

		preg_match_all( '/([+\-]?\d+)\s*(d|day|days|w|week|weeks|m|month|months|y|year|years)/', $date_offset, $matches, PREG_SET_ORDER );
		$base_date = self::get_base_date( $date_cond );

		if ( ! $base_date ) {
			return '';
		}

		if ( 'today' === $date_cond ) {
			$base_date = FrmAppHelper::get_localized_date( 'Y-m-d', gmdate( 'Y-m-d H:i:s' ) );
		} else {
			// Standardize the base date before it is offsetted and passed to strtotime to avoid between date formats like 'd/m/Y' and 'm/d/Y'.
			$base_date = FrmDatesField::convert_date_from_settings_format_to_db( $base_date );
		}

		foreach ( $matches as $match ) {
			switch ( $match[2] ) {
				case 'd':
				case 'day':
				case 'days':
					$base_date .= $match[1] . ' days ';
					break;
				case 'w':
				case 'week':
				case 'weeks':
					$base_date .= $match[1] . ' weeks ';
					break;
				case 'm':
				case 'month':
				case 'months':
					$base_date .= $match[1] . ' months ';
					break;
				case 'y':
				case 'year':
				case 'years':
					$base_date .= $match[1] . ' years ';
					break;
			}
		}

		return $base_date ? gmdate( $date_format, strtotime( $base_date ) ) : gmdate( $date_format );
	}

	/**
	 * Returns the base date from which an offset is added/subtracted to get the limit.
	 *
	 * @param string $date_cond
	 *
	 * @return string
	 */
	private static function get_base_date( $date_cond ) {
		$date_cond_substrings = explode( '_', $date_cond );
		if ( 'today' !== $date_cond && ! empty( $date_cond_substrings[1] ) ) {
			$field_id = FrmField::get_id_by_key( $date_cond_substrings[1] );
			$item_meta = FrmAppHelper::get_post_param( 'item_meta' );
			if ( ! empty( $field_id ) && ! empty( $item_meta[ $field_id ] ) ) {
				return $item_meta[ $field_id ];
			}
		}

		return 'today' === $date_cond ? 'today' : '';
	}

	/**
	 * Return plugin version.
	 *
	 * @since 1.04
	 *
	 * @return string
	 */
	public static function plugin_version() {
		return self::$plug_version;
	}
}
