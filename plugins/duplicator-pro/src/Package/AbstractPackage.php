<?php

namespace Duplicator\Package;

use DUP_PRO_Archive;
use DUP_PRO_Archive_Build_Mode;
use DUP_PRO_Archive_Filter_Info;
use DUP_PRO_Archive_Filter_Scope_Base;
use DUP_PRO_Archive_Filter_Scope_Directory;
use DUP_PRO_Archive_Filter_Scope_File;
use DUP_PRO_Database;
use DUP_PRO_DB;
use DUP_PRO_Global_Entity;
use DUP_PRO_Installer;
use DUP_PRO_Log;
use DUP_PRO_Multisite;
use Duplicator\Models\TemplateEntity;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_Tree_files;
use DUP_PRO_Validator;
use DUP_PRO_ZipArchive_Mode;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Installer\Package\DescriptorDBTableInfo;
use Duplicator\Installer\Package\InstallerDescriptors;
use Duplicator\Libs\DupArchive\Headers\DupArchiveFileHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveHeader;
use Duplicator\Libs\DupArchive\Processors\DupArchiveProcessingFailure;
use Duplicator\Libs\Index\FileIndexManager;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapOpenBasedir;
use Duplicator\Libs\Snap\SnapOrigFileManager;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\WpConfig\WPConfigTransformer;
use Duplicator\Libs\WpUtils\WpUtilsMultisite;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\Local\LocalStorage;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Create\BuildProgress;
use Duplicator\Package\Create\DatabaseInfo;
use Duplicator\Package\Create\DbBuildProgress;
use Duplicator\Package\Create\DupArchive\PackageDupArchiveCreateState;
use Duplicator\Package\Create\DupArchive\PackageDupArchiveExpandState;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Package\Recovery\RecoveryStatus;
use Duplicator\Utils\ExpireOptions;
use Exception;
use ReflectionObject;
use Throwable;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use VendorDuplicator\Amk\JsonSerialize\JsonUnserializeMap;

abstract class AbstractPackage
{
    use TraitCreateActiviyLog;

    const EXEC_TYPE_NOT_SET   = -1; // User for legacy packages load, never used for new packages
    const EXEC_TYPE_MANUAL    = 0;
    const EXEC_TYPE_SCHEDULED = 1;
    const EXEC_TYPE_RUN_NOW   = 2;

    const FLAG_MANUAL                = 'MANUAL';
    const FLAG_SCHEDULE              = 'SCHEDULE';
    const FLAG_SCHEDULE_RUN_NOW      = 'SCHEDULE_RUN_NOW';
    const FLAG_DB_ONLY               = 'DB_ONLY';
    const FLAG_MEDIA_ONLY            = 'MEDIA_ONLY';
    const FLAG_HAVE_LOCAL            = 'HAVE_LOCAL';
    const FLAG_HAVE_REMOTE           = 'HAVE_REMOTE';
    const FLAG_DISASTER_AVAIABLE     = 'DISASTER_AVAIABLE';
    const FLAG_DISASTER_SET          = 'DISASTER_SET';
    const FLAG_CREATED_AFTER_RESTORE = 'CREATED_AFTER_RESTORE';
    const FLAG_ZIP_ARCHIVE           = 'ZIP_ARCHIVE';
    const FLAG_DUP_ARCHIVE           = 'DUP_ARCHIVE';
    const FLAG_ACTIVE                = 'ACTIVE'; // For future use
    const FLAG_TEMPLATE              = 'TEMPLATE'; // For future use
    const FLAG_TEMPORARY             = 'TEMPORARY'; // Temporary package for creation initial package

    const STATUS_REQUIREMENTS_FAILED = -6;
    const STATUS_STORAGE_FAILED      = -5;
    const STATUS_STORAGE_CANCELLED   = -4;
    const STATUS_PENDING_CANCEL      = -3;
    const STATUS_BUILD_CANCELLED     = -2;
    const STATUS_ERROR               = -1;
    const STATUS_PRE_PROCESS         = 0;
    const STATUS_SCANNING            = 3;
    const STATUS_SCAN_VALIDATION     = 4;
    const STATUS_AFTER_SCAN          = 5;
    const STATUS_START               = 10;
    const STATUS_DBSTART             = 20;
    const STATUS_DBDONE              = 39;
    const STATUS_ARCSTART            = 40;
    const STATUS_ARCVALIDATION       = 60;
    const STATUS_ARCDONE             = 65;
    const STATUS_COPIEDPACKAGE       = 70;
    const STATUS_STORAGE_PROCESSING  = 75;
    const STATUS_COMPLETE            = 100;

    const FILE_TYPE_INSTALLER = 0;
    const FILE_TYPE_ARCHIVE   = 1;
    const FILE_TYPE_LOG       = 3;

    const PACKAGE_HASH_DATE_FORMAT = 'YmdHis';

    /** @var int<-1,max> */
    protected $ID = -1;
    /** @var string */
    public $VersionWP = '';
    /** @var string */
    public $VersionDB = '';
    /** @var string */
    public $VersionPHP = '';
    /** @var string */
    public $VersionOS = '';
    /** @var string */
    protected $name = '';
    /** @var string */
    protected $hash = '';
    /** @var int Enum self::EXEC_TYPE_* */
    protected $execType = self::EXEC_TYPE_NOT_SET;
    /** @var string */
    public $notes = '';
    /** @var string */
    public $StorePath = DUPLICATOR_PRO_SSDIR_PATH_TMP;
    /** @var string */
    public $StoreURL = DUPLICATOR_PRO_SSDIR_URL . '/';
    /** @var string */
    public $ScanFile = '';
    /** @var float */
    public $timer_start = -1;
    /** @var string */
    public $Runtime = '';
    /** @var string */
    public $ExeSize = '0';
    /** @var string */
    public $ZipSize = '0';
    /** @var string */
    public $Brand = '';
    /** @var int<-2,max> */
    public $Brand_ID = -2;
    /** @var int Enum DUP_PRO_ZipArchive_Mode */
    public $ziparchive_mode = DUP_PRO_ZipArchive_Mode::Multithreaded;
    /** @var DUP_PRO_Archive */
    public $Archive;
    /** @var DUP_PRO_Multisite */
    public $Multisite;
    /** @var DUP_PRO_Installer */
    public $Installer;
    /** @var DUP_PRO_Database */
    public $Database;
    /** @var string[] */
    public $components = [];

    /** @var int self::STATUS_* enum */
    protected int $status = self::STATUS_PRE_PROCESS;
    /** @var float */
    protected float $progressPercent = 0.0;
    /** @var int<-1,max> */
    protected $schedule_id = -1;
    // Schedule ID that created this
    // Chunking progress through build and storage uploads

    /** @var InstallerDescriptors */
    protected $descriptorsMng;
    /** @var BuildProgress */
    public $build_progress;
    /** @var DbBuildProgress */
    public $db_build_progress;
    /** @var DUP_PRO_Package_Upload_Info[] */
    public $upload_infos = [];
    /** @var int<-1,max> */
    public $active_storage_id = -1;
    /** @var int<-1,max> */
    public $template_id = -1;
    /** @var bool */
    protected $buildEmailSent = false;

    /** @var string */
    protected $version = DUPLICATOR_PRO_VERSION;
    protected string $created;
    /** @var string */
    protected $updated = '';
    /** @var string[] list ENUM self::FLAG_* */
    protected $flags = [];
    /** @var bool */
    protected $flagUpdatedAfterLoad = true;

    /**
     * Class contructor
     * The constructor is final to prevent PHP stan error
     * Unsafe usage of new static(). See: https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
     * For now I have solved it this way but if in the future it is necessary to expand the builders there are other ways to handle this
     *
     * @param int            $execType   self::EXEC_TYPE_* ENUM
     * @param int[]          $storageIds Storages id
     * @param TemplateEntity $template   Template for Backup or null
     * @param ScheduleEntity $schedule   Schedule for Backup or null
     */
    final public function __construct(
        $execType = self::EXEC_TYPE_MANUAL,
        $storageIds = [],
        ?TemplateEntity $template = null,
        ?ScheduleEntity $schedule = null
    ) {
        global $wp_version;

        switch ($execType) {
            case self::EXEC_TYPE_MANUAL:
                $this->execType = self::EXEC_TYPE_MANUAL;
                break;
            case self::EXEC_TYPE_SCHEDULED:
                $this->execType = self::EXEC_TYPE_SCHEDULED;
                break;
            case self::EXEC_TYPE_RUN_NOW:
                $this->execType = self::EXEC_TYPE_RUN_NOW;
                break;
            default:
                throw new Exception("Package type $execType not supported");
        }

        $this->VersionOS  = defined('PHP_OS') ? PHP_OS : 'unknown';
        $this->VersionWP  = $wp_version;
        $this->VersionPHP = phpversion();
        $dbversion        = DUP_PRO_DB::getVersion();
        $this->VersionDB  = (empty($dbversion) ? '- unknown -' : $dbversion);

        if ($schedule !== null) {
            $this->schedule_id = $schedule->getId();
        }

        $timestamp     = time();
        $this->created = gmdate("Y-m-d H:i:s", $timestamp);
        $this->name    = $this->getNameFromFormat($template, $timestamp);
        $this->hash    = $this->makeHash();

        $this->components = BuildComponents::COMPONENTS_DEFAULT;

        $this->Database          = new DUP_PRO_Database($this);
        $this->Archive           = new DUP_PRO_Archive($this);
        $this->Multisite         = new DUP_PRO_Multisite();
        $this->Installer         = new DUP_PRO_Installer($this);
        $this->build_progress    = new BuildProgress();
        $this->db_build_progress = new DbBuildProgress();

        $this->build_progress->setBuildMode();

        $this->setByTemplate($template);
        if (empty($storageIds)) {
            $storageIds = [StoragesUtil::getDefaultStorageId()];
        }
        $this->addUploadInfos($storageIds);
        $this->updatePackageFlags();
    }

    /**
     * Clone
     *
     * @return void
     */
    public function __clone()
    {
        $this->Database          = clone $this->Database;
        $this->Archive           = clone $this->Archive;
        $this->Multisite         = clone $this->Multisite;
        $this->Installer         = clone $this->Installer;
        $this->build_progress    = clone $this->build_progress;
        $this->db_build_progress = clone $this->db_build_progress;
        $cloneInfo               = [];
        foreach ($this->upload_infos as $key => $obj) {
            $cloneInfo[$key] = clone $obj;
        }
        $this->upload_infos = $cloneInfo;
    }

    /**
     * Set properties by template
     *
     * @param TemplateEntity $template template
     *
     * @return void
     */
    protected function setByTemplate(?TemplateEntity $template = null)
    {
        if ($template === null) {
            return;
        }

        //BRAND
        $brand_data = BrandEntity::getByIdOrDefault((int) $template->installer_opts_brand);
        $brand_data->prepareAttachmentsInstaller();
        $this->Brand    = $brand_data->name;
        $this->Brand_ID = $brand_data->getId();
        $this->notes    = $template->notes;

        //MULTISITE
        $this->Multisite->FilterSites = $template->filter_sites;

        //ARCHIVE
        $this->components           = $template->components;
        $this->Archive->FilterOn    = $template->archive_filter_on;
        $this->Archive->FilterDirs  = $template->archive_filter_dirs;
        $this->Archive->FilterExts  = $template->archive_filter_exts;
        $this->Archive->FilterFiles = $template->archive_filter_files;
        $this->Archive->FilterNames = $template->archive_filter_names;

        //INSTALLER
        $this->Installer->OptsDBHost   = $template->installer_opts_db_host;
        $this->Installer->OptsDBName   = $template->installer_opts_db_name;
        $this->Installer->OptsDBUser   = $template->installer_opts_db_user;
        $this->Installer->OptsSecureOn = $template->installer_opts_secure_on;
        $this->Installer->passowrd     = $template->installerPassowrd;
        $this->Installer->OptsSkipScan = $template->installer_opts_skip_scan;

        // CPANEL
        $this->Installer->OptsCPNLEnable   = $template->installer_opts_cpnl_enable;
        $this->Installer->OptsCPNLHost     = $template->installer_opts_cpnl_host;
        $this->Installer->OptsCPNLUser     = $template->installer_opts_cpnl_user;
        $this->Installer->OptsCPNLDBAction = $template->installer_opts_cpnl_db_action;
        $this->Installer->OptsCPNLDBHost   = $template->installer_opts_cpnl_db_host;
        $this->Installer->OptsCPNLDBName   = $template->installer_opts_cpnl_db_name;
        $this->Installer->OptsCPNLDBUser   = $template->installer_opts_cpnl_db_user;

        //DATABASE
        $this->Database->FilterOn        = $template->database_filter_on;
        $this->Database->prefixFilter    = $template->databasePrefixFilter;
        $this->Database->prefixSubFilter = $template->databasePrefixSubFilter;
        $this->Database->FilterTables    = $template->database_filter_tables;
        $this->Database->Compatible      = $template->database_compatibility_modes;
    }

    /**
     * Get package id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->ID;
    }

    /**
     * Get package status
     *
     * @return int self::STATUS_* enum
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Add upload info
     *
     * @param int[] $storage_ids storage ids
     *
     * @return void
     */
    protected function addUploadInfos($storage_ids)
    {
        DUP_PRO_Log::traceObject('ADDING UPLOAD INFOS', $storage_ids);
        $this->upload_infos = [];
        foreach ($storage_ids as $storage_id) {
            if (AbstractStorageEntity::exists($storage_id) == false) {
                DUP_PRO_Log::trace("Storage id {$storage_id} not found");
                continue;
            }
            $this->upload_infos[] = new DUP_PRO_Package_Upload_Info($storage_id);
        }
        DUP_PRO_Log::trace('NUMBER UPLOAD INFOS ADDED: ' . count($this->upload_infos));
    }


    /**
     * Get backup type
     *
     * @return string
     */
    abstract public static function getBackupType(): string;

    /**
     * Register package type, in this function must add filter duplicator_package_type_classes_map
     *
     * @return void
     */
    public static function registerType(): void
    {
        add_filter('duplicator_package_type_classes_map', function (array $classesMap): array {
            $classesMap[static::getBackupType()] = static::class;
            return $classesMap;
        });
    }

    /**
     * Generate a Backup name from a template
     *
     * @param ?TemplateEntity $template  Template to use
     * @param int             $timestamp Timestamp
     *
     * @return string
     */
    protected function getNameFromFormat(
        ?TemplateEntity $template = null,
        $timestamp = 0
    ) {
        $nameFormat = new NameFormat();
        $nameFormat->setTimestamp($timestamp);
        $nameFormat->setScheduleId($this->schedule_id);
        if ($template instanceof TemplateEntity) {
            $nameFormat->setFormat($template->package_name_format);
            $nameFormat->setTemplateId($template->getId());
        }
        return $nameFormat->getName();
    }

    /**
     * Return the package class name by type
     *
     * @param string $type Backup type
     *
     * @return class-string<self>
     */
    final protected static function getClassNameByType(string $type): string
    {
        $typesMap = apply_filters('duplicator_package_type_classes_map', []);

        if (isset($typesMap[$type])) {
            if (!is_subclass_of($typesMap[$type], self::class)) {
                throw new Exception("Package type $type is not a subclass of " . self::class);
            }
            return $typesMap[$type];
        } else {
            throw new Exception("Package type $type not supported");
        }
    }

    /**
     * Return Backup from json
     *
     * @param string       $json      json string
     * @param class-string $mainClass Main object class name
     * @param ?object      $rowData   Database row data
     *
     * @return static
     */
    protected static function getFromJson(string $json, string $mainClass, ?object $rowData = null)
    {
        if (!is_subclass_of($mainClass, self::class)) {
            throw new Exception("Package type {$mainClass} is not a subclass of " . self::class);
        }

        $map = new JsonUnserializeMap(
            [
                ''                                           => 'cl:' . $mainClass,
                'Archive'                                    => 'cl:' . DUP_PRO_Archive::class,
                'Archive/Package'                            => 'rf:',
                'Archive/FileIndexManager'                   => 'cl:' . FileIndexManager::class,
                'Archive/FilterInfo'                         => 'cl:' . DUP_PRO_Archive_Filter_Info::class,
                'Archive/FilterInfo/Dirs'                    => '?cl:' . DUP_PRO_Archive_Filter_Scope_Directory::class,
                'Archive/FilterInfo/Files'                   => '?cl:' . DUP_PRO_Archive_Filter_Scope_File::class,
                'Archive/FilterInfo/Exts'                    => '?cl:' . DUP_PRO_Archive_Filter_Scope_Base::class,
                'Archive/FilterInfo/TreeSize'                => '?cl:' . DUP_PRO_Tree_files::class,
                'Multisite'                                  => 'cl:' . DUP_PRO_Multisite::class,
                'Installer'                                  => 'cl:' . DUP_PRO_Installer::class,
                'Installer/Package'                          => 'rf:',
                'Installer/origFileManger'                   => '?cl:' . SnapOrigFileManager::class,
                'Installer/configTransformer'                => '?cl:' . WPConfigTransformer::class,
                'Installer/archiveDescriptor'                => '?cl:' . ArchiveDescriptor::class,
                'Database'                                   => 'cl:' . DUP_PRO_Database::class,
                'Database/Package'                           => 'rf:',
                'Database/info'                              => 'cl:' . DatabaseInfo::class,
                'Database/info/tablesList/*'                 => 'cl:' . DescriptorDBTableInfo::class,
                'build_progress'                             => 'cl:' . BuildProgress::class,
                'build_progress/dupCreate'                   => '?cl:' . PackageDupArchiveCreateState::class,
                'build_progress/dupCreate/package'           => 'rf:',
                'build_progress/dupCreate/archiveHeader'     => 'cl:' . DupArchiveHeader::class,
                'build_progress/dupCreate/failures/*'        => 'cl:' . DupArchiveProcessingFailure::class,
                'build_progress/dupExpand'                   => '?cl:' . PackageDupArchiveExpandState::class,
                'build_progress/dupExpand/package'           => 'rf:',
                'build_progress/dupExpand/archiveHeader'     => 'cl:' . DupArchiveHeader::class,
                'build_progress/dupExpand/currentFileHeader' => '?cl:' . DupArchiveFileHeader::class,
                'build_progress/dupExpand/failures/*'        => 'cl:' . DupArchiveProcessingFailure::class,
                'db_build_progress'                          => 'cl:' . DbBuildProgress::class,
                'upload_infos/*'                             => 'cl:' . DUP_PRO_Package_Upload_Info::class,
            ]
        );

        /** @var ?static */
        $package = JsonSerialize::unserializeWithMap($json, $map);
        if (!$package instanceof $mainClass) {
            throw new Exception('Can\'t read json object ');
        }
        // MAKE SURE THIS IS TRUE TO AVOID INFINITE LOOPS
        $package->flagUpdatedAfterLoad = true;

        if (is_object($rowData)) {
            $reflect = new ReflectionObject($package);

            $dbValuesToProps = [
                'id'         => 'ID',
                'name'       => 'name',
                'hash'       => 'hash',
                'status'     => 'status',
                'flags'      => 'flags',
                'version'    => 'version',
                'created'    => 'created',
                'updated_at' => 'updated',
            ];

            foreach ($dbValuesToProps as $dbKey => $propName) {
                if (
                    !isset($rowData->{$dbKey}) ||
                    !property_exists($package, $propName)
                ) {
                    continue;
                }

                $prop = $reflect->getProperty($propName);
                $prop->setAccessible(true);
                $prop->setValue($package, $rowData->{$dbKey});
            }
        }

        if ($package->execType) {
            if (strlen($package->getVersion()) == 0) {
                $tmp              = JsonSerialize::unserialize($json);
                $package->version = $tmp['Version'];
            }
        }

        // For legacy packages, set execType if not set
        if ($package->execType === self::EXEC_TYPE_NOT_SET) {
            if ($package->hasFlag(self::FLAG_MANUAL)) {
                $package->execType = self::EXEC_TYPE_MANUAL;
            } elseif ($package->hasFlag(self::FLAG_SCHEDULE)) {
                $package->execType = self::EXEC_TYPE_SCHEDULED;
            } elseif ($package->hasFlag(self::FLAG_SCHEDULE_RUN_NOW)) {
                $package->execType = self::EXEC_TYPE_RUN_NOW;
            }
        }

        // THIS MUST BE SET AT THE END OF THE FUNCTION TO AVOID INFINITE LOOPS, DON
        $package->flagUpdatedAfterLoad = false;
        return $package;
    }

    /**
     * Return Backup flags
     *
     * @return string[] ENUM of DUP_PRO_Package::FLAG_* constants
     */
    protected function getFlags()
    {
        if ($this->flagUpdatedAfterLoad == false) {
            $this->updatePackageFlags();
            $this->flagUpdatedAfterLoad = true;
        }
        return $this->flags;
    }

    /**
     * Check if package have flag
     *
     * @param string $flag flag to check, ENUM of DUP_PRO_Package::FLAG_* constants
     *
     * @return bool
     */
    public function hasFlag($flag)
    {
        return in_array($flag, $this->getFlags());
    }

    /**
     * Returns true if this is a DB only Backup
     *
     * @return bool
     */
    public function isDBOnly()
    {
        return BuildComponents::isDBOnly($this->components) || $this->Archive->ExportOnlyDB;
    }

    /**
     * Returns true if this is a File only Backup
     *
     * @return bool
     */
    public function isDBExcluded()
    {
        return BuildComponents::isDBExcluded($this->components);
    }


    /**
     * Get execution type
     *
     * @return int
     */
    public function getExecutionType(): int
    {
        return $this->execType;
    }

    /**
     * Check if package have local storage
     *
     * @return bool
     */
    public function haveLocalStorage(): bool
    {
        foreach ($this->upload_infos as $upload_info) {
            if ($upload_info->isLocal()) {
                $filePath = SnapIO::trailingslashit($upload_info->getStorage()->getLocationString()) . $this->getArchiveFilename();
                if (file_exists($filePath)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if package have remote storage
     *
     * @return bool
     */
    public function haveRemoteStorage(): bool
    {
        foreach ($this->upload_infos as $upload_info) {
            if (
                $upload_info->isRemote() &&
                $upload_info->packageExists() &&
                $upload_info->has_completed(true) &&
                !$upload_info->isDownloadFromRemote()
            ) {
                return true;
            }
        }
        return false;
    }


    /**
     * Get all storages in which the package exists.
     * This function may also send requests to remote storages if necessary.
     *
     * @param bool   $remoteOnly if true return only remote storages
     * @param string $returnType 'obj' or 'id'
     *
     * @return AbstractStorageEntity[]
     */
    public function getValidStorages($remoteOnly = false, string $returnType = 'obj'): array
    {
        $packageUpdate = false;
        $storages      = [];
        $storagesIds   = [];
        foreach ($this->upload_infos as $upload_info) {
            if (
                ($remoteOnly && !$upload_info->isRemote()) ||
                !$upload_info->packageExists() ||
                !$upload_info->has_completed(true)
            ) {
                continue;
            }
            $storage = $upload_info->getStorage();
            if ($storage->isValid() === false) {
                continue;
            }
            if (in_array($storage->getId(), $storagesIds)) {
                continue;
            }
            if (!$storage->hasPackage($this)) {
                $upload_info->setPackageExists(false);
                $packageUpdate = true;
                continue;
            }
            if ($returnType === 'obj') {
                $storages[] = $storage;
            }
            $storagesIds[] = $storage->getId();
        }
        if ($packageUpdate) {
            $this->update();
        }

        return ($returnType === 'obj' ? $storages : $storagesIds);
    }

    /**
     *
     * @param bool $die if true die on error otherwise return true on success and false on error
     *
     * @return bool
     */
    public function save($die = true)
    {
        if ($this->ID < 1) {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $this->version = DUPLICATOR_PRO_VERSION;
            // Created is set in the constructor
            $this->updated = gmdate("Y-m-d H:i:s");

            $results = $wpdb->insert(
                static::getTableName(),
                [
                    'type'         => static::getBackupType(),
                    'name'         => $this->name,
                    'hash'         => $this->hash,
                    'archive_name' => $this->getArchiveFilename(),
                    'status'       => 0,
                    'flags'        => '',
                    'package'      => '',
                    'version'      => $this->version,
                    'created'      => $this->created,
                    'updated_at'   => $this->updated,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
            if ($results === false) {
                DUP_PRO_Log::trace("Problem inserting Backup: {$wpdb->last_error}");
                if ($die) {
                    DUP_PRO_Log::errorAndDie(
                        "Duplicator is unable to insert a Backup record into the database table.",
                        "'{$wpdb->last_error}'"
                    );
                }
                return false;
            }
            $this->ID = $wpdb->insert_id;
        }
        // I run the update in each case even after the insert because the saved object does not have the id
        return $this->update($die);
    }

    /**
     * update Backup in database
     *
     * @param bool $die if true die on error otherwise return true on success and false on error
     *
     * @return bool
     */
    public function update($die = true): bool
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $this->cleanObjectBeforeSave();
        $this->updatePackageFlags();
        $this->version = DUPLICATOR_PRO_VERSION;
        $this->updated = gmdate("Y-m-d H:i:s");

        $packageObj = JsonSerialize::serialize($this, JSON_PRETTY_PRINT | JsonSerialize::JSON_SKIP_CLASS_NAME);
        if (!$packageObj) {
            if ($die) {
                DUP_PRO_Log::errorAndDie("Package SetStatus was unable to serialize Backup object while updating record.");
            }
            return false;
        }
        $wpdb->flush();
        if (
            $wpdb->update(
                static::getTableName(),
                [
                    'name'         => $this->name,
                    'hash'         => $this->hash,
                    'archive_name' => $this->getArchiveFilename(),
                    'status'       => (int) $this->status,
                    'flags'        => implode(',', $this->flags),
                    'package'      => $packageObj,
                    'version'      => $this->version,
                    'created'      => $this->created,
                    'updated_at'   => $this->updated,
                ],
                ['ID' => $this->ID],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ],
                ['%d']
            ) === false
        ) {
            if ($die) {
                DUP_PRO_Log::errorAndDie("Database update error: " . $wpdb->last_error);
            } else {
                DUP_PRO_Log::infoTrace("Database update error: " . $wpdb->last_error);
            }
            return false;
        }

        return true;
    }

    /**
     *  Sets the status to log the state of the build and save in database
     *
     *  @param int $status The status self::STATUS_* enum
     *
     *  @return void
     */
    final public function setStatus(int $status): void
    {
        if (
            $status < self::STATUS_REQUIREMENTS_FAILED ||
            $status > self::STATUS_COMPLETE
        ) {
            throw new Exception("Package SetStatus did not receive a proper code.");
        }

        $previousStatus = $this->status;
        $hasChanged     = ($previousStatus != $status);
        if ($hasChanged) {
            // Execute hooks only if status has changed
            do_action('duplicator_pro_package_before_set_status', $this, $status);
            $this->status = $status;
            // Each time the status is changed, the progress percent is set to the status
            $this->setProgressPercent($status);
        }

        $this->update(); // Always update Backup

        if ($hasChanged) {
            do_action('duplicator_pro_package_after_set_status', $this, $status);
            // Add log event after update only if status has changed
            $this->addLogEvent($previousStatus);
        }
    }

    /**
     * Set progress
     *
     * @param float $progressPercent Progress percentage
     *
     * @return void
     */
    public function setProgressPercent(float $progressPercent): void
    {
        $this->progressPercent = round(max(0.0, min(100.0, $progressPercent)), 1);
    }

    /**
     * Get progress percentage
     *
     * @return float
     */
    public function getProgressPercent(): float
    {
        return $this->progressPercent;
    }

    /**
     * Check if the package has a valid storage, meaning the backup exists in the storage.
     *
     * @return bool
     */
    public function hasValidStorage(): bool
    {
        return count($this->getValidStorages()) > 0;
    }

    /**
     * Return archive file name
     *
     * @return string
     */
    public function getArchiveFilename(): string
    {
        $extension = strtolower($this->Archive->Format);
        return "{$this->getNameHash()}_archive.{$extension}";
    }

    /**
     * Get the name of the file that contains the database
     *
     * @return string
     */
    public function getDatabaseFilename(): string
    {
        return $this->getNameHash() . '_database.sql';
    }

    /**
     * Get the name of the file that contains the list of directories
     *
     * @return string
     */
    public function getIndexFileName(): string
    {
        return $this->getNameHash() . '_index.txt';
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get hash
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }



    /**
     * Get name hash
     *
     * @return string
     */
    public function getNameHash(): string
    {
        return $this->name . '_' . $this->hash;
    }

    /**
     * Get Internal archive hash
     *
     * @return string Backup hash
     */
    public function getPrimaryInternalHash()
    {
        $archiveInfo = ArchiveDescriptor::getArchiveNameParts($this->getArchiveFilename());
        return $archiveInfo['packageHash'];
    }

    /**
     * Get secondary Backup hash
     *
     * @return string Backup hash
     */
    public function getSecondaryInternalHash()
    {
        $newHash    = $this->makeHash();
        $hashParts  = explode('_', $newHash);
        $firstPart  = substr($hashParts[0], 0, 7);
        $hashParts  = explode('_', $this->hash);
        $secondPart = substr($hashParts[1], -8);
        return $firstPart . '-' . $secondPart;
    }

    /**
     * Get the backup's descriptor manager
     *
     * @return InstallerDescriptors The descriptor manager
     */
    public function getDescriptorMng()
    {
        if (is_null($this->descriptorsMng)) {
            $this->descriptorsMng = new InstallerDescriptors(
                $this->getPrimaryInternalHash(),
                date(self::PACKAGE_HASH_DATE_FORMAT, strtotime($this->created))
            );
        }

        return $this->descriptorsMng;
    }

    /**
     * Get version of Backups stored in DB
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get scheudle id
     *
     * @return int
     */
    public function getScheduleId()
    {
        return $this->schedule_id;
    }

    /**
     * Get Backup storages
     *
     * @return AbstractStorageEntity[]
     */
    public function getStorages(): array
    {
        $storages = [];
        foreach ($this->upload_infos as $upload_info) {
            $storage = $upload_info->getStorage();
            if ($storage->isValid() === false) {
                continue;
            }
            $storages[] = $storage;
        }
        return $storages;
    }

    /**
     * Return true if package have storage type
     *
     * @param int $storage_type storage type
     *
     * @return bool
     */
    public function containsStorageType($storage_type): bool
    {
        foreach ($this->getStorages() as $storage) {
            if ($storage->getSType() == $storage_type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validates the inputs from the UI for correct data input
     *
     * @return DUP_PRO_Validator
     */
    public function validateInputs()
    {
        $validator = new DUP_PRO_Validator();

        if ($this->Archive->FilterOn) {
            $validator->explode_filter_custom(
                $this->Archive->FilterDirs,
                ';',
                DUP_PRO_Validator::FILTER_VALIDATE_FOLDER_WITH_COMMENT,
                [
                    'valkey' => 'FilterDirs',
                    'errmsg' => __(
                        'Directory: <b>%1$s</b> is an invalid path. 
                        Please remove the value from the Archive > Files Tab > Folders input box and apply only valid paths.',
                        'duplicator-pro'
                    ),
                ]
            );

            $validator->explode_filter_custom(
                $this->Archive->FilterExts,
                ';',
                DUP_PRO_Validator::FILTER_VALIDATE_FILE_EXT,
                [
                    'valkey' => 'FilterExts',
                    'errmsg' => __(
                        'File extension: <b>%1$s</b> is an invalid extension name. 
                        Please remove the value from the Archive > Files Tab > File Extensions input box and apply only valid extensions. For example \'jpg\'',
                        'duplicator-pro'
                    ),
                ]
            );

            $validator->explode_filter_custom(
                $this->Archive->FilterFiles,
                ';',
                DUP_PRO_Validator::FILTER_VALIDATE_FILE_WITH_COMMENT,
                [
                    'valkey' => 'FilterFiles',
                    'errmsg' => __(
                        'File: <b>%1$s</b> is an invalid file name. 
                        Please remove the value from the Archive > Files Tab > Files input box and apply only valid file names.',
                        'duplicator-pro'
                    ),
                ]
            );
        }

        //FILTER_VALIDATE_DOMAIN throws notice message on PHP 5.6
        if (defined('FILTER_VALIDATE_DOMAIN')) {
            // phpcs:ignore PHPCompatibility.Constants.NewConstants.filter_validate_domainFound
            $validator->filter_var($this->Installer->OptsDBHost, FILTER_VALIDATE_DOMAIN, [
                'valkey'   => 'OptsDBHost',
                'errmsg'   => __('MySQL Server Host: <b>%1$s</b> isn\'t a valid host', 'duplicator-pro'),
                'acc_vals' => [
                    '',
                    'localhost',
                ],
            ]);
        }

        return $validator;
    }


    /**
     * Get created date
     *
     * @return string
     */
    public function getCreated(): string
    {
        return $this->created;
    }

    /**
     * Retur the Backup flags
     *
     * @return void
     */
    protected function updatePackageFlags()
    {
        if (empty($this->flags)) {
            switch ($this->getExecutionType()) {
                case self::EXEC_TYPE_MANUAL:
                    $this->flags[] = self::FLAG_MANUAL;
                    break;
                case self::EXEC_TYPE_SCHEDULED:
                    $this->flags[] = self::FLAG_SCHEDULE;
                    break;
                case self::EXEC_TYPE_RUN_NOW:
                    $this->flags[] = self::FLAG_SCHEDULE_RUN_NOW;
                    break;
            }

            $this->flags[] = $this->Archive->Format == 'ZIP' ? self::FLAG_ZIP_ARCHIVE : self::FLAG_DUP_ARCHIVE;

            if ($this->isDBOnly()) {
                $this->flags[] = self::FLAG_DB_ONLY;
            }

            if (BuildComponents::isMediaOnly($this->components)) {
                $this->flags[] = self::FLAG_MEDIA_ONLY;
            }
        }

        $this->flags = array_diff(
            $this->flags,
            [
                self::FLAG_HAVE_LOCAL,
                self::FLAG_HAVE_REMOTE,
                self::FLAG_DISASTER_SET,
                self::FLAG_DISASTER_AVAIABLE,
            ]
        );

        if ($this->status == self::STATUS_COMPLETE) {
            // ONLY for complete Backups
            if ($this->haveLocalStorage()) {
                $this->flags[] = self::FLAG_HAVE_LOCAL;
            }

            if ($this->haveRemoteStorage()) {
                $this->flags[] = self::FLAG_HAVE_REMOTE;
            }

            if (RecoveryPackage::getRecoverPackageId() === $this->ID) {
                $this->flags[] = self::FLAG_DISASTER_SET;
            } else {
                $status = new RecoveryStatus($this);
                if ($status->isRecoveable()) {
                    $this->flags[] = self::FLAG_DISASTER_AVAIABLE;
                }
            }
        }
    }

    /**
     * Clean object before save
     *
     * @return void
     */
    protected function cleanObjectBeforeSave()
    {
        if ($this->status == self::STATUS_COMPLETE || $this->status < self::STATUS_PRE_PROCESS) {
            // If complete clean build progress, to clean temp data
            $this->build_progress->reset();
            $this->db_build_progress->reset();
            $this->Archive->FilterInfo->reset();
        }
    }

    /**
     *
     * @param int $id Backup ID
     *
     * @return false|static false if fail
     */
    public static function getById($id)
    {
        if ($id < 0) {
            return false;
        }

        global $wpdb;
        $table = static::getTableName();
        $sql   = $wpdb->prepare("SELECT * FROM `{$table}` where ID = %d", $id);
        $row   = $wpdb->get_row($sql);
        //DUP_PRO_Log::traceObject('Object row', $row);
        if ($row) {
            return static::packageFromRow($row);
        } else {
            return false;
        }
    }

    /**
     * Get the next active Backup
     *
     * @return ?AbstractPackage
     */
    public static function getNextActive(): ?AbstractPackage
    {
        $result = static::getPackagesByStatus([
            'relation' => 'AND',
            [
                'op'     => '>=',
                'status' => self::STATUS_PRE_PROCESS,
            ],
            [
                'op'     => '<',
                'status' => self::STATUS_COMPLETE,
            ]
        ], 1, 0, '`id` ASC');
        if (count($result) > 0) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Get schedule if is set
     *
     * @return ?ScheduleEntity
     */
    public function getSchedule(): ?ScheduleEntity
    {
        if ($this->schedule_id === -1) {
            return null;
        }

        if (($schedule = ScheduleEntity::getById($this->schedule_id)) === false) {
            DUP_PRO_Log::traceBacktrace("No ScheduleEntity found: id {$this->schedule_id}");
            return null;
        }

        return $schedule;
    }

    /**
     * Post scheduled build failure
     *
     * @param array<string, mixed> $tests Tests results
     *
     * @return void
     */
    public function postScheduledBuildFailure($tests = null): void
    {
        $this->postScheduledBuildProcessing(0, false, $tests);
    }

    /**
     * Post scheduled storage failure
     *
     * @return void
     */
    public function postScheduledStorageFailure(): void
    {
        $this->postScheduledBuildProcessing(1, false);
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
        try {
            if ($this->schedule_id == -1) {
                return;
            }
            if (($schedule = $this->getSchedule()) === null) {
                throw new Exception("Couldn't get schedule by ID {$this->schedule_id} to start post scheduled build processing.");
            }

            $system_global                  = SystemGlobalEntity::getInstance();
            $system_global->schedule_failed = !$success;
            $system_global->save();
            $schedule->times_run++;
            $schedule->last_run_time   = time();
            $schedule->last_run_status = ($success ? ScheduleEntity::RUN_STATUS_SUCCESS : ScheduleEntity::RUN_STATUS_FAILURE);
            $schedule->save();

            if (!empty($tests) && $tests['RES']['INSTALL'] == 'Fail') {
                $system_global->addQuickFix(
                    __('Backup was cancelled because installer files from a previous migration were found.', 'duplicator-pro'),
                    __(
                        'Click the button to remove all installer files.',
                        'duplicator-pro'
                    ),
                    [
                        'special' => ['remove_installer_files' => 1],
                    ]
                );
            }
        } catch (Exception $ex) {
            DUP_PRO_Log::trace($ex->getMessage());
        }
    }

    /**
     * Starts the Backup build process
     *
     * @param bool $closeOnEnd if true the function will close the log and die on error
     *
     * @return void
     */
    public function runBuild($closeOnEnd = true): void
    {
        try {
            DUP_PRO_Log::trace('Main build step');

            // START LOGGING
            DUP_PRO_Log::open($this->getNameHash());
            $global = DUP_PRO_Global_Entity::getInstance();
            $this->build_progress->startTimer();
            if ($this->build_progress->initialized == false) {
                $this->runBuildStart();
            }

            // At one point having this as an else as not part of the main logic prevented failure emails from getting sent.
            // Note2: Think that by putting has_completed() at top of check will prevent archive from continuing to build after a failure has hit.
            if ($this->build_progress->hasCompleted()) {
                $this->runBuildComplete();
            } elseif (!$this->build_progress->database_script_built) {
                //START BUILD
                //PHPs serialze method will return the object, but the ID above is not passed
                //for one reason or another so passing the object back in seems to do the trick
                try {
                    if ((!$global->package_mysqldump) && ($global->package_phpdump_mode == DUP_PRO_DB::PHPDUMP_MODE_MULTI)) {
                        $this->Database->buildInChunks();
                    } else {
                        $this->Database->build();
                        $this->build_progress->database_script_built = true;
                        $this->update();
                    }
                } catch (Exception $e) {
                    do_action('duplicator_pro_build_database_fail', $this);
                    DUP_PRO_Log::infoTrace("Runtime error in database dump Message: " . $e->getMessage());
                    throw $e;
                }

                DUP_PRO_Log::trace("Done building database");
                if ($this->build_progress->database_script_built) {
                    DUP_PRO_Log::trace("Set db built for Backup $this->ID");
                }
            } elseif (!$this->build_progress->archive_built) {
                $this->Archive->buildFile($this);
                $this->update();
            } elseif (!$this->build_progress->installer_built) {
                // Note: Duparchive builds installer within the main build flow not here
                $this->Installer->build($this->build_progress);
                $this->update();
                if ($this->build_progress->failed) {
                    throw new Exception('ERROR: Problem adding installer to archive.');
                }
            }

            if ($this->build_progress->failed) {
                throw new Exception('Build progress fail');
            }
        } catch (Exception $e) {
            DUP_PRO_Log::infoTraceException($e, 'Build failed');
            $message  = "Backup creation failed.\n"
                . " EXCEPTION message: " . $e->getMessage() . "\n";
            $message .= $e->getFile() . ' LINE: ' . $e->getLine() . "\n";
            $message .= $e->getTraceAsString();
            $this->buildFail($message, $closeOnEnd);
        }

        if ($closeOnEnd) {
            DUP_PRO_Log::close();
        }
    }

    /**
     * Run build start
     *
     * @return void
     */
    protected function runBuildStart(): void
    {
        global $wp_version;
        $global = DUP_PRO_Global_Entity::getInstance();

        DUP_PRO_Log::trace("**** START OF BUILD: " . $this->getNameHash());

        do_action('duplicator_pro_build_before_start', $this);
        $this->timer_start     = microtime(true);
        $this->ziparchive_mode = $global->ziparchive_mode;
        if (!License::can(License::CAPABILITY_MULTISITE_PLUS)) {
            $this->Multisite->FilterSites = [];
        }
        $php_max_time       = @ini_get("max_execution_time");
        $php_max_memory     = @ini_get('memory_limit');
        $php_max_time       = ($php_max_time == 0) ? "(0) no time limit imposed" : "[{$php_max_time}] not allowed";
        $php_max_memory     = ($php_max_memory === false) ? "Unable to set php memory_limit" : WP_MAX_MEMORY_LIMIT . " ({$php_max_memory} default)";
        $architecture       = SnapUtil::getArchitectureString();
        $clientkickoffstate = $global->clientside_kickoff ? 'on' : 'off';
        $archive_engine     = $global->get_archive_engine();
        $serverSoftware     = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'SERVER_SOFTWARE', 'unknown');
        $info               = "********************************************************************************\n";
        $info              .= "********************************************************************************\n";
        $info              .= "DUPLICATOR PRO PACKAGE-LOG: " . @date("Y-m-d H:i:s") . "\n";
        $info              .= "NOTICE: Do NOT post to public sites or forums \n";
        $info              .= "PACKAGE CREATION START\n";
        $info              .= "********************************************************************************\n";
        $info              .= "********************************************************************************\n";
        $info              .= "VERSION:\t" . DUPLICATOR_PRO_VERSION . "\n";
        $info              .= "WORDPRESS:\t{$wp_version}\n";
        $info              .= "PHP INFO:\t" . phpversion() . ' | ' . 'SAPI: ' . php_sapi_name() . "\n";
        $info              .= "SERVER:\t\t{$serverSoftware} \n";
        $info              .= "ARCHITECTURE:\t{$architecture} \n";
        $info              .= "CLIENT KICKOFF: {$clientkickoffstate} \n";
        $info              .= "PHP TIME LIMIT: {$php_max_time} \n";
        $info              .= "PHP MAX MEMORY: {$php_max_memory} \n";
        $info              .= "RUN TYPE:\t" . PackageUtils::getExecTypeString($this->getExecutionType(), $this->template_id) . "\n";
        $info              .= "MEMORY STACK:\t" . SnapServer::getPHPMemory() . "\n";
        $info              .= "ARCHIVE ENGINE: {$archive_engine}\n";
        $info              .= "PACKAGE COMPONENTS:\n\t" . BuildComponents::displayComponentsList($this->components, ",\n\t");
        DUP_PRO_Log::infoTrace($info);
        // CREATE DB RECORD
        $this->build_progress->setBuildMode();

        if ($this->Archive->isArchiveEncrypt() && !SettingsUtils::isArchiveEncryptionAvailable()) {
            throw new Exception("Archive encryption isn't available.");
        }

        $this->build_progress->initialized = true;
        $this->setStatus(self::STATUS_START);
        do_action('duplicator_pro_build_start', $this);

        if (
            $this->getExecutionType() === self::EXEC_TYPE_SCHEDULED &&
            !License::can(License::CAPABILITY_SCHEDULE)
        ) {
            // Prevent scheduled backups from running if the license doesn't support it
            throw new Exception("Can't process package schedule " . $this->ID . " because Duplicator isn't licensed");
        }
    }

    /**
     * Run build complete
     *
     * @return void
     */
    protected function runBuildComplete(): void
    {
        DUP_PRO_Log::info("\n********************************************************************************");
        DUP_PRO_Log::info("STORAGE:");
        DUP_PRO_Log::info("********************************************************************************");
        foreach ($this->upload_infos as $upload_info) {
            $storage = $upload_info->getStorage();
            if ($storage->isValid() === false) {
                continue;
            }
            // Protection against deleted storage
            $storage_type_string = strtoupper($storage->getStypeName());
            $storage_path        = $storage->getLocationString();
            DUP_PRO_Log::info($storage_type_string . ": " . $storage->getName() . ', ' . $storage_path);
        }

        if (!$this->build_progress->failed) {
            // Only makees sense to perform build integrity check on completed archives
            $this->buildIntegrityCheck();
        }

        $timerEnd      = microtime(true);
        $timerSum      = SnapString::formattedElapsedTime($timerEnd, $this->timer_start);
        $this->Runtime = $timerSum;
        // FINAL REPORT
        $info  = "\n********************************************************************************\n";
        $info .= "RECORD ID:[{$this->ID}]\n";
        $info .= "TOTAL PROCESS RUNTIME: {$timerSum}\n";
        $info .= "PEAK PHP MEMORY USED: " . SnapServer::getPHPMemory(true) . "\n";
        $info .= "DONE PROCESSING => {$this->name} " . @date("Y-m-d H:i:s") . "\n";
        DUP_PRO_Log::info($info);
        DUP_PRO_Log::trace("Done Backup building");

        if ($this->build_progress->failed) {
            throw new Exception("Backup creation failed.");
        } else {
            //File Cleanup
            $this->buildCleanup();
            do_action('duplicator_pro_build_completed', $this);
        }
    }

    /**
     * Set Backup for cancellation
     *
     * @return void
     */
    public function setForCancel(): void
    {
        $pending_cancellations = static::getPendingCancellations();
        if (!in_array($this->ID, $pending_cancellations)) {
            array_push($pending_cancellations, $this->ID);
            ExpireOptions::set(DUPLICATOR_PRO_PENDING_CANCELLATION_TRANSIENT, $pending_cancellations, DUPLICATOR_PRO_PENDING_CANCELLATION_TIMEOUT);
        }
    }

    /**
     * Clear all pending cancellations
     *
     * @return void
     */
    public static function clearPendingCancellations(): void
    {
        if (ExpireOptions::delete(DUPLICATOR_PRO_PENDING_CANCELLATION_TRANSIENT) == false) {
            DUP_PRO_Log::traceError("Couldn't remove pending cancel transient");
        }
    }

    /**
     *
     * @param boolean $delete_temp Deprecated, always true
     *
     * @return boolean
     */
    public function delete($delete_temp = false)
    {
        $ret_val = false;
        global $wpdb;
        $tblName   = static::getTableName();
        $getResult = $wpdb->get_results($wpdb->prepare("SELECT name, hash FROM `{$tblName}` WHERE id = %d", $this->ID), ARRAY_A);
        if ($getResult) {
            $row       = $getResult[0];
            $name_hash = "{$row['name']}_{$row['hash']}";
            $delResult = $wpdb->query($wpdb->prepare("DELETE FROM `{$tblName}` WHERE id = %d", $this->ID));
            if ($delResult != 0) {
                $ret_val = true;
                static::deleteDefaultLocalFiles($name_hash, $delete_temp);
                $this->deleteLocalStorageFiles();
            }
        }

        return $ret_val;
    }

    /**
     * Get log filename
     *
     * @return string
     */
    public function getLogFilename()
    {
        return $this->getNameHash() . '_log.txt';
    }

    /**
     * Delete local storage files
     *
     * @return void
     */
    protected function deleteLocalStorageFiles()
    {
        $storages           = $this->getStorages();
        $archive_filename   = $this->getArchiveFilename();
        $installer_filename = $this->Installer->getInstallerLocalName();
        $log_filename       = $this->getLogFilename();
        $index_filename     = $this->getIndexFileName();

        foreach ($storages as $storage) {
            if ($storage->getSType() !== LocalStorage::getSType()) {
                continue;
            }
            $path               = $storage->getLocationString();
            $archive_filepath   = $path . "/" . $archive_filename;
            $installer_filepath = $path . "/" . $installer_filename;
            $log_filepath       = $path . "/" . $log_filename;
            $index_filepath     = $path . "/" . $index_filename;
            @unlink($archive_filepath);
            @unlink($installer_filepath);
            @unlink($log_filepath);
            @unlink($index_filepath);
        }
    }

    /**
     * Return list of local storages
     *
     * @return AbstractStorageEntity[]
     */
    public function getLocalStorages(): array
    {
        $storages = [];
        foreach ($this->upload_infos as $upload_info) {
            if (!$upload_info->isLocal()) {
                continue;
            }
            $storages[] = $upload_info->getStorage();
        }
        return $storages;
    }

    /**
     * Build cleanup
     *
     * @return void
     */
    protected function buildCleanup(): void
    {
        $files = SnapIO::regexGlob(DUPLICATOR_PRO_SSDIR_PATH_TMP);
        if (count($files) > 0) {
            $filesToStore = [
                $this->Installer->getInstallerLocalName(),
                $this->Archive->getFileName(),
            ];
            $newPath      = DUPLICATOR_PRO_SSDIR_PATH;

            foreach ($files as $file) {
                $fileName = basename($file);

                if (!strstr($fileName, $this->getNameHash())) {
                    continue;
                }

                if (in_array($fileName, $filesToStore)) {
                    if (function_exists('rename')) {
                        rename($file, "{$newPath}/{$fileName}");
                    } elseif (function_exists('copy')) {
                        copy($file, "{$newPath}/{$fileName}");
                    } else {
                        throw new Exception('copy and rename function don\'t found');
                    }
                }

                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        $this->setStatus(self::STATUS_COPIEDPACKAGE);
    }


    /**
     * Integriry check for the build process
     *
     * @return void
     */
    protected function buildIntegrityCheck()
    {
        //INTEGRITY CHECKS
        //We should not rely on data set in the serlized object, we need to manually check each value
        //indepentantly to have a true integrity check.
        DUP_PRO_Log::info("\n********************************************************************************");
        DUP_PRO_Log::info("INTEGRITY CHECKS:");
        DUP_PRO_Log::info("********************************************************************************");
        //------------------------
        //SQL CHECK:  File should be at minimum 5K.  A base WP install with only Create tables is about 9K
        $sql_temp_path = $this->Database->getTempSafeFilePath();
        $sql_temp_size = @filesize($sql_temp_path);
        $sql_easy_size = SnapString::byteSize($sql_temp_size);
        $sql_done_txt  = SnapIO::tailFile($sql_temp_path, 3);

        // Note: Had to add extra size check of 800 since observed bad sql when filter was on
        if (
            in_array(BuildComponents::COMP_DB, $this->components) &&
            (!strstr($sql_done_txt, (string) DUPLICATOR_PRO_DB_EOF_MARKER) ||
                (!$this->Database->FilterOn && $sql_temp_size < DUPLICATOR_PRO_MIN_SIZE_DBFILE_WITHOUT_FILTERS) ||
                ($this->Database->FilterOn && $this->Database->info->tablesFinalCount > 0 && $sql_temp_size < DUPLICATOR_PRO_MIN_SIZE_DBFILE_WITH_FILTERS))
        ) {
            $this->build_progress->failed = true;
            $error_text                   = "ERROR: SQL file not complete. 
                The file looks too small ($sql_temp_size bytes) or the end of file marker was not found.";
            $system_global                = SystemGlobalEntity::getInstance();
            if ($this->Database->DBMode == 'MYSQLDUMP') {
                $fix_text = __('Click button to switch database engine to PHP', 'duplicator-pro');
                $system_global->addQuickFix(
                    $error_text,
                    $fix_text,
                    [
                        'global' => [
                            'package_mysqldump'          => 0,
                            'package_mysqldump_qrylimit' => 32768,
                        ],
                    ]
                );
            } else {
                $fix_text = __('Click button to switch database engine to MySQLDump', 'duplicator-pro');
                $system_global->addQuickFix($error_text, $fix_text, [
                    'global' => [
                        'package_mysqldump'      => 1,
                        'package_mysqldump_path' => '',
                    ],
                ]);
            }
            DUP_PRO_Log::error("$error_text  **RECOMMENDATION: $fix_text", '');
            throw new Exception($error_text);
        }

        DUP_PRO_Log::info("SQL FILE: {$sql_easy_size}");
        //------------------------
        //INSTALLER CHECK:
        $exe_temp_path = SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH_TMP . '/' . $this->Installer->getInstallerLocalName());
        $exe_temp_size = @filesize($exe_temp_path);
        $exe_easy_size = SnapString::byteSize($exe_temp_size);
        $exe_done_txt  = SnapIO::tailFile($exe_temp_path, 10);
        if (!strstr($exe_done_txt, 'DUPLICATOR_PRO_INSTALLER_EOF') && !$this->build_progress->failed) {
            throw new Exception("ERROR: Installer file not complete.  The end of file marker was not found.  Please try to re-create the Backup.");
        }
        DUP_PRO_Log::info("INSTALLER FILE: {$exe_easy_size}");
        //------------------------
        //ARCHIVE CHECK:
        // Only performs check if we were able to obtain the count
        DUP_PRO_Log::trace("Archive file count is " . $this->Archive->file_count);
        if ($this->Archive->file_count != -1) {
            $zip_easy_size = SnapString::byteSize($this->Archive->Size);
            if (!($this->Archive->Size)) {
                throw new Exception("ERROR: The archive file contains no size. Archive Size: {$zip_easy_size}");
            }

            $scan_filepath = DUPLICATOR_PRO_SSDIR_PATH_TMP . "/{$this->getNameHash()}_scan.json";
            $json          = '';
            DUP_PRO_Log::trace("***********Does $scan_filepath exist?");
            if (file_exists($scan_filepath)) {
                $json = file_get_contents($scan_filepath);
            } else {
                $error_message = sprintf(
                    __(
                        "Can't find Scanfile %s. Please ensure there no non-English characters in the Backup or schedule name.",
                        'duplicator-pro'
                    ),
                    $scan_filepath
                );
                throw new Exception($error_message);
            }

            $scanReport         = json_decode($json);
            $expected_filecount = (int) ($scanReport->ARC->UDirCount + $scanReport->ARC->UFileCount);
            DUP_PRO_Log::info("ARCHIVE FILE: {$zip_easy_size} ");
            DUP_PRO_Log::info(sprintf(__('EXPECTED FILE/DIRECTORY COUNT: %1$s', 'duplicator-pro'), number_format($expected_filecount)));
            DUP_PRO_Log::info(sprintf(__('ACTUAL FILE/DIRECTORY COUNT: %1$s', 'duplicator-pro'), number_format($this->Archive->file_count)));
            $this->ExeSize = $exe_easy_size;
            $this->ZipSize = $zip_easy_size;
            /* ------- ZIP Filecount Check -------- */
            // Any zip of over 500 files should be within 2% - this is probably too loose but it will catch gross errors
            DUP_PRO_Log::trace("Expected filecount = $expected_filecount and archive filecount=" . $this->Archive->file_count);
            if ($expected_filecount > 500) {
                $straight_ratio = ($this->Archive->file_count > 0 ? (float) $expected_filecount / (float) $this->Archive->file_count : 0);
                // RSR NEW
                $warning_count = $scanReport->ARC->UnreadableFileCount + $scanReport->ARC->UnreadableDirCount;
                DUP_PRO_Log::trace("Unread counts) unreadfile:{$scanReport->ARC->UnreadableFileCount} unreaddir:{$scanReport->ARC->UnreadableDirCount}");
                $warning_ratio = ((float) ($expected_filecount + $warning_count)) / (float) $this->Archive->file_count;
                DUP_PRO_Log::trace(
                    "Straight ratio is $straight_ratio and warning ratio is $warning_ratio. 
                    # Expected=$expected_filecount # Warning=$warning_count and #Archive File {$this->Archive->file_count}"
                );
                // Allow the real file count to exceed the expected by 10% but only allow 1% the other way
                if (($straight_ratio < 0.90) || ($straight_ratio > 1.01)) {
                    // Has to exceed both the straight as well as the warning ratios
                    if (($warning_ratio < 0.90) || ($warning_ratio > 1.01)) {
                        $zip_file_count = $this->Archive->file_count;
                        $error_message  = sprintf(
                            'ERROR: File count in archive vs expected suggests a bad archive (%1$d vs %2$d).',
                            $zip_file_count,
                            $expected_filecount
                        );
                        if ($this->build_progress->current_build_mode == DUP_PRO_Archive_Build_Mode::Shell_Exec) {
                            // $fix_text = "Go to: Settings > Packages Tab > Archive Engine to ZipArchive.";
                            $fix_text      = __("Click on button to set archive engine to DupArchive.", 'duplicator-pro');
                            $system_global = SystemGlobalEntity::getInstance();
                            $system_global->addQuickFix(
                                $error_message,
                                $fix_text,
                                [
                                    'global' => ['archive_build_mode' => 3],
                                ]
                            );
                            $error_message .= ' **' . sprintf(__("RECOMMENDATION: %s", 'duplicator-pro'), $fix_text);
                        }

                        DUP_PRO_Log::trace($error_message);
                        throw new Exception($error_message);
                    }
                }
            }
        }
    }




    /**
     * Backup build fail, this method die the process and set the Backup status to error
     *
     * @param string $message Error message
     * @param bool   $die     If true, the process will die
     *
     * @return void
     */
    public function buildFail(string $message, bool $die = true): void
    {
        $this->build_progress->failed = true;
        $this->setStatus(self::STATUS_ERROR);
        $this->postScheduledBuildProcessing(0, false);
        do_action('duplicator_pro_build_fail', $this);
        if ($die) {
            DUP_PRO_Log::errorAndDie($message);
        } else {
            DUP_PRO_Log::error($message);
        }
    }

    /**
     *
     * @param string $hash Hash
     *
     * @return false|static false if fail
     */
    public static function getByHash($hash)
    {
        global $wpdb;
        $table = static::getTableName();
        $sql   = $wpdb->prepare("SELECT * FROM `{$table}` where hash = %s", $hash);
        $row   = $wpdb->get_row($sql);
        if ($row) {
            return static::packageFromRow($row);
        } else {
            return false;
        }
    }

    /**
     * Get hash from backup archive filename
     *
     * @param string $archiveName Archive filename
     *
     * @return ?static Return Backup or null on failure
     */
    public static function getByArchiveName($archiveName)
    {
        global $wpdb;
        if (!preg_match(DUPLICATOR_PRO_ARCHIVE_REGEX_PATTERN, $archiveName, $matches)) {
            return null;
        }

        $table = static::getTableName();
        $sql   = $wpdb->prepare("SELECT * FROM `{$table}` where archive_name = %s", $archiveName);
        $row   = $wpdb->get_row($sql);
        if ($row) {
            return static::packageFromRow($row);
        } else {
            return null;
        }
    }

    /**
     * Process storage upload
     *
     * @return bool
     */
    public function processStorages()
    {
        //START LOGGING
        DUP_PRO_Log::open($this->getNameHash());
        DUP_PRO_Log::info("-----------------------------------------");
        DUP_PRO_Log::info("STORAGE PROCESSING THREAD INITIATED");
        $complete = (count($this->upload_infos) == 0);
        // Indicates if all storages have finished (succeeded or failed all-together)

        $error_present         = false;
        $local_default_present = false;
        if (!$complete) {
            $complete            = true;
            $latest_upload_infos = $this->getLatestUploadInfos();

            foreach ($latest_upload_infos as $upload_info) {
                if ($upload_info->isDefaultStorage()) {
                    $local_default_present = true;
                }

                if ($upload_info->isFailed()) {
                    DUP_PRO_Log::trace("The following Upload Info is marked as failed");
                    DUP_PRO_Log::traceObject('upload_info var:', $upload_info);
                    $error_present = true;
                } elseif ($upload_info->has_completed() == false) {
                    DUP_PRO_Log::trace("The following Upload Info hasn't completed yet");
                    DUP_PRO_Log::traceObject('upload_info var:', $upload_info);
                    $complete = false;
                    $storage  = $upload_info->getStorage();
                    if ($storage->isValid() === false) {
                        DUP_PRO_Log::trace("Storage id {$upload_info->getStorageId()} is unknown");
                        continue;
                    }

                    if ($upload_info->has_started() === false) {
                        DUP_PRO_Log::trace("Upload Info hasn't started yet, starting it");
                        $upload_info->start();
                    }

                    // Process a bit of work then let the next cron take care of if it's completed or not.
                    StoragesUtil::processPackage($this, $upload_info);
                    break;
                } else {
                    $storage = $upload_info->getStorage();
                    if ($storage->isValid() === false) {
                        DUP_PRO_Log::trace("Storage id {$upload_info->getStorageId()} is unknown");
                        continue;
                    }

                    $storage_type_string = strtoupper($storage->getStypeName());
                    DUP_PRO_Log::trace(
                        "Upload Info already completed for storage id: " . $upload_info->getStorageId() .
                            ", type: " . $storage_type_string . ", name: " . $storage->getName()
                    );
                }
            }
        } else {
            DUP_PRO_Log::trace("No storage ids defined for Backup $this->ID!");
            $error_present = true;
        }

        if ($complete) {
            DUP_PRO_Log::info("STORAGE PROCESSING COMPLETED");
            if ($error_present) {
                DUP_PRO_Log::trace("Storage error is present");
                $this->setStatus(self::STATUS_COMPLETE);
                $this->postScheduledBuildProcessing(1, false);
                if ($local_default_present == false) {
                    DUP_PRO_Log::trace("Deleting Backup files from default location.");
                    static::deleteDefaultLocalFiles($this->getNameHash(), true, false);
                }
            } else {
                if ($local_default_present == false) {
                    DUP_PRO_Log::trace("Deleting Backup files from default location.");
                    static::deleteDefaultLocalFiles($this->getNameHash(), true, false);
                } else {
                    $default_local_storage = StoragesUtil::getDefaultStorage();
                    DUP_PRO_Log::trace('Purge old default local storage Backups');
                    $default_local_storage->purgeOldPackages();
                }
                $this->setStatus(self::STATUS_COMPLETE);
                $this->postScheduledBuildProcessing(1, true);
            }
            do_action('duplicator_pro_package_transfer_completed', $this);
        }

        return $complete;
    }

    /**
     * Get all Backups marked for cancellation
     *
     * @return int[] array of Backup ids
     */
    public static function getPendingCancellations()
    {
        $pending_cancellations = ExpireOptions::get(DUPLICATOR_PRO_PENDING_CANCELLATION_TRANSIENT);
        if ($pending_cancellations === false) {
            $pending_cancellations = [];
        }
        return $pending_cancellations;
    }

    /**
     * Check if the Backup is marked for cancellation
     *
     * @return bool
     */
    public function isCancelPending()
    {
        $pending_cancellations = static::getPendingCancellations();
        return in_array($this->ID, $pending_cancellations);
    }

    /**
     * Removes all files related to the namehash from the directory
     *
     * @param string $nameHash       Package namehash
     * @param string $dir            path to dir
     * @param bool   $deleteLogFiles if set to true will delete log files too
     *
     * @return void
     */
    public static function deletePackageFilesInDir($nameHash, $dir, $deleteLogFiles = false): void
    {
        $globFiles = glob(SnapIO::safePath(SnapIO::untrailingslashit($dir) . "/" . $nameHash . "_*"));
        foreach ($globFiles as $globFile) {
            if (!$deleteLogFiles && SnapString::endsWith($globFile, '_log.txt')) {
                DUP_PRO_Log::trace("Skipping purge of $globFile because deleteLogFiles is false.");
                continue;
            }

            if (SnapIO::unlink($globFile)) {
                DUP_PRO_Log::trace("Successful purge of $globFile.");
            } else {
                DUP_PRO_Log::trace("Failed purge of $globFile.");
            }
        }
    }


    /**
     * Delete default local files
     *
     * @param string $name_hash        Package namehash
     * @param bool   $delete_temp      if set to true will delete temp files too
     * @param bool   $delete_log_files if set to true will delete log files too
     *
     * @return void
     */
    public static function deleteDefaultLocalFiles($name_hash, $delete_temp, $delete_log_files = true): void
    {
        if ($delete_temp) {
            static::deletePackageFilesInDir($name_hash, DUPLICATOR_PRO_SSDIR_PATH_TMP, true);
        }
        static::deletePackageFilesInDir($name_hash, DUPLICATOR_PRO_SSDIR_PATH, $delete_log_files);
    }


    /**
     * Get upload infos
     *
     * @return array<int,DUP_PRO_Package_Upload_Info>
     */
    public function getLatestUploadInfos(): array
    {
        $upload_infos = [];
        // Just save off the latest per the storage id
        foreach ($this->upload_infos as $upload_info) {
            $upload_infos[$upload_info->getStorageId()] = $upload_info;
        }

        return $upload_infos;
    }



    /**
     * Select Backups from database
     *
     * @param string   $where            where conditions
     * @param int      $limit            max row numbers if 0 the limit is PHP_INT_MAX
     * @param int      $offset           offset 0 is at begin
     * @param string   $orderBy          default `id` ASC if empty no order
     * @param string   $resultType       ids => int[], row => row without Backup blob, fullRow => row with Backup blob, objs => DUP_Package objects[]
     * @param string[] $backupTypes      backup types to include, is empty all types are included
     * @param bool     $includeTemporary if true include temporary packages
     *
     * @return self[]|object[]|int[]
     */
    public static function dbSelect(
        string $where,
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '`id` ASC',
        string $resultType = 'objs',
        array $backupTypes = [],
        bool $includeTemporary = false
    ): array {
        global $wpdb;
        $table = static::getTableName();
        $where = ' WHERE ' . (strlen($where) > 0 ? $where : '1');

        if (!$includeTemporary) {
            $where .= ' AND FIND_IN_SET(\'' . AbstractPackage::FLAG_TEMPORARY . '\', `flags`) = 0';
        }

        if (count($backupTypes) > 0) {
            $where .= ' AND `type` IN ("' . implode('","', $backupTypes) . '")';
        }

        $packages   = [];
        $offsetStr  = $wpdb->prepare(' OFFSET %d', $offset);
        $limitStr   = $wpdb->prepare(' LIMIT %d', ($limit > 0 ? $limit : PHP_INT_MAX));
        $orderByStr = empty($orderBy) ? '' : ' ORDER BY ' . $orderBy . ' ';
        switch ($resultType) {
            case 'ids':
                $cols = '`id`';
                break;
            case 'row':
                $cols = '`id`,`type`,`name`,`hash`,`archive_name`,`status`,`flags`,`version`,`created`,`updated_at`';
                break;
            case 'fullRow':
            case 'objs':
            default:
                $cols = '*';
                break;
        }

        $rows = $wpdb->get_results('SELECT ' . $cols . ' FROM `' . $table . '` ' . $where . $orderByStr . $limitStr . $offsetStr);
        if ($rows != null) {
            switch ($resultType) {
                case 'ids':
                    foreach ($rows as $row) {
                        $packages[] = $row->id;
                    }
                    break;
                case 'row':
                case 'fullRow':
                    $packages = $rows;
                    break;
                case 'objs':
                default:
                    foreach ($rows as $row) {
                        $package = static::packageFromRow($row);
                        if ($package != null) {
                            $packages[] = $package;
                        }
                    }
            }
        }
        return $packages;
    }


    /**
     * Conditions Example
     * [
     *   relation = 'AND',
     *   [ 'op' => '>=' , 'status' =>  self::STATUS_START ]
     *   [ 'op' => '<' , 'status' =>  self::STATUS_COMPLETE ]
     * ]
     *
     * @param array<string|int,string|array{op:string,status:int}> $conditions Conditions
     *
     * @return string
     */
    protected static function statusContitionsToWhere($conditions = [])
    {
        $accepted_op = [
            '<',
            '>',
            '=',
            '<>',
            '>=',
            '<=',
        ];
        $relation    = (isset($conditions['relation']) && strtoupper($conditions['relation']) == 'OR') ? ' OR ' : ' AND ';
        unset($conditions['relation']);
        $where = '';
        if (!empty($conditions)) {
            $str_conds = [];
            foreach ($conditions as $cond) {
                $op          = (isset($cond['op']) && in_array($cond['op'], $accepted_op)) ? $cond['op'] : '=';
                $status      = isset($cond['status']) ? (int) $cond['status'] : 0;
                $str_conds[] = 'status ' . $op . ' ' . $status;
            }

            $where = implode($relation, $str_conds) . ' ';
        } else {
            $where = '1 ';
        }

        return $where;
    }

    /**
     * Execute $callback function foreach Backup result
     *
     * @param callable $callback    function callback(DUP_PRO_Package $package)
     * @param string   $where       where conditions
     * @param int      $limit       max row numbers if 0 the limit is PHP_INT_MAX
     * @param int      $offset      offset 0 is at begin
     * @param string   $orderBy     default `id` ASC if empty no order
     * @param string[] $backupTypes backup types to include, is empty all types are included
     *
     * @return void
     */
    public static function dbSelectCallback(
        callable $callback,
        string $where,
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '`id` ASC',
        array $backupTypes = []
    ): void {
        $ids = static::dbSelect($where, $limit, $offset, $orderBy, 'ids', $backupTypes);

        foreach ($ids as $id) {
            if (($package = static::getById($id)) == false) {
                continue;
            }

            call_user_func($callback, $package);
            unset($package);
        }
    }

    /**
     * Get Backups with status conditions and/or pagination
     * Conditions Example
     * [
     *   relation = 'AND',
     *   [ 'op' => '>=' , 'status' =>  self::STATUS_START ]
     *   [ 'op' => '<' , 'status' =>  self::STATUS_COMPLETE ]
     * ]
     *
     * @param array<string|int,string|array{op:string,status:int}> $conditions  Conditions if empty get all Backups
     * @param int                                                  $limit       max row numbers if 0 the limit is PHP_INT_MAX
     * @param int                                                  $offset      offset 0 is at begin
     * @param string                                               $orderBy     default `id` ASC if empty no order
     * @param string                                               $resultType  ids => int[], row => row without Backup blob,
     *                                                                          fullRow => row with Backup blob, objs =>
     *                                                                          DUP_Package objects[]
     * @param string[]                                             $backupTypes backup types to include, is empty all types are included
     *
     * @return DUP_PRO_Package[]|object[]|int[]
     */
    public static function getPackagesByStatus(
        array $conditions = [],
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '`id` ASC',
        string $resultType = 'objs',
        array $backupTypes = []
    ) {
        return static::dbSelect(static::statusContitionsToWhere($conditions), $limit, $offset, $orderBy, $resultType, $backupTypes);
    }

    /**
     * Get Backups row db with status conditions and/or pagination
     *
     * Conditions Example
     * [
     *   relation = 'AND',
     *   [ 'op' => '>=' , 'status' =>  self::STATUS_START ]
     *   [ 'op' => '<' , 'status' =>  self::STATUS_COMPLETE ]
     * ]
     *
     * @param array<string|int,string|array{op:string,status:int}> $conditions  Conditions if empty get all Backups
     * @param int                                                  $limit       max row numbers if 0 the limit is PHP_INT_MAX
     * @param int                                                  $offset      offset 0 is at begin
     * @param string                                               $orderBy     default `id` ASC if empty no order
     * @param string[]                                             $backupTypes backup types to include, is empty all types are included
     *
     * @return object[]      // return row database without Backup blob
     */
    public static function getRowByStatus(
        array $conditions = [],
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '`id` ASC',
        array $backupTypes = []
    ) {
        return static::dbSelect(static::statusContitionsToWhere($conditions), $limit, $offset, $orderBy, 'row', $backupTypes);
    }

    /**
     * Get Backups ids with status conditions and/or pagination
     * Conditions Example
     * [
     *   relation = 'AND',
     *   [ 'op' => '>=' , 'status' =>  self::STATUS_START ]
     *   [ 'op' => '<' , 'status' =>  self::STATUS_COMPLETE ]
     * ]
     *
     * @param array<string|int,string|array{op:string,status:int}> $conditions  Conditions if empty get all Backups
     * @param int                                                  $limit       max row numbers if 0 the limit is PHP_INT_MAX
     * @param int                                                  $offset      offset 0 is at begin
     * @param string                                               $orderBy     default `id` ASC if empty no order
     * @param string[]                                             $backupTypes backup types to include, is empty all types are included
     *
     * @return int[] return row database without Backup blob
     */
    public static function getIdsByStatus(
        array $conditions = [],
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '`id` ASC',
        array $backupTypes = []
    ): array {
        return static::dbSelect(static::statusContitionsToWhere($conditions), $limit, $offset, $orderBy, 'ids', $backupTypes);
    }

    /**
     * count Backup with status condition
     * Conditions Example
     * [
     *   relation = 'AND',
     *   [ 'op' => '>=' , 'status' =>  self::STATUS_START ]
     *   [ 'op' => '<' , 'status' =>  self::STATUS_COMPLETE ]
     * ]
     *
     * @param array<string|int,string|array{op:string,status:int}> $conditions  Conditions if empty get all Backups
     * @param string[]                                             $backupTypes backup types to include, is empty all types are included
     *
     * @return int
     */
    public static function countByStatus(array $conditions = [], array $backupTypes = [])
    {
        $where = static::statusContitionsToWhere($conditions);
        $ids   = static::dbSelect($where, 0, 0, '', 'ids', $backupTypes);
        return count($ids);
    }

    /**
     * Execute $callback function foreach Backup result
     * For each iteration the memory is released
     * Conditions Example
     * [
     *   relation = 'AND',
     *   [ 'op' => '>=' , 'status' =>  self::STATUS_START ]
     *   [ 'op' => '<' , 'status' =>  self::STATUS_COMPLETE ]
     * ]
     *
     * @param callable                                             $callback    function callback(DUP_PRO_Package $package)
     * @param array<string|int,string|array{op:string,status:int}> $conditions  Conditions if empty get all Backups
     * @param int                                                  $limit       max row numbers if 0 the limit is PHP_INT_MAX
     * @param int                                                  $offset      offset 0 is at begin
     * @param string                                               $orderBy     default `id` ASC if empty no order
     * @param string[]                                             $backupTypes backup types to include, is empty all types are included
     *
     * @return void
     */
    public static function dbSelectByStatusCallback(
        callable $callback,
        array $conditions = [],
        int $limit = 0,
        int $offset = 0,
        string $orderBy = '`id` ASC',
        array $backupTypes = []
    ): void {
        static::dbSelectCallback($callback, static::statusContitionsToWhere($conditions), $limit, $offset, $orderBy, $backupTypes);
    }

    /**
     *
     * @param object $row Database row
     *
     * @return ?static
     */
    protected static function packageFromRow($row)
    {
        $package = null;

        if (strlen($row->hash) == 0) {
            DUP_PRO_Log::trace("Hash is 0 for the Backup $row->id...");
            return null;
        }

        if (property_exists($row, 'id')) {
            $row->id = (int) $row->id;
        }
        if (property_exists($row, 'type')) {
            $row->type = (string) $row->type;
        }
        if (property_exists($row, 'status')) {
            $row->status = (int) $row->status;
        }
        if (property_exists($row, 'flags')) {
            $row->flags = strlen($row->flags) == 0 ? [] : explode(',', $row->flags);
        }

        try {
            $class   = static::getClassNameByType($row->type);
            $package = static::getFromJson($row->package, $class, $row);
        } catch (Throwable $ex) {
            DUP_PRO_Log::infoTraceException($ex, "Problem getting Backup from json.");
            return null;
        }

        return $package;
    }


    /**
     * Generates a scan report
     *
     * @return array<string,mixed> of scan results
     */
    public function createScanReport(): array
    {
        global $wpdb;
        $report = [];
        DUP_PRO_Log::trace('Scanning');
        try {
            $global = DUP_PRO_Global_Entity::getInstance();
            do_action('duplicator_before_scan_report', $this);

            //Set tree filters
            $this->Archive->setTreeFilters();

            //Load scan data necessary for report
            $db                        = $this->Database->getScanData();
            $timerStart                = microtime(true);
            $this->ScanFile            = "{$this->getNameHash()}_scan.json";
            $report['RPT']['ScanTime'] = "0";
            $report['RPT']['ScanFile'] = $this->ScanFile;
            //FILES
            $scanPath              = DUPLICATOR_PRO_SSDIR_PATH_TMP . "/{$this->ScanFile}";
            $dirCount              = $this->Archive->DirCount;
            $fileCount             = $this->Archive->FileCount;
            $fullCount             = $dirCount + $fileCount;
            $unreadable            = array_merge($this->Archive->FilterInfo->Files->Unreadable, $this->Archive->FilterInfo->Dirs->Unreadable);
            $site_warning_size     = $global->archive_build_mode === DUP_PRO_Archive_Build_Mode::ZipArchive ?
                DUPLICATOR_PRO_SCAN_SITE_ZIP_ARCHIVE_WARNING_SIZE : DUPLICATOR_PRO_SCAN_SITE_WARNING_SIZE;
            $filteredTables        = ($this->Database->FilterOn ? explode(',', $this->Database->FilterTables) : []);
            $subsites              = WpUtilsMultisite::getSubsites($this->Multisite->FilterSites, $filteredTables);
            $hasImportableSites    = SnapUtil::inArrayExtended($subsites, fn($subsite): bool => count($subsite->filteredTables) === 0);
            $hasNotImportableSites = SnapUtil::inArrayExtended($subsites, fn($subsite): bool => count($subsite->filteredTables) > 0);
            $hasFilteredSiteTables = $this->Database->info->tablesBaseCount !== $this->Database->info->tablesFinalCount;
            $pathsOutOpenbaseDir   = array_filter($this->Archive->FilterInfo->Dirs->Unknown, fn($path): bool => !SnapOpenBasedir::isPathValid($path));

            // Filtered subsites
            $filteredSites = [];
            if (is_multisite() && License::can(License::CAPABILITY_MULTISITE_PLUS)) {
                $filteredSites = array_map(
                    fn($siteId) => get_blog_details(['blog_id' => $siteId]),
                    $this->Multisite->FilterSites
                );
            }

            // Check if the user has the privileges to show the CREATE FUNCTION and CREATE PROCEDURE statements
            $privileges_to_show_create_func = true;
            $query                          = $wpdb->prepare("SHOW PROCEDURE STATUS WHERE `Db` = %s", $wpdb->dbname);
            $procedures                     = $wpdb->get_col($query, 1);
            if (count($procedures)) {
                $create                         = $wpdb->get_row("SHOW CREATE PROCEDURE `" . $procedures[0] . "`", ARRAY_N);
                $privileges_to_show_create_func = isset($create[2]);
            }

            $query     = $wpdb->prepare("SHOW FUNCTION STATUS WHERE `Db` = %s", $wpdb->dbname);
            $functions = $wpdb->get_col($query, 1);
            if (count($functions)) {
                $create                         = $wpdb->get_row("SHOW CREATE FUNCTION `" . $functions[0] . "`", ARRAY_N);
                $privileges_to_show_create_func = $privileges_to_show_create_func && isset($create[2]);
            }
            $privileges_to_show_create_func = apply_filters('duplicator_privileges_to_show_create_func', $privileges_to_show_create_func);

            //Add info to report to
            $report = [
                'Status' => 1,
                'ARC'    => [
                    'Size'                => SnapString::byteSize($this->Archive->Size),
                    'DirCount'            => number_format($dirCount),
                    'FileCount'           => number_format($fileCount),
                    'FullCount'           => number_format($fullCount),
                    'USize'               => $this->Archive->Size,
                    'UDirCount'           => $dirCount,
                    'UFileCount'          => $fileCount,
                    'UFullCount'          => $fullCount,
                    'UnreadableDirCount'  => $this->Archive->FilterInfo->Dirs->getUnreadableCount(),
                    'UnreadableFileCount' => $this->Archive->FilterInfo->Files->getUnreadableCount(),
                    'FilterDirsAll'       => $this->Archive->FilterDirsAll,
                    'FilterFilesAll'      => $this->Archive->FilterFilesAll,
                    'FilterExtsAll'       => $this->Archive->FilterExtsAll,
                    'FilteredCoreDirs'    => $this->Archive->filterWpCoreFoldersList(),
                    'RecursiveLinks'      => $this->Archive->RecursiveLinks,
                    'UnreadableItems'     => $unreadable,
                    'PathsOutOpenbaseDir' => $pathsOutOpenbaseDir,
                    'FilteredSites'       => $filteredSites,
                    'Subsites'            => $subsites,
                    'Status'              => [
                        'Size'                   => $this->Archive->Size <= $site_warning_size && $this->Archive->Size >= 0,
                        'Big'                    => count($this->Archive->FilterInfo->Files->Size) <= 0,
                        'AddonSites'             => count($this->Archive->FilterInfo->Dirs->AddonSites) <= 0,
                        'UnreadableItems'        => empty($this->Archive->RecursiveLinks) && empty($unreadable) && empty($pathsOutOpenbaseDir),
                        'showCreateFuncStatus'   => $privileges_to_show_create_func,
                        'showCreateFunc'         => $privileges_to_show_create_func,
                        'HasImportableSites'     => $hasImportableSites,
                        'HasNotImportableSites'  => $hasNotImportableSites,
                        'HasFilteredCoreFolders' => $this->Archive->hasWpCoreFolderFiltered(),
                        'HasFilteredSiteTables'  => $hasFilteredSiteTables,
                        'HasFilteredSites'       => !empty($filteredSites),
                        'IsDBOnly'               => $this->isDBOnly(),
                        'Network'                => !$hasNotImportableSites && empty($filteredSites),
                        'PackageIsNotImportable' => !(
                            (!$hasFilteredSiteTables || $hasImportableSites) &&
                            (!$hasNotImportableSites || License::can(License::CAPABILITY_MULTISITE_PLUS))
                        ),
                    ],
                ],
                'DB'     => [
                    'Status'         => $db['Status'],
                    'SizeInBytes'    => $db['Size'],
                    'Size'           => SnapString::byteSize($db['Size']),
                    'Rows'           => number_format($db['Rows']),
                    'TableCount'     => $db['TableCount'],
                    'TableList'      => $db['TableList'],
                    'FilteredTables' => ($this->Database->FilterOn ? explode(',', $this->Database->FilterTables) : []),
                    'DBExcluded'     => BuildComponents::isDBExcluded($this->components),
                ],
                'SRV'    => BuildRequirements::getChecks($this)['SRV'],
                'RPT'    => [
                    'ScanCreated' => @date("Y-m-d H:i:s"),
                    'ScanTime'    => SnapString::formattedElapsedTime(microtime(true), $timerStart),
                    'ScanPath'    => $scanPath,
                    'ScanFile'    => $this->ScanFile,
                ],
            ];

            if (($json = JsonSerialize::serialize($report, JSON_PRETTY_PRINT | JsonSerialize::JSON_SKIP_CLASS_NAME)) === false) {
                throw new Exception('Problem encoding json');
            }

            if (@file_put_contents($scanPath, $json) === false) {
                throw new Exception('Problem writing scan file');
            }

            //Safe to clear at this point only JSON
            //report stores the full directory and file lists
            $this->Archive->Dirs  = [];
            $this->Archive->Files = [];
            /**
             * don't save filter info in report scan json.
             */
            $report['ARC']['FilterInfo'] = $this->Archive->FilterInfo;
            DUP_PRO_Log::trace("TOTAL SCAN TIME = " . SnapString::formattedElapsedTime(microtime(true), $timerStart));
        } catch (Exception $ex) {
            DUP_PRO_Log::trace("SCAN ERROR: " . $ex->getMessage());
            DUP_PRO_Log::trace("SCAN ERROR: " . $ex->getTraceAsString());
            DUP_PRO_Log::errorAndDie("An error has occurred scanning the file system.", $ex->getMessage());
        }

        do_action('duplicator_after_scan_report', $this, $report);
        return $report;
    }

    /**
     * Adds file and dirs lists to scan report.
     *
     * @param string $json_path    string The path to the json file
     * @param bool   $includeLists Include the file and dir lists in the report
     *
     * @return mixed The scan report
     */
    public function getScanReportFromJson($json_path, $includeLists = false)
    {
        if (!file_exists($json_path)) {
            $message = sprintf(
                __(
                    "ERROR: Can't find Scanfile %s. Please ensure there no non-English characters in the Backup or schedule name.",
                    'duplicator-pro'
                ),
                $json_path
            );
            throw new Exception($message);
        }

        $json_contents = file_get_contents($json_path);

        $report = json_decode($json_contents);
        if ($report === null) {
            throw new Exception("Couldn't decode scan file.");
        }

        if ($includeLists) {
            $targetRootPath     = DUP_PRO_Archive::getTargetRootPath();
            $indexManager       = $this->Archive->getIndexManager();
            $report->ARC->Dirs  = $indexManager->getPathArray(FileIndexManager::LIST_TYPE_DIRS, $targetRootPath);
            $report->ARC->Files = $indexManager->getPathArray(FileIndexManager::LIST_TYPE_FILES, $targetRootPath);
        }

        return $report;
    }


    /**
     *  Makes the hashkey for the Backup files
     *
     *  @return string A unique hashkey
     */
    final protected function makeHash()
    {
        // IMPORTANT!  Be VERY careful in changing this format - the FTP delete logic requires 3 segments with the last segment to be the date in YmdHis format.
        try {
            $date = date(self::PACKAGE_HASH_DATE_FORMAT, strtotime($this->created));
            if (function_exists('random_bytes')) {
                // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.random_bytesFound
                $rand = (string) random_bytes(8);
                return bin2hex($rand) . mt_rand(1000, 9999) . '_' . $date;
            } else {
                return strtolower(md5(uniqid((string) random_int(0, mt_getrandmax()), true))) . '_' . $date;
            }
        } catch (Exception $exc) {
            return strtolower(md5(uniqid((string) random_int(0, mt_getrandmax()), true))) . '_' . $date;
        }
    }


    /**
     * Get Backup table name
     *
     * @return string
     */
    public static function getTableName()
    {
        global $wpdb;
        return $wpdb->base_prefix . "duplicator_backups";
    }

    /**
     * Init entity table
     *
     * @return string[] Strings containing the results of the various update queries.
     */
    final public static function initTable()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tableName       = static::getTableName();

        $flags = [
            self::FLAG_MANUAL,
            self::FLAG_SCHEDULE,
            self::FLAG_SCHEDULE_RUN_NOW,
            self::FLAG_DB_ONLY,
            self::FLAG_MEDIA_ONLY,
            self::FLAG_HAVE_LOCAL,
            self::FLAG_HAVE_REMOTE,
            self::FLAG_DISASTER_AVAIABLE,
            self::FLAG_DISASTER_SET,
            self::FLAG_CREATED_AFTER_RESTORE,
            self::FLAG_ACTIVE,
            self::FLAG_TEMPLATE,
            self::FLAG_ZIP_ARCHIVE,
            self::FLAG_DUP_ARCHIVE,
            self::FLAG_TEMPORARY,
        ];

        $flagsStr = array_map(fn($flag): string => "'{$flag}'", $flags);
        $flagsStr = implode(',', $flagsStr);

        // PRIMARY KEY must have 2 spaces before for dbDelta to work
        // Mysql 5.5 can't have more than 1 DEFAULT CURRENT_TIMESTAMP
        $sql = <<<SQL
CREATE TABLE `{$tableName}` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `type` varchar(100) NOT NULL,
    `name` varchar(250) NOT NULL,
    `hash` varchar(50) NOT NULL,
    `archive_name` varchar(350) NOT NULL DEFAULT '',
    `status` int(11) NOT NULL,
    `flags` set({$flagsStr}) NOT NULL DEFAULT '',
    `package` longtext NOT NULL,
    `version` varchar(30) NOT NULL DEFAULT '',
    `created` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY `type_idx` (`type`),
    KEY `hash` (`hash`),
    KEY `flags` (`flags`),
    KEY `version` (`version`),
    KEY `created` (`created`),
    KEY `updated_at` (`updated_at`),
    KEY `status` (`status`),
    KEY `name` (`name`(191)),
    KEY `archive_name` (`archive_name`(191))
) {$charset_collate};
SQL;

        return SnapWP::dbDelta($sql);
    }
}
