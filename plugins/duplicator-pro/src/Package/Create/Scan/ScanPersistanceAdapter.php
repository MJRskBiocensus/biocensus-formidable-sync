<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

 namespace Duplicator\Package\Create\Scan;

use Duplicator\Libs\Chunking\Iterators\GenericSeekableIteratorInterface;
use Duplicator\Libs\Chunking\Persistance\FileJsonPersistanceAdapter;
use Duplicator\Package\Create\Scan\ScanResult;
use Exception;

class ScanPersistanceAdapter extends FileJsonPersistanceAdapter
{
    const PERSSITANCE_FILE_POSTFIX = '_scan_persistance.json';

    protected \Duplicator\Package\Create\Scan\ScanResult $scanResult;

    /**
     * Class constructor
     *
     * @param string     $hash       persistance file hash
     * @param ScanResult $scanResult scan result object
     */
    public function __construct($hash, ScanResult $scanResult)
    {
        if (empty($hash)) {
            throw new Exception('hash can\'t be empty');
        }
        $path             = DUPLICATOR_PRO_SSDIR_PATH_TMP . '/' . $hash . self::PERSSITANCE_FILE_POSTFIX;
        $this->scanResult = $scanResult;
        parent::__construct($path);
    }

    /**
     * Load data from previous iteration if exists
     *
     * @return mixed return iterator position
     */
    public function getPersistanceData()
    {
        $data = parent::getPersistanceData();
        if (is_array($data) && isset($data['scanResult']) && isset($data['itPosition'])) {
            $this->scanResult->import($data['scanResult']);
            return $data['itPosition'];
        }
        return null;
    }

    /**
     * Save data for next step
     *
     * @param mixed                            $data data to save
     * @param GenericSeekableIteratorInterface $it   current iterator
     *
     * @return bool This function returns true on success, or FALSE on failure.
     */
    public function savePersistanceData($data, GenericSeekableIteratorInterface $it)
    {
        $saveDate = [
            'scanResult' => $this->scanResult,
            'itPosition' => $data,
        ];
        return parent::savePersistanceData($saveDate, $it);
    }
}
