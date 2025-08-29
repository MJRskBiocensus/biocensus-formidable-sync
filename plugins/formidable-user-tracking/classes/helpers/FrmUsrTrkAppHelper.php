<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmUsrTrkAppHelper {

	/**
	 * @since 2.0
	 *
	 * @var string $plug_version
	 */
	public static $plug_version = '2.0.3';

	/**
	 * @return string
	 */
	public static function plugin_folder() {
		return basename( self::plugin_path() );
	}

	/**
	 * @return string
	 */
	public static function plugin_path() {
		return dirname( dirname( dirname( __FILE__ ) ) );
	}

	/**
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( '', self::plugin_path() . '/formidable-user-tracking.php' );
	}

	/**
	 * @return string
	 */
	public static function plugin_version() {
		return self::$plug_version;
	}

	/**
	 * @return void
	 */
	public static function include_auto_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			FrmUsrTrkUpdate::load_hooks();
		}
	}

	/**
	 * Returns the keywords used in a referer query.
	 *
	 * @param string $query
	 *
	 * @return string|bool
	 */
	public static function get_referer_query( $query ) {
		if ( strpos( $query, 'google.' ) ) {
			$pattern = '/^.*[\?&]q=(.*)$/';
		} else if ( strpos( $query, 'bing.com' ) ) {
			$pattern = '/^.*q=(.*)$/';
		} else if ( strpos( $query, 'yahoo.' ) ) {
			$pattern = '/^.*[\?&]p=(.*)$/';
		} else if ( strpos( $query, 'ask.' ) ) {
			$pattern = '/^.*[\?&]q=(.*)$/';
		} else {
			return false;
		}

		preg_match( $pattern, $query, $matches );

		if ( ! $matches ) {
			return false;
		}

		$amp_pos  = strpos( $matches[1], '&' );
		$querystr = $amp_pos === false ? $matches[1] : substr( $matches[1], 0, $amp_pos );
		return urldecode( $querystr );
	}

	/**
	 * Prepare the summary of actions taken.
	 *
	 * @since 2.0
	 * @param int $total_steps
	 * @param int $total_time
	 * @return string
	 */
	public static function get_journey_summary( $total_steps, $total_time ) {
		if ( $total_time ) {
			/* translators: %1$s: Number of steps, %2$s: Total time spent */
			$info = __( 'User took %1$s steps over %2$s', 'formidable-usr-trk' );
		} else {
			/* translators: %1$s: Number of steps */
			$info = __( 'User took %1$s steps', 'formidable-usr-trk' );
		}
		return sprintf(
			$info,
			$total_steps,
			self::get_readable_duration( $total_time )
		);
	}

	/**
	 * Convert a duration in number of seconds to a readable format.
	 *
	 * @since 2.0
	 * @param int|string $seconds
	 * @return string
	 */
	public static function get_readable_duration( $seconds ) {
		return FrmAppHelper::human_time_diff( 0, (int) $seconds, 2 );
	}
}
