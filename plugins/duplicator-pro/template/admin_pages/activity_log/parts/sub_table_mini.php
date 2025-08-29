<?php

/**
 * Activity Log list template
 *
 * @package   Duplicator
 * @copyright (c) 2024, Snap Creek LLC
 */

use Duplicator\Controllers\ActivityLogPageController;
use Duplicator\Models\ActivityLog\AbstractLogEvent;
use Duplicator\Models\ActivityLog\LogUtils;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 */

$severityLevels = LogUtils::getSeverityLabels();
/** @var array<AbstractLogEvent> */
$logs = $tplMng->getDataValueArrayRequired('logs');
?>
<table class="widefat dup-table-list striped dup-activity-log-table small">
    <thead>
        <tr>
            <th scope="col" class="manage-column column-date"><?php esc_html_e('Date', 'duplicator-pro'); ?></th>
            <th scope="col" class="manage-column column-severity"><?php esc_html_e('Severity', 'duplicator-pro'); ?></th>
            <th scope="col" class="manage-column column-title"><?php esc_html_e('Title', 'duplicator-pro'); ?></th>
            <th scope="col" class="manage-column column-description"><?php esc_html_e('Description', 'duplicator-pro'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)) : ?>
            <tr>
                <td colspan="4" class="no-items">
                    <?php esc_html_e('No activity logs found.', 'duplicator-pro'); ?>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td class="column-date">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->getCreatedAt()))); ?>
                    </td>
                    <td class="column-severity">
                        <span class="dup-log-severity <?php echo esc_attr(ActivityLogPageController::getSeverityClass($log->getSeverity())); ?>">
                            <?php echo esc_html($severityLevels[$log->getSeverity()] ?? __('Unknown', 'duplicator-pro')); ?>
                        </span>
                    </td>
                    <td class="column-title">
                        <?php echo esc_html($log->getTitle()); ?>
                    </td>
                    <td class="column-description">
                        <?php echo esc_html($log->getShortDescription()); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>