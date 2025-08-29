<?php

namespace Duplicator\Models\ActivityLog;

use DUP_PRO_Archive_Build_Mode;
use Duplicator\Core\Views\TplMng;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\BuildComponents;

/**
 * Log event for backup creation
 */
class LogEventBackupCreate extends AbstractLogEvent
{
    const SUB_TYPE_ERROR     = 'error';
    const SUB_TYPE_CANCELLED = 'cancelled';
    const SUB_TYPE_START     = 'start';
    const SUB_TYPE_DB_DUMP   = 'db_dump';
    const SUB_TYPE_FILE_DUMP = 'file_dump';
    const SUB_TYPE_TRANSFER  = 'transfer';
    const SUB_TYPE_END       = 'end';

    /**
     * Class constructor
     *
     * @param AbstractPackage $package  Package
     * @param int             $parentId Parent ID, if 0 the event have no event parent
     */
    public function __construct(AbstractPackage $package, int $parentId = 0)
    {
        $this->subType                   = self::SUB_TYPE_START;
        $this->severity                  = self::SEVERITY_INFO;
        $this->parentId                  = $parentId;
        $this->data['packageId']         = $package->getId();
        $this->data['packageName']       = $package->getName();
        $this->data['packageStatus']     = $package->getStatus();
        $this->data['components']        = $package->components;
        $this->data['filterOn']          = $package->Archive->FilterOn;
        $this->data['filterDirs']        = strlen($package->Archive->FilterDirs) > 0 ? explode(';', $package->Archive->FilterDirs) : [];
        $this->data['filterExts']        = strlen($package->Archive->FilterExts) > 0 ? explode(';', $package->Archive->FilterExts) : [];
        $this->data['filterFiles']       = strlen($package->Archive->FilterFiles) > 0 ? explode(';', $package->Archive->FilterFiles) : [];
        $this->data['fileCount']         = $package->Archive->FileCount;
        $this->data['dirCount']          = $package->Archive->DirCount;
        $this->data['size']              = $package->Archive->Size;
        $this->data['dbFilterOn']        = $package->Database->FilterOn;
        $this->data['dbFilterTables']    = strlen($package->Database->FilterTables) > 0 ? explode(';', $package->Database->FilterTables) : [];
        $this->data['dbPrefixFilter']    = $package->Database->prefixFilter;
        $this->data['dbPrefixSubFilter'] = $package->Database->prefixSubFilter;
        switch ($package->build_progress->current_build_mode) {
            case DUP_PRO_Archive_Build_Mode::Shell_Exec:
                $this->data['archiveEngine'] = __('Shell Exec', 'duplicator-pro');
                break;
            case DUP_PRO_Archive_Build_Mode::ZipArchive:
                $this->data['archiveEngine'] = __('Zip Archive', 'duplicator-pro');
                break;
            case DUP_PRO_Archive_Build_Mode::DupArchive:
                $this->data['archiveEngine'] = __('Dup Archive', 'duplicator-pro');
                break;
            default:
                $this->data['archiveEngine'] = __('Unknown', 'duplicator-pro');
                break;
        }
        $this->data['databaseEngine'] = $package->Database->DBMode;


        $status = $package->getStatus();

        if ($status == AbstractPackage::STATUS_BUILD_CANCELLED) {
            $this->subType  = self::SUB_TYPE_CANCELLED;
            $this->title    = sprintf(__('Backup cancelled: %s', 'duplicator-pro'), $package->getName());
            $this->severity = self::SEVERITY_WARNING;
        } elseif ($status < AbstractPackage::STATUS_PRE_PROCESS) {
            $this->subType  = self::SUB_TYPE_ERROR;
            $this->title    = sprintf(__('Backup create: %s - Error', 'duplicator-pro'), $package->getName());
            $this->severity = self::SEVERITY_ERROR;
        } elseif ($status < AbstractPackage::STATUS_DBSTART) {
            $this->subType = self::SUB_TYPE_START;
            $this->title   = sprintf(__('Backup create: %s', 'duplicator-pro'), $package->getName());
        } elseif ($status < AbstractPackage::STATUS_ARCSTART) {
            $this->subType = self::SUB_TYPE_DB_DUMP;
            $this->title   = sprintf(__('Backup create: %s - DB Dump', 'duplicator-pro'), $package->getName());
        } elseif ($status < AbstractPackage::STATUS_COPIEDPACKAGE) {
            $this->subType = self::SUB_TYPE_FILE_DUMP;
            $this->title   = sprintf(__('Backup create: %s - File Dump', 'duplicator-pro'), $package->getName());
        } elseif ($status < AbstractPackage::STATUS_COMPLETE) {
            $this->subType = self::SUB_TYPE_TRANSFER;
            $this->title   = sprintf(__('Backup create: %s - Transfer', 'duplicator-pro'), $package->getName());
        } else {
            $this->subType = self::SUB_TYPE_END;
            $this->title   = sprintf(__('Backup create: %s - Completed', 'duplicator-pro'), $package->getName());
        }
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'backup_create';
    }

    /**
     * Return entity type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('Backup Create', 'duplicator-pro');
    }

    /**
     * Return required capability for this log event
     *
     * @return string
     */
    public static function getCapability(): string
    {
        return \Duplicator\Core\CapMng::CAP_CREATE;
    }

    /**
     * Return short description
     *
     * @return string
     */
    public function getShortDescription(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_ERROR:
                return __('Backup Error', 'duplicator-pro');
            case self::SUB_TYPE_CANCELLED:
                return __('Backup Cancelled', 'duplicator-pro');
            case self::SUB_TYPE_START:
                $subEvents = array_merge(
                    self::getList(
                        [
                            'parent_id' => $this->getId(),
                            'order'     => 'DESC',
                            'orderby'   => 'created_at',
                            'per_page'  => 1,
                        ]
                    )
                );
                if (count($subEvents) > 0) {
                    return $subEvents[0]->getShortDescription();
                } else {
                    return __('Backup Create', 'duplicator-pro');
                }
            case self::SUB_TYPE_DB_DUMP:
                return __('Database Dump', 'duplicator-pro');
            case self::SUB_TYPE_FILE_DUMP:
                return __('File Dump', 'duplicator-pro');
            case self::SUB_TYPE_TRANSFER:
                return __('Backup Transfer', 'duplicator-pro');
            case self::SUB_TYPE_END:
                return __('Backup Completed', 'duplicator-pro');
            default:
                return __('Backup Create', 'duplicator-pro');
        }
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
                <strong><?php esc_html_e('Archive Engine:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['archiveEngine']); ?>
                </span>
            </div>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Database Engine:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['databaseEngine']); ?>
                </span>
            </div>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Components:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html(BuildComponents::displayComponentsList($this->data['components'], ", ")); ?>
                </span>
            </div>
            <?php
            $subEvents = array_merge(
                // [$this],
                self::getList(
                    [
                        'parent_id' => $this->getId(),
                        'order'     => 'ASC',
                        'orderby'   => 'created_at',
                    ]
                )
            );
            if (count($subEvents) > 0) {
                ?>
                <div class="margin-top-1">
                    <?php TplMng::getInstance()->render('admin_pages/activity_log/parts/sub_table_mini', ['logs' => $subEvents]); ?>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Return object type label, can be overridden by child classes
     * by default it returns the same as static::getTypeLabel() but can change in base of object properties
     *
     * @return string
     */
    public function getObjectTypeLabel(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_ERROR:
                return __('Backup Error', 'duplicator-pro');
            case self::SUB_TYPE_CANCELLED:
                return __('Backup Cancelled', 'duplicator-pro');
            case self::SUB_TYPE_START:
                return __('Backup Create', 'duplicator-pro');
            case self::SUB_TYPE_DB_DUMP:
                return __('Database Dump', 'duplicator-pro');
            case self::SUB_TYPE_FILE_DUMP:
                return __('File Dump', 'duplicator-pro');
            case self::SUB_TYPE_TRANSFER:
                return __('Backup Transfer', 'duplicator-pro');
            case self::SUB_TYPE_END:
                return __('Backup Completed', 'duplicator-pro');
            default:
                return __('Backup Create', 'duplicator-pro');
        }
    }
}
