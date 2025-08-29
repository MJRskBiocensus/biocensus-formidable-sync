<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages\Local;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use Duplicator\Package\AbstractPackage;
use DUP_PRO_Package_Upload_Info;
use Duplicator\Core\Upgrade\UpgradeFunctions;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Exception;
use wpdb;

class DefaultLocalStorage extends LocalStorage
{
    // Used on old Backups before 4.5.13
    const OLD_VIRTUAL_STORAGE_ID = -2;
    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->name  = __('Default', 'duplicator-pro');
        $this->notes = __('The default location for storage on this server.', 'duplicator-pro');
    }

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultConfig(): array
    {
        $config = parent::getDefaultConfig();
        return array_merge(
            $config,
            [
                'storage_folder'    => DUPLICATOR_PRO_SSDIR_PATH,
                'max_packages'      => 20,
                'filter_protection' => true,
            ]
        );
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport(array $data, string $dataVersion, array $extraData = []): bool
    {
        unset($data['config']['storage_folder']);
        return parent::settingsImport($data, $dataVersion, $extraData);
    }

    /**
     * Wakeup method
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();
        // Force storage folder value, it can be changed by user and must be updated after migration
        $this->config['storage_folder'] = DUPLICATOR_PRO_SSDIR_PATH;
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return -2;
    }

    /**
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Default Local', 'duplicator-pro');
    }

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon(): string
    {
        return '<i class="far fa-hdd fa-fw"></i>';
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority(): int
    {
        return 0;
    }

    /**
     * Is editable
     *
     * @return bool
     */
    public static function isDefault(): bool
    {
        return true;
    }

    /**
     * Is type selectable
     *
     * @return bool
     */
    public static function isSelectable(): bool
    {
        return false;
    }

    /**
     * Returns the config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getConfigFieldsData(): array
    {
        return $this->getDefaultConfigFieldsData();
    }

    /**
     * Returns the default config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfigFieldsData(): array
    {
        return [
            'storage'       => $this,
            'maxPackages'   => $this->config['max_packages'],
            'storageFolder' => $this->config['storage_folder'],
        ];
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    protected function getConfigFieldsTemplatePath(): string
    {
        return 'admin_pages/storages/configs/local_default';
    }

    /**
     * Update data from http request, this method don't save data, just update object properties
     *
     * @param string $message Message
     *
     * @return bool True if success and all data is valid, false otherwise
     */
    public function updateFromHttpRequest(&$message = ''): bool
    {
        // Don't call parent, default properties are not editable
        $this->notes                  = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
        $this->config['max_packages'] = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_default_store_files', 20);

        $message = __('Default local storage updated.', 'duplicator-pro');
        return true;
    }

    /**
     * Copy from default
     *
     * @param AbstractPackage             $package     the Backup
     * @param DUP_PRO_Package_Upload_Info $upload_info the upload info
     *
     * @return void
     */
    public function copyFromDefault(AbstractPackage $package, DUP_PRO_Package_Upload_Info $upload_info): void
    {
        DUP_PRO_Log::infoTrace("SUCCESS: Backup is in default location: " . DUPLICATOR_PRO_SSDIR_PATH);
        // It's the default local storage location so do nothing - it's already there
        $upload_info->copied_archive   = true;
        $upload_info->copied_installer = true;
        $package->update();
    }

    /**
     * Purge old Backups
     *
     * @param array<string> $keepList List of Backups to keep
     *
     * @return false|string[] false on failure or array of deleted Backups
     */
    public function purgeOldPackages(array $keepList = [])
    {
        if (($packagesList = parent::purgeOldPackages($keepList)) === false) {
            return false;
        }

        $global = DUP_PRO_Global_Entity::getInstance();
        if (
            $global->getPurgeBackupRecords() !== self::BACKUP_RECORDS_REMOVE_DEFAULT ||
            count($packagesList) == 0
        ) {
            return $packagesList;
        }

        try {
            DUP_PRO_Log::infoTrace("Clean up backup table removing old Backups.");

            /** @var wpdb $wpdb*/
            global $wpdb;

            $escapedList = array_map(
                fn($path) => esc_sql(basename($path)),
                $packagesList
            );

            // Purge Backup record logic
            $table       = AbstractPackage::getTableName();
            $max_created = $wpdb->get_var(
                "SELECT max(created) FROM `" . $table . "` WHERE `archive_name` IN ('" . implode("', '", $escapedList) . "')"
            );
            $sql         = $wpdb->prepare("DELETE FROM " . $table . " WHERE created <= %s AND status >= %d", $max_created, AbstractPackage::STATUS_COMPLETE);
            $wpdb->query($sql);
        } catch (Exception $e) {
            DUP_PRO_Log::infoTraceException($e, "FAIL: purge Backup for storage " . $this->name . '[ID: ' . $this->id . '] type:' . static::getStypeName());
            return false;
        }

        return  $packagesList;
    }

    /**
     * Delete current entity
     *
     * @return bool True on success, or false on error.
     */
    public function delete(): bool
    {
        // Don't delete default storage
        return false;
    }

    /**
     * True if purge is enabled
     *
     * @return bool
     */
    public function isPurgeEnabled(): bool
    {
        return isset($this->config['purge_packages']) && $this->config['purge_packages'];
    }

    /**
     * Creates the snapshot directory if it doesn't already exists
     *
     * @param bool $skipIfExists If true it will skip creating the directory if it already exists
     *
     * @return bool True if success, false otherwise
     */
    public function initStorageDirectory($skipIfExists = false): bool
    {
        $exists = is_dir($this->config['storage_folder']);

        if (parent::initStorageDirectory($skipIfExists) === false) {
            return false;
        }

        if ($skipIfExists && $exists) {
            return true;
        }

        $path_ssdir_tmp        = SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH_TMP);
        $path_ssdir_tmp_import = SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH_TMP_IMPORT);
        $path_plugin           = SnapIO::safePath(DUPLICATOR____PATH);
        $path_import           = SnapIO::safePath(DUPLICATOR_PRO_PATH_IMPORTS);

        //snapshot tmp directory
        wp_mkdir_p($path_ssdir_tmp);
        SnapIO::chmod($path_ssdir_tmp, 'u+rwx');
        SnapIO::createSilenceIndex($path_ssdir_tmp);

        wp_mkdir_p($path_ssdir_tmp_import);
        SnapIO::chmod($path_ssdir_tmp_import, 'u+rwx');
        SnapIO::createSilenceIndex($path_ssdir_tmp_import);

        wp_mkdir_p($path_import);
        SnapIO::chmod($path_import, 'u+rwx');
        SnapIO::createSilenceIndex($path_import);

        //plugins dir/files
        SnapIO::chmod($path_plugin . 'files', 'u+rwx');

        //Handle missing index.php in old directories
        self::addIndexToOldDirs();

        return true;
    }

    /**
     * Create index.php file in old directories
     *
     * @return bool True if success, false otherwise
     */
    protected static function addIndexToOldDirs()
    {
        $paths = [
            SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH_INSTALLER),
            SnapIO::safePathTrailingslashit(DUPLICATOR_PRO_SSDIR_PATH_TMP) . 'extras',
            SnapIO::safePath(DUPLICATOR_PRO_PATH_RECOVER),
        ];

        $customRecoveryPath = DUP_PRO_Global_Entity::getInstance()->getRecoveryCustomPath();
        if (strlen($customRecoveryPath) > 0) {
            $paths[] = SnapIO::safePath($customRecoveryPath);
        }

        $success = true;
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            if (!SnapIO::createSilenceIndex($path)) {
                DUP_PRO_Log::trace("ERROR: Unable to create index.php file in {$path}");
                $success = false;
            }
        }

        return $success;
    }
}
