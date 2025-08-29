<?php

namespace Duplicator\Package\Storage\Status;

use DUP_PRO_Log;
use DUP_PRO_Package;
use Exception;

/**
 * Class to handle checking backup status in remote storages
 */
class StatusChecker
{
    /** @var int Number of packages to process in each chunk */
    const CHUNK_SIZE = 5;
    /** @var int Minimum time between checks for each package */
    const MIN_INTERVAL_CRON = WEEK_IN_SECONDS;
    /** @var int Manual check interval in seconds */
    const MIN_INTERVAL_MANUAL = 6 * HOUR_IN_SECONDS;
    /** @var int WP-CLI check interval in seconds */
    const MIN_INTERVAL_WP_CLI = 30;

    /**
     * Process the next chunk of packages that need checking
     *
     * @param int $minCheckInterval Minimum check interval in seconds
     *
     * @return int Number of packages processed, -1 on error
     */
    public static function processNextChunk(int $minCheckInterval): int
    {
        $processed = 0;

        try {
            $where = self::buildWhereClause($minCheckInterval);
            DUP_PRO_Log::trace("UPDATE REMOTE STORAGE STATUS, WHERE: {$where}");
            DUP_PRO_Package::dbSelectCallback(
                function (DUP_PRO_Package $package) use (&$processed): void {
                    self::updateStoragesStatus($package);
                    $processed++;
                },
                $where,
                self::CHUNK_SIZE,
                0,
                'updated_at ASC',
                [DUP_PRO_Package::getBackupType()]
            );
            DUP_PRO_Log::trace("UPDATED {$processed} PACKAGES");
        } catch (Exception $e) {
            DUP_PRO_Log::traceException($e, "Error processing backup status check chunk");
            return -1;
        }

        return $processed;
    }

    /**
     * Build the where clause for the package selection
     *
     * @param int $minCheckInterval Minimum check interval in seconds
     *
     * @return string
     */
    private static function buildWhereClause(int $minCheckInterval): string
    {
        global $wpdb;
        return $wpdb->prepare(
            "(status < %d OR status = %d) AND updated_at < DATE_SUB(%s, INTERVAL %d SECOND)",
            DUP_PRO_Package::STATUS_PRE_PROCESS,
            DUP_PRO_Package::STATUS_COMPLETE,
            gmdate("Y-m-d H:i:s"), // Current time
            $minCheckInterval
        );
    }

    /**
     * Check the status of a single package in all its storages
     *
     * @param DUP_PRO_Package $package The package to check
     *
     * @return void
     */
    protected static function updateStoragesStatus(DUP_PRO_Package $package): void
    {
        $ids = $package->getValidStorages(false, 'id');
        if ($package->save(false) === false) {
            // Make sure the updated_at is updated
            throw new Exception("FAILE TO UPDATE THE PACKAGE {$package->getId()}");
        }
        DUP_PRO_Log::trace("CHECKING PACKAGE {$package->getId()} NAME: {$package->getName()} VALID STORAGES: " . wp_json_encode($ids));
    }
}
