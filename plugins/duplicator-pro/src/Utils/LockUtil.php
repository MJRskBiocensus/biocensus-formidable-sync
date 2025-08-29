<?php

namespace Duplicator\Utils;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;

/**
 * Lock utility
 */
class LockUtil
{
    const TEST_SQL_LOCK_NAME = 'duplicator_pro_test_lock';
    const LOCK_MODE_FILE     = 0;
    const LOCK_MODE_SQL      = 1;

    /** @var false|resource */
    protected static $lockingFile = false;

    /**
     * Return default lock
     *
     * @return int Enum lock type
     */
    public static function getDefaultLockType(): int
    {
        $lockType = self::LOCK_MODE_FILE;

        if (self::getSqlLock(self::TEST_SQL_LOCK_NAME)) {
            $lockType = (self::checkSqlLock(self::TEST_SQL_LOCK_NAME) ? self::LOCK_MODE_SQL : self::LOCK_MODE_FILE);
            self::releaseSqlLock(self::TEST_SQL_LOCK_NAME);
        }
        DUP_PRO_Log::trace("Lock type auto set to {$lockType}");
        return $lockType;
    }

    /**
     * Return lock mode
     *
     * @return int Lock mode ENUM self::LOCK_MODE_FILE or self::LOCK_MODE_SQL
     */
    public static function getLockMode()
    {
        return DUP_PRO_Global_Entity::getInstance()->lock_mode;
    }

    /**
     * Lock process
     *
     * @return bool true if lock acquired
     */
    public static function lockProcess()
    {
        if (self::getLockMode() == self::LOCK_MODE_SQL) {
            return self::getSqlLock();
        } else {
            return self::getFileLock();
        }
    }

    /**
     * Unlock process
     *
     * @return bool true if lock released
     */
    public static function unlockProcess()
    {
        if (self::getLockMode() == self::LOCK_MODE_SQL) {
            return self::releaseSqlLock();
        } else {
            return self::releaseFileLock();
        }
    }

    /**
     * Get file lock
     *
     * @return bool True if file lock acquired
     */
    protected static function getFileLock()
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        if ($global->lock_mode == self::LOCK_MODE_SQL) {
            return false;
        }

        if (
            self::$lockingFile === false &&
            (self::$lockingFile = fopen(DUPLICATOR_PRO_LOCKING_FILE_FILENAME, 'c+')) === false
        ) {
            // Problem opening the locking file report this is a critical error
            DUP_PRO_Log::trace("Problem opening locking file so auto switching to SQL lock mode");
            $global->lock_mode = self::LOCK_MODE_SQL;
            $global->save();
        }

        if (($acquired_lock = flock(self::$lockingFile, LOCK_EX | LOCK_NB)) !== false) {
            DUP_PRO_Log::trace("File lock acquired " . DUPLICATOR_PRO_LOCKING_FILE_FILENAME);
        } else {
            DUP_PRO_Log::trace("File lock denied " . DUPLICATOR_PRO_LOCKING_FILE_FILENAME);
        }

        return $acquired_lock;
    }

    /**
     * Release file lock
     *
     * @return bool True if file lock released
     */
    protected static function releaseFileLock()
    {
        if (self::$lockingFile === false) {
            return true;
        }

        $success = true;
        if (!flock(self::$lockingFile, LOCK_UN)) {
            DUP_PRO_Log::trace("File lock can't release");
            $success = false;
        } else {
            DUP_PRO_Log::trace("File lock released");
        }

        if (fclose(self::$lockingFile) === false) {
            DUP_PRO_Log::trace("Can't close file lock file");
        }

        self::$lockingFile = false;

        return $success;
    }
    /**
     * Gets an SQL lock request
     *
     * @see releaseSqlLock()
     *
     * @param string $lock_name The name of the lock to check
     *
     * @return bool Returns true if an SQL lock request was successful
     */
    protected static function getSqlLock($lock_name = 'duplicator_pro_lock'): bool
    {
        global $wpdb;

        $query_string = $wpdb->prepare("SELECT GET_LOCK(%s, 0)", $lock_name);
        $ret_val      = $wpdb->get_var($query_string);

        if ($ret_val == 0) {
            DUP_PRO_Log::trace("Mysql lock {$lock_name} denied");
            return false;
        } elseif ($ret_val == null) {
            DUP_PRO_Log::trace("Error retrieving mysql lock {$lock_name}");
            return false;
        }

        DUP_PRO_Log::trace("Mysql lock {$lock_name} acquired");
        return true;
    }

    /**
     * Rturn true if sql lock is set
     *
     * @param string $lock_name lock nam
     *
     * @return bool
     */
    protected static function checkSqlLock($lock_name = 'duplicator_pro_lock'): bool
    {
        global $wpdb;

        $query_string = $wpdb->prepare("SELECT IS_USED_LOCK(%s)", $lock_name);
        $ret_val      = $wpdb->get_var($query_string);

        return $ret_val > 0;
    }

    /**
     * Releases the SQL lock request
     *
     * @see getSqlLock()
     *
     * @param string $lock_name The name of the lock to release
     *
     * @return bool
     */
    protected static function releaseSqlLock($lock_name = 'duplicator_pro_lock'): bool
    {
        global $wpdb;

        $query_string = $wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name);
        $ret_val      = $wpdb->get_var($query_string);

        if ($ret_val == 0) {
            DUP_PRO_Log::trace("Failed releasing sql lock {$lock_name} because it wasn't established by this thread");
            return false;
        } elseif ($ret_val == null) {
            DUP_PRO_Log::trace("Tried to release sql lock {$lock_name} but it didn't exist");
            return false;
        } else {
            // Lock was released
            DUP_PRO_Log::trace("SQL lock {$lock_name} released");
        }

        return true;
    }
}
