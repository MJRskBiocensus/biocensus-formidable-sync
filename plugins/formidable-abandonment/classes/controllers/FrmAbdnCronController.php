<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class FrmAbdnCronController.
 *
 * @since 1.0
 */
final class FrmAbdnCronController {

	/**
	 * Initiate cron job hook and schedule event.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'frm_mark_abandoned_entry', array( 'FrmAbdnCronController', 'cron' ) );
		add_filter( 'cron_schedules', array( 'FrmAbdnCronController', 'add_cron_schedule' ) );

		if ( ! wp_next_scheduled( 'frm_mark_abandoned_entry' ) ) {
			wp_schedule_event( self::get_next_cron_date_gmt(), 'frm_abandonment_schedule', 'frm_mark_abandoned_entry' );
		}
	}

	/**
	 * Abandoned cron callback.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function cron() {
		$page           = 1;
		$update_offset  = self::get_compare_time();

		// Remove update actions.
		remove_action( 'frm_after_update_entry', 'FrmProEntriesController::add_published_hooks', 2 );
		remove_action( 'frm_after_update_entry', 'FrmProFormActionsController::trigger_update_actions', 10 );
		remove_action( 'frm_after_create_entry', 'FrmFormActionsController::trigger_create_actions', 20 );
		add_action( 'frm_after_update_entry', 'FrmAbandonmentActionController::trigger_abandonment_actions', 10, 2 );

		while ( true ) {
			// Get in progress entries.
			$in_progress_entries = FrmAbdnEntry::get_in_progress_entries( $page, 10, $update_offset );

			if ( ! $in_progress_entries ) {
				break;
			}

			// Convert entries status from in progress to abandoned.
			FrmAbdnEntry::mark_entries_abandoned( $in_progress_entries );

			// Set limit to 100 pages to prevent extra load on server.
			if ( 100 === $page ) {
				break;
			}

			$page++;
		}

		// Remove the abandonment hooks.
		remove_action( 'frm_after_update_entry', 'FrmAbandonmentActionController::trigger_abandonment_actions', 10 );
	}

	/**
	 * Abandonment cron schedule.
	 *
	 * @since 1.0
	 *
	 * @param array<mixed> $schedules WP cron schedules.
	 * @return array<mixed>
	 */
	public static function add_cron_schedule( $schedules ) {
		$schedules['frm_abandonment_schedule'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'formidable-abandonment' ),
		);

		return $schedules;
	}

	/**
	 * Get hours offset to marking entries or cookie time expire.
	 *
	 * @since 1.0
	 *
	 * @return numeric Date time in mysql format
	 */
	private static function get_offset() {
		/**
		 * Offset use to compare with updated_at column to set entries from "In Progress" to abandoned, 60 minutes by default.
		 *
		 * @since 1.0
		 *
		 * @param int $updated_at non-negative integer uses to compare with entry updated_at column.
		 */
		$interval_in_minutes = apply_filters( 'frm_mark_abandonment_entries_period', 60 );

		if ( ! is_numeric( $interval_in_minutes ) || $interval_in_minutes < 1 ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Please return a positive integer.', 'formidable-abandonment' ), '1.0' );
			// If it's wrong we will process with the default 1 Hour.
			$interval_in_minutes = 60;
		}

		return $interval_in_minutes;
	}

	/**
	 * Get date time in mysql format to compare with entry updated_at column.
	 *
	 * @since 1.0
	 *
	 * @return string Date time in mysql format
	 */
	private static function get_compare_time() {
		$date_time = new DateTime( 'NOW', new DateTimeZone( 'UTC' ) );
		$date_time->modify( '-' . self::get_offset() . ' minutes' );
		return $date_time->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Get next cron occurrence schedule.
	 *
	 * @since 1.0
	 *
	 * @return int
	 */
	private static function get_next_cron_date_gmt() {
		if ( is_callable( 'FrmAppHelper::filter_gmt_offset' ) ) {
			FrmAppHelper::filter_gmt_offset();
		}

		$date = absint( strtotime( '+5 minutes' ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		return $date ? $date : absint( current_time( 'timestamp' ) );
	}
}
