<?php

namespace Duplicator\Utils;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use Duplicator\Models\ActivityLog\LogUtils;
use Duplicator\Package\Storage\Status\StatusCheckCron;
use Duplicator\Utils\Email\EmailSummaryBootstrap;
use Duplicator\Utils\UsageStatistics\StatsBootstrap;

class CronUtils
{
    const INTERVAL_HOURLY           = 'duplicator_hourly_cron';
    const INTERVAL_DAILY            = 'duplicator_daily_cron';
    const INTERVAL_WEEKLY           = 'duplicator_weekly_cron';
    const INTERVAL_MONTHLY          = 'duplicator_monthly_cron';
    const ACTIVITY_LOG_CLEANUP_HOOK = 'duplicator_activity_log_cleanup';

    /**
     * Init WordPress hooks
     *
     * @return void
     */
    public static function init(): void
    {
        add_filter('cron_schedules', fn($schedules) => self::defaultCronIntervals($schedules));
        add_filter(
            'cron_schedules',
            [
                DUP_PRO_Global_Entity::class,
                'customCleanupCronInterval',
            ]
        );
        add_action('duplicator_pro_after_activation', [self::class, 'activate'], 10, 2);
        add_action('duplicator_pro_after_deactivation', [self::class, 'deactivate']);
        self::registerCronFunctions();
    }

    /**
     * Add duplicator pro cron schedules
     *
     * @param array<string, array<string,int|string>> $schedules schedules
     *
     * @return array<string, array<string,int|string>>
     */
    protected static function defaultCronIntervals($schedules)
    {
        $schedules[self::INTERVAL_HOURLY] = [
            'interval' => HOUR_IN_SECONDS,
            'display'  => __('Once an Hour', 'duplicator-pro'),
        ];

        $schedules[self::INTERVAL_DAILY] = [
            'interval' => DAY_IN_SECONDS,
            'display'  => __('Once a Day', 'duplicator-pro'),
        ];

        $schedules[self::INTERVAL_WEEKLY] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Once a Week', 'duplicator-pro'),
        ];

        $schedules[self::INTERVAL_MONTHLY] = [
            'interval' => MONTH_IN_SECONDS,
            'display'  => __('Once a Month', 'duplicator-pro'),
        ];

        $schedules[StatusCheckCron::CRON_NAME_INTERVAL] = [
            'interval' => StatusCheckCron::CRON_TIME_INTERVAL,
            'display'  => "Once every " . StatusCheckCron::CRON_TIME_INTERVAL / MINUTE_IN_SECONDS . " minutes",
        ];

        return $schedules;
    }

    /**
     * Cron function activation
     *
     * @return void
     */
    public static function registerCronFunctions(): void
    {
        StatusCheckCron::cronFunction();
        // These are necessary for cron job for cleanup of installer files
        add_action(
            DUP_PRO_Global_Entity::CLEANUP_HOOK,
            [
                DUP_PRO_Global_Entity::class,
                'cleanupCronJob',
            ]
        );
        add_action(StatsBootstrap::USAGE_TRACKING_CRON_HOOK, [StatsBootstrap::class, 'sendPluginStatCron']);
        add_action(EmailSummaryBootstrap::CRON_HOOK, [EmailSummaryBootstrap::class, 'send']);
        add_action(self::ACTIVITY_LOG_CLEANUP_HOOK, [self::class, 'activityLogCleanupCron']);
    }

    /**
     * Initialize the cron hooks
     *
     * @param false|string $oldVersion current version
     * @param string       $newVersion new version
     *
     * @return void
     */
    public static function activate($oldVersion, $newVersion): void
    {
        EmailSummaryBootstrap::activationAction();
        StatsBootstrap::cronActivatie();
        StatusCheckCron::activate();

        // Schedule daily activity log cleanup
        self::scheduleEvent(time(), self::INTERVAL_DAILY, self::ACTIVITY_LOG_CLEANUP_HOOK);
    }

    /**
     * Clean up cron hooks on plugin deactivation
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Unschedule custom cron event for cleanup if it's scheduled
        if (wp_next_scheduled(\DUP_PRO_Global_Entity::CLEANUP_HOOK)) {
            // Unschedule the hook
            $timestamp = wp_next_scheduled(\DUP_PRO_Global_Entity::CLEANUP_HOOK);
            wp_unschedule_event($timestamp, \DUP_PRO_Global_Entity::CLEANUP_HOOK);
        }

        EmailSummaryBootstrap::deactivationAction();
        StatsBootstrap::cronDeactivate();
        StatusCheckCron::deactivate();

        // Unschedule activity log cleanup
        self::unscheduleEvent(self::ACTIVITY_LOG_CLEANUP_HOOK);
    }

    /**
     * Schedules cron event if it's not already scheduled.
     *
     * @param int    $timestamp        Timestamp of the first next run time
     * @param string $cronIntervalName Name of cron interval to be used
     * @param string $hook             Hook that we want to assign to the given cron interval
     *
     * @return void
     */
    public static function scheduleEvent($timestamp, $cronIntervalName, $hook): void
    {
        DUP_PRO_Log::trace("SCHEDULING CRON EVENT BEFOR CHECK: " . $hook);
        if (!wp_next_scheduled($hook)) {
            DUP_PRO_Log::trace("SCHEDULING CRON EVENT: " . $hook);
            // Assign the hook to the schedule
            wp_schedule_event($timestamp, $cronIntervalName, $hook);
        }
    }

    /**
     * Unschedules cron event if it's scheduled.
     *
     * @param string $hook Name of the hook that we want to unschedule
     *
     * @return void
     */
    public static function unscheduleEvent($hook): void
    {
        if (wp_next_scheduled($hook)) {
            DUP_PRO_Log::trace("UNSCHEDULING CRON EVENT: " . $hook);
            // Unschedule the hook
            $timestamp = wp_next_scheduled($hook);
            wp_unschedule_event($timestamp, $hook);
        }
    }

    /**
     * Activity log cleanup cron job
     * This function is called daily to clean up old activity logs
     *
     * @return void
     */
    public static function activityLogCleanupCron(): void
    {
        DUP_PRO_Log::trace("Running activity log cleanup cron job");
        $deletedLogs = LogUtils::cleanupOldLogs();
        if ($deletedLogs > 0) {
            DUP_PRO_Log::trace("Activity log cleanup cron: deleted {$deletedLogs} old log entries");
        }
    }
}
