<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\DropboxAddon\Models;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use Duplicator\Addons\DropboxAddon\Utils\DropboxClient;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StorageAuthInterface;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\OAuth\TokenEntity;
use Duplicator\Utils\OAuth\TokenService;
use Exception;

/**
 * @property DropboxAdapter $adapter
 */
class DropboxStorage extends AbstractStorageEntity implements StorageAuthInterface
{
    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultConfig(): array
    {
        $config = parent::getDefaultConfig();
        return array_merge(
            $config,
            [
                'v2_access_token' => '',
                'authorized'      => false,
                'token_json'      => false,
            ]
        );
    }

    /**
     * Serialize
     *
     * Wakeup method.
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();

        if ($this->legacyEntity) {
            // Old storage entity
            $this->legacyEntity = false;
            // Make sure the storage type is right from the old entity
            $this->storage_type = static::getSType();
            $this->config       = [
                'storage_folder' => ltrim($this->dropbox_storage_folder, '/\\'),
                'max_packages'   => $this->dropbox_max_files,
                'authorized'     => ($this->dropbox_authorization_state == 4),
            ];
            if ($this->dropbox_v2_access_token !== '') {
                $this->config['v2_access_token'] = $this->dropbox_v2_access_token;
            } elseif ($this->dropbox_access_token !== '' && $this->dropbox_access_token_secret !== '') {
                $this->config['v2_access_token'] = self::getV2Token($this->dropbox_access_token, $this->dropbox_access_token_secret);
            }

            // reset old values
            $this->dropbox_access_token        = '';
            $this->dropbox_access_token_secret = '';
            $this->dropbox_v2_access_token     = '';
            $this->dropbox_storage_folder      = '';
            $this->dropbox_max_files           = 10;
            $this->dropbox_authorization_state = 0;
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 1;
    }

    /**
     * Returns the storage type icon.
     *
     * @return string Returns the storage icon
     */
    public static function getStypeIcon(): string
    {
        return '<img src="' . esc_url(DUPLICATOR_PRO_IMG_URL . '/dropbox.svg') . '" class="dup-storage-icon" alt="' . esc_attr(static::getStypeName()) . '" />';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Dropbox', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        $dropBoxInfo = $this->getAccountInfo();
        if (!isset($dropBoxInfo['locale']) || $dropBoxInfo['locale'] == 'en') {
            return "https://dropbox.com/home/Apps/Duplicator%20Pro/" . ltrim($this->getStorageFolder(), '/');
        } else {
            return "https://dropbox.com/home";
        }
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    public function isValid(): bool
    {
        return $this->isAuthorized();
    }

    /**
     * Is autorized
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return $this->config['authorized'];
    }

    /**
     * Authorized from HTTP request
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function authorizeFromRequest(&$message = ''): bool
    {
        try {
            if (($refreshToken = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'auth_code')) === '') {
                throw new Exception(__('Authorization code is empty', 'duplicator-pro'));
            }

            $this->name                     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');
            $this->notes                    = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
            $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_packages', 10);
            $this->config['storage_folder'] = self::getSanitizedInputFolder('storage_folder', 'remove');

            $this->revokeAuthorization();

            $token = new TokenEntity(self::getSType(), ['refresh_token' => $refreshToken]);

            if ($token->refresh(true) === false) {
                DUP_PRO_Log::infoTrace("Problem initializing Dropbox with {$refreshToken}");
                throw new Exception(__("Couldn't connect. Dropbox access token is invalid or doesn't have required permissions.", 'duplicator-pro'));
            }
            /** @todo Config should contain scalar data but it was chosen to assign complex structures ignoring the type. this should be fixed sooner or later. */
            $this->config['token_json']      = [ // @phpstan-ignore assign.propertyType
                'refresh_token' => $token->getRefreshToken(),
                'access_token'  => $token->getAccessToken(),
                'expires_in'    => $token->getExpiresIn(),
                'created'       => $token->getCreated(),
                'scope'         => $token->getScope(),
            ];
            $this->config['v2_access_token'] = $token->getAccessToken();
            $this->config['authorized']      = true;
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Problem authorizing Dropbox access token msg: " . $e->getMessage());
            $message = $e->getMessage();
            return false;
        }

        $message = __('Dropbox is connected successfully and Storage Provider Updated.', 'duplicator-pro');
        return true;
    }

    /**
     * Revokes authorization
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function revokeAuthorization(&$message = ''): bool
    {
        if (!$this->isAuthorized()) {
            $message = __('Dropbox isn\'t authorized.', 'duplicator-pro');
            return true;
        }

        try {
            $client = $this->getAdapter()->getClient();
            $client->revokeToken();
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Problem revoking Dropbox access token msg: " . $e->getMessage());
        } finally {
            $this->config['v2_access_token'] = '';
            $this->config['authorized']      = false;
            $this->config['token_json']      = false;
        }

        $message = __('Dropbox is disconnected successfully.', 'duplicator-pro');
        return true;
    }

    /**
     * Get authorization URL
     *
     * @todo: This should be refactored to use the new TokenService class.
     *
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        return (new TokenService(static::getSType()))->getRedirectUri();
    }

    /**
     * Returns the config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getConfigFieldsData(): array
    {
        return array_merge($this->getDefaultConfigFieldsData(), [
            'accountInfo' => $this->getAccountInfo(),
            'quotaInfo'   => $this->getQuota(),
        ]);
    }

    /**
     * Returns the default config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfigFieldsData(): array
    {
        return [
            'storage'       => $this,
            'accountInfo'   => false,
            'quotaInfo'     => false,
            'storageFolder' => $this->config['storage_folder'],
            'maxPackages'   => $this->config['max_packages'],
        ];
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    protected function getConfigFieldsTemplatePath(): string
    {
        return 'dropboxaddon/configs/dropbox';
    }

    /**
     * Update data from http request, this method don't save data, just update object properties
     *
     * @param string $message Message
     *
     * @return bool True if success and all data is valid, false otherwise
     */
    public function updateFromHttpRequest(&$message = ''): bool
    {
        if ((parent::updateFromHttpRequest($message) === false)) {
            return false;
        }

        $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'dropbox_max_files', 10);
        $this->config['storage_folder'] = self::getSanitizedInputFolder('_dropbox_storage_folder', 'remove');

        $message = sprintf(
            __('Dropbox Storage Updated. Folder: %1$s', 'duplicator-pro'),
            $this->getStorageFolder()
        );
        return true;
    }

    /**
     * Get the storage adapter
     *
     * @return DropboxAdapter
     */
    protected function getAdapter(): DropboxAdapter
    {
        if (! $this->adapter) {
            $global = DUP_PRO_Global_Entity::getInstance();

            // if we have an oauth2 token, we may need to refresh the access token.
            if (isset($this->config['token_json']) && is_array($this->config['token_json'])) {
                $token = new TokenEntity(self::getSType(), $this->config['token_json']);
                if ($token->isAboutToExpire()) {
                    if ($token->refresh()) {
                        /** @todo Config should contain scalar data but it was chosen to assign complex structures ignoring the type. this should be fixed sooner or later. */
                        $this->config['token_json']      = [ // @phpstan-ignore assign.propertyType
                            'refresh_token' => $token->getRefreshToken(),
                            'access_token'  => $token->getAccessToken(),
                            'expires_in'    => $token->getExpiresIn(),
                            'created'       => $token->getCreated(),
                            'scope'         => $token->getScope(),
                        ];
                        $this->config['v2_access_token'] = $token->getAccessToken();
                        $this->save();
                    } else {
                        DUP_PRO_Log::infoTrace('Problem refreshing Dropbox token');
                    }
                }
            }
            $this->adapter = new DropboxAdapter(
                $this->config['v2_access_token'],
                $this->getStorageFolder(),
                !$global->ssl_disableverify,
                ($global->ssl_useservercerts ? '' : DUPLICATOR_PRO_CERT_PATH),
                $global->ipv4_only
            );
        }

        return $this->adapter;
    }

    /**
     * Get dropbox api key and secret
     *
     * @return array{app_key:string,app_secret:string}
     */
    protected static function getApiKeySecret(): array
    {
        $dk   = self::getDk1();
        $dk   = self::getDk2() . $dk;
        $akey = CryptBlowfish::decryptIfAvaiable('EQNJ53++6/40fuF5ke+IaQ==', $dk);
        $asec = CryptBlowfish::decryptIfAvaiable('ui25chqoBexPt6QDi9qmGg==', $dk);
        $akey = trim($akey);
        $asec = trim($asec);
        if (($akey != $asec) || ($akey != "fdda100")) {
            $akey = self::getAk1() . self::getAk2();
            $asec = self::getAs1() . self::getAs2();
        }
        return [
            'app_key'    => $asec,
            'app_secret' => $akey,
        ];
    }

    /**
     * Get dk1
     *
     * @return string
     */
    private static function getDk1(): string
    {
        return 'y8!!';
    }

    /**
     * Get dk2
     *
     * @return string
     */
    private static function getDk2(): string
    {
        return '32897';
    }

    /**
     * Get ak1
     *
     * @return string
     */
    private static function getAk1(): string
    {
        return strrev('i6gh72iv');
    }

    /**
     * Get ak2
     *
     * @return string
     */
    private static function getAk2(): string
    {
        return strrev('1xgkhw2');
    }

    /**
     * Get as1
     *
     * @return string
     */
    private static function getAs1(): string
    {
        return strrev('z7fl2twoo');
    }

    /**
     * Get as2
     *
     * @return string
     */
    private static function getAs2(): string
    {
        return strrev('2z2bfm');
    }

    /**
     * Set v2 access token from v1 client
     *
     * @param string $accessToken       V1 access token
     * @param string $accessTokenSecret V1 access token secret
     *
     * @return string V2 access token
     */
    protected static function getV2Token($accessToken = '', $accessTokenSecret = ''): string
    {
        if (strlen($accessToken) == 0 && strlen($accessTokenSecret) == 0) {
            return '';
        }

        $oldToken = [
            't' => $accessToken,
            's' => $accessTokenSecret,
        ];

        $accessToken = DropboxClient::accessTokenFromOauth1($oldToken, self::getApiKeySecret());
        if ($accessToken) {
            return $accessToken;
        } else {
            DUP_PRO_Log::trace("Problem converting Dropbox access token");
            return '';
        }
    }

    /**
     * Get account info
     *
     * @return false|array<string,mixed>
     */
    protected function getAccountInfo()
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        try {
            return $this->getAdapter()->getClient()->getAccountInfo();
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Problem getting Dropbox account info. " . $e->getMessage());
        }
        return false;
    }

    /**
     * Get dropbox quota
     *
     * @return false|array{used:int,total:int,perc:float,available:string}
     */
    protected function getQuota()
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        $quota = $this->getAdapter()->getClient()->getQuota();
        if (
            !isset($quota['used']) ||
            !isset($quota['allocation']['allocated']) ||
            $quota['allocation']['allocated'] <= 0
        ) {
            return false;
        }

        $quota_used          = $quota['used'];
        $quota_total         = $quota['allocation']['allocated'];
        $used_perc           = round($quota_used * 100 / $quota_total, 1);
        $available_quota     = $quota_total - $quota_used;
        $available_quota_str = size_format($available_quota) ?: 'unknown';

        return [
            'used'      => $quota_used,
            'total'     => $quota_total,
            'perc'      => $used_perc,
            'available' => $available_quota_str,
        ];
    }

    /**
     * Get upload chunk size in bytes
     *
     * @return int bytes
     */
    public function getUploadChunkSize(): int
    {
        $dGlobal = DynamicGlobalEntity::getInstance();
        $size    = (int) $dGlobal->getVal('dropbox_upload_chunksize_in_kb', 2000);
        return $size * KB_IN_BYTES;
    }

    /**
     * Get download chunk size in bytes
     *
     * @return int bytes
     */
    public function getDownloadChunkSize(): int
    {
        $dGlobal = DynamicGlobalEntity::getInstance();
        return $dGlobal->getVal('dropbox_download_chunksize_in_kb', 10000) * KB_IN_BYTES;
    }

    /**
     * Get upload chunk timeout in seconds
     *
     * @return int timeout in microseconds, 0 unlimited
     */
    public function getUploadChunkTimeout(): int
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        return (int) ($global->php_max_worker_time_in_sec <= 0 ? 0 :  $global->php_max_worker_time_in_sec * SECONDS_IN_MICROSECONDS);
    }

    /**
     * @return void
     */
    public static function registerType(): void
    {
        parent::registerType();

        add_action('duplicator_update_global_storage_settings', function (): void {
            $dGlobal = DynamicGlobalEntity::getInstance();

            foreach (static::getDefaultSettings() as $key => $default) {
                $value = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, $key, $default);
                $dGlobal->setValInt($key, $value);
            }
        });
    }

    /**
     * Get default settings
     *
     * @return array<string, scalar>
     */
    protected static function getDefaultSettings(): array
    {
        return [
            'dropbox_upload_chunksize_in_kb'   => 2000,
            'dropbox_download_chunksize_in_kb' => 10000,
        ];
    }

    /**
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
        $dGlobal = DynamicGlobalEntity::getInstance();
        TplMng::getInstance()->render(
            'dropboxaddon/configs/global_options',
            [
                'uploadChunkSize'   => $dGlobal->getVal('dropbox_upload_chunksize_in_kb', 2000),
                'downloadChunkSize' => $dGlobal->getVal('dropbox_download_chunksize_in_kb', 10000),
            ]
        );
    }
}
