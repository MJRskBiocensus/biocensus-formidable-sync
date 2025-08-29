<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package;

use DateTime;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use Duplicator\Models\TemplateEntity;
use Duplicator\Core\Constants;
use Exception;
use Duplicator\Installer\Models\MigrateData;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Package\Recovery\RecoveryPackage;

class PackageUtils
{
    const DEFAULT_BACKUP_TYPE     = 'Standard';
    const BULK_DELETE_LIMIT_CHUNK = 100;

    /**
     * Restise excecure packages registration
     *
     * @return void
     */
    public static function registerStandardPackageType(): void
    {
        DUP_PRO_Package::registerType();
    }

    /**
     * Update CREATED AFTER INSTALL FLAGS
     *
     * @param MigrateData $migrationData migration data
     *
     * @return void
     */
    public static function updateCreatedAfterInstallFlags(MigrateData $migrationData): void
    {
        if ($migrationData->restoreBackupMode == false) {
            return;
        }

        // Refresh recovery Backup set beforw backup
        $ids = DUP_PRO_Package::dbSelect('FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_DISASTER_SET . '\', `flags`)', 0, 0, '', 'ids');
        if (count($ids)) {
            RecoveryPackage::setRecoveablePackage($ids[0]);
        }

        // Update all backups with created after restore flag or created after install time
        DUP_PRO_Package::dbSelectCallback(
            function (DUP_PRO_Package $package): void {
                $package->updateMigrateAfterInstallFlag();
                $package->save();
            },
            'FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_CREATED_AFTER_RESTORE . '\', `flags`) OR 
            (
                `id` > ' .  $migrationData->packageId . ' AND
                `created` < \'' . esc_sql($migrationData->installTime) . '\'
            )'
        );
    }

    /**
     * Get the number of Backups
     *
     * @param string[] $backupTypes backup types to include, is empty all types are included
     *
     * @return int
     */
    public static function getNumPackages(array $backupTypes = []): int
    {
        $ids = DUP_PRO_Package::getIdsByStatus(
            [],
            0,
            0,
            '',
            $backupTypes
        );
        return count($ids);
    }

    /**
     * Get the number of complete Backups
     *
     * @param string[] $backupTypes backup types to include, is empty all types are included
     *
     * @return int
     */
    public static function getNumCompletePackages(array $backupTypes = []): int
    {
        $ids = DUP_PRO_Package::getIdsByStatus(
            [
                [
                    'op'     => '>=',
                    'status' => AbstractPackage::STATUS_COMPLETE,
                ],
            ],
            0,
            0,
            '',
            $backupTypes
        );
        return count($ids);
    }

    /**
     * Get packages without storages
     *
     * @param int $limit Limit the number of packages to return, if 0 no limit is applied
     *
     * @return int[]
     */
    public static function getPackageWithoutStorages(int $limit = 0): array
    {
        $where = '(`status` = ' . AbstractPackage::STATUS_COMPLETE . ' OR `status` < ' . AbstractPackage::STATUS_PRE_PROCESS . ')' .
            ' AND FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_HAVE_LOCAL . '\', `flags`) = 0' .
            ' AND FIND_IN_SET(\'' . DUP_PRO_Package::FLAG_HAVE_REMOTE . '\', `flags`) = 0';
        return DUP_PRO_Package::dbSelect($where, $limit, 0, '', 'ids', [DUP_PRO_Package::getBackupType()]);
    }

    /**
     * Massive delete packages without storages using direct SQL query
     *
     * @param int $limit Limit the number of packages to return, if 0 no limit is applied
     *
     * @return int Number of packages deleted
     */
    public static function bulkDeletePackageWithoutStorages(int $limit = 0): int
    {
        // In that case we can use direct SQL query because the backup don't have storages,so we don't need remove local files
        global $wpdb;

        $table = DUP_PRO_Package::getTableName();

        $ids   = self::getPackageWithoutStorages($limit);
        $count = count($ids);

        if ($count == 0) {
            return 0;
        }

        $idList = implode(',', $ids);

        $query  = "DELETE FROM `{$table}` WHERE id IN ({$idList})";
        $result = $wpdb->query($query);

        if ($result === false) {
            throw new Exception("Error deleting packages without storages: " . $wpdb->last_error);
        }

        return (int) $result;
    }

    /**
     * Delete packages without storages in chunks
     *
     * @return int Number of packages deleted in this chunk, -1 if error
     */
    public static function bulkDeletePackageWithoutStoragesChunk(): int
    {
        try {
            return self::bulkDeletePackageWithoutStorages(self::BULK_DELETE_LIMIT_CHUNK);
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Error in bulkDeletePackageWithoutStoragesChunk: " . $e->getMessage());
            return -1;
        }
    }

    /**
     * Creates a default name
     *
     * @param bool $preDate if true prepend date to name
     *
     * @return string Default Backup name
     */
    public static function getDefaultPackageName(bool $preDate = true): string
    {
        //Remove specail_chars from final result
        $special_chars = [
            ".",
            "-",
        ];
        $name          = ($preDate) ?
            date('Ymd') . '_' . sanitize_title(get_bloginfo('name', 'display')) :
            sanitize_title(get_bloginfo('name', 'display')) . '_' . date('Ymd');
        $name          = substr(sanitize_file_name($name), 0, 40);
        return str_replace($special_chars, '', $name);
    }

    /**
     *  Provides various date formats
     *
     *  @param string $utcDate created date in the GMT timezone
     *  @param int    $format  Various date formats to apply
     *
     *  @return string formatted date
     */
    public static function formatLocalDateTime(string $utcDate, int $format = 1): string
    {
        $date = get_date_from_gmt($utcDate);
        $date = new DateTime($date);
        switch ($format) {
            //YEAR
            case 1:
                return $date->format('Y-m-d H:i');
            case 2:
                return $date->format('Y-m-d H:i:s');
            case 3:
                return $date->format('y-m-d H:i');
            case 4:
                return $date->format('y-m-d H:i:s');
                //MONTH
            case 5:
                return $date->format('m-d-Y H:i');
            case 6:
                return $date->format('m-d-Y H:i:s');
            case 7:
                return $date->format('m-d-y H:i');
            case 8:
                return $date->format('m-d-y H:i:s');
                //DAY
            case 9:
                return $date->format('d-m-Y H:i');
            case 10:
                return $date->format('d-m-Y H:i:s');
            case 11:
                return $date->format('d-m-y H:i');
            case 12:
                return $date->format('d-m-y H:i:s');
            default:
                return $date->format('Y-m-d H:i');
        }
    }

    /**
     *  Cleanup all tmp files
     *
     *  @param bool $all empty all contents
     *
     *  @return bool true on success fail on failure
     */
    public static function tmpCleanup($all = false): bool
    {
        //Delete all files now
        if ($all) {
            $dir = DUPLICATOR_PRO_SSDIR_PATH_TMP . "/*";
            foreach (glob($dir) as $file) {
                if (basename($file) === 'index.php') {
                    continue;
                }
                SnapIO::rrmdir($file);
            }
        } else {
            // Remove scan files that are 24 hours old
            $dir = DUPLICATOR_PRO_SSDIR_PATH_TMP . "/*_scan.json";
            foreach (glob($dir) as $file) {
                if (filemtime($file) <= time() - Constants::TEMP_CLEANUP_SECONDS) {
                    SnapIO::rrmdir($file);
                }
            }
        }

        // Clean up extras directory if it is still hanging around
        $extras_directory = SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH_TMP) . '/extras';
        if (file_exists($extras_directory)) {
            try {
                if (!SnapIO::rrmdir($extras_directory)) {
                    throw new Exception('Failed to delete: ' . $extras_directory);
                }
            } catch (Exception $ex) {
                DUP_PRO_Log::trace("Couldn't recursively delete {$extras_directory}");
            }
        }

        return true;
    }

    /**
     * Safe tmp cleanup
     *
     * @param bool $purgeTempArchives if true purge temp archives
     *
     * @return void
     */
    public static function safeTmpCleanup(bool $purgeTempArchives = false): void
    {
        if ($purgeTempArchives) {
            $dir = DUPLICATOR_PRO_SSDIR_PATH_TMP . "/*_archive.zip.*";
            foreach (glob($dir) as $file_path) {
                unlink($file_path);
            }
            $dir = DUPLICATOR_PRO_SSDIR_PATH_TMP . "/*_archive.daf.*";
            foreach (glob($dir) as $file_path) {
                unlink($file_path);
            }
        } else {
            $dir   = DUPLICATOR_PRO_SSDIR_PATH_TMP . "/*";
            $files = glob($dir);
            if ($files !== false) {
                foreach ($files as $file_path) {
                    if (basename($file_path) === 'index.php') {
                        continue;
                    }
                    if (filemtime($file_path) <= time() - Constants::TEMP_CLEANUP_SECONDS) {
                        SnapIO::rrmdir($file_path);
                    }
                }
            }
        }
    }

    /**
     * Get type string
     *
     * @param int $executionType execution type
     * @param int $templateId    template id
     *
     * @return string
     */
    public static function getExecTypeString(int $executionType, int $templateId = -1): string
    {
        switch ($executionType) {
            case AbstractPackage::EXEC_TYPE_MANUAL:
                if ($templateId != -1) {
                    $template = TemplateEntity::getById($templateId);
                    if (isset($template->is_manual) && !$template->is_manual) {
                        return __('Template', 'duplicator-pro') . ' ' . $template->name;
                    }
                }
                return __('Manual', 'duplicator-pro');
            case AbstractPackage::EXEC_TYPE_SCHEDULED:
                return __('Schedule', 'duplicator-pro');
            case AbstractPackage::EXEC_TYPE_RUN_NOW:
                return __('Schedule (Run Now)', 'duplicator-pro');
            default:
                return __('Unknown', 'duplicator-pro');
        }
    }

    /**
     * Returns an array with stats about the orphaned files
     *
     * @return string[] The full path of the orphaned file
     */
    public static function getOrphanedPackageFiles(): array
    {
        $global  = DUP_PRO_Global_Entity::getInstance();
        $orphans = [];

        $numPackages = DUP_PRO_Package::countByStatus([], [DUP_PRO_Package::getBackupType()]);
        $numPerPage  = 100;
        $pages       = floor($numPackages / $numPerPage) + 1;

        $skipStart = ['dup_pro'];
        for ($page = 0; $page < $pages; $page++) {
            $offset       = $page * $numPerPage;
            $pagePackages = DUP_PRO_Package::getRowByStatus([], $numPerPage, $offset);
            foreach ($pagePackages as $cPack) {
                $skipStart[] = $cPack->name . '_' . $cPack->hash;
            }
        }
        $pagePackages      = null;
        $fileTimeSkipInSec = (
            max(
                Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN,
                $global->max_package_runtime_in_min
            ) + Constants::ORPAHN_CLEANUP_DELAY_MAX_PACKAGE_RUNTIME
        ) * 60;

        if (file_exists(DUPLICATOR_PRO_SSDIR_PATH) && ($handle = opendir(DUPLICATOR_PRO_SSDIR_PATH)) !== false) {
            while (false !== ($fileName = readdir($handle))) {
                if ($fileName == '.' || $fileName == '..') {
                    continue;
                }

                $fileFullPath = DUPLICATOR_PRO_SSDIR_PATH . '/' . $fileName;

                if (is_dir($fileFullPath)) {
                    continue;
                }
                if (time() - filemtime($fileFullPath) < $fileTimeSkipInSec) {
                    // file younger than 2 hours skip for security
                    continue;
                }
                if (!preg_match(DUPLICATOR_PRO_FULL_GEN_BACKUP_FILE_REGEX_PATTERN, $fileName)) {
                    continue;
                }
                foreach ($skipStart as $skip) {
                    if (strpos($fileName, $skip) === 0) {
                        continue 2;
                    }
                }
                $orphans[] = $fileFullPath;
            }
            closedir($handle);
        }
        return $orphans;
    }

    /**
     * Returns an array with stats about the orphaned files
     *
     * @return array{size:int,count:int} The total count and file size of orphaned files
     */
    public static function getOrphanedPackageInfo(): array
    {
        $files         = self::getOrphanedPackageFiles();
        $info          = [];
        $info['size']  = 0;
        $info['count'] = 0;
        if (count($files)) {
            foreach ($files as $path) {
                $get_size = @filesize($path);
                if ($get_size > 0) {
                    $info['size'] += $get_size;
                    $info['count']++;
                }
            }
        }
        return $info;
    }
}
