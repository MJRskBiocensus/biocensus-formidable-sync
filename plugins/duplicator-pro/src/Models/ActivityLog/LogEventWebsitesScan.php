<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\BuildComponents;
use Exception;

/**
 * Log event for backup creation
 */
class LogEventWebsitesScan extends AbstractLogEvent
{
    const SUB_TYPE_ERROR = 'scan_error';
    const SUB_TYPE_START = 'scan_start';
    const SUB_TYPE_END   = 'scan_end';

    /**
     * Class constructor
     *
     * @param AbstractPackage $package  Package
     * @param string          $status   Status ENUM self::SUB_TYPE_*
     * @param int             $parentId Parent ID, if 0 the event have no event parent
     */
    public function __construct(AbstractPackage $package, string $status, int $parentId = 0)
    {
        $this->data['packageId']   = $package->getId();
        $this->data['packageName'] = $package->getName();
        $this->data['components']  = $package->components;
        $this->data['filterOn']    = $package->Archive->FilterOn;
        $this->data['filterDirs']  = strlen($package->Archive->FilterDirs) > 0 ? explode(';', $package->Archive->FilterDirs) : [];
        $this->data['filterExts']  = strlen($package->Archive->FilterExts) > 0 ? explode(';', $package->Archive->FilterExts) : [];
        $this->data['filterFiles'] = strlen($package->Archive->FilterFiles) > 0 ? explode(';', $package->Archive->FilterFiles) : [];
        $this->data['fileCount']   = $package->Archive->FileCount;
        $this->data['dirCount']    = $package->Archive->DirCount;
        $this->data['size']        = $package->Archive->Size;

        switch ($status) {
            case self::SUB_TYPE_ERROR:
                $this->title = __('Scan Error', 'duplicator-pro');
                break;
            case self::SUB_TYPE_START:
                $this->title = __('Scan', 'duplicator-pro');
                break;
            case self::SUB_TYPE_END:
                $this->title = __('Scan Completed', 'duplicator-pro');
                break;
            default:
                throw new Exception('Invalid status: ' . $status);
        }
        $this->subType = $status;
        if ($this->subType === self::SUB_TYPE_ERROR) {
            $this->severity = self::SEVERITY_ERROR;
        } else {
            $this->severity = self::SEVERITY_INFO;
        }
        $this->parentId = $parentId;
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'websites_scan';
    }

    /**
     * Return entity type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('Websites Scan', 'duplicator-pro');
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
                return __('Scan Error', 'duplicator-pro');
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
                    return __('Scan Started', 'duplicator-pro');
                }
            case self::SUB_TYPE_END:
                return sprintf(
                    __('Scan completed: %1$d files, %2$d directories; size: %3$s', 'duplicator-pro'),
                    $this->data['fileCount'],
                    $this->data['dirCount'],
                    SnapString::byteSize((int)$this->data['size'])
                );
            default:
                return __('Scan', 'duplicator-pro');
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
                <strong><?php esc_html_e('Components:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html(BuildComponents::displayComponentsList($this->data['components'], ", ")); ?>
                </span>
            </div>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Filter On:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['filterOn'] ? __('Yes', 'duplicator-pro') : __('No', 'duplicator-pro')); ?>
                </span>
            </div>
            <?php if ($this->data['filterOn']) : ?>
                <?php if (count($this->data['filterDirs']) > 0) : ?>
                    <div class="dup-log-type-wrapper">
                        <strong><?php esc_html_e('Filter Dirs:', 'duplicator-pro'); ?></strong><br>
                        <?php foreach ($this->data['filterDirs'] as $dir) : ?>
                            - <?php echo esc_html($dir); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (count($this->data['filterFiles']) > 0) : ?>
                    <div class="dup-log-type-wrapper">
                        <strong><?php esc_html_e('Filter Files:', 'duplicator-pro'); ?></strong><br>
                        <?php foreach ($this->data['filterFiles'] as $file) : ?>
                            - <?php echo esc_html($file); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (count($this->data['filterExts']) > 0) : ?>
                    <div class="dup-log-type-wrapper">
                        <strong><?php esc_html_e('Filter Exts:', 'duplicator-pro'); ?></strong>
                        <span class="dup-log-type">
                            <?php echo esc_html(implode(', ', $this->data['filterExts'])); ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php
            endif;
            switch ($this->subType) {
                case self::SUB_TYPE_START:
                    $subEvents = array_merge(
                        [$this],
                        self::getList(
                            [
                                'parent_id' => $this->getId(),
                                'order'     => 'ASC',
                                'orderby'   => 'created_at',
                            ]
                        )
                    );
                    ?>
                    <div class="margin-top-1">
                        <?php TplMng::getInstance()->render(
                            'admin_pages/activity_log/parts/sub_table_mini',
                            ['logs' => $subEvents]
                        ); ?>
                    </div>
                    <?php
                    break;
                case self::SUB_TYPE_END:
                    ?>
                    <div class="dup-activity-log-scan-end">
                        <div class="dup-log-type-wrapper">
                            <strong><?php esc_html_e('File Count:', 'duplicator-pro'); ?></strong>
                            <span class="dup-log-type">
                                <?php echo esc_html($this->data['fileCount']); ?>
                            </span>
                        </div>
                        <div class="dup-log-type-wrapper">
                            <strong><?php esc_html_e('Directory Count:', 'duplicator-pro'); ?></strong>
                            <span class="dup-log-type">
                                <?php echo esc_html($this->data['dirCount']); ?>
                            </span>
                        </div>
                        <div class="dup-log-type-wrapper">
                            <strong><?php esc_html_e('Size:', 'duplicator-pro'); ?></strong>
                            <span class="dup-log-type">
                                <?php echo esc_html(SnapString::byteSize((int)$this->data['size'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php
                    break;
            }
            ?>
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
                return __('Scan Error', 'duplicator-pro');
            case self::SUB_TYPE_START:
                return __('Scan Start', 'duplicator-pro');
            case self::SUB_TYPE_END:
                return __('Scan End', 'duplicator-pro');
            default:
                return __('Scan', 'duplicator-pro');
        }
    }
}
