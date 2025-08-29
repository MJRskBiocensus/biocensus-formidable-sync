<?php
/**
 * Plugin uninstall file.
 *
 * @since 1.0
 *
 * @package formidable-abandonment
 */

// If uninstall.php is not called by WordPress, then die.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin cron job.
wp_clear_scheduled_hook( 'frm_mark_abandoned_entry' );
