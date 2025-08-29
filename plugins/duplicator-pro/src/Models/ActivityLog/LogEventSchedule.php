<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;

class LogEventSchedule extends AbstractLogEvent
{
    /**
     * Class constructor
     *
     * @param ScheduleEntity $schedule Schedule entity
     */
    public function __construct(ScheduleEntity $schedule)
    {
        $this->data['scheduleId']   = $schedule->getId();
        $this->data['scheduleName'] = $schedule->name;
        $tempalte                   = $schedule->getTemplate();
        $this->data['templateName'] = $tempalte->name;
        $storagesIds                = $schedule->storage_ids;
        $this->data['storageNames'] = [];
        foreach ($storagesIds as $storageId) {
            $storage                      = AbstractStorageEntity::getById($storageId);
            $this->data['storageNames'][] = $storage->getName();
        }
        $this->title = sprintf(__('Processing schedule: %s', 'duplicator-pro'), $this->data['scheduleName']);
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'schedule_process';
    }

    /**
     * Return entity type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('Schedule Process', 'duplicator-pro');
    }

    /**
     * Return required capability for this log event
     *
     * @return string
     */
    public static function getCapability(): string
    {
        return \Duplicator\Core\CapMng::CAP_SCHEDULE;
    }

    /**
     * Return short description
     *
     * @return string
     */
    public function getShortDescription(): string
    {
        return sprintf(
            __(
                'Template: %1$s, Storages: %2$s',
                'duplicator-pro'
            ),
            $this->data['templateName'],
            implode(', ', $this->data['storageNames'])
        );
    }

    /**
     * Display detailed information in html format
     *
     * @return void
     */
    public function detailHtml(): void
    {
        ?>
        <div class="dup-log-detail-meta">
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Schedule:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html($this->data['scheduleName']); ?></span>
            </div>
        </div>
        <div class="dup-log-detail-meta">
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Template:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html($this->data['templateName']); ?></span>
            </div>
        </div>
        <div class="dup-log-detail-meta">
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Storages:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html(implode(', ', $this->data['storageNames'])); ?></span>
            </div>
        </div>
        <?php
    }
}
