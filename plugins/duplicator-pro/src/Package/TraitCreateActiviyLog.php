<?php

namespace Duplicator\Package;

use DUP_PRO_Log;
use Duplicator\Models\ActivityLog\LogEventBackupCreate;
use Duplicator\Models\ActivityLog\LogEventWebsitesScan;
use Exception;
use Throwable;

/**
 * This trait is used to create an activity log for package creation
 */
trait TraitCreateActiviyLog
{
    /** @var int */
    protected $mainScanLogId = 0;
    /** @var int */
    protected $mainActivityLogId = 0;

    /**
     * Add log event
     *
     * @param int $previousStatus Previous status ENUM AbstractPackage::STATUS_*
     *
     * @return bool True if the log event was added, false otherwise
     */
    protected function addLogEvent(int $previousStatus): bool
    {
        if (!$this instanceof AbstractPackage) {
            throw new Exception('This method can only be called on an instance of AbstractPackage');
        }

        try {
            $onScan = false;
            switch ($previousStatus) {
                case AbstractPackage::STATUS_PRE_PROCESS:
                case AbstractPackage::STATUS_SCANNING:
                case AbstractPackage::STATUS_AFTER_SCAN:
                    $onScan = true;
                    break;
                default:
                    $onScan = false;
                    break;
            }

            switch ($this->getStatus()) {
                case AbstractPackage::STATUS_ERROR:
                    if ($onScan) {
                        if ($this->addScanLogEvent() == false) {
                            throw new Exception('Error adding scan log event');
                        }
                    } else {
                        if ($this->addBuildLogEvent() == false) {
                            throw new Exception('Error adding build log event');
                        }
                    }
                    break;
                case AbstractPackage::STATUS_PRE_PROCESS:
                case AbstractPackage::STATUS_SCANNING:
                    $this->mainScanLogId = 0;
                    // Continue with the next status
                case AbstractPackage::STATUS_SCAN_VALIDATION:
                case AbstractPackage::STATUS_AFTER_SCAN:
                    if ($this->addScanLogEvent() == false) {
                        throw new Exception('Error adding scan log event');
                    }
                    break;
                case AbstractPackage::STATUS_REQUIREMENTS_FAILED:
                case AbstractPackage::STATUS_STORAGE_FAILED:
                case AbstractPackage::STATUS_STORAGE_CANCELLED:
                case AbstractPackage::STATUS_PENDING_CANCEL:
                case AbstractPackage::STATUS_BUILD_CANCELLED:
                case AbstractPackage::STATUS_START:
                case AbstractPackage::STATUS_DBSTART:
                case AbstractPackage::STATUS_DBDONE:
                case AbstractPackage::STATUS_ARCSTART:
                case AbstractPackage::STATUS_ARCVALIDATION:
                case AbstractPackage::STATUS_ARCDONE:
                case AbstractPackage::STATUS_COPIEDPACKAGE:
                case AbstractPackage::STATUS_STORAGE_PROCESSING:
                case AbstractPackage::STATUS_COMPLETE:
                    if ($this->addBuildLogEvent() == false) {
                        throw new Exception('Error adding build log event');
                    }
                    break;
                default:
                    throw new Exception('Invalid status: ' . $this->getStatus());
            }
            return true;
        } catch (Throwable $e) {
            DUP_PRO_Log::traceException($e, 'Error adding log event');
            return false;
        }
    }

    /**
     * Add scan log event
     *
     * @return bool True if the log event was added, false otherwise
     */
    protected function addScanLogEvent(): bool
    {
        if (!$this instanceof AbstractPackage) {
            throw new Exception('This method can only be called on an instance of AbstractPackage');
        }

        $statusesToLog = [
            AbstractPackage::STATUS_SCANNING,
            AbstractPackage::STATUS_AFTER_SCAN,
            AbstractPackage::STATUS_ERROR,
        ];

        if (!in_array($this->getStatus(), $statusesToLog)) {
            return true;
        }

        $updateMainScanLogId = ($this->mainScanLogId === 0);
        switch ($this->getStatus()) {
            case AbstractPackage::STATUS_SCANNING:
                $status = LogEventWebsitesScan::SUB_TYPE_START;
                break;
            case AbstractPackage::STATUS_AFTER_SCAN:
                $status = LogEventWebsitesScan::SUB_TYPE_END;
                break;
            case AbstractPackage::STATUS_ERROR:
            default:
                $status = LogEventWebsitesScan::SUB_TYPE_ERROR;
                break;
        }

        $activityLog = new LogEventWebsitesScan($this, $status, $this->mainScanLogId);
        if ($activityLog->save() == false) {
            return false;
        }
        if ($updateMainScanLogId) {
            $this->mainScanLogId = $activityLog->getId();
        } else {
            // Set the worst severity for the main scan log
            $mainLog = LogEventWebsitesScan::getById($this->mainScanLogId);
            if ($mainLog instanceof LogEventWebsitesScan && $activityLog->getSeverity() > $mainLog->getSeverity()) {
                $mainLog->setSeverity($activityLog->getSeverity());
            }
        }
        return true;
    }


    /**
     * Method to add a log event
     *
     * @return bool True if the log event was added, false otherwise
     */
    protected function addBuildLogEvent(): bool
    {
        if (!$this instanceof AbstractPackage) {
            throw new Exception('This method can only be called on an instance of AbstractPackage');
        }

        $statusesToLog = [
            AbstractPackage::STATUS_REQUIREMENTS_FAILED,
            AbstractPackage::STATUS_STORAGE_FAILED,
            AbstractPackage::STATUS_STORAGE_CANCELLED,
            AbstractPackage::STATUS_BUILD_CANCELLED,
            AbstractPackage::STATUS_ERROR,
            AbstractPackage::STATUS_START,
            AbstractPackage::STATUS_DBSTART,
            AbstractPackage::STATUS_ARCSTART,
            AbstractPackage::STATUS_STORAGE_PROCESSING,
            AbstractPackage::STATUS_COMPLETE,
        ];

        if (!in_array($this->getStatus(), $statusesToLog)) {
            return true;
        }

        $updateMainActivityLogId = ($this->mainActivityLogId === 0);
        $activityLog             = new LogEventBackupCreate($this, $this->mainActivityLogId);
        if ($activityLog->save() == false) {
            return false;
        }
        if ($updateMainActivityLogId) {
            // Update the main activity log id only if it is not already set
            $this->mainActivityLogId = $activityLog->getId();
        } else {
            // Set the worst severity for the main activity log
            $mainLog = LogEventBackupCreate::getById($this->mainActivityLogId);
            if ($mainLog instanceof LogEventBackupCreate && $activityLog->getSeverity() > $mainLog->getSeverity()) {
                $mainLog->setSeverity($activityLog->getSeverity());
            }
        }
        return true;
    }
}
