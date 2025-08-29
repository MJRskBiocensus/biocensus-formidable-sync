<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package\Storage;

use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_Upload_Info;
use Duplicator\Libs\Chunking\ChunkingManager;
use Duplicator\Libs\Chunking\Iterators\GenericSeekableIteratorInterface;
use Duplicator\Libs\Chunking\Iterators\TimeoutFileCopyIterator;
use Duplicator\Models\Storages\AbstractStorageAdapter;
use Duplicator\Models\Storages\Local\LocalStorageAdapter;
use Exception;

/**
 * Chunk manager for storage uploads
 */
class StorageTransferChunkFiles extends ChunkingManager
{
    /** @var int<0,max> */
    protected $chunkSize = 0;
    /** @var int timeout in microseconds */
    protected int $chunkTimeout;
    protected \Duplicator\Models\Storages\AbstractStorageAdapter $adapter;
    protected \DUP_PRO_Package_Upload_Info $uploadInfo;
    protected \DUP_PRO_Package $package;
    /** @var bool */
    protected $download = false;

    /**
     * Class contructor
     *
     * @param mixed $extraData    extra data for manager used on extended classes
     * @param int   $maxIteration max number of iterations, 0 for no limit
     * @param int   $timeOut      timeout in microseconds, 0 for no timeout
     * @param int   $throttling   throttling microseconds, 0 for no throttling
     */
    public function __construct($extraData = null, $maxIteration = 0, $timeOut = 0, $throttling = 0)
    {
        $this->chunkSize = $extraData['chunkSize'];

        if (!$extraData['adapter'] instanceof AbstractStorageAdapter) {
            throw new Exception('Adapter must be an instance of AbstractStorageAdapter');
        }
        $this->adapter = $extraData['adapter'];

        if (!$extraData['upload_info'] instanceof DUP_PRO_Package_Upload_Info) {
            throw new Exception('Upload info must be an instance of DUP_PRO_Package_Upload_Info');
        }
        $this->uploadInfo = $extraData['upload_info'];

        if (!$extraData['package'] instanceof DUP_PRO_Package) {
            throw new Exception('Package must be an instance of DUP_PRO_Package');
        }
        $this->package = $extraData['package'];

        if (isset($extraData['download']) && is_bool($extraData['download'])) {
            $this->download = $extraData['download'];
        }

        $this->chunkTimeout = (int) (!empty($extraData['chunkTimeout']) ? $extraData['chunkTimeout'] : 0);

        parent::__construct($extraData, $maxIteration, $timeOut, $throttling);
    }

    /**
     * Execute chunk action
     *
     * @param string                    $key     the current key
     * @param array<string, string|int> $current the current element
     *
     * @return bool
     */
    protected function action($key, $current)
    {
        if (strlen($current['from']) == 0) {
            return true;
        }

        if ($this->download) {
            return $this->downloadAction($current);
        } else {
            return $this->uploadAction($current);
        }
    }

    /**
     * Execute upload chunk action
     *
     * @param array<string, string|int> $current the current element
     *
     * @return bool
     */
    protected function uploadAction($current)
    {
        if (!$this->adapter->areMethodsAvailable(AbstractStorageAdapter::METHODS_UPLOAD)) {
            throw new Exception('Adapter must have upload methods available');
        }

        if (is_file($current['from'])) {
            DUP_PRO_Log::infoTrace('Copying file: ' . $current['from']);
            DUP_PRO_Log::infoTrace('Offset: ' . $current['offset'] . ' of: ' . filesize($current['from']));

            $result = $this->adapter->copyToStorage(
                $current['from'],
                $current['to'],
                $current['offset'],
                $this->chunkSize,
                $this->chunkTimeout,
                $this->uploadInfo->copyExtraData
            );
            if ($result === false) {
                return false;
            } else {
                /** @var TimeoutFileCopyIterator */
                $it = $this->it;
                $it->updateCurrentFileOffset($result);
                return true;
            }
        } elseif (is_dir($current['from'])) {
            return $this->adapter->createDir($current['to']);
        } else {
            return false;
        }
    }

    /**
     * Execute download chunk action
     *
     * @param array<string, string|int> $current the current element
     *
     * @return bool
     */
    protected function downloadAction($current)
    {
        if (!$this->adapter->areMethodsAvailable(AbstractStorageAdapter::METHODS_DOWNLOAD)) {
            throw new Exception('Adapter must have upload methods available');
        }

        if ($this->adapter->isFile($current['from'])) {
            DUP_PRO_Log::infoTrace('Copying file: ' . $current['from'] . ' to: ' . $current['to']);
            DUP_PRO_Log::infoTrace('Offset: ' . $current['offset'] . ' of: ' . $this->adapter->fileSize($current['from']));

            $result = $this->adapter->copyFromStorage(
                $current['from'],
                $current['to'],
                $current['offset'],
                $this->chunkSize,
                $this->chunkTimeout,
                $this->uploadInfo->copyExtraData
            );
            if ($result === false) {
                return false;
            } else {
                /** @var TimeoutFileCopyIterator */
                $it = $this->it;
                $it->updateCurrentFileOffset($result);
                return true;
            }
        } elseif ($this->adapter->isDir($current['from'])) {
            return wp_mkdir_p($current['to']);
        } else {
            return false;
        }
    }

    /**
     * Return iterator
     *
     * @param array<string, mixed> $extraData extra data for manager used on extended classes
     *
     * @return TimeoutFileCopyIterator
     */
    protected function getIterator($extraData = null): TimeoutFileCopyIterator
    {
        $adapter = $this->download ? $this->adapter : new LocalStorageAdapter('/');
        $it      = new TimeoutFileCopyIterator($extraData['replacements'], $adapter, function (): void {
            //reset extra data when the file being copied changes
            $this->uploadInfo->copyExtraData = [];
        });
        $it->setTotalSize();
        return $it;
    }

    /**
     * Return persistance adapter
     *
     * @param mixed $extraData extra data for manager used on extended classes
     *
     * @return UploadPackageFilePersistanceAdapter
     */
    protected function getPersistance($extraData = null): UploadPackageFilePersistanceAdapter
    {
        return new UploadPackageFilePersistanceAdapter($this->uploadInfo, $this->package);
    }
}
