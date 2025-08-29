<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use DUP_PRO_Archive;
use DUP_PRO_Package;
use DUP_PRO_ScanValidator;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\Support\SupportToolkit;
use Exception;
use Duplicator\Package\Storage\Status\StatusChecker;
use Duplicator\Utils\Logging\ErrorHandler;

class ServicesTools extends AbstractAjaxService
{
    /** @var int Maximum number of remote storage backup checks before stopping */
    const MAX_AJAX_BACKUP_REMOTE_STORAGE_CHECKS = 1000;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        if (!License::can(License::CAPABILITY_PRO_BASE)) {
            return;
        }
        $this->addAjaxCall('wp_ajax_DUP_PRO_CTRL_Tools_runScanValidator', 'runScanValidator');
        $this->addAjaxCall('wp_ajax_duplicator_download_support_toolkit', 'downloadSupportToolkit');
        $this->addAjaxCall('wp_ajax_duplicator_purge_backups_without_storages', 'bulkDeleteBackupsWithoutStorages');
        $this->addAjaxCall('wp_ajax_duplicator_get_backups_without_storages', 'getBackupsWithoutStorages');
        $this->addAjaxCall('wp_ajax_duplicator_check_remote_backups', 'checkRemoteBackups');
    }

    /**
     * Calls the ScanValidator and returns display JSON result
     *
     * @return void
     */
    public function runScanValidator(): void
    {
        ErrorHandler::init();
        check_ajax_referer('DUP_PRO_CTRL_Tools_runScanValidator', 'nonce');

        // Let's setup execution time on proper way (multiserver supported)
        try {
            if (function_exists('set_time_limit')) {
                set_time_limit(0); // unlimited
            } else {
                if (function_exists('ini_set') && SnapUtil::isIniValChangeable('max_execution_time')) {
                    ini_set('max_execution_time', '0'); // unlimited
                }
            }

            // there is error inside PHP because of PHP versions and server setup,
            // let's try to made small hack and set some "normal" value if is possible
        } catch (Exception $ex) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(3600); // 60 minutes
            } else {
                if (function_exists('ini_set') && SnapUtil::isIniValChangeable('max_execution_time')) {
                    @ini_set('max_execution_time', '3600'); //  60 minutes
                }
            }
        }

        //scan-recursive
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, [
            'scan-recursive' => [
                'filter' => FILTER_VALIDATE_BOOLEAN,
                'flags'  => FILTER_NULL_ON_FAILURE,
            ],
        ]);

        if (is_null($inputData['scan-recursive'])) {
            $isValid = false;
        }

        $result = [
            'success'  => false,
            'message'  => '',
            'scanData' => null,
        ];

        try {
            if (!$isValid) {
                throw new Exception(__("Invalid Request.", 'duplicator-pro'));
            }

            $scanner            = new DUP_PRO_ScanValidator();
            $scanner->recursion = $inputData['scan-recursive'];
            $result['scanData'] = $scanner->run(DUP_PRO_Archive::getScanPaths());
            $result['success']  = ($result['scanData']->fileCount > 0);
        } catch (Exception $exc) {
            $result['success'] = false;
            $result['message'] = $exc->getMessage();
        }

        wp_send_json($result);
    }

    /**
     * Function to download diagnostic data
     *
     * @return never
     */
    public function downloadSupportToolkit(): void
    {
        AjaxWrapper::fileDownload(
            [
                self::class,
                'downloadSupportToolkitCallback',
            ],
            'duplicator_download_support_toolkit',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }

    /**
     * Function to create diagnostic data
     *
     * @return array{path:string,name:string}
     */
    public static function downloadSupportToolkitCallback(): array
    {
        $domain = SnapURL::wwwRemove(SnapURL::parseUrl(network_home_url(), PHP_URL_HOST));

        return [
            'path' => SupportToolkit::getToolkit(),
            'name' => SupportToolkit::SUPPORT_TOOLKIT_PREFIX .
                substr(sanitize_file_name($domain), 0, 12) . '_' .
                date(DUP_PRO_Package::PACKAGE_HASH_DATE_FORMAT) . '.zip',
        ];
    }

    /**
     * Returns json info about the number of invalid backup and total backup records
     *
     * @return void
     */
    public function getBackupsWithoutStorages(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'getBackupsWithoutStoragesCallback',
            ],
            'duplicator_get_backups_without_storages',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Returns info about the number of invalid backup and total backup records
     *
     * @return array{message:string,stats:array{total:int,invalid:int}}
     */
    public static function getBackupsWithoutStoragesCallback(): array
    {
        $stats = [
            'total'   => DUP_PRO_Package::countByStatus(
                [
                    'relation' => 'OR',
                    [
                        'op'     => '>=',
                        'status' => DUP_PRO_Package::STATUS_COMPLETE,
                    ],
                    [
                        'op'     => '<',
                        'status' => DUP_PRO_Package::STATUS_PRE_PROCESS,
                    ],
                ],
                [DUP_PRO_Package::getBackupType()]
            ),
            'invalid' => count(PackageUtils::getPackageWithoutStorages()),
        ];

        return [
            'message' => sprintf(
                _x(
                    '%1$d of %2$d backup records don\'t exist in any of their storages.',
                    '%1$d is the number of invalid records, %2$d is the total number of records',
                    'duplicator-pro'
                ),
                $stats['invalid'],
                $stats['total']
            ),
            'stats'   => $stats,
        ];
    }

    /**
     * Purges backup records that have no valid storage and returns a message
     *
     * @return void
     */
    public function bulkDeleteBackupsWithoutStorages(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'bulkDeleteBackupsWithoutStoragesCallback',
            ],
            'duplicator_purge_backups_without_storages',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Purges backup records that have no valid storage and returns a message
     *
     * @return array{success:bool,message:string,processed:int,totalProcessed:int}
     */
    public static function bulkDeleteBackupsWithoutStoragesCallback(): array
    {
        $result = [
            'success'        => false,
            'message'        => '',
            'processed'      => -1,
            'totalProcessed' => 0,
        ];

        try {
            $totalProcessed = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'totalProcessed', 0);
            $processed      = PackageUtils::bulkDeletePackageWithoutStoragesChunk();

            if ($processed >= 0) {
                $totalProcessed += $processed;
            }

            $result['success']        = ($processed >= 0);
            $result['message']        = sprintf(
                _n(
                    'Successfully deleted %d backup record.',
                    'Successfully deleted %d backup records.',
                    $totalProcessed,
                    'duplicator-pro'
                ),
                $totalProcessed
            );
            $result['processed']      = $processed;
            $result['totalProcessed'] = $totalProcessed;
        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }


    /**
     * Check remote backups status
     *
     * @return array{success: bool, message: string, processed: int, totalProcessed: int}
     */
    public function checkRemoteBackups(): array
    {
        AjaxWrapper::json(
            [
                self::class,
                'checkRemoteBackupsCallback',
            ],
            'duplicator_check_remote_backups',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }

    /**
     * Check remote backups status
     *
     * @return array{success:bool,message:string,processed:int,totalProcessed:int}
     */
    public static function checkRemoteBackupsCallback(): array
    {
        $result = [
            'success'        => false,
            'message'        => '',
            'processed'      => -1,
            'totalProcessed' => 0,
        ];

        try {
            $totalProcessed = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'totalProcessed', 0);
            $processed      = StatusChecker::processNextChunk(StatusChecker::MIN_INTERVAL_MANUAL);

            if ($processed >= 0) {
                $totalProcessed += $processed;
            }

            $result['success']        = ($processed >= 0);
            $result['message']        = sprintf(
                _n(
                    'Successfully checked %d backup.',
                    'Successfully checked %d backups.',
                    $totalProcessed,
                    'duplicator-pro'
                ),
                $totalProcessed
            );
            $result['processed']      = $processed;
            $result['totalProcessed'] = $totalProcessed;
        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
