<?php

/**
 * Class for handling archive setup and build process
 *
 * Standard: PSR-2 (almost)
 *
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package    DUP_PRO
 * @subpackage classes/package
 * @copyright  (c) 2017, Snapcreek LLC
 * @license    https://opensource.org/licenses/GPL-3.0 GNU Public License
 * @since      1.0.0
 *
 * @notes: Trace process time
 *  $timer01 = DUP_PRO_U::getMicrotime();
 *  DUP_PRO_Log::trace("SCAN TIME-B = " . DUP_PRO_U::elapsedTime(DUP_PRO_U::getMicrotime(), $timer01));
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Libs\Index\FileIndexManager;
use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\Local\LocalStorage;
use Duplicator\Package\Create\BuildProgress;
use Duplicator\Package\Create\DupArchive\PackageDupArchive;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Create\Scan\ScanChunker;
use Duplicator\Libs\Scan\ScanIterator;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\Scan\ScanOptions;
use Duplicator\Package\Create\Scan\ScanResult;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\PathUtil;

require_once(DUPLICATOR____PATH . '/classes/package/class.pack.archive.filters.php');
require_once(DUPLICATOR____PATH . '/classes/package/class.pack.archive.zip.php');
require_once(DUPLICATOR____PATH . '/classes/package/class.pack.archive.shellzip.php');

class DUP_PRO_Archive
{
    /** @var int */
    const SCAN_CHUNK_MAX_ITERATIONS = 50000;

    /** @var bool */
    public $ExportOnlyDB = false;
    /** @var string */
    public $FilterDirs = '';
    /** @var string */
    public $FilterExts = '';
    /** @var string */
    public $FilterFiles = '';
    /** @var string[] */
    public $FilterDirsAll = [];
    /** @var string[] */
    public $FilterExtsAll = [];
    /** @var string[] */
    public $FilterFilesAll = [];
    /** @var bool */
    public $FilterOn = false;
    /** @var bool */
    public $FilterNames = false;
    /** @var string archive file name */
    protected string $File = '';
    /** @var string archive format */
    public $Format = '';
    /** @var string */
    public $PackDir = '';
    /** @var int<0, max> */
    public $Size = 0;
    /** @var string[] */
    public $Dirs = [];
    /** @var int<0, max> */
    public $DirCount = 0;
    /** @var string[] */
    public $RecursiveLinks = [];
    /** @var string[] */
    public $Files = [];
    /** @var int<0, max> */
    public $FileCount = 0;
    /** @var int<-2, max> */
    public $file_count = -1;
    /** @var DUP_PRO_Archive_Filter_Info */
    public $FilterInfo;
    /** @var string */
    public $ListDelimiter = "\n";
    /** @var AbstractPackage */
    public $Package;
    /** @var string[] */
    private $tmpFilterDirsAll = [];
    /** @var FileIndexManager */
    private ?FileIndexManager $indexManager = null;

    /**
     * Class constructor
     *
     * @param AbstractPackage $package The Backup to build
     */
    public function __construct(AbstractPackage $package)
    {
        $this->Package = $package;
        if (DUP_PRO_Global_Entity::getInstance()->archive_build_mode == DUP_PRO_Archive_Build_Mode::DupArchive) {
            $this->Format = 'DAF';
        } else {
            $this->Format = 'ZIP';
        }
        $this->File       = $package->getNameHash() . '_archive.' . strtolower($this->Format);
        $this->FilterOn   = false;
        $this->FilterInfo = new DUP_PRO_Archive_Filter_Info();
        $this->PackDir    = static::getTargetRootPath();
    }

    /**
     * Get archive file name
     *
     * @return string
     */
    public function getFileName(): string
    {
        if (empty($this->File)) {
            // This check is for legacy packages, in some cases the file name is not set
            $this->File = $this->Package->getNameHash() . '_archive.' . strtolower($this->Format);
        }
        return $this->File;
    }

    /**
     * Get the index manager
     *
     * @param bool $create If true, create the index file
     *
     * @return FileIndexManager
     */
    public function getIndexManager($create = false): FileIndexManager
    {
        if ($this->indexManager === null) {
            $path               = DUPLICATOR_PRO_SSDIR_PATH_TMP . '/' . $this->Package->getIndexFileName();
            $this->indexManager = new FileIndexManager($path, $create);
        }

        return $this->indexManager;
    }

    /**
     * Free index manager file lock
     *
     * @return void
     */
    public function freeIndexManager(): void
    {
        unset($this->indexManager);
        gc_collect_cycles();
        $this->indexManager = null;
    }

    /**
     * Clone object
     *
     * @return void
     */
    public function __clone()
    {
        $this->FilterInfo = clone $this->FilterInfo;
    }

    /**
     * Filter props on json encode
     *
     * @return string[]
     */
    public function __sleep()
    {
        $props = array_keys(get_object_vars($this));
        return array_diff($props, ['Package', 'tmpFilterDirsAll', 'indexManager']);
    }

    /**
     * Return true if archive must is encrypted
     *
     * @return bool
     */
    public function isArchiveEncrypt(): bool
    {
        return (
            $this->Package->Installer->OptsSecureOn == ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT &&
            strlen($this->Package->Installer->passowrd) > 0
        );
    }

    /**
     * Get archive password, empty no password
     *
     * Important: This function returns the valued password only in case the security mode is encrypted archive.
     * In case the security is only password only at the installer level this function will return the empty password.
     *
     * @return string
     */
    public function getArchivePassword()
    {
        if ($this->Package->Installer->OptsSecureOn == ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT) {
            return $this->Package->Installer->passowrd;
        } else {
            return '';
        }
    }

    /**
     * Builds the archive file
     *
     * @param AbstractPackage $package The Backup to build
     *
     * @return void
     */
    public function buildFile(AbstractPackage $package): void
    {
        DUP_PRO_Log::trace("Building archive");
        $this->Package = $package;
        $buildProgress = $this->Package->build_progress;
        if (strlen($this->PackDir) > 0 && !is_dir($this->PackDir)) {
            throw new Exception("The 'PackDir' property must be a valid directory.");
        }

        $completed = false;
        switch ($this->Format) {
            case 'TAR':
                break;
            case 'DAF':
                $completed = PackageDupArchive::create($this->Package);
                $this->Package->update();
                break;
            default:
                $this->Format = 'ZIP';
                if ($buildProgress->current_build_mode == DUP_PRO_Archive_Build_Mode::Shell_Exec) {
                    DUP_PRO_Log::trace('Doing shell exec zip');
                    $completed = DUP_PRO_ShellZip::create($this->Package);
                } else {
                    $zipArchive = new DUP_PRO_ZipArchive($this->Package);
                    $completed  = $zipArchive->create();
                }
                $this->Package->update();
                break;
        }

        if ($completed) {
            if ($buildProgress->failed) {
                throw new Exception("Error building archive");
            } else {
                $filepath   = SnapIO::safePath("{$this->Package->StorePath}/{$this->getFileName()}");
                $this->Size = @filesize($filepath);
                $this->Package->setStatus(AbstractPackage::STATUS_ARCDONE);
                DUP_PRO_Log::trace("filesize of archive = {$this->Size}");
                DUP_PRO_Log::trace("Done building archive");
            }
        } else {
            DUP_PRO_Log::trace("Archive chunk completed");
        }
    }

    /**
     * Set archive start status
     *
     * @return void
     */
    public function setArcvhieStarted(): void
    {
        $this->Package->build_progress->setStartValues();
        $this->Package->setStatus(AbstractPackage::STATUS_ARCSTART);
    }

    /**
     * Is archive started
     *
     * @return bool
     */
    public function isArchiveStarted()
    {
        return $this->Package->build_progress->archive_started;
    }

    /**
     * return all paths to scan
     *
     * @return string[]
     */
    public static function getScanPaths()
    {
        static $scanPaths = null;
        if (is_null($scanPaths)) {
            $paths = self::getArchiveListPaths();
            // The folder that contains wp-config must not be scanned in full but only added
            unset($paths['wpconfig']);
            $scanPaths = [$paths['home']];
            unset($paths['home']);
            foreach ($paths as $path) {
                $addPath = true;
                foreach ($scanPaths as $resPath) {
                    if (SnapIO::getRelativePath($path, $resPath) !== false) {
                        $addPath = false;
                        break;
                    }
                }
                if ($addPath) {
                    $scanPaths[] = $path;
                }
            }
            $scanPaths = array_values(array_unique($scanPaths));
        }
        return $scanPaths;
    }

    /**
     * Create filters info and generate meta data about the dirs and files needed for the build
     *
     * @link http://msdn.microsoft.com/en-us/library/aa365247%28VS.85%29.aspx Windows filename restrictions
     *
     * @param bool $reset If true, reset the scan stats
     *
     * @return bool Returns true if the build has finished successfully
     */
    public function scanFiles($reset = false)
    {
        if ($reset) {
            PackageUtils::safeTmpCleanup();
            $this->resetScanStats();
            $this->createFilterInfo();
            $this->getIndexManager(true);
        }

        $rootPath = DUP_PRO_Archive::getTargetRootPath();
        //If the root directory is a filter then skip it all
        if (in_array($rootPath, $this->FilterDirsAll) || $this->Package->isDBOnly()) {
            DUP_PRO_Log::trace('SKIP ALL FILES');
            $this->getIndexManager()->save();
            return true;
        }

        $scanOpts = new ScanOptions([
            'rootPath'             => $rootPath,
            'skipSizeWarning'      => \DUP_PRO_Global_Entity::getInstance()->skip_archive_scan,
            'filterBadEncoding'    => $this->FilterNames,
            'filterDirs'           => $this->FilterDirsAll,
            'filterFiles'          => $this->FilterFilesAll,
            'filterFileExtensions' => $this->FilterExtsAll,
            'sort'                 => ScanIterator::SORT_ASC,
        ]);

        $scanChunkTimeout = DUP_PRO_Global_Entity::getInstance()->php_max_worker_time_in_sec;
        $pathsToScan      = self::getScanPaths();
        $scanChunker      = new ScanChunker(
            [
                'package'      => $this->Package,
                'pathsToScan'  => $pathsToScan,
                'indexManager' => $this->getIndexManager(),
                'scanOpts'     => $scanOpts,
            ],
            self::SCAN_CHUNK_MAX_ITERATIONS,
            $scanChunkTimeout * SECONDS_IN_MICROSECONDS,
            0
        );

        $result = $scanChunker->start($reset);
        switch ($result) {
            case ScanChunker::CHUNK_STOP:
                DUP_PRO_Log::infoTrace("Scan chunk continues.");
                break;
            case ScanChunker::CHUNK_COMPLETE:
                $this->setFilterInfo($scanChunker->getScanResult());
                $this->setBuildFilters();
                DUP_PRO_Log::infoTrace("Scan chunk complete.");
                break;
            case ScanChunker::CHUNK_ERROR:
                throw new Exception('Error on scan');
        }

        $this->getIndexManager()->save();
        return $result == ScanChunker::CHUNK_COMPLETE;
    }

    /**
     * Validate the index file
     *
     * @return bool
     */
    public function validateIndexFile(): bool
    {
        $indexMng = $this->getIndexManager();

        if (($actualCount = $indexMng->getCount(FileIndexManager::LIST_TYPE_FILES)) !== $this->FileCount) {
            DUP_PRO_Log::infoTrace('File count mismatch: Expected ' . $this->FileCount . ' but got ' . $actualCount);
            return false;
        }

        if (($actualCount = $indexMng->getCount(FileIndexManager::LIST_TYPE_DIRS)) !== $this->DirCount) {
            DUP_PRO_Log::infoTrace('Dir count mismatch: Expected ' . $this->DirCount . ' but got ' . $actualCount);
            return false;
        }

        DUP_PRO_Log::trace('Index file validation passed');
        return true;
    }

    /**
     * Set info from chunk scan result
     *
     * @param ScanResult $scanResult The scan result
     *
     * @return void
     */
    protected function setFilterInfo(ScanResult $scanResult)
    {
        if (!empty($scanResult->bigDirs)) {
            $this->FilterInfo->Dirs->Size = array_map(
                fn($item): array => [
                    'ubytes' => $item['size'],
                    'bytes'  => SnapString::byteSize($item['size']),
                    'nodes'  => $item['nodes'],
                    'name'   => basename($item['path']),
                    'dir'    => pathinfo($item['relativePath'], PATHINFO_DIRNAME),
                    'path'   => $item['relativePath'],
                ],
                $scanResult->bigDirs
            );
        }

        if (!empty($scanResult->bigFiles)) {
            $this->FilterInfo->Files->Size = array_map(
                fn($item): array => [
                    'ubytes' => $item['size'],
                    'bytes'  => SnapString::byteSize($item['size']),
                    'nodes'  => 1,
                    'name'   => basename($item['path']),
                    'dir'    => pathinfo($item['relativePath'], PATHINFO_DIRNAME),
                    'path'   => $item['relativePath'],
                ],
                $scanResult->bigFiles
            );
        }

        $this->FilterInfo->Dirs->Unreadable = [];
        foreach ($scanResult->unreadableDirs as $dirPath) {
            $this->FilterInfo->Dirs->addUnreadableItem($dirPath);
        }

        $this->FilterInfo->Files->Unreadable = [];
        foreach ($scanResult->unreadableFiles as $filePath) {
            $this->FilterInfo->Files->addUnreadableItem($filePath);
        }

        $this->FilterInfo->Dirs->Unknown = [];
        foreach ($scanResult->unknownPaths as $path) {
            $this->FilterInfo->Dirs->Unknown[] = $path;
        }

        $this->FilterInfo->Dirs->AddonSites = $scanResult->addonSites;
        $this->RecursiveLinks               = $scanResult->recursiveLinks;

        $this->DirCount  = $scanResult->dirCount;
        $this->FileCount = $scanResult->fileCount;
        $this->Size      = $scanResult->size;
    }

    /**
     * Set filters after scan finishes
     *
     * @return void
     */
    protected function setBuildFilters()
    {
        DUP_PRO_Log::trace('set filters all');
        $this->FilterDirsAll  = array_merge($this->FilterDirsAll, $this->RecursiveLinks, $this->FilterInfo->Dirs->Unreadable);
        $this->FilterFilesAll = array_merge($this->FilterFilesAll, $this->FilterInfo->Files->Unreadable);
        sort($this->FilterDirsAll);
        sort($this->FilterFilesAll);
    }

    /**
     * Init Scan stats
     *
     * @return void
     */
    private function resetScanStats(): void
    {
        $this->RecursiveLinks = [];
        $this->FilterInfo->reset(true);
        // For file
        $this->Size      = 0;
        $this->FileCount = 0;
        $this->DirCount  = 0;
    }


    /**
     * Get the file path to the archive file within default storage directory
     *
     * @return string Returns the full file path to the archive file
     */
    public function getSafeFilePath()
    {
        return SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH . "/{$this->getFileName()}");
    }

    /**
     * Get the store URL to the archive file
     *
     * @return string Returns the full URL path to the archive file
     */
    public function getURL(): string
    {
        return DUPLICATOR_PRO_SSDIR_URL . "/{$this->getFileName()}";
    }

    /**
     * Parse path filter
     *
     * @param string $input         The input string
     * @param bool   $getFilterList If true, return the filter list
     *
     * @return string|string[]    The filter list or the input string
     */
    public static function parsePathFilter($input = '', $getFilterList = false)
    {
        // replace all new line with ;
        $input = str_replace(["\r\n", "\n", "\r"], ';', $input);
        // remove all empty content
        $input = trim(preg_replace('/;([\s\t]*;)+/', ';', $input), "; \t\n\r\0\x0B");
        // get content array
        $line_array = preg_split('/[\s\t]*;[\s\t]*/', $input);
        $result     = [];
        foreach ($line_array as $val) {
            if (strlen($val) == 0 || preg_match('/^[\s\t]*?#/', $val)) {
                if (!$getFilterList) {
                    $result[] = trim($val);
                }
            } else {
                $safePath = str_replace(["\t", "\r"], '', $val);
                $safePath = SnapIO::untrailingslashit(SnapIO::safePath(trim($safePath)));
                if (strlen($safePath) >= 2) {
                    $result[] = $safePath;
                }
            }
        }

        if ($getFilterList) {
            $result = array_unique($result);
            sort($result);
            return $result;
        } else {
            return implode(";", $result);
        }
    }

    /**
     * Parse the list of ";" separated paths to make paths/format safe
     *
     * @param string $paths       A list of paths to parse
     * @param bool   $getPathList If true, return the path list
     *
     * @return string|string[]   Returns a cleanup up ";" separated string of dir paths
     */
    public static function parseDirectoryFilter($paths = '', $getPathList = false)
    {
        $dirList = [];

        foreach (self::parsePathFilter($paths, true) as $path) {
            if (is_dir($path)) {
                $dirList[] = $path;
            }
        }

        if ($getPathList) {
            return $dirList;
        } else {
            return implode(";", $dirList);
        }
    }

    /**
     * Parse the list of ";" separated extension names to make paths/format safe
     *
     * @param string $extensions A list of file extension names to parse
     *
     * @return string   Returns a cleanup up ";" separated string of extension names
     */
    public static function parseExtensionFilter($extensions = ""): string
    {
        $filter_exts = "";
        if (!empty($extensions) && $extensions != ";") {
            $filter_exts = str_replace([' ', '.'], '', $extensions);
            $filter_exts = str_replace(",", ";", $filter_exts);
            $filter_exts = DUP_PRO_STR::appendOnce($extensions, ";");
        }
        return $filter_exts;
    }

    /**
     * Parse the list of ";" separated paths to make paths/format safe
     *
     * @param string $paths       A list of paths to parse
     * @param bool   $getPathList If true, return the path list
     *
     * @return string|string[]   Returns a cleanup up ";" separated string of file paths
     */
    public static function parseFileFilter($paths = '', $getPathList = false)
    {
        $fileList = [];

        foreach (self::parsePathFilter($paths, true) as $path) {
            if (!is_dir($path)) {
                $fileList[] = $path;
            }
        }

        if ($getPathList) {
            return $fileList;
        } else {
            return implode(";", $fileList);
        }
    }

    /**
     * return true if path is child of duplicator backup path
     *
     * @param string $path The path to check
     *
     * @return boolean
     */
    public static function isBackupPathChild($path): bool
    {
        return (preg_match('/[\/]' . preg_quote(DUPLICATOR_PRO_SSDIR_NAME, '/') . '[\/][^\/]+$/', $path) === 1);
    }

    /**
     * Creates all of the filter information meta stores
     *
     * @todo: Create New Section Settings > Packages > Filters
     * Two new check boxes one for directories and one for files
     * Readonly list boxes for directories and files
     *
     * @return void
     */
    private function createFilterInfo(): void
    {
        DUP_PRO_Log::traceObject('Filter files', $this->FilterFiles);
        $this->FilterInfo->Dirs->Core = [];
        //FILTER: INSTANCE ITEMS
        if ($this->FilterOn) {
            $this->FilterInfo->Dirs->Instance = self::parsePathFilter($this->FilterDirs, true);
            $this->FilterInfo->Exts->Instance = explode(";", $this->FilterExts);
            // Remove blank entries
            $this->FilterInfo->Exts->Instance  = array_filter(array_map('trim', $this->FilterInfo->Exts->Instance));
            $this->FilterInfo->Files->Instance = self::parsePathFilter($this->FilterFiles, true);
        }

        //FILTER: GLOBAL ITMES
        if ($GLOBALS['DUPLICATOR_PRO_GLOBAL_DIR_FILTERS_ON']) {
            $this->FilterInfo->Dirs->Global = self::getDefaultGlobalDirFilter();
        }
        DUP_PRO_Log::traceObject('FILTER INFO GLOBAL DIR ', $this->FilterInfo->Dirs->Global);

        if ($GLOBALS['DUPLICATOR_PRO_GLOBAL_FILE_FILTERS_ON']) {
            $this->FilterInfo->Files->Global = static::getDefaultGlobalFileFilter();
        } else {
            $this->FilterInfo->Files->Global = [];
        }

        //Configuration files
        $this->FilterInfo->Files->Global[] = static::getArchiveListPaths('home') . '/.htaccess';
        $this->FilterInfo->Files->Global[] = static::getArchiveListPaths('home') . '/.user.ini';
        $this->FilterInfo->Files->Global[] = static::getArchiveListPaths('home') . '/php.ini';
        $this->FilterInfo->Files->Global[] = static::getArchiveListPaths('home') . '/web.config';
        $this->FilterInfo->Files->Global[] = static::getArchiveListPaths('wpcontent') . '/debug.log';
        $this->FilterInfo->Files->Global[] = SnapWP::getWPConfigPath();
        DUP_PRO_Log::traceObject('FILTER INFO GLOBAL FILES ', $this->FilterInfo->Files->Global);
        //FILTER: CORE ITMES
        //Filters Duplicator free Backups & All pro local directories
        $storages = AbstractStorageEntity::getAll();

        foreach ($storages as $storage) {
            if ($storage->getSType() !== LocalStorage::getSType()) {
                continue;
            }
            /** @var LocalStorage $storage */
            if (!$storage->isFilterProtection()) {
                continue;
            }
            $path     = SnapIO::safePathUntrailingslashit($storage->getLocationString());
            $realPath = SnapIO::safePathUntrailingslashit($storage->getLocationString(), true);

            $this->FilterInfo->Dirs->Core[] = $path;
            if ($path != $realPath) {
                $this->FilterInfo->Dirs->Core[] = $realPath;
            }
        }

        $compMng = new BuildComponents($this->Package->components);

        $this->FilterDirsAll  = array_merge(
            $this->FilterInfo->Dirs->Instance,
            $this->FilterInfo->Dirs->Global,
            $this->FilterInfo->Dirs->Core,
            $this->Package->Multisite->getDirsToFilter(),
            $compMng->getFiltersDirs()
        );
        $this->FilterExtsAll  = array_merge($this->FilterInfo->Exts->Instance, $this->FilterInfo->Exts->Global, $this->FilterInfo->Exts->Core);
        $this->FilterFilesAll = array_merge(
            $this->FilterInfo->Files->Instance,
            $this->FilterInfo->Files->Global,
            $this->FilterInfo->Files->Core,
            $compMng->getFiltersFiles()
        );

        $this->tmpFilterDirsAll = array_map('trailingslashit', $this->FilterDirsAll);

        //PHP 5 on windows decode patch
        if (!SnapUtil::isPHP7Plus() && SnapServer::isWindows()) {
            foreach ($this->tmpFilterDirsAll as $key => $value) {
                if (preg_match('/[^\x20-\x7f]/', $value)) {
                    $this->tmpFilterDirsAll[$key] = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
                }
            }
        }
        DUP_PRO_Log::trace('Filter files Ok');
    }

    /**
     * Return global default filter
     *
     * @return string[]
     */
    public static function getDefaultGlobalDirFilter()
    {
        static $dirFiltersLits = null;
        if (is_null($dirFiltersLits)) {
            $arcPaths = array_map('trailingslashit', self::getArchiveListPaths());
            $items    = [
                'home'      => [
                    'wp-snapshots',
                    '.opcache',
                    '.tmb',
                ],
                'wpcontent' => [
                    'backups-dup-lite',
                    'backups-dup-pro',
                    'ai1wm-backups',
                    'backupwordpress',
                    'content/cache',
                    'contents/cache',
                    'infinitewp/backups',
                    'managewp/backups',
                    'old-cache',
                    'updraft',
                    'wpvividbackups',
                    'wishlist-backup',
                    'wfcache',
                    'bps-backup',
                    'cache',
                ],
                'uploads'   =>  [
                    'aiowps_backups',
                    'backupbuddy_temp',
                    'backupbuddy_backups',
                    'ithemes-security/backups',
                    'mainwp/backup',
                    'pb_backupbuddy',
                    'snapshots',
                    'sucuri',
                    'wp-clone',
                    'wp_all_backup',
                    'wpbackitup_backups',
                    'backup-guard',
                ],
                'plugins'   => [
                    'all-in-one-wp-migration/storage',
                    'really-simple-captcha/tmp',
                    'wordfence/tmp',
                ],
            ];

            $dirFiltersLits = [];
            foreach ($items as $pathKey => $pathsList) {
                foreach ($pathsList as $subPath) {
                    $dirFiltersLits[] = $arcPaths[$pathKey] . $subPath;
                }
            }
        }
        return apply_filters('duplicator_pro_global_dir_filters', $dirFiltersLits);
    }

    /**
     * Return global default filter
     *
     * @return string[]
     */
    public static function getDefaultGlobalFileFilter()
    {
        static $fileFiltersLits = null;
        if (is_null($fileFiltersLits)) {
            $fileFiltersLits = [
                'error_log',
                'debug_log',
                'ws_ftp.log',
                'dbcache',
                'pgcache',
                'objectcache',
                '.DS_Store',
            ];
        }
        return apply_filters('duplicator_pro_global_file_filters', $fileFiltersLits);
    }

    /**
     * Builds a tree for both file size warnings and name check warnings
     * The trees are used to apply filters from the scan screen
     *
     * @return bool
     */
    public function setTreeFilters(): bool
    {
        DUP_PRO_Log::trace('BUILD: File Size tree');
        $rootPath  = static::getTargetRootPath();
        $scanPaths = static::getScanPaths();

        if (count($this->FilterInfo->Dirs->Size) || count($this->FilterInfo->Files->Size)) {
            $treeObj = new DUP_PRO_Tree_files($scanPaths, false);
            foreach ($this->FilterInfo->Dirs->Size as $fileData) {
                $data = [
                    'is_warning' => true,
                    'size'       => $fileData['bytes'],
                    'ubytes'     => $fileData['ubytes'],
                    'nodes'      => $fileData['nodes'],
                ];
                try {
                    $treeObj->addElement($rootPath . $fileData['path'], $data);
                } catch (Exception $e) {
                    DUP_PRO_Log::trace('Add filter dir size error MSG: ' . $e->getMessage());
                }
            }

            foreach ($this->FilterInfo->Files->Size as $fileData) {
                $data = [
                    'is_warning' => true,
                    'size'       => $fileData['bytes'],
                    'ubytes'     => $fileData['ubytes'],
                    'nodes'      => 1,
                ];
                try {
                    $treeObj->addElement($rootPath . $fileData['path'], $data);
                } catch (Exception $e) {
                    DUP_PRO_Log::trace('Add filter file size error MSG: ' . $e->getMessage());
                }
            }

            $treeObj->uasort([self::class, 'sortTreeByFolderWarningName']);
            $treeObj->treeTraverseCallback([$this, 'checkTreeNodesFolder']);
        } else {
            $treeObj = new DUP_PRO_Tree_files($scanPaths, false);
        }


        $this->FilterInfo->TreeSize = self::getJsTreeStructure($treeObj, esc_html__('No large files found during this scan.', 'duplicator-pro'), true);
        DUP_PRO_Log::trace(' END');
        return true;
    }

    /**
     * Three sort function
     *
     * @param DUP_PRO_Tree_files_node $a Node
     * @param DUP_PRO_Tree_files_node $b Node
     *
     * @return int<-1,1>
     */
    public static function sortTreeByFolderWarningName($a, $b): int
    {
        // check sort by path type
        if ($a->isDir && !$b->isDir) {
            return -1;
        } elseif (!$a->isDir && $b->isDir) {
            return 1;
        } else {
            // sort by warning
            if (
                (isset($a->data['is_warning']) && $a->data['is_warning'] == true) &&
                (!isset($b->data['is_warning']) || $b->data['is_warning'] == false)
            ) {
                return -1;
            } elseif (
                (!isset($a->data['is_warning']) || $a->data['is_warning'] == false) &&
                (isset($b->data['is_warning']) && $b->data['is_warning'] == true)
            ) {
                return 1;
            } else {
                // sort by name
                return strcmp($a->name, $b->name);
            }
        }
    }

    /**
     * Check tree node
     *
     * @param DUP_PRO_Tree_files_node $node Tree node
     *
     * @return void
     */
    public function checkTreeNodesFolder($node): void
    {
        $node->data['is_core']     = 0;
        $node->data['is_filtered'] = 0;
        if ($node->isDir) {
            $node->data['is_core'] = (int) SnapWP::isWpCore($node->fullPath, SnapWP::PATH_FULL);
            if (in_array($node->fullPath, $this->FilterDirsAll)) {
                $node->data['is_filtered'] = 1;
            }

            $relPath = SnapIO::getRelativePath($node->fullPath, self::getTargetRootPath());
            if (($info = $this->getIndexManager()->findByPath(FileIndexManager::LIST_TYPE_DIRS, $relPath)) !== null) {
                $node->data['size']  = SnapString::byteSize($info->getSize());
                $node->data['nodes'] = $info->getNodes();
            }
        } else {
            $ext = pathinfo($node->fullPath, PATHINFO_EXTENSION);
            if (in_array($ext, $this->FilterExtsAll)) {
                $node->data['is_filtered'] = 1;
            } elseif (in_array($node->fullPath, $this->FilterFilesAll)) {
                $node->data['is_filtered'] = 1;
            }
        }
    }

    /**
     * Get tree structure for jsTree
     *
     * @param DUP_PRO_Tree_files $treeObj       Tree object
     * @param string             $notFoundText  Text for empty tree
     * @param bool               $addFullLoaded Add full loaded flag
     *
     * @return array<string, mixed>
     */
    public static function getJsTreeStructure($treeObj, $notFoundText = '', $addFullLoaded = true): array
    {
        $treeList = array_values($treeObj->getTreeList());
        switch (count($treeList)) {
            case 0:
                return [
                    //'id'          => 'no_child_founds',
                    'text'  => $notFoundText, // node text
                    'type'  => 'info-text',
                    'state' => [
                        'opened'            => false, // is the node open
                        'disabled'          => true, // is the node disabled
                        'selected'          => false, // is the node selected,
                        'checked'           => false,
                        'checkbox_disabled' => true,
                    ],
                ];
            case 1:
                return self::treeNodeTojstreeNode($treeList[0], true, $notFoundText, $addFullLoaded);
            default:
                $rootPath = self::getTargetRootPath();
                $result   = [
                    //'id'          => 'no_child_founds',
                    'text'     => $rootPath,
                    'type'     => 'folder',
                    'children' => [],
                    'state'    => [
                        'opened'            => true, // is the node open
                        'disabled'          => true, // is the node disabled
                        'selected'          => false, // is the node selected,
                        'checked'           => false,
                        'checkbox_disabled' => true,
                    ],
                ];
                foreach ($treeList as $treeRootNode) {
                    $result['children'][] = self::treeNodeTojstreeNode($treeRootNode, true, $notFoundText, $addFullLoaded);
                }

                return $result;
        }
    }

    /**
     * Get jsTree node from tree node
     *
     * @param DUP_PRO_Tree_files_node $node          Tree node
     * @param bool                    $root          Is root node
     * @param string                  $notFoundText  Text for empty tree
     * @param bool                    $addFullLoaded Add full loaded flag
     *
     * @return array<string, mixed>
     */
    protected static function treeNodeTojstreeNode($node, $root = false, $notFoundText = '', $addFullLoaded = true): array
    {
        $name       = $root ? $node->fullPath : $node->name;
        $isCore     = isset($node->data['is_core']) && $node->data['is_core'];
        $isFiltered = isset($node->data['is_filtered']) && $node->data['is_filtered'];
        if (isset($node->data['size'])) {
            $name .= ' <span class="size" >' . (($node->data['size'] !== false && !$isFiltered) ? $node->data['size'] : '&nbsp;') . '</span>';
        }

        if (isset($node->data['nodes'])) {
            $name .= ' <span class="nodes" >' . (($node->data['nodes'] > 1 && !$isFiltered) ? $node->data['nodes'] : '&nbsp;') . '</span>';
        }

        $li_classes = '';
        $a_attr     = [];
        $li_attr    = [];
        if ($root) {
            $li_classes .= ' root-node';
        }

        if ($isCore) {
            $li_classes .= ' core-node';
            if ($node->isDir) {
                $a_attr['title'] = esc_attr__('Core WordPress directories should not be filtered. Use caution when excluding files.', 'duplicator-pro');
            }
            $isWraning = false;
            // never warings for cores files
        } else {
            $isWraning = isset($node->data['is_warning']) && $node->data['is_warning'];
        }

        if ($isWraning) {
            $li_classes .= ' warning-node';
        }

        if ($isFiltered) {
            $li_classes .= ' filtered-node';
            if ($node->isDir) {
                $a_attr['title'] = esc_attr__('This dir is filtered.', 'duplicator-pro');
            } else {
                $a_attr['title'] = esc_attr__('This file is filtered.', 'duplicator-pro');
            }
        }

        if ($addFullLoaded && $node->isDir) {
            $li_attr['data-full-loaded'] = false;
            if (!$root && $node->haveChildren && !$isWraning) {
                $li_classes .= ' warning-childs';
            }
        }

        $li_attr['class'] = $li_classes;
        $result           = [
            //'id'          => $node->id, // will be autogenerated if omitted
            'text'     => $name, // node text
            'fullPath' => $node->fullPath,
            'type'     => $node->isDir ? 'folder' : 'file',
            'state'    => [
                'opened'            => true, // is the node open
                'disabled'          => false, // is the node disabled
                'selected'          => false, // is the node selected,
                'checked'           => false,
                'checkbox_disabled' => $isCore || $isFiltered,
            ],
            'children' => [], // array of strings or objects
            'li_attr'  => $li_attr, // attributes for the generated LI node
            'a_attr'   => $a_attr, // attributes for the generated A node
        ];
        if ($root) {
            if (count($node->childs) == 0) {
                $result['state']['disabled'] = true;
                $result['state']['opened']   = true;
                $result['li_attr']['class'] .= ' no-warnings';
                $result['children'][]        = [
                    //'id'          => 'no_child_founds',
                    'text'  => $notFoundText, // node text
                    'type'  => 'info-text',
                    'state' => [
                        'opened'            => false, // is the node open
                        'disabled'          => true, // is the node disabled
                        'selected'          => false, // is the node selected,
                        'checked'           => false,
                        'checkbox_disabled' => true,
                    ],
                ];
            } else {
                $result['li_attr']['class'] .= ' warning-childs';
            }
        } else {
            if (count($node->childs) == 0) {
                $result['children']        = $node->haveChildren;
                $result['state']['opened'] = false;
            }
        }

        foreach ($node->childs as $child) {
            $result['children'][] = self::treeNodeTojstreeNode($child, false, '', $addFullLoaded);
        }

        return $result;
    }

    /**
     * get the main target root path to make archive
     *
     * @return string
     */
    public static function getTargetRootPath()
    {
        static $targetRootPath = null;
        if (is_null($targetRootPath)) {
            $paths = self::getArchiveListPaths();
            unset($paths['wpconfig']);
            $targetRootPath = SnapIO::trailingslashit(SnapIO::getCommonPath($paths));
        }
        return $targetRootPath;
    }

    /**
     * Get original wordpress URLs
     *
     * @param null|string $urlKey if set will only return the url identified by that key
     *
     * @return array<string,string>|string return empty string if key doesn't exist
     */
    public static function getOriginalUrls($urlKey = null)
    {
        static $origUrls = null;
        if (is_null($origUrls)) {
            $restoreMultisite = false;
            if (is_multisite() && get_main_site_id() !== get_current_blog_id()) {
                $restoreMultisite = true;
                restore_current_blog();
                switch_to_blog(get_main_site_id());
            }

            $updDirs = wp_upload_dir(null, false, true);
            if (($wpConfigDir = SnapWP::getWPConfigPath()) !== false) {
                $wpConfigDir = dirname($wpConfigDir);
            }

            if (DUP_PRO_Global_Entity::getInstance()->homepath_as_abspath) {
                $homeUrl = site_url();
            } else {
                $homeUrl   = home_url();
                $homeParse = SnapURL::parseUrl(home_url());
                $absParse  = SnapURL::parseUrl(site_url());
                if ($homeParse['host'] === $absParse['host'] && SnapIO::isChildPath($homeParse['path'], $absParse['path'], false, false)) {
                    $homeParse['path'] = $absParse['path'];
                    $homeUrl           = SnapURL::buildUrl($homeParse);
                }
            }

            $origUrls = [
                'home'      => $homeUrl,
                'abs'       => site_url(),
                'login'     => wp_login_url(),
                'wpcontent' => content_url(),
                'uploads'   => $updDirs['baseurl'],
                'plugins'   => plugins_url(),
                'muplugins' => WPMU_PLUGIN_URL, // @phpstan-ignore phpstanWP.wpConstant.fetch
                'themes'    => get_theme_root_uri(),
            ];
            if ($restoreMultisite) {
                restore_current_blog();
            }
        }

        if ($urlKey === null) {
            return $origUrls;
        }

        if (isset($origUrls[$urlKey])) {
            return $origUrls[$urlKey];
        } else {
            return '';
        }
    }

    /**
     * Get WordPress core dirs
     *
     * @return string[]
     */
    public function filterWpCoreFoldersList(): array
    {
        return array_intersect($this->FilterDirsAll, PathUtil::getWPCoreDirs());
    }

    /**
     * Check if the wordpress core dirs are filtered
     *
     * @return bool
     */
    public function hasWpCoreFolderFiltered(): bool
    {
        return count($this->filterWpCoreFoldersList()) > 0;
    }

    /**
     * return the wordpress original dir paths
     *
     * @param string|null $pathKey path key
     *
     * @return array<string,string>|string return empty string if key doesn't exist
     */
    public static function getOriginalPaths($pathKey = null)
    {
        return SnapWP::getWpPaths($pathKey, DUP_PRO_Global_Entity::getInstance()->homepath_as_abspath);
    }

    /**
     * Return the wordpress original dir paths.
     *
     * @param string|null $pathKey path key
     *
     * @return array<string,string>|string return empty string if key doesn't exist
     */
    public static function getArchiveListPaths($pathKey = null)
    {
        return SnapWP::getNormalizedWpPaths($pathKey, DUP_PRO_Global_Entity::getInstance()->homepath_as_abspath);
    }

    /**
     *
     * @param string $path path to check
     *
     * @return bool return true if path is a path of current wordpress installation
     */
    public static function isCurrentWordpressInstallPath($path): bool
    {
        static $currentWpPaths = null;

        if (is_null($currentWpPaths)) {
            $currentWpPaths = array_merge(self::getOriginalPaths(), self::getArchiveListPaths());
            $currentWpPaths = array_map('trailingslashit', $currentWpPaths);
            $currentWpPaths = array_values(array_unique($currentWpPaths));
        }
        return in_array(trailingslashit($path), $currentWpPaths);
    }

    /**
     * Check if the homepath and abspath are equivalent
     *
     * @return bool
     */
    public static function isAbspathHomepathEquivalent()
    {
        static $isEquivalent = null;
        if (is_null($isEquivalent)) {
            $absPath      = SnapIO::safePathUntrailingslashit(ABSPATH, true);
            $homePath     = SnapIO::safePathUntrailingslashit(get_home_path(), true);
            $isEquivalent = (strcmp($homePath, $absPath) === 0);
        }
        return $isEquivalent;
    }

    /**
     * Get the path the file or dir should have inside the archive
     *
     * @param string $file     package file path
     * @param string $basePath base path
     *
     * @return string
     */
    public function getLocalPath($file, $basePath = ''): string
    {
        $safeFile = SnapIO::safePathUntrailingslashit($file);
        return ltrim($basePath . preg_replace('/^' . preg_quote($this->PackDir, '/') . '(.*)/m', '$1', $safeFile), '/');
    }
}
