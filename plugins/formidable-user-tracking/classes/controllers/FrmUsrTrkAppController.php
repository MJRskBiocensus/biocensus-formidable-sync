<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 2.0
 */
class FrmUsrTrkAppController {

	/**
	 * @return void
	 */
	public static function load_lang() {
		load_plugin_textdomain( 'formidable-usr-trk', false, FrmUsrTrkAppHelper::plugin_folder() . '/languages/' );
	}

	/**
	 * @return void
	 */
	public static function enqueue_styles() {
		$version = FrmUsrTrkAppHelper::plugin_version();
		wp_enqueue_style( 'frm-usr-trk', FrmUsrTrkAppHelper::plugin_url() . '/css/frm-usr-trk.css', array(), $version );
	}

	/**
	 * @since 2.0.1
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		self::enqueue_styles();
		self::enqueue_js_scripts();
	}

	/**
	 * @since 2.0.1
	 *
	 * @return void
	 */
	private static function enqueue_js_scripts() {
		$version = FrmUsrTrkAppHelper::plugin_version();
		wp_enqueue_script( 'frm-usr-trk-admin', FrmUsrTrkAppHelper::plugin_url() . '/js/frm-usr-trk-admin.js', array(), $version );
	}

	/**
	 * @param object|null $entry
	 * @return void
	 */
	public static function show_tracking_data( $entry ) {
		if ( ! $entry || empty( $entry->description['referrer'] ) ) {
			return;
		}

		$data = $entry->description;
		if ( is_string( $data['referrer'] ) && ! isset( $data['user_journey'] ) ) {
			$created_at = strtotime( $entry->created_at );
			$data['user_journey'] = self::convert_journey_string_to_array( $data['referrer'], $created_at );
		}

		if ( empty( $data['user_journey'] ) ) {
			return;
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		$sep         = ' &#183; ';

		include FrmUsrTrkAppHelper::plugin_path() . '/classes/views/user-tracking.php';
	}

	/**
	 * The old tracking was a string. Convert and parse into array.
	 *
	 * @param string    $tracking
	 * @param int|false $created_at Date in time string format.
	 *
	 * @return array
	 */
	private static function convert_journey_string_to_array( $tracking, $created_at ) {
		if ( ! strpos( $tracking, "\r\n" ) ) {
			// There might only be one URL here.
			return array();
		}

		$journey  = array(
			'keywords' => array(),
		);

		$tracking = array_filter( explode( "\r\n", $tracking ) );

		// We need each timestamp to be different.
		$start_time = gmdate( 'Y-m-d H:i:s', $created_at - count( $tracking ) );

		foreach ( $tracking as $track ) {
			$parts = explode( ': ', $track );
			$step  = array();
			if ( isset( $parts[1] ) && strpos( $parts[0], 'Keyword ' ) === 0 ) {
				$journey['keywords'][] = trim( $parts[1] );
				continue;
			}

			self::convert_url_to_new_format( $parts, $step );
			if ( isset( $step['summary'] ) && empty( $step['summary'] ) ) {
				// Don't include an empty step.
				continue;
			}

			$journey[ $start_time ] = $step;
			$start_time = gmdate( 'Y-m-d H:i:s', strtotime( $start_time ) + 1 );
		}

		if ( empty( $journey['keywords'] ) ) {
			unset( $journey['keywords'] );
		}

		return $journey;
	}

	/**
	 * @param array $parts
	 * @param array $step
	 * @return void
	 */
	private static function convert_url_to_new_format( $parts, &$step ) {
		$original = implode( ' ', $parts );
		if ( ! isset( $parts[1] ) ) {
			// We're not sure what value this is, so leave it alone.
			$step['summary'] = trim( $original );
			return;
		}

		if ( strpos( $parts[0], 'Referer ' ) === 0 ) {
			$step['referer'] = trim( str_replace( 'Referer ', '', $original ) );
			return;
		}

		$part_two        = trim( $parts[1] );
		$is_relative_url = substr( $part_two, 0, 1 ) === '/';
		if ( $is_relative_url ) {
			// Convert the relative url to full link.
			$site_url             = untrailingslashit( FrmAppHelper::site_url() );
			$step['relative_url'] = ltrim( $part_two, '/' );
			$step['url']          = $site_url . $part_two;
		} else {
			$step['summary'] = $part_two;
		}
	}

	/**
	 * Appends user journey to referrer data for entries created with latest version of user tracking add on.
	 *
	 * @param array $referrer Referrer data.
	 * @param array $data The entry description.
	 *
	 * @return array $referrer
	 */
	public static function append_user_journey( $referrer, $data ) {
		if ( ! empty( $data['user_journey'] ) ) {
			$referrer['value'] .= self::convert_journey_to_string( $data['user_journey'] );
		}

		return $referrer;
	}

	/**
	 * @param array $journey The path saved with the entry.
	 * @return string
	 */
	private static function convert_journey_to_string( $journey ) {
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		$output      = array();

		// Skip items that may have been created before this version.
		$journey       = array_filter( $journey, 'is_array' );
		$previous_date = '';

		foreach ( $journey as $day => $page ) {
			if ( is_numeric( $day ) || ! is_array( $page ) || ! isset( $page['url'] ) ) {
				continue;
			}

			$date = FrmAppHelper::get_localized_date( $date_format, $day );
			if ( $date !== $previous_date ) {
				$previous_date = $date;
				$output[] = "\r\n" . $date;
			}

			$time = FrmAppHelper::get_localized_date( $time_format, $day );

			$output[] = '<b>' . $time . '</b> ' . $page['url'];
		}

		return " \r\n" . implode( "\r\n", $output );
	}

	/**
	 * @param array $parts
	 * @return array
	 */
	public static function unset_document_title_parts( $parts ) {
		unset( $parts['site'] );
		unset( $parts['tagline'] );
		return $parts;
	}

	/**
	 * Don't show the user journey in the sidebar too.
	 *
	 * @param array $data
	 * @since 2.0
	 * @return array
	 */
	public static function remove_from_sidebar( $data ) {
		if ( ! empty( $data['user_journey'] ) ) {
			unset( $data['user_journey'] );
			if ( isset( $data['referrer'] ) ) {
				unset( $data['referrer'] );
			}
		}
		if ( isset( $data['referrer'] ) && strpos( $data['referrer'], '1:' ) ) {
			// Prevent referrer from showing twice for entries created before v2.0.
			unset( $data['referrer'] );
		}
		return $data;
	}

	/**
	 * Remove the entry description from the duplicate check.
	 * This is required because additional keys are added to the entry description (user_journey),
	 * so the description will usually never be a match.
	 *
	 * @since 2.0.3
	 *
	 * @param array $values
	 * @return array
	 */
	public static function exclude_description_from_duplicate_check( $values ) {
		unset( $values['description'] );
		return $values;
	}
}
