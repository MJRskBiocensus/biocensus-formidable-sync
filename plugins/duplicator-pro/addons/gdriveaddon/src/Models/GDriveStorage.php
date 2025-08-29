<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\GDriveAddon\Models;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StorageAuthInterface;
use Duplicator\Utils\OAuth\TokenEntity;
use Duplicator\Utils\OAuth\TokenService;
use Duplicator\Views\AdminNotices;
use Exception;

/**
 * @property GDriveAdapter $adapter
 */
class GDriveStorage extends AbstractStorageEntity implements StorageAuthInterface
{
    // These numbers represent clients created in Google Cloud Console
    const GDRIVE_CLIENT_NATIVE  = 1; // Native client 1
    const GDRIVE_CLIENT_WEB0722 = 2; // Web client 07/2022
    const GDRIVE_CLIENT_LATEST  = 2; // Latest out of these above

    const REQUIRED_SCOPES = [
        "openid",
        "https://www.googleapis.com/auth/userinfo.profile",
        "https://www.googleapis.com/auth/userinfo.email",
        // The drive.file scope limits access to just those files created by the plugin
        "https://www.googleapis.com/auth/drive.file",
    ];

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
                'storage_folder_id'      => '',
                'storage_folder_web_url' => '',
                'token_json'             => '',
                'refresh_token'          => '',
                'client_number'          => -1,
                'authorized'             => false,
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
                'token_json'     => $this->gdrive_access_token_set_json,
                'refresh_token'  => $this->gdrive_refresh_token,
                'storage_folder' => ltrim($this->gdrive_storage_folder, '/\\'),
                'client_number'  => $this->gdrive_client_number,
                'max_packages'   => $this->gdrive_max_files,
                'authorized'     => ($this->gdrive_authorization_state == 1),
            ];
            // reset old values
            $this->gdrive_access_token_set_json = '';
            $this->gdrive_refresh_token         = '';
            $this->gdrive_storage_folder        = '';
            $this->gdrive_client_number         = -1;
            $this->gdrive_max_files             = 10;
            $this->gdrive_authorization_state   = 0;
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 3;
    }

    /**
     * Returns the storage type icon.
     *
     * @return string Returns the storage icon
     */
    public static function getStypeIcon(): string
    {
        $imgUrl = DUPLICATOR_PRO_IMG_URL . '/google-drive.svg';
        return '<img src="' . esc_url($imgUrl) . '" class="dup-storage-icon" alt="' . esc_attr(static::getStypeName()) . '" />';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Google Drive', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        if ($this->isAuthorized()) {
            return $this->config['storage_folder_web_url'];
        } else {
            return __('Not Authenticated', 'duplicator-pro');
        }
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return (SnapUtil::isCurlEnabled() || SnapUtil::isUrlFopenEnabled());
    }

    /**
     * Get supported notice, displayed if storage isn't supported
     *
     * @return string html string or empty if storage is supported
     */
    public static function getNotSupportedNotice(): string
    {
        if (static::isSupported()) {
            return '';
        }

        if (!SnapUtil::isCurlEnabled() && !SnapUtil::isUrlFopenEnabled()) {
            return esc_html__(
                'Google Drive requires either the PHP CURL extension enabled or the allow_url_fopen runtime configuration to be enabled.',
                'duplicator-pro'
            );
        } elseif (!SnapUtil::isCurlEnabled()) {
            return esc_html__('Google Drive requires the PHP CURL extension enabled.', 'duplicator-pro');
        } else {
            return esc_html__('Google Drive requires the allow_url_fopen runtime configuration to be enabled.', 'duplicator-pro');
        }
    }

    /**
     * Get upload chunk size in bytes
     *
     * @return int bytes
     */
    public function getUploadChunkSize(): int
    {
        $dGlobal     = DynamicGlobalEntity::getInstance();
        $chunkSizeKb = $dGlobal->getVal('gdrive_upload_chunksize_in_kb', 256);

        return $chunkSizeKb * KB_IN_BYTES;
    }

    /**
     * Get download chunk size in bytes
     *
     * @return int bytes
     */
    public function getDownloadChunkSize(): int
    {
        $dGlobal     = DynamicGlobalEntity::getInstance();
        $chunkSizeKb = $dGlobal->getVal('gdrive_download_chunksize_in_kb', 10 * 1024);

        return $chunkSizeKb * KB_IN_BYTES;
    }

    /**
     * Get upload chunk timeout in seconds
     *
     * @return int timeout in microseconds, 0 unlimited
     */
    public function getUploadChunkTimeout(): int
    {
        // @todo: fixed to 10 seconds for historical reasons, make it configurable.
        return 10 * 1000000;
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
     * Returns an HTML anchor tag of location
     *
     * @return string Returns an HTML anchor tag with the storage location as a hyperlink.
     */
    public function getHtmlLocationLink(): string
    {
        if (! $this->isAuthorized() || empty($this->config['storage_folder_web_url'])) {
            return '<span>' . esc_html($this->getStorageFolder()) . '</span>';
        }

        return sprintf("<a href=\"%s\" target=\"_blank\">%s</a>", esc_url($this->config['storage_folder_web_url']), esc_html($this->getStorageFolder()));
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
        $tokenPairString = '';
        try {
            if (($refreshToken = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'auth_code')) === '') {
                throw new Exception(__('Authorization code is empty', 'duplicator-pro'));
            }

            $this->name                     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');
            $this->notes                    = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
            $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_packages', 10);
            $this->config['storage_folder'] = self::getSanitizedInputFolder('storage_folder', 'remove');

            $this->revokeAuthorization();

            $token = (new TokenEntity(static::getSType(), ['refresh_token' => $refreshToken]));
            if (!$token->refresh(true)) {
                throw new Exception(__('Failed to fetch information from Google Drive. Make sure the token is valid.', 'duplicator-pro'));
            }

            if (empty($token->getScope())) {
                throw new Exception(__("Couldn't connect. Google Drive scopes not found.", 'duplicator-pro'));
            }

            if (! $token->hasScopes(static::REQUIRED_SCOPES)) {
                throw new Exception(
                    __(
                        "Authorization failed. You did not allow all required permissions. Try again and make sure that you checked all checkboxes.",
                        'duplicator-pro'
                    )
                );
            }

            $this->config['refresh_token'] = $token->getRefreshToken();
            $this->config['token_json']    = wp_json_encode([
                'created'       => $token->getCreated(),
                'access_token'  => $token->getAccessToken(),
                'refresh_token' => $token->getRefreshToken(),
                'expires_in'    => $token->getExpiresIn(),
                'scope'         => $token->getScope(),
            ]);
            $this->config['client_number'] = self::GDRIVE_CLIENT_LATEST;

            $this->config['authorized'] = $token->isValid();
        } catch (Exception $e) {
            DUP_PRO_Log::traceException($e, "Problem authorizing Google Drive access token");
            DUP_PRO_Log::traceObject('Token pair string from authorization:', $tokenPairString);
            $message = $e->getMessage();
            return false;
        }
        $this->save();

        $message = __('Google Drive is connected successfully and Storage Provider Updated.', 'duplicator-pro');
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
            $message = __('Google Drive isn\'t authorized.', 'duplicator-pro');
            return true;
        }

        try {
            $client = $this->getAdapter()->getService()->getClient();

            if (!empty($this->config['refresh_token'])) {
                $client->revokeToken($this->config['refresh_token']);
            }

            $accessTokenObj = json_decode($this->config['token_json']);
            if (is_object($accessTokenObj) && property_exists($accessTokenObj, 'access_token')) {
                $gdrive_access_token = $accessTokenObj->access_token;
            } else {
                $gdrive_access_token = false;
            }

            if (!empty($gdrive_access_token)) {
                $client->revokeToken($gdrive_access_token);
            }
        } catch (Exception $e) {
            DUP_PRO_Log::trace("Problem revoking Google Drive access token msg: " . $e->getMessage());
            $message = $e->getMessage();
            return false;
        } finally {
            $this->config['token_json']    = '';
            $this->config['refresh_token'] = '';
            $this->config['client_number'] = -1;
            $this->config['authorized']    = false;
        }

        $message = __('Google Drive is disconnected successfully.', 'duplicator-pro');
        return true;
    }

    /**
     * Get authorization URL
     *
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        return (new TokenService(static::getSType()))->getRedirectUri();
    }

    /**
     * Get storage adapter
     *
     * @return GDriveAdapter
     */
    protected function getAdapter(): GDriveAdapter
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        $token  = $this->getTokenFromConfig();

        if (! $this->adapter) {
            $storageFolderId = '';
            if (! empty($this->config['storage_folder_id'])) {
                $storageFolderId = $this->config['storage_folder_id'];
            }
            $this->adapter = new GDriveAdapter(
                $token,
                $this->config['storage_folder'],
                $storageFolderId,
                !$global->ssl_disableverify,
                ($global->ssl_useservercerts ? '' : DUPLICATOR_PRO_CERT_PATH),
                $global->ipv4_only
            );
            $this->adapter->initialize();
            if ($token->isValid() && empty($this->config['storage_folder_id'])) {
                $storageFolder                          = $this->adapter->getPathInfo('/');
                $this->config['storage_folder_id']      = $storageFolder->id;
                $this->config['storage_folder_web_url'] = $storageFolder->webUrl;
                $this->save();
            }
        }
        if (! $token->isValid()) {
            return $this->adapter;
        }

        // This check is only needed if we have a valid storage.
        $storageFolder = $this->adapter->getPathInfo('/');
        if ($storageFolder->name !== basename($this->getStorageFolder())) {
            // root folder id & storage folder name is different.
            $this->adapter = new GDriveAdapter(
                $token,
                $this->config['storage_folder'],
                '',
                !$global->ssl_disableverify,
                ($global->ssl_useservercerts ? '' : DUPLICATOR_PRO_CERT_PATH),
                $global->ipv4_only
            );
            $this->adapter->initialize();
            $storageFolder                          = $this->adapter->getPathInfo('/');
            $this->config['storage_folder_id']      = $storageFolder->id;
            $this->config['storage_folder_web_url'] = $storageFolder->webUrl;
            $this->save();
        }

        return $this->adapter;
    }

    /**
     * Returns the config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getConfigFieldsData(): array
    {
        $userInfo    = false;
        $quotaString = '';
        $adapter     = $this->getAdapter();

        if ($this->isAuthorized() && $adapter->isValid()) {
            try {
                $serviceDrive = $adapter->getService();
                $optParams    = ['fields' => '*'];
                $about        = $serviceDrive->about->get($optParams);
                $storageQuota = $about->getStorageQuota();
                $quota_total  = max($storageQuota->getLimit(), 1);
                $quota_used   = $storageQuota->getUsage();
                $userInfo     = $about->getUser();

                if (is_numeric($quota_total) && is_numeric($quota_used)) {
                    $available_quota = $quota_total - $quota_used;
                    $used_perc       = round($quota_used * 100 / $quota_total, 1);
                    $quotaString     = sprintf(
                        __('%1$s%% used, %2$s available', 'duplicator-pro'),
                        $used_perc,
                        size_format($available_quota)
                    );
                }
            } catch (\Exception $e) {
                DUP_PRO_Log::info("Problem getting Google Drive user info and quota: " . $e->getMessage());
                $userInfo = $quotaString = null;
            }
        }

        // Adapter is invalid, but we have a refresh token, so it has been revoked
        if (! $adapter->isValid() && ! empty($this->config['refresh_token'])) {
            $errorMessage = __('Google Drive has been disconnected. Please connect to Google Drive again.', 'duplicator-pro');
            AdminNotices::displayGeneralAdminNotice($errorMessage, AdminNotices::GEN_ERROR_NOTICE);
        }

        return array_merge($this->getDefaultConfigFieldsData(), [
            'userInfo'    => $userInfo,
            'quotaString' => $quotaString,
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
            'storageFolder' => $this->config['storage_folder'],
            'maxPackages'   => $this->config['max_packages'],
            'userInfo'      => null,
            'quotaString'   => null,
        ];
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    protected function getConfigFieldsTemplatePath(): string
    {
        return 'gdriveaddon/configs/google_drive';
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

        $previousStorageFolder          = $this->config['storage_folder'];
        $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'gdrive_max_files', 10);
        $this->config['storage_folder'] = self::getSanitizedInputFolder('_gdrive_storage_folder', 'remove');

        if ($previousStorageFolder !== $this->config['storage_folder']) {
            $this->config['storage_folder_id']      = '';
            $this->config['storage_folder_web_url'] = '';
        }

        $message = sprintf(
            __('Google Drive Storage Updated.', 'duplicator-pro'),
            $this->config['server'],
            $this->getStorageFolder()
        );
        return true;
    }

    /**
     * Get the token entity from config
     *
     * @return TokenEntity
     */
    protected function getTokenFromConfig()
    {
        $token = new TokenEntity(static::getSType(), $this->config['token_json']);
        if ($token->isValid() && $token->isAboutToExpire()) {
            try {
                if (! $token->refresh(true)) {
                    $token->updateProperties(['access_token' => '']); // clear access token
                    $this->config['authorized'] = false;
                    $this->save();
                    return $token;
                }
            } catch (Exception $e) {
                DUP_PRO_Log::traceException($e, "Problem refreshing Google Drive access token");
            }
            $this->config['token_json'] = wp_json_encode([
                'created'       => $token->getCreated(),
                'access_token'  => $token->getAccessToken(),
                'refresh_token' => $token->getRefreshToken(),
                'expires_in'    => $token->getExpiresIn(),
                'scope'         => $token->getScope(),
            ]);
            $this->save();
        }
        return $token;
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
            'gdrive_upload_chunksize_in_kb'   => 1024,
            'gdrive_download_chunksize_in_kb' => 10 * 1024,
        ];
    }

    /**
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
        $dGlobal = DynamicGlobalEntity::getInstance();

        TplMng::getInstance()->render(
            'gdriveaddon/configs/global_options',
            [
                'uploadChunkSize'   => $dGlobal->getVal('gdrive_upload_chunksize_in_kb', 1024),
                'downloadChunkSize' => $dGlobal->getVal('gdrive_download_chunksize_in_kb', 10 * 1024),
            ]
        );
    }
}
