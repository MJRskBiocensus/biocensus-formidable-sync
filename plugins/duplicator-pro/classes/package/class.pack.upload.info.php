<?php

use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\Local\DefaultLocalStorage;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\Storages\UnknownStorage;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

defined("ABSPATH") or die("");
abstract class DUP_PRO_Upload_Status
{
    const Pending   = 0;
    const Running   = 1;
    const Succeeded = 2;
    const Failed    = 3;
    const Cancelled = 4;
}

// Tracks the progress of the Backup with relation to a specific storage provider
// Used to track a specific upload as well as later report on its' progress
class DUP_PRO_Package_Upload_Info
{
    /** @var int<-1,max> */
    protected $storage_id = -1;
    /** @var class-string<AbstractStorageEntity> */
    protected $storageClass = AbstractStorageEntity::class;
    /** @var int */
    public $archive_offset = 0;
    /** @var bool Next byte of archive to copy */
    public $copied_installer = false;
    /** @var bool Whether installer has been copied */
    public $copied_archive = false;
    /** @var float Whether archive has been copied */
    public $progress = 0;
    /** @var int 0-100 where this particular storage is at */
    public $num_failures = 0;
    /** @var bool */
    protected $failed = false;
    /** @var bool true if transfer was cancelled */
    protected $cancelled = false;
    /** @var scalar */
    public $upload_id;
    /** @var int */
    public $failure_count = 0;
    /** @var mixed */
    public $data = '';
    /** @var mixed */
    public $data2 = '';
    // Storage specific data
    // Log related properties - these all SHOULD be public but since we need to json_encode them they have to be public. Ugh.
    /** @var bool */
    public $has_started = false;
    /** @var string */
    public $status_message_details = '';
    // Details about the storage run (success or failure)
    /** @var int */
    public $started_timestamp = 0;
    /** @var int */
    public $stopped_timestamp = 0;
    /** @var mixed[] chunk iterator data */
    public $chunkPosition = [];
    /** @var ?AbstractStorageEntity */
    protected $storage;
    /** @var bool */
    private $packageExists = true;
    /** @var bool */
    private $isDownloadFromRemote = false;
    /** @var array<string,mixed> Copy to persistance extra data */
    public $copyExtraData = [];

    /**
     * Class constructor
     *
     * @param int                                 $storage_id   The storage id
     * @param class-string<AbstractStorageEntity> $storageClass The storage class
     */
    public function __construct($storage_id, $storageClass = AbstractStorageEntity::class)
    {
        $this->setStorageId($storage_id);
        $this->storageClass = $storageClass;
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string, mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data            = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS | JsonSerialize::JSON_SKIP_CLASS_NAME);
        $data['storage'] = null;
        return $data;
    }

    /**
     * Set the storage id
     *
     * @param int $storage_id The storage id
     *
     * @return void
     */
    public function setStorageId($storage_id): void
    {
        if ($storage_id < 0) {
            $this->storage_id = -1;
        }
        $this->storage_id = (int) $storage_id;
        $this->storage    = null;
    }

    /**
     * Get the storage object
     *
     * @return AbstractStorageEntity
     */
    public function getStorage()
    {
        if ($this->storage === null) {
            if ($this->storage_id == DefaultLocalStorage::OLD_VIRTUAL_STORAGE_ID) {
                // Legacy old Backups use virtual storage id -2
                $this->storage    = StoragesUtil::getDefaultStorage();
                $this->storage_id = $this->storage->getId();
            } else {
                $this->storage = $this->storageClass::getById($this->storage_id);
            }

            if ($this->storage === false) {
                $this->storage = new UnknownStorage();
            }
        }
        return $this->storage;
    }

    /**
     * Get storage id
     *
     * @return int
     */
    public function getStorageId(): int
    {
        // For old Backups, some storage ids are strings
        return (int) $this->storage_id;
    }

    /**
     * Return true if is default storage
     *
     * @return bool
     */
    public function isDefaultStorage(): bool
    {
        $storage = $this->getStorage();
        return ($storage instanceof DefaultLocalStorage);
    }

    /**
     * Return true if is local
     *
     * @return bool
     */
    public function isLocal()
    {
        $storage = $this->getStorage();
        if ($storage instanceof UnknownStorage) {
            return false;
        }
        return $this->getStorage()->isLocal();
    }

    /**
     * Return true if is remote
     *
     * @return bool
     */
    public function isRemote()
    {
        $storage = $this->getStorage();
        if ($storage instanceof UnknownStorage) {
            return false;
        }
        return !$this->getStorage()->isLocal();
    }

    /**
     * Is failed
     *
     * @return bool True if upload has failed
     */
    public function isFailed()
    {
        return $this->failed;
    }

    /**
     * Return true if the upload has started
     *
     * @return bool
     */
    public function has_started()
    {
        return $this->has_started;
    }

    /**
     * Start the upload
     *
     * @return void
     */
    public function start(): void
    {
        $this->has_started       = true;
        $this->started_timestamp = time();
    }

    /**
     * Stop the upload
     *
     * @return void
     */
    public function stop(): void
    {
        $this->stopped_timestamp = time();
    }

    /**
     * Get started timestamp
     *
     * @return int
     */
    public function get_started_timestamp()
    {
        return $this->started_timestamp;
    }

    /**
     * Get stopped timestamp
     *
     * @return int
     */
    public function get_stopped_timestamp()
    {
        return $this->stopped_timestamp;
    }

    /**
     * Get the status text
     *
     * @return string
     */
    public function get_status_text()
    {
        $status      = $this->get_status();
        $status_text = __('Unknown', 'duplicator-pro');
        if ($status == DUP_PRO_Upload_Status::Pending) {
            $status_text = __('Pending', 'duplicator-pro');
        } elseif ($status == DUP_PRO_Upload_Status::Running) {
            $status_text = __('Running', 'duplicator-pro');
        } elseif ($status == DUP_PRO_Upload_Status::Succeeded) {
            $status_text = __('Succeeded', 'duplicator-pro');
        } elseif ($status == DUP_PRO_Upload_Status::Failed) {
            $status_text = __('Failed', 'duplicator-pro');
        } elseif ($status == DUP_PRO_Upload_Status::Cancelled) {
            $status_text = __('Cancelled', 'duplicator-pro');
        }

        return $status_text;
    }

    /**
     * Get the status
     *
     * @return int
     */
    public function get_status(): int
    {
        if ($this->cancelled) {
            $status = DUP_PRO_Upload_Status::Cancelled;
        } elseif ($this->failed) {
            $status = DUP_PRO_Upload_Status::Failed;
        } elseif ($this->has_started() === false) {
            $status = DUP_PRO_Upload_Status::Pending;
        } elseif ($this->has_completed(true)) {
            $status = DUP_PRO_Upload_Status::Succeeded;
        } else {
            $status = DUP_PRO_Upload_Status::Running;
        }

        return $status;
    }

    /**
     * Set the status message details
     *
     * @param string $status_message_details The status message details
     *
     * @return void
     */
    public function set_status_message_details($status_message_details): void
    {
        $this->status_message_details = $status_message_details;
    }

    /**
     * Get the status message
     *
     * @return string
     */
    public function get_status_message(): string
    {
        $message    = '';
        $status     = $this->get_status();
        $storage    = AbstractStorageEntity::getById($this->storage_id);
        $isDownload = $this->isDownloadFromRemote();
        if ($storage !== false) {
            if ($status == DUP_PRO_Upload_Status::Pending) {
                $message = $storage->getPendingText($isDownload);
            } elseif ($status == DUP_PRO_Upload_Status::Failed) {
                $message = $storage->getFailedText($isDownload);
            } elseif ($status == DUP_PRO_Upload_Status::Cancelled) {
                $message = $storage->getCancelledText($isDownload);
            } elseif ($status == DUP_PRO_Upload_Status::Succeeded) {
                $message = $storage->getSuccessText($isDownload);
            } else {
                $message = $storage->getActionText($isDownload);
            }
        } else {
            $message = "Error. Unknown storage id {$this->storage_id}";
            DUP_PRO_Log::trace($message);
        }

        $message_details = $this->status_message_details == '' ? '' : " ($this->status_message_details)";
        return "$message$message_details";
    }

    /**
     * Return true if the upload has completed
     *
     * @param bool $count_only_success If true then only return true if the upload has completed successfully
     *
     * @return bool
     */
    public function has_completed($count_only_success = false)
    {
        $retval = false;
        if ($count_only_success) {
            $retval = (($this->failed == false) && ($this->cancelled == false) && ($this->copied_installer && $this->copied_archive));
        } else {
            $retval = $this->failed || ($this->copied_installer && $this->copied_archive) || $this->cancelled;
        }

        if ($retval && ($this->stopped_timestamp == null)) {
            // Having to set stopped this way because we aren't OO and allow everyone to set failed/other flags so impossible to know exactly when its done
            $this->stop();
        }

        return $retval;
    }

    /**
     * Increase the failure count
     *
     * @return void
     */
    public function increaseFailureCount(): void
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        $this->failure_count++;
        DUP_PRO_Log::infoTrace("Failure count increasing to $this->failure_count [Storage Id: $this->storage_id]");
        if ($this->failure_count > $global->max_storage_retries) {
            DUP_PRO_Log::infoTrace("* Failure count reached to max level, Storage Status updated to failed [Storage Id: $this->storage_id]");
            $this->uploadFailed();
        }
    }

    /**
     * True if transfer was cancelled
     *
     * @return bool true if cancelled
     */
    public function isCancelled()
    {
        return $this->cancelled;
    }

    /**
     * Cancell the upload
     *
     * @return void
     */
    public function cancelTransfer(): void
    {
        if ($this->cancelled === true) {
            return;
        }

        do_action('duplicator_pro_transfer_cancelled', $this);

        $this->cancelled = true;
    }

    /**
     * Fail the upload without retry again.
     *
     * @return void
     */
    public function uploadFailed(): void
    {
        if ($this->failed === true) {
            return;
        }

        do_action('duplicator_pro_transfer_failed', $this);

        $this->failed = true;
    }

    /**
     * Return true if it's a download from remote
     *
     * @return bool
     */
    public function isDownloadFromRemote()
    {
        return $this->isDownloadFromRemote;
    }

    /**
     * Set download from remote
     *
     * @param bool $isDownloadFromRemote True if download from remote
     *
     * @return void
     */
    public function setDownloadFromRemote(bool $isDownloadFromRemote): void
    {
        $this->isDownloadFromRemote = $isDownloadFromRemote;
    }

    /**
     * Set Backup exists
     *
     * @param bool $packageExists True if package exists
     *
     * @return void
     */
    public function setPackageExists($packageExists): void
    {
        $this->packageExists = $packageExists;
    }

    /**
     * Get Backup exists
     *
     * @return bool
     */
    public function packageExists()
    {
        return $this->packageExists;
    }
}
