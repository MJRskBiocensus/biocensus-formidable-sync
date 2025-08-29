<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Upgrade;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use Duplicator\Models\TemplateEntity;
use Duplicator\Models\ScheduleEntity;
use DUP_PRO_Secure_Global_Entity;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\ActivityLog\AbstractLogEvent;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\AbstractPackage;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Error;
use Exception;

/**
 * Utility class managing when the plugin is updated
 *
 * DUP_PRO_Upgrade_U
 */
class UpgradeFunctions
{
    const FIRST_VERSION_WITH_NEW_TABLES                     = '4.5.14-beta2';
    const FIRST_VERSION_DEFAULT_PURGE                       = '4.5.20-beta1';
    const FIRST_VERSION_WITH_MULTISITE_DRM                  = '4.5.21-beta1';
    const FIRST_VERSION_WITH_PACKAGE_TYPE                   = '4.5.22-beta3';
    const FIRST_VERSION_WITH_DYNAMIC_GLOBAL_ENTITY_IMPROVED = '4.5.22-beta8';

    const LEGACY_DB_PACKAGES_TABLE_NAME = 'duplicator_pro_packages';
    const LEGACY_DB_ENTITIES_TABLE_NAME = 'duplicator_pro_entities';

    /**
     * This function is executed when the plugin is activated and
     * every time the version saved in the wp_options is different from the plugin version both in upgrade and downgrade.
     *
     * @param false|string $currentVersion current Duplicator version, false if is first installation
     * @param string       $newVersion     new Duplicator Version
     *
     * @return void
     */
    public static function performUpgrade($currentVersion, $newVersion): void
    {
        // *** BEFORE EVERYTHING, INIT DATABASE ***
        self::updateDatabase($currentVersion);

        if ($currentVersion == false) {
            DUP_PRO_Log::trace("PERFORM UPGRADE FROM FIRST INSTALLATION VERSION: " . $newVersion);
        } else {
            DUP_PRO_Log::trace("PERFORM UPGRADE FROM VERSION: " . $currentVersion . " TO " . $newVersion);
        }

        self::storeDupSecureKey($currentVersion);

        // Update Global settings
        self::moveDataToDynamicGlobalEntity($currentVersion);
        // Setup All Directories
        self::updatePackageType($currentVersion);
        self::fixDoubleDefaultStorages();
        //important to run after updateStorages
        self::updateBackupRecordPurgeSettings($currentVersion);
    }

    /**
     * Update database.
     *
     * @param false|string $currentVersion current Duplicator version, false if is first installation
     *
     * @return void
     */
    public static function updateDatabase($currentVersion): void
    {
        self::renameLegacyTablesName($currentVersion);

        // Init tables
        AbstractEntity::initTable();
        AbstractLogEvent::initTable();
        AbstractPackage::initTable();

        // Init entities
        DUP_PRO_Global_Entity::getInstance()->save();
        DynamicGlobalEntity::getInstance()->save();
        StoragesUtil::initDefaultStorage();
        TemplateEntity::createDefault();
        TemplateEntity::getManualTemplate(); // If not exists, create it
    }

    /**
     * Save DUP SECURE KEY
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    protected static function storeDupSecureKey($currentVersion)
    {
        if ($currentVersion !== false && SnapUtil::versionCompare($currentVersion, '4.5.0', '<=', 3)) {
            CryptBlowfish::createWpConfigSecureKey(true, true);
        } else {
            CryptBlowfish::createWpConfigSecureKey(false, false);
        }
    }

    /**
     * Rename legacy tables
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    public static function renameLegacyTablesName($currentVersion): void
    {
        if (
            version_compare($currentVersion, self::FIRST_VERSION_WITH_NEW_TABLES, '>=')
        ) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        // RENAME OLD TABLES BEFORE 4.5.14
        $mapping = [
            $wpdb->base_prefix . self::LEGACY_DB_PACKAGES_TABLE_NAME => DUP_PRO_Package::getTableName(),
            $wpdb->base_prefix . self::LEGACY_DB_ENTITIES_TABLE_NAME => AbstractEntity::getTableName(),
        ];

        foreach ($mapping as $oldTable => $newTable) {
            $oldTableQuery = $wpdb->prepare("SHOW TABLES LIKE %s", $oldTable);
            $newTableQuery = $wpdb->prepare("SHOW TABLES LIKE %s", $newTable);
            if (
                $wpdb->get_var($oldTableQuery) === $oldTable &&
                $wpdb->get_var($newTableQuery) !== $newTable
            ) {
                $wpdb->query("RENAME TABLE `" . esc_sql($oldTable) . "` TO `" . esc_sql($newTable) . "`");
            }
        }
    }

    /**
     * Update legacy package adding type column if empty
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    protected static function updatePackageType($currentVersion)
    {
        if ($currentVersion == false || version_compare($currentVersion, self::FIRST_VERSION_WITH_PACKAGE_TYPE, '>=')) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $table = DUP_PRO_Package::getTableName();
        $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET type = %s WHERE type IS NULL OR type = ''", DUP_PRO_Package::getBackupType()));
    }

    /**
     * Move data to dynamic global entity from secure global entity and global entity
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    protected static function moveDataToDynamicGlobalEntity($currentVersion): void
    {
        if (
            $currentVersion == false ||
            version_compare($currentVersion, self::FIRST_VERSION_WITH_DYNAMIC_GLOBAL_ENTITY_IMPROVED, '>=')
        ) {
            return;
        }

        $global  = DUP_PRO_Global_Entity::getInstance();
        $sGlobal = DUP_PRO_Secure_Global_Entity::getInstance();
        $dGlobal = DynamicGlobalEntity::getInstance();

        if (!is_null($global->basic_auth_enabled) && $global->basic_auth_enabled) {
            $dGlobal->setValBool('basic_auth_enabled', $global->basic_auth_enabled);
            $global->basic_auth_enabled = null;
        }

        if (!is_null($global->basic_auth_user) && strlen($global->basic_auth_user) > 0) {
            $dGlobal->setValString('basic_auth_user', $global->basic_auth_user);
            $global->basic_auth_user = null;
        }

        if (!is_null($global->license_key_visible) && $global->license_key_visible) {
            $dGlobal->setValInt('license_key_visible', $global->license_key_visible);
            $global->license_key_visible = null;
        }

        if (!is_null($sGlobal->lkp) && strlen($sGlobal->lkp) > 0) {
            $dGlobal->setValString('license_key_visible_pwd', $sGlobal->lkp);
            $sGlobal->lkp = null;
        }

        if (!is_null($sGlobal->basic_auth_password) && strlen($sGlobal->basic_auth_password) > 0) {
            $dGlobal->setValString('basic_auth_password', $sGlobal->basic_auth_password);
            $sGlobal->basic_auth_password = null;
        }

        $global->save();
        $sGlobal->save();
        $dGlobal->save();
    }


    /**
     * Removed double default storages
     *
     * @return void
     */
    protected static function fixDoubleDefaultStorages()
    {
        try {
            $defaultStorageId = StoragesUtil::getDefaultStorageId();
            $doubleStorageIds = StoragesUtil::removeDoubleDefaultStorages();

            if ($doubleStorageIds === []) {
                return;
            }

            // Auto assign references to the correct default storage
            ScheduleEntity::listCallback(
                function (ScheduleEntity $schedule) use ($defaultStorageId, $doubleStorageIds): void {
                    $save = false;
                    foreach ($schedule->storage_ids as $key => $storageId) {
                        if (!in_array($storageId, $doubleStorageIds)) {
                            continue;
                        }
                        $schedule->storage_ids[$key] = $defaultStorageId;
                        $save                        = true;
                    }
                    $schedule->storage_ids = array_values(array_unique($schedule->storage_ids));
                    if ($save) {
                        $schedule->save();
                    }
                }
            );

            DUP_PRO_Package::dbSelectByStatusCallback(
                function (DUP_PRO_Package $package) use ($defaultStorageId, $doubleStorageIds): void {
                    $save = false;
                    if (in_array($package->active_storage_id, $doubleStorageIds)) {
                        $package->active_storage_id = $defaultStorageId;
                        $save                       = true;
                    }
                    foreach ($package->upload_infos as $key => $info) {
                        if (!in_array($info->getStorageId(), $doubleStorageIds)) {
                            continue;
                        }
                        $info->setStorageId($defaultStorageId);
                        $save = true;
                    }
                    if ($save) {
                        $package->save();
                    }
                },
                [
                    [
                        'op'     => '>=',
                        'status' => AbstractPackage::STATUS_COMPLETE,
                    ],
                ]
            );
        } catch (Exception | Error $e) {
            DUP_PRO_Log::trace("Error fixing remove double storage: " . $e->getMessage());
            return;
        }
    }

    /**
     * Sets the correct backup purge setting based on previous default local storage settings
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    public static function updateBackupRecordPurgeSettings($currentVersion): void
    {
        if ($currentVersion == false || version_compare($currentVersion, self::FIRST_VERSION_DEFAULT_PURGE, '>=')) {
            return;
        }

        $global = DUP_PRO_Global_Entity::getInstance();
        if (StoragesUtil::getDefaultStorage()->isPurgeEnabled()) {
            $global->setPurgeBackupRecords(AbstractStorageEntity::BACKUP_RECORDS_REMOVE_DEFAULT);
        } else {
            $global->setPurgeBackupRecords(AbstractStorageEntity::BACKUP_RECORDS_REMOVE_NEVER);
        }

        $global->save();
    }
}
