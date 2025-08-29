<?php

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\MigrationMng;
use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\Crypt\CryptBlowfish;

require_once(DUPLICATOR____PATH . '/classes/package/class.pack.upload.info.php');
require_once(DUPLICATOR____PATH . '/classes/package/class.pack.multisite.php');

/**
 * Class used to store and process all Backup logic
 *
 * @package Dupicator\classes
 */
class DUP_PRO_Package extends AbstractPackage
{
    /**
     * Get backup type
     *
     * @return string
     */
    public static function getBackupType(): string
    {
        return PackageUtils::DEFAULT_BACKUP_TYPE;
    }

    /**
     * Cancel all uploads
     *
     * @return void
     */
    public function cancelAllUploads(): void
    {
        DUP_PRO_Log::trace("Cancelling all uploads");
        foreach ($this->upload_infos as $upload_info) {
            if ($upload_info->has_completed() == false) {
                $upload_info->cancelTransfer();
            }
        }
    }

    /**
     * Processes the Backup after the build
     *
     * @param int                  $stage   0 for failure at build, 1 for failure during storage phase
     * @param bool                 $success true if build was successful
     * @param array<string, mixed> $tests   Tests results
     *
     * @return void
     */
    protected function postScheduledBuildProcessing($stage, $success, $tests = [])
    {
        if ($this->schedule_id == -1) {
            return;
        }

        parent::postScheduledBuildProcessing($stage, $success, $tests);

        try {
            $this->sendBuildEmail($stage, $success);
        } catch (Exception $ex) {
            DUP_PRO_Log::trace($ex->getMessage());
        }
    }

    /**
     * What % along we are in the given status level
     *
     * @return float
     */
    public function getStatusProgress(): float
    {
        switch ($this->getStatus()) {
            case AbstractPackage::STATUS_DBSTART:
            case AbstractPackage::STATUS_DBDONE:
                return $this->getProgressPercent();
            case AbstractPackage::STATUS_ARCSTART:
                if (
                    $this->build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::Shell_Exec ||
                    $this->ziparchive_mode == DUP_PRO_ZipArchive_Mode::SingleThread
                ) {
                    // Make an estimation on engine without chunks.
                    // This is a workaround to avoid the progress bar from showing 0% when the engine is running.
                    // Amount of time passing before we give them a 1%
                    $time_per_percent       = 11;
                    $thread_age             = time() - $this->build_progress->thread_start_time;
                    $total_percentage_delta = AbstractPackage::STATUS_ARCDONE - AbstractPackage::STATUS_ARCSTART;

                    if ($thread_age > ($total_percentage_delta * $time_per_percent)) {
                        // It's maxed out so just give them the done condition for the rest of the time
                        return AbstractPackage::STATUS_ARCDONE;
                    } else {
                        $percentage_delta = (int) ($thread_age / $time_per_percent);

                        return AbstractPackage::STATUS_ARCSTART + $percentage_delta;
                    }
                } else {
                    return $this->getProgressPercent();
                }
            case AbstractPackage::STATUS_ARCVALIDATION:
            case AbstractPackage::STATUS_ARCDONE:
            case AbstractPackage::STATUS_COPIEDPACKAGE:
                return $this->getProgressPercent();
            case AbstractPackage::STATUS_STORAGE_PROCESSING:
                if ($this->isDownloadInProgress()) {
                    $activeInfo = null;
                    foreach ($this->upload_infos as $uInfo) {
                        if ($uInfo->isDownloadFromRemote() && $uInfo->has_completed() === false) {
                            $activeInfo = $uInfo;
                        }
                    }

                    if (is_null($activeInfo)) {
                        return 0.0;
                    }

                    return round($activeInfo->progress, 1);
                }

                $completed_infos  = 0;
                $total_infos      = count($this->upload_infos);
                $partial_progress = 0;
                foreach ($this->upload_infos as $upload_info) {
                    if ($upload_info->isDownloadFromRemote()) {
                        continue;
                    }

                    if ($upload_info->has_completed()) {
                        $completed_infos++;
                    } else {
                        $partial_progress += $upload_info->progress;
                    }
                }

                DUP_PRO_Log::trace("partial progress $partial_progress");
                DUP_PRO_Log::trace("completed infos before $completed_infos");
                $bcd = ($partial_progress / (float) 100);
                DUP_PRO_Log::trace("partial progress info contributor=$bcd");
                $completed_infos += $bcd;
                DUP_PRO_Log::trace("completed infos after $completed_infos");
                // Add on the particulars where the latest guy is at
                // return 100 * (bcdiv($completed_infos, $total_infos, 2));
                return SnapUtil::percentage($completed_infos, $total_infos, 0);
            case AbstractPackage::STATUS_COMPLETE:
                return 100.0;
            case AbstractPackage::STATUS_REQUIREMENTS_FAILED:
            case AbstractPackage::STATUS_STORAGE_FAILED:
            case AbstractPackage::STATUS_STORAGE_CANCELLED:
            case AbstractPackage::STATUS_PENDING_CANCEL:
            case AbstractPackage::STATUS_BUILD_CANCELLED:
            case AbstractPackage::STATUS_ERROR:
            case AbstractPackage::STATUS_PRE_PROCESS:
            case AbstractPackage::STATUS_SCANNING:
            case AbstractPackage::STATUS_SCAN_VALIDATION:
            case AbstractPackage::STATUS_AFTER_SCAN:
            case AbstractPackage::STATUS_START:
            default:
                return 0.0;
        }
    }

    /**
     * Get display size
     *
     * @return string
     */
    public function getDisplaySize()
    {
        $status = $this->getStatus();
        $global = DUP_PRO_Global_Entity::getInstance();
        if ($status == 100 || $this->transferWasInterrupted()) {
            return SnapString::byteSize($this->Archive->Size);
        } elseif (
            ($this->build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::DupArchive) &&
            ($status >= self::STATUS_ARCVALIDATION) &&
            ($status <= self::STATUS_ARCDONE)
        ) {
            return __('Validating', 'duplicator-pro');
        } elseif (
            (($this->build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::Shell_Exec) ||
                (($this->build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::ZipArchive) &&
                    ($global->ziparchive_mode == DUP_PRO_ZipArchive_Mode::SingleThread))) &&
            ($status <= self::STATUS_ARCDONE) &&
            ($status >= self::STATUS_PRE_PROCESS)
        ) {
            return __('Building', 'duplicator-pro');
        } else {
            $size              = 0;
            $temp_archive_path = DUPLICATOR_PRO_SSDIR_PATH_TMP . '/' . $this->getArchiveFilename();
            $archive_path      = DUPLICATOR_PRO_SSDIR_PATH . '/' . $this->getArchiveFilename();
            if (file_exists($archive_path)) {
                $size = @filesize($archive_path);
            } elseif (file_exists($temp_archive_path)) {
                $size = @filesize($temp_archive_path);
            } else {
                //  DUP_PRO_Log::trace("Couldn't find archive for file size");
            }
            return SnapString::byteSize($size);
        }
    }

    /**
     * @return string Url to the Backup's log file
     */
    public function getLogUrl(): string
    {
        $link_log = $this->StoreURL . $this->getLogFilename();
        if (!file_exists($this->getSafeLogFilepath())) {
            // backward compatibility
            $link_log = "{$this->StoreURL}{$this->getNameHash()}.log";
        }

        return $link_log;
    }

    /**
     * Get dump filename
     *
     * @return string
     */
    public function getDumpFilename(): string
    {
        return $this->getNameHash() . '_dump.txt';
    }

    /**
     * Get safe log filepath
     *
     * @return string
     */
    public function getSafeLogFilepath()
    {
        $filename = $this->getLogFilename();
        return SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH . "/$filename");
    }

    /**
     * Dump file exists
     *
     * @return bool
     */
    public function dumpFileExists(): bool
    {
        $filename = $this->getDumpFilename();
        $filepath = SnapIO::safePath(DUPLICATOR_PRO_DUMP_PATH . "/$filename");
        return file_exists($filepath);
    }

    /**
     * Get upload info for storage id
     *
     * @param int $storage_id storage id
     *
     * @return ?DUP_PRO_Package_Upload_Info upload info or null if not found
     */
    public function getUploadInfoForStorageId($storage_id)
    {
        $selected_upload_info = null;
        foreach ($this->upload_infos as $upload_info) {
            if ($upload_info->getStorageId() == $storage_id) {
                $selected_upload_info = &$upload_info;
                break;
            }
        }

        return $selected_upload_info;
    }

    /**
     * Marks the backup as not existing in the storage. If the removeBackup flag is set to true
     * and the backup does not exist in any storage, the backup record will be removed from the database.
     *
     * @param int  $storageId    Storage ID
     * @param bool $removeBackup If true, the backup record will be removed from the database
     *                           if it does not exist in any storage
     *
     * @return bool True if the backup record was removed from the database
     */
    public function unsetStorage($storageId, $removeBackup = false): bool
    {
        if (($uploadInfo = $this->getUploadInfoForStorageId($storageId)) !== null) {
            $uploadInfo->setPackageExists(false);
            if (!$this->update()) {
                DUP_PRO_Log::trace("Failed to update backup record with ID: " . $this->getId());
                return false;
            }
        }

        if (!$removeBackup || $this->hasValidStorage()) {
            return false;
        }

        if (!$this->delete()) {
            DUP_PRO_Log::trace("Failed to remove Backup record with ID: " . $this->getId());
            return false;
        }

        return true;
    }

    /**
     * Get local Backup file
     *
     * @param int $file_type AbstractPackage::FILE_TYPE_* Enum
     *
     * @return bool|string file path or false if don't exists
     */
    public function getLocalPackageFilePath(int $file_type)
    {
        switch ($file_type) {
            case self::FILE_TYPE_INSTALLER:
                $fileName = $this->Installer->getInstallerLocalName();
                break;
            case self::FILE_TYPE_ARCHIVE:
                $fileName = $this->getArchiveFilename();
                break;
            case self::FILE_TYPE_LOG:
                $fileName = $this->getLogFilename();
                break;
            default:
                throw new Exception("File type $file_type not supported");
        }

        //First check if default file exists
        if (file_exists($filePath = SnapIO::trailingslashit(DUPLICATOR_PRO_SSDIR_PATH) . $fileName)) {
            return SnapIO::safePath($filePath);
        }

        foreach ($this->getLocalStorages() as $localStorage) {
            $filePath = SnapIO::trailingslashit($localStorage->getLocationString()) . $fileName;
            if (file_exists($filePath)) {
                return SnapIO::safePath($filePath);
            }
        }

        return false;
    }

    /**
     * @param int $fileType AbstractPackage::FILE_TYPE_* Enum
     *
     * @return string URL at which the file can be downloaded
     */
    public function getLocalPackageFileURL(int $fileType): string
    {
        if ($fileType == self::FILE_TYPE_LOG) {
            return $this->getLogUrl();
        }

        if (!$this->getLocalPackageFilePath($fileType)) {
            return "";
        }

        switch ($fileType) {
            case self::FILE_TYPE_INSTALLER:
                return $this->getLocalPackageAjaxDownloadURL(self::FILE_TYPE_INSTALLER);
            case self::FILE_TYPE_ARCHIVE:
                return file_exists(SnapIO::trailingslashit(DUPLICATOR_PRO_SSDIR_PATH) . $this->getArchiveFilename())
                    ? $this->Archive->getURL()
                    : $this->getLocalPackageAjaxDownloadURL(self::FILE_TYPE_ARCHIVE);
            default:
                throw new Exception("File type $fileType not supported");
        }
    }

    /**
     * Get download security token
     *
     * @param string $hash hash
     *
     * @return string
     */
    public static function getLocalPackageAjaxDownloadToken(string $hash): string
    {
        return md5($hash . CryptBlowfish::getDefaultKey());
    }

    /**
     * Get local Backup ajax download url
     *
     * @param int $fileType AbstractPackage::FILE_TYPE_* Enum
     *
     * @return string URL at which the file can be downloaded
     */
    public function getLocalPackageAjaxDownloadURL(int $fileType): string
    {
        return admin_url('admin-ajax.php') . "?" . http_build_query([
            'action'   => 'duplicator_pro_download_package_file',
            'hash'     =>  $this->hash,
            'token'    =>  static::getLocalPackageAjaxDownloadToken($this->hash),
            'fileType' => $fileType,
        ]);
    }

    /**
     * Use only in extreme cases to get rid of a runaway Backup
     *
     * @param int $id Backup ID
     *
     * @return boolean
     */
    public static function forceDelete($id)
    {
        $ret_val = false;
        global $wpdb;
        $tblName   = static::getTableName();
        $getResult = $wpdb->get_results($wpdb->prepare("SELECT name, hash FROM `{$tblName}` WHERE id = %d", $id), ARRAY_A);
        if ($getResult) {
            $row       = $getResult[0];
            $name_hash = "{$row['name']}_{$row['hash']}";
            $delResult = $wpdb->query($wpdb->prepare("DELETE FROM `{$tblName}` WHERE id = %d", $id));
            if ($delResult != 0) {
                $ret_val = true;
                static::deleteDefaultLocalFiles($name_hash, true);
            }
        }

        return $ret_val;
    }

    /**
     * Return true if contains non default storage
     *
     * @return bool
     */
    public function containsNonDefaultStorage(): bool
    {
        $defStorageId = StoragesUtil::getDefaultStorageId();
        foreach ($this->upload_infos as $upload_info) {
            if ($upload_info->getStorageId() === $defStorageId) {
                continue;
            }

            if (($storage = AbstractStorageEntity::getById($upload_info->getStorageId())) === false) {
                DUP_PRO_Log::traceError("Package refers to a storage provider that no longer exists - " . $upload_info->getStorageId());
                continue;
            }

            return true;
        }
        return false;
    }

    /**
     * Quickly determine without going through the overhead of creating Backup objects
     *
     * @return bool
     */
    public static function isPackageRunning(): bool
    {
        $ids = DUP_PRO_Package::getIdsByStatus(
            [
                [
                    'op'     => '>=',
                    'status' => self::STATUS_PRE_PROCESS,
                ],
                [
                    'op'     => '<',
                    'status' => self::STATUS_COMPLETE,
                ],
            ]
        );
        return count($ids) > 0;
    }

    /**
     * Returns true if there are packages that are in the process of being cancelled
     *
     * @return bool
     */
    public static function isPackageCancelling(): bool
    {
        return count(static::getPendingCancellations()) > 0;
    }

    /**
     * Check is Brand is properly prepered
     *
     * @return array<string,mixed>
     */
    public static function isActiveBrandPrepared(): array
    {
        $manual_template = TemplateEntity::getManualTemplate();
        $brand           = BrandEntity::getByIdOrDefault((int) $manual_template->installer_opts_brand);
        if (is_array($brand->attachments)) {
            $attachments = count($brand->attachments);
            $exists      = [];
            if ($attachments > 0) {
                $installer = DUPLICATOR____PATH . '/installer/dup-installer/assets/images/brand';
                if (file_exists($installer) && is_dir($installer)) {
                    foreach ($brand->attachments as $attachment) {
                        if (file_exists("{$installer}{$attachment}")) {
                            $exists[] = "{$installer}{$attachment}";
                        }
                    }
                }
            }
            //return ($attachments == count($exists));

            return [
                'LogoAttachmentExists' => ($attachments > 0),
                'LogoCount'            => $attachments,
                'LogoFinded'           => count($exists),
                'LogoImageExists'      => ($attachments == count($exists)),
                'LogoImages'           => $exists,
                'Name'                 => $brand->name,
                'Notes'                => $brand->notes,
            ];
        }


        return [
            'LogoAttachmentExists' => false,
            'LogoCount'            => 0,
            'LogoFinded'           => 0,
            'LogoImageExists'      => true,
            'LogoImages'           => [],
            'Name'                 => __('Default', 'duplicator-pro'),
            'Notes'                => __('The default content used when a brand is not defined.', 'duplicator-pro'),
        ];
    }

    /**
     * Update the Backup migration flag
     *
     * @return void
     */
    public function updateMigrateAfterInstallFlag(): void
    {
        $this->updatePackageFlags();
        $this->flags = array_diff(
            $this->flags,
            [self::FLAG_CREATED_AFTER_RESTORE]
        );
        $data        = MigrationMng::getMigrationData();
        // check if package id is set for old versions before 4.5.14
        if ($data->restoreBackupMode && $data->packageId > 0) {
            $installTime = strtotime($data->installTime);
            $created     = strtotime($this->created);
            if (
                $this->getId() > $data->packageId && // If Backup is create after installer Backup
                $created < $installTime // But berore the installer time
            ) {
                $this->flags[] = self::FLAG_CREATED_AFTER_RESTORE;
            }
        }
        $this->flags = array_values($this->flags);
    }

    /**
     * Processes the Backup after the build
     *
     * @param int  $stage   0 for failure at build, 1 for failure during storage phase
     * @param bool $success true if build was successful
     *
     * @return void
     */
    protected function sendBuildEmail($stage, $success)
    {
        try {
            if ($this->buildEmailSent) {
                return;
            }

            $global = DUP_PRO_Global_Entity::getInstance();
            switch ($global->send_email_on_build_mode) {
                case DUP_PRO_Global_Entity::EMAIL_BUILD_MODE_NEVER:
                    return;
                case DUP_PRO_Global_Entity::EMAIL_BUILD_MODE_ALL:
                    break;
                case DUP_PRO_Global_Entity::EMAIL_BUILD_MODE_FAILURE:
                    if ($success) {
                        return;
                    }
                    break;
                default:
                    return;
            }

            $to = !empty($global->notification_email_address) ? $global->notification_email_address : get_option('admin_email');
            if (empty($to) !== false) {
                throw new Exception("Would normally send a build notification but admin email is empty.");
            }

            if (($schedule = $this->getSchedule()) === null) {
                throw new Exception("Couldn't get schedule by ID {$this->schedule_id} to start post scheduled build processing.");
            }

            DUP_PRO_Log::trace("Attempting to send build notification to $to");
            $data = [
                'success'      => $success,
                'messageTitle' => __('BACKUP SUCCEEDED', 'duplicator-pro'),
                'packageID'    => $this->getId(),
                'packageName'  => $this->getName(),
                'scheduleName' => $schedule->name,
                'storageNames' => array_map(fn(AbstractStorageEntity $s): string => $s->getName(), $this->getStorages()),
                'packagesLink' => ControllersManager::getMenuLink(ControllersManager::PACKAGES_SUBMENU_SLUG, null, null, [], false),
                'logExists'    => file_exists($this->getSafeLogFilepath()),
            ];
            if ($success) {
                $data    = array_merge($data, [
                    'fileCount'   => $this->Archive->FileCount,
                    'packageSize' => SnapString::byteSize($this->Archive->Size),
                    'tableCount'  => $this->Database->info->tablesFinalCount,
                    'sqlSize'     => SnapString::byteSize($this->Database->Size),
                ]);
                $subject = sprintf(__('Backup of %1$s (%2$s) Succeeded', 'duplicator-pro'), home_url(), $schedule->name);
            } else {
                $data['messageTitle']  = __('BACKUP FAILED', 'duplicator-pro') . ' ';
                $data['messageTitle'] .= $stage === 0
                    ? __('DURING BUILD PHASE', 'duplicator-pro')
                    : __('DURING STORAGE PHASE. CHECK SITE FOR DETAILS.', 'duplicator-pro');
                $subject               = sprintf(__('Backup of %1$s (%2$s) Failed', 'duplicator-pro'), home_url(), $schedule->name);
            }

            $message     = \Duplicator\Core\Views\TplMng::getInstance()->render("mail/scheduled-build", $data, false);
            $attachments = $data['logExists'] ? $this->getSafeLogFilepath() : '';

            if (!wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8'], $attachments)) {
                throw new Exception("Problem sending build notification to {$to} regarding Backup {$this->getId()}");
            }

            $this->buildEmailSent = true;
            $this->save();
            DUP_PRO_Log::trace('wp_mail reporting send success');
        } catch (Exception | Error $ex) {
            DUP_PRO_Log::traceException($ex, "Problem sending build notification email");
        }
    }

    /**
     * Get active storage, false if none
     *
     * @return false|AbstractStorageEntity
     */
    public function getActiveStorage()
    {
        if ($this->active_storage_id != -1) {
            if (($storage = AbstractStorageEntity::getById($this->active_storage_id)) === false) {
                DUP_PRO_Log::traceError("Active storage for Backup {$this->getId()} is {$this->active_storage_id} but it's coming back false so resetting.");
                $this->active_storage_id = -1;
                $this->save();
            }
            return $storage;
        } else {
            return false;
        }
    }

    /**
     * Returns true if a download is in progress
     *
     * @return bool
     */
    public function isDownloadInProgress(): bool
    {
        foreach ($this->upload_infos as $upload_info) {
            if ($upload_info->isDownloadFromRemote() && $upload_info->has_completed() === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return Backup life
     *
     * @param string $type can be hours,human,timestamp
     *
     * @return int|string Backup life in hours, timestamp or human readable format
     */
    public function getPackageLife($type = 'timestamp')
    {
        $created = strtotime($this->created);
        $current = strtotime(gmdate("Y-m-d H:i:s"));
        $delta   = $current - $created;

        switch ($type) {
            case 'hours':
                return max(0, floor($delta / 60 / 60));
            case 'human':
                return human_time_diff($created, $current);
            case 'timestamp':
            default:
                return $delta;
        }
    }

    /**
     *  Saves the active options associated with the active(latest) package.
     *
     *  @param ?array<string,mixed> $post The _POST server object
     *
     *  @return void
     *
     *  @see DUP_PRO_Package::GetActive
     **/
    public static function setManualTemplateFromPost(?array $post = null): void
    {
        if (!isset($post)) {
            return;
        }

        $post                  = stripslashes_deep($post);
        $mtemplate             = TemplateEntity::getManualTemplate();
        $mtemplate->components = BuildComponents::getFromInput($post);

        if (isset($post['package_name_format'])) {
            $mtemplate->package_name_format = SnapUtil::sanitize($post['package_name_format']);
        }

        if (isset($post['filter-paths'])) {
            $post_filter_paths               = SnapUtil::sanitizeNSChars($post['filter-paths']);
            $mtemplate->archive_filter_dirs  = DUP_PRO_Archive::parseDirectoryFilter($post_filter_paths);
            $mtemplate->archive_filter_files = DUP_PRO_Archive::parseFileFilter($post_filter_paths);
        } else {
            $mtemplate->archive_filter_dirs  = '';
            $mtemplate->archive_filter_files = '';
        }

        $filter_sites = !empty($post['mu-exclude']) ? $post['mu-exclude'] : '';
        if (isset($post['filter-exts'])) {
            $post_filter_exts               = sanitize_text_field($post['filter-exts']);
            $mtemplate->archive_filter_exts = DUP_PRO_Archive::parseExtensionFilter($post_filter_exts);
        } else {
            $mtemplate->archive_filter_exts = '';
        }

        $tablelist  = isset($post['dbtables-list']) ? SnapUtil::sanitizeNSCharsNewlineTrim($post['dbtables-list']) : '';
        $compatlist = isset($post['dbcompat']) ? implode(',', $post['dbcompat']) : '';
        // PACKAGE
        // Replaces any \n \r or \n\r from the Backup notes
        if (isset($post['package-notes'])) {
            $mtemplate->notes = SnapUtil::sanitizeNSCharsNewlineTrim($post['package-notes']);
        } else {
            $mtemplate->notes = '';
        }

        //MULTISITE
        $mtemplate->filter_sites = $filter_sites;
        //ARCHIVE
        $mtemplate->archive_filter_on    = isset($post['filter-on']);
        $mtemplate->archive_filter_names = isset($post['filter-names']);
        //INSTALLER
        $secureOn = (isset($post['secure-on']) ? (int) $post['secure-on'] : ArchiveDescriptor::SECURE_MODE_NONE);
        switch ($secureOn) {
            case ArchiveDescriptor::SECURE_MODE_NONE:
            case ArchiveDescriptor::SECURE_MODE_INST_PWD:
            case ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT:
                $mtemplate->installer_opts_secure_on = $secureOn;
                break;
            default:
                throw new Exception(__('Select valid secure mode', 'duplicator-pro'));
        }

        $mtemplate->installerPassowrd = isset($post['secure-pass']) ? SnapUtil::sanitizeNSCharsNewlineTrim($post['secure-pass']) : '';
        //BRAND
        $mtemplate->installer_opts_brand     = ((isset($post['installer_opts_brand']) && (int) $post['installer_opts_brand'] > 0) ? (int) $post['installer_opts_brand'] : -1);
        $mtemplate->installer_opts_skip_scan = (isset($post['skipscan']) && 1 == $post['skipscan']);
        //cPanel
        $mtemplate->installer_opts_cpnl_enable    = (isset($post['installer_opts_cpnl_enable']) && 1 == $post['installer_opts_cpnl_enable']);
        $mtemplate->installer_opts_cpnl_host      = isset($post['installer_opts_cpnl_host']) ? sanitize_text_field($post['installer_opts_cpnl_host']) : '';
        $mtemplate->installer_opts_cpnl_user      = isset($post['installer_opts_cpnl_user']) ? sanitize_text_field($post['installer_opts_cpnl_user']) : '';
        $mtemplate->installer_opts_cpnl_db_action = isset($post['installer_opts_cpnl_db_action']) ? sanitize_text_field($post['installer_opts_cpnl_db_action']) : '';
        $mtemplate->installer_opts_cpnl_db_host   = isset($post['installer_opts_cpnl_db_host']) ? sanitize_text_field($post['installer_opts_cpnl_db_host']) : '';
        $mtemplate->installer_opts_cpnl_db_name   = isset($post['installer_opts_cpnl_db_name']) ? sanitize_text_field($post['installer_opts_cpnl_db_name']) : '';
        $mtemplate->installer_opts_cpnl_db_user   = isset($post['installer_opts_cpnl_db_user']) ? sanitize_text_field($post['installer_opts_cpnl_db_user']) : '';
        //Basic
        $mtemplate->installer_opts_db_host = isset($post['installer_opts_db_host']) ? sanitize_text_field($post['installer_opts_db_host']) : '';
        $mtemplate->installer_opts_db_name = isset($post['installer_opts_db_name']) ? sanitize_text_field($post['installer_opts_db_name']) : '';
        $mtemplate->installer_opts_db_user = isset($post['installer_opts_db_user']) ? sanitize_text_field($post['installer_opts_db_user']) : '';
        // DATABASE
        $mtemplate->database_filter_on      = isset($post['dbfilter-on']);
        $mtemplate->databasePrefixFilter    = isset($post['db-prefix-filter']);
        $mtemplate->databasePrefixSubFilter = isset($post['db-prefix-sub-filter']);
        $mtemplate->database_filter_tables  = sanitize_text_field($tablelist);

        $mtemplate->database_compatibility_modes = $compatlist;
        $mtemplate->save();
    }

    /**
     * Check if backup transfer is interrupted
     *
     * @return bool returns true if Backup transfer was canceled or failed
     */
    public function transferWasInterrupted(): bool
    {
        $recentUploadInfos = static::getRecentUploadInfos();
        foreach ($recentUploadInfos as $recentUploadInfo) {
            if ($recentUploadInfo->isFailed() || $recentUploadInfo->isCancelled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get recent unique $uploadInfos with giving highest priority to the latest one uploadInfo
     * if two or more uploadInfo of the same storage type exists
     *
     * @return DUP_PRO_Package_Upload_Info[]
     */
    protected function getRecentUploadInfos(): array
    {
        $uploadInfos    = [];
        $tempStorageIds = [];
        foreach (array_reverse($this->upload_infos) as $upload_info) {
            if (!in_array($upload_info->getStorageId(), $tempStorageIds)) {
                $tempStorageIds[] = $upload_info->getStorageId();
                $uploadInfos[]    = $upload_info;
            }
        }
        return $uploadInfos;
    }
}
