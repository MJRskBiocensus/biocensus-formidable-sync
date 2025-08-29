<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * Contains helper function definitions used for Calendar View.
 *
 * @since 5.5.1
 */
class FrmViewsCalendarHelper {

	/**
	 * Returns true if the WordPress version is above or equal to 6.2.
	 * If this is unavailable, the year range will fall back to the +/- 5
	 * years range for calendar date range dropdown.
	 *
	 * @since 5.5.1
	 * @return bool
	 */
	public static function wp_version_supports_table_column_placeholders() {
		return version_compare( get_bloginfo( 'version' ), '6.2', '>=' );
	}

	/**
	 * Init the calendar filters.
	 * Called mainly in build_calendar from FrmViewsDisplayController
	 *
	 * @since 5.7
	 * @return void
	 */
	public static function init_filters() {
		add_filter( 'frm_display_value', array( 'FrmViewsCalendarHelper', 'wrap_calendar_date_in_span' ), 10, 2 );
		add_filter( 'frmpro_detaillink_shortcode', array( 'FrmViewsCalendarHelper', 'repeating_event_update_detailink_shortcode' ), 10, 2 );
		add_filter( 'frmpro_fields_replace_shortcodes', array( 'FrmViewsCalendarHelper', 'maybe_fix_inaccurate_calendar_dates_recurring_events' ), 10, 5 );
		add_filter( 'frm_single_shortcode_processing_class', array( 'FrmViewsCalendarHelper', 'add_calendar_single_shortcode_processing_class' ), 10 );
	}

	/**
	 * Init admin filters.
	 *
	 * @since 5.7
	 * @return void
	 */
	public static function init_admin_filters() {
		add_filter( 'frm_helper_shortcodes', array( 'FrmViewsCalendarHelper', 'init_shortcodes_labels' ), 10, 2 );
	}

	/**
	 * Remove the calendar filters.
	 * Called mainly in build_calendar from FrmViewsDisplayController
	 *
	 * @since 5.7
	 * @return void
	 */
	public static function remove_filters() {
		remove_filter( 'frm_display_value', array( 'FrmViewsCalendarHelper', 'wrap_calendar_date_in_span' ) );
		remove_filter( 'frmpro_detaillink_shortcode', array( 'FrmViewsCalendarHelper', 'repeating_event_update_detailink_shortcode' ) );
		remove_filter( 'frmpro_fields_replace_shortcodes', array( 'FrmViewsCalendarHelper', 'maybe_fix_inaccurate_calendar_dates_recurring_events' ) );
		remove_filter( 'frm_single_shortcode_processing_class', array( 'FrmViewsCalendarHelper', 'add_calendar_single_shortcode_processing_class' ) );
	}

	/**
	 * Add shortcode labels used in Customization Panel
	 *
	 * @since 5.7
	 * @param array $entry_shortcodes
	 * @param bool  $settings_tab
	 * @return array
	 */
	public static function init_shortcodes_labels( $entry_shortcodes, $settings_tab ) {
		if ( ! $settings_tab ) {
			$entry_shortcodes['end_event_date format="Y-m-d"'] = __( 'Calendar End Date', 'formidable-views' );
		}
		return $entry_shortcodes;
	}

	/**
	 * Add processing class for event date and end event date shortcodes.
	 *
	 * @since 5.7
	 * @param array $classes_list The processing classes list.
	 * @return array
	 */
	public static function add_calendar_single_shortcode_processing_class( $classes_list ) {
		return array_merge(
			$classes_list,
			array(
				'end_event_date' => 'FrmViewsCalendarHelper',
			)
		);
	}

	/**
	 * Returns an array that has start and end years.
	 *
	 * @since 5.5.1
	 *
	 * @param array $event_dates        Event date fields.
	 * @param array $field_mapped_dates An array that contains event dates mapped to date fields.
	 * @param int   $year
	 * @param int   $form_id
	 * @return array
	 */
	public static function get_year_range_for_date_field( $event_dates, $field_mapped_dates, $year, $form_id ) {
		$start_year = '';
		$end_year   = '';
		$metas      = FrmDb::get_results( 'frm_item_metas', array( 'field_id' => $field_mapped_dates ), 'field_id,meta_value' );

		self::set_year_range_component( 'start_date', $start_year, $metas, $field_mapped_dates );
		self::set_year_range_component( 'end_date', $end_year, $metas, $field_mapped_dates );

		$create_or_updated_at_date = array_diff( $event_dates, $field_mapped_dates );
		if ( $create_or_updated_at_date ) { // if any of the event start or end dates is not mapped to a date field.
			self::maybe_update_years_using_entry_dates( $start_year, $end_year, $create_or_updated_at_date, $form_id );
		}

		if ( ! $start_year && $end_year ) {
			$start_year = $end_year; // If start year is not found from either of field/entry metas, fall back to end year.
		}

		if ( ! $end_year && $start_year ) {
			$end_year = $start_year; // If end year is not found from either of field/entry metas, fall back to start year.
		}

		return array(
			'start' => $start_year ? min( $start_year, $year ) : $year, // We need to make sure we always include the current year.
			'end'   => $end_year ? max( $end_year, $year ) : $year,
		);
	}

	/**
	 * Calculates the start/end years from item metas and set the value to $year_component.
	 *
	 * @since 5.5.1
	 * @param string $date               Either 'start_date' or 'end_date'.
	 * @param string $year_component     The variable to whom the calculated year is assigned to, passed by reference.
	 * @param array  $metas              Entry values for the date fields mapped to event date or event end date.
	 * @param array  $field_mapped_dates An array that contains event dates mapped to date fields.
	 * @return void
	 */
	private static function set_year_range_component( $date, &$year_component, $metas, $field_mapped_dates ) {
		$field_mapped_dates = array_map( 'intval', $field_mapped_dates );
		$component_metas    = array_filter(
			$metas,
			function ( $meta ) use ( $field_mapped_dates ) {
				return in_array( (int) $meta->field_id, $field_mapped_dates, true );
			}
		);

		if ( ! $component_metas ) {
			return;
		}

		$component_date_values = array_column( $component_metas, 'meta_value' );
		$component_timestamps  = array_map( 'strtotime', $component_date_values );

		if ( $component_timestamps ) {
			$year_component = 'start_date' === $date ? gmdate( 'Y', min( $component_timestamps ) ) : gmdate( 'Y', max( $component_timestamps ) );
		}
	}

	/**
	 * Checks Form entries created_at or updated_at years and use it to update the year range if needed.
	 *
	 * @since 5.5.1
	 *
	 * @param string $start_year
	 * @param string $end_year
	 * @param array  $create_or_updated_at_date An array that has at most one element with the value of either created_at/updated_at.
	 * @param int    $form_id
	 * @return void
	 */
	private static function maybe_update_years_using_entry_dates( &$start_year, &$end_year, $create_or_updated_at_date, $form_id ) {
		global $wpdb;

		$min_max = isset( $create_or_updated_at_date['start_date'] ) ? 'MIN' : 'MAX';
		$date    = reset( $create_or_updated_at_date );

		if ( 'MIN' === $min_max ) {
			$query = $wpdb->prepare( "SELECT YEAR(MIN(%i)) FROM {$wpdb->prefix}frm_items WHERE form_id=%d", $date, $form_id ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
		} else {
			$query = $wpdb->prepare( "SELECT YEAR(MAX(%i)) FROM {$wpdb->prefix}frm_items WHERE form_id=%d", $date, $form_id ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
		}

		$cache_key  = $date . '_year_for_form_' . $form_id;
		$entry_year = FrmDb::check_cache( $cache_key, 'frm_items', $query, 'get_var' );

		if ( 'MIN' === $min_max ) {
			$start_year = $start_year ? min( $start_year, $entry_year ) : $entry_year;
		} else {
			$end_year = $end_year ? max( $end_year, $entry_year ) : $entry_year;
		}
	}

	/**
	 * Returns min and max years from entries created_at dates.
	 *
	 * @since 5.5.1
	 * @param object $view
	 * @return object|null
	 */
	public static function get_range_from_db( $view ) {
		global $wpdb;

		$cache_key = 'year_range_for_form_created_at_' . $view->frm_form_id;
		$query     = $wpdb->prepare(
			"SELECT YEAR(MIN(%i)) as min_year, YEAR(MAX(%i)) as max_year FROM {$wpdb->prefix}frm_items WHERE form_id=%d", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
			$view->frm_date_field_id ? $view->frm_date_field_id : 'created_at',
			$view->frm_edate_field_id ? $view->frm_edate_field_id : 'created_at',
			$view->frm_form_id
		);

		return FrmDb::check_cache( $cache_key, 'frm_items', $query, 'get_row' );
	}

	/**
	 * Return the default calendar styles based on theme.json.
	 *
	 * @since 5.6
	 * @return array
	 */
	private static function get_default_style_data() {
		$defaults = wp_get_global_styles();
		$style    = array();

		if ( isset( $defaults['color'] ) ) {
			if ( isset( $defaults['color']['text'] ) ) {
				$style['--frm-views-calendar-color'] = $defaults['color']['text'];
			}
			if ( isset( $defaults['color']['background'] ) ) {
				$style['--frm-views-calendar-background-color'] = $defaults['color']['background'];
			}
		}

		if ( isset( $defaults['typography'] ) ) {
			if ( isset( $defaults['typography']['fontSize'] ) ) {
				$style['--frm-views-calendar-font-size'] = $defaults['typography']['fontSize'];
			}
		}

		if ( isset( $defaults['elements'] ) && isset( $defaults['elements']['button'] ) ) {
			if ( isset( $defaults['elements']['button']['color'] ) && isset( $defaults['elements']['button']['color']['background'] ) ) {
				$style['--frm-views-calendar-accent-color']    = $defaults['elements']['button']['color']['background'];
				$style['--frm-views-calendar-accent-bg-color'] = FrmStylesHelper::hex2rgba( $defaults['elements']['button']['color']['background'], 0.1 );
			}
			if ( isset( $defaults['elements']['button']['border'] ) && isset( $defaults['elements']['button']['border']['color'] ) ) {
				$style['--frm-views-calendar-border-color'] = $defaults['elements']['button']['border']['color'];
			}
		}

		return $style;
	}

	/**
	 * Return the calendar style data based on Gutenberg customization attributes.
	 *
	 * @since 5.6
	 * @param array $atts The attributes passed from the block.
	 * @return array
	 */
	public static function get_style_data( $atts ) {
		$style = self::get_default_style_data();

		if ( ! isset( $atts['calendarViews'] ) ) {
			return $style;
		}

		$active_colors = $atts['calendarViews']['activeColors'];

		$style['--frm-views-calendar-border-color']     = $active_colors['strokes'];
		$style['--frm-views-calendar-background-color'] = $active_colors['background'];
		$style['--frm-views-calendar-color']            = $active_colors['text'];
		$style['--frm-views-calendar-accent-color']     = $active_colors['primaryColor'];
		$style['--frm-views-calendar-font-size']        = $atts['calendarViews']['font']['size'] . 'px';
		$style['--frm-views-calendar-accent-bg-color']  = FrmStylesHelper::hex2rgba( $active_colors['primaryColor'], 0.1 );

		return $style;
	}

	/**
	 * Calendar wrapper classname. It's used to customize the calendar style per Gutenberg options.
	 *
	 * @since 5.6
	 * @param int   $view_id The view ID.
	 * @param array $atts The attributes passed from the block.
	 *
	 * @return string
	 */
	public static function gutenberg_wrapper_classname( $view_id, $atts = array() ) {
		$classname = 'frm-views-calendar-' . $view_id;

		if ( empty( $atts ) ) {
			return $classname;
		}

		if ( isset( $atts['align'] ) ) {
			$classname .= ' align' . $atts['align'];
		}

		return $classname;
	}

	/**
	 * Sorts the daily entries by time.
	 *
	 * @since 5.6
	 * @param array $daily_entries The array of daily entries to be sorted.
	 * @return array The sorted daily entries.
	 */
	public static function sort_daily_entries_by_time( $daily_entries ) {
		foreach ( $daily_entries as &$entry ) {
			usort( $entry, array( 'FrmViewsCalendarHelper', 'time_sort' ) );
		}
		return $daily_entries;
	}

	/**
	 * Sorts the given array of objects based on the 'time' property in ascending order.
	 *
	 * @since 5.6
	 * @param object $a The first object to compare.
	 * @param object $b The second object to compare.
	 * @return int
	 */
	private static function time_sort( $a, $b ) {
		if ( ! isset( $a->time ) || ! isset( $a->time ) ) {
			return 0;
		}
		$time1_minutes = intval( substr( $a->time, 0, 2 ) ) * 60 + intval( substr( $a->time, 3 ) );
		$time2_minutes = intval( substr( $b->time, 0, 2 ) ) * 60 + intval( substr( $b->time, 3 ) );

		if ( $time1_minutes === $time2_minutes ) {
			return 0;
		}
		if ( $time1_minutes < $time2_minutes ) {
			return -1;
		}

		return 1;
	}

	/**
	 * Check if a classname is already in the inline style of a stylesheet.
	 *
	 * @since 5.6
	 *
	 * @param string $classname The classname to check for.
	 * @param string $style_handle The style handle to check.
	 * @return bool
	 */
	public static function has_classname_in_inline_style( $classname, $style_handle ) {
		if ( ! wp_style_is( $style_handle, 'registered' ) ) {
			return false;
		}
		$stylesheet = wp_styles()->registered[ $style_handle ];

		foreach ( $stylesheet->extra as $extra ) {
			if ( is_array( $extra ) ) {
				$extra = implode( ' ', $extra );
			}

			if ( false !== strpos( $extra, $classname ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Loads the frontend scripts required for the calendar functionality.
	 *
	 * This function enqueues the 'frm-calendar-script' JavaScript file, which is responsible for providing the calendar functionality on the frontend.
	 * The script is loaded using the FrmViewsAppHelper::plugin_url() method to get the plugin's URL, and the FrmViewsAppHelper::plugin_version() method to get the plugin's version.
	 *
	 * @since 5.6
	 */
	public static function load_calendar_frontend_scripts() {
		global $wp_scripts;
		if ( ! $wp_scripts->get_data( 'formidable', 'data' ) ) {
			FrmAppHelper::localize_script( 'front' );
		}
		wp_enqueue_script( 'frm-calendar', FrmViewsAppHelper::plugin_url() . '/js/calendar.js', array( 'formidable' ), FrmViewsAppHelper::plugin_version(), true );
	}

	/**
	 * Adjust the date for repeated events when they come with the default month or year
	 *
	 * @since 5.7
	 *
	 * @param string $date
	 * @param array  $args An array that holds the event's current year and month.
	 */
	public static function maybe_fix_repeating_date( $date, $args ) {
		$day = gmdate( 'd', strtotime( $date ) );
		return $args['year'] . '-' . $args['month'] . '-' . $day;
	}

	/**
	 * Correct inaccurate dates for recurring events in calendar views.
	 *
	 * @since 5.7
	 *
	 * @param string $replace_with The string to replace the shortcode with.
	 * @param string $tag The shortcode tag.
	 * @param array  $atts The shortcode attributes.
	 * @param object $field The field object.
	 * @param array  $extra_args The extra shortcode options.
	 * @return string
	 */
	public static function maybe_fix_inaccurate_calendar_dates_recurring_events( $replace_with, $tag, $atts, $field, $extra_args = array() ) {
		if ( empty( $field->type ) || 'date' !== $field->type || empty( $extra_args['args']['calendar_repeating_period'] ) || ! preg_match( '/(week|day)$/', $extra_args['args']['calendar_repeating_period'] ) || 1 < $extra_args['date_shortcode_count']['date_field'] ) {
			return $replace_with;
		}
		if ( 0 === $extra_args['date_shortcode_count']['date_field'] ) {
			return $extra_args['args']['event_date'];
		}
		return $extra_args['args']['event_end_date'];
	}

	/**
	 * Replace the calendar date shortcode with a span around the date.
	 *
	 * @since 5.7
	 *
	 * @param string $value The shortcode field value.
	 * @param object $field The field object.
	 *
	 * @return string
	 */
	public static function wrap_calendar_date_in_span( $value, $field ) {
		if ( 'date' !== $field->type ) {
			return $value;
		}
		return '<span class="frm-calendar-event-date">' . $value . '</span>';
	}

	/**
	 * Replace the calendar end date shortcode with the event end date.
	 * Function used to render the shortcode for event end date in FrmProContent::replace_single_shortcode
	 *
	 * @since 5.7
	 *
	 * @param string $content
	 * @param array  $atts
	 * @param array  $shortcodes
	 * @param int    $short_key
	 * @param array  $args Passed in FrmProContent::replace_single_shortcode when called.
	 *
	 * @return void
	 */
	public static function do_shortcode_end_event_date( &$content, $atts, $shortcodes, $short_key, $args ) {
		$get_event_date = FrmAppHelper::get_param( 'frmev-end', '', 'get', 'sanitize_text_field' );
		$event_date     = '';
		if ( ! empty( $get_event_date ) ) {
			$event_date = FrmProFieldsHelper::get_date( $get_event_date, get_option( 'date_format' ) );
		}
		$content = str_replace( $shortcodes[0][ $short_key ], $event_date, $content );
	}

	/**
	 * Add extra dates params frmev-start & frmev-end for repeating events.
	 *
	 * @since 5.7
	 * @param string $detail_link The URL string of the [detaillink] shortcode.
	 * @param array  $args The shortcode args.
	 * @return string
	 */
	public static function repeating_event_update_detailink_shortcode( $detail_link, $args ) {

		if ( empty( $args['calendar_repeating_period'] ) ) {
			return $detail_link;
		}

		if ( ! empty( $args['event_date'] ) ) {
			$detail_link .= '?frmev-start=' . $args['event_date'];
		}

		if ( ! empty( $args['event_end_date'] ) ) {
			$detail_link .= '&frmev-end=' . $args['event_end_date'];
		}

		return $detail_link;
	}
}
