<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package\Create;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use Duplicator\Package\Create\DupArchive\PackageDupArchiveCreateState;
use Duplicator\Package\Create\DupArchive\PackageDupArchiveExpandState;

/**
 * Backup build progress
 */
class BuildProgress
{
    /** @var int */
    public $thread_start_time = 0;
    /** @var bool */
    public $initialized = false;
    /** @var bool */
    public $installer_built = false;
    /** @var bool */
    public $archive_started = false;
    /** @var float */
    public $archive_start_time = 0;
    /** @var bool */
    public $archive_has_database = false;
    /** @var bool */
    public $archive_built = false;
    /** @var bool */
    public $database_script_built = false;
    /** @var bool */
    public $failed = false;
    /** @var int */
    public $next_archive_file_index = 0;
    /** @var int */
    public $next_archive_dir_index = 0;
    /** @var int */
    public $retries = 0;
    /** @var int */
    public $current_build_mode = -1;
    /** @var bool */
    public $current_build_compression = true;
    /** @var ?PackageDupArchiveCreateState */
    public $dupCreate;
    /** @var ?PackageDupArchiveExpandState */
    public $dupExpand;
    /** @var string[] */
    public $warnings = [];

    /**
     * Class contructor
     */
    public function __construct()
    {
    }



    /**
     * Set build mode
     *
     * @return int Return enum DUP_PRO_Archive_Build_Mode
     */
    public function setBuildMode()
    {
        if ($this->current_build_mode == -1) {
            DUP_PRO_Log::trace('set build mode');
            $global = DUP_PRO_Global_Entity::getInstance();
            $global->set_build_mode(true);
            $this->current_build_mode        = $global->getBuildMode();
            $this->current_build_compression = $global->archive_compression;
        } else {
            DUP_PRO_Log::trace("Build mode already set to $this->current_build_mode");
        }

        return $this->current_build_mode;
    }

    /**
     * Set build file progress start values
     *
     * @return void
     */
    public function setStartValues(): void
    {
        $this->archive_started         = true;
        $this->archive_start_time      = microtime(true);
        $this->retries                 = 0;
        $this->failed                  = false;
        $this->next_archive_file_index = 0;
        $this->next_archive_dir_index  = 0;
        $this->dupCreate               = null;
        $this->warnings                = [];
    }

    /**
     * Reset build progress values
     *
     * @return void
     */
    public function reset(): void
    {
        // don't reset current_build_mode and current_build_compression
        $this->thread_start_time       = 0;
        $this->initialized             = false;
        $this->installer_built         = false;
        $this->archive_started         = false;
        $this->archive_start_time      = 0;
        $this->archive_has_database    = false;
        $this->archive_built           = false;
        $this->database_script_built   = false;
        $this->failed                  = false;
        $this->next_archive_file_index = 0;
        $this->next_archive_dir_index  = 0;
        $this->retries                 = 0;
        $this->dupCreate               = null;
        $this->dupExpand               = null;
        $this->warnings                = [];
    }

    /**
     * Return true if is completed
     *
     * @return bool
     */
    public function hasCompleted(): bool
    {
        return $this->failed || ($this->installer_built && $this->archive_built && $this->database_script_built);
    }

    /**
     * Return true if is out of max time
     *
     * @param int $max_time max time
     *
     * @return bool
     */
    public function timedOut($max_time)
    {
        if ($max_time > 0) {
            $time_diff = time() - $this->thread_start_time;
            return ($time_diff >= $max_time);
        } else {
            return false;
        }
    }

    /**
     * Start time
     *
     * @return void
     */
    public function startTimer(): void
    {
        $this->thread_start_time = time();
    }
}
