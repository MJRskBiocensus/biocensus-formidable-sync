<?php

/**
 * Version Pro Base functionalities
 *
 * Name: Duplicator PRO base
 * Version: 1
 * Author: Snap Creek
 * Author URI: http://snapcreek.com
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\ProBase;

use DUP_PRO_Log;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Views\AdminNotices;
use Exception;

class LicensingController
{
    //License actions
    const ACTION_ACTIVATE_LICENSE   = 'activate_license';
    const ACTION_DEACTIVATE_LICENSE = 'deactivate_license';
    const ACTION_CHANGE_VISIBILITY  = 'change_visibility';
    const ACTION_CLEAR_KEY          = 'clear_key';
    const ACTION_FORCE_REFRESH      = 'force_refresh';

    const LICENSE_KEY_OPTION_AUTO_ACTIVE = 'duplicator_pro_license_auto_active';

    /**
     * License controller init
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'licenseAutoActive']);
        add_action('admin_init', [self::class, 'forceUpgradeCheckAction']);
        //add_action('duplicator_render_page_content_' . ControllersManager::SETTINGS_SUBMENU_SLUG, array(__CLASS__, 'renderLicenseContent'), 10, 2);
        add_action('duplicator_settings_general_before', [self::class, 'renderLicenseContent']);
        add_filter('duplicator_page_actions_' . ControllersManager::SETTINGS_SUBMENU_SLUG, [self::class, 'pageActions']);
        add_filter('duplicator_template_file', [self::class, 'getTemplateFile'], 10, 2);
    }

    /**
     * Method call on admin_init hook
     *
     * @return void
     */
    public static function licenseAutoActive(): void
    {
        if (($lKey = get_option(self::LICENSE_KEY_OPTION_AUTO_ACTIVE, false)) === false) {
            return;
        }
        if (!CapMng::getInstance()->can(CapMng::CAP_LICENSE)) {
            return;
        }
        if (($action = SettingsPageController::getInstance()->getActionByKey(self::ACTION_ACTIVATE_LICENSE)) == false) {
            return;
        }
        delete_option(self::LICENSE_KEY_OPTION_AUTO_ACTIVE);
        $redirect = $action->getUrl(['_license_key' => $lKey]);

        DUP_PRO_Log::trace("CONTROLLER LICENSE AUTO ACTIVE: Redirecting to " . $action->getUrl());

        if (wp_safe_redirect($redirect)) {
            exit;
        } else {
            throw new Exception(__('Error redirecting to license activation page', 'duplicator-pro'));
        }
    }

    /**
     * Return force upgrade check URL
     *
     * @return string
     */
    public static function getForceUpgradeCheckURL(): string
    {
        return SnapWP::adminUrl('update-core.php', ['force-check' => 1]);
    }


    /**
     * Force upgrade check action
     *
     * @return void
     */
    public static function forceUpgradeCheckAction(): void
    {
        global $pagenow;

        if ($pagenow !== 'update-core.php') {
            return;
        }

        if (!SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'force-check')) {
            return;
        }

        License::forceUpgradeCheck();
    }

    /**
     * Define actions related to the license
     *
     * @param PageAction[] $actions Page actions array from filter
     *
     * @return PageAction[] Updated page actions array
     */
    public static function pageActions($actions)
    {
        $actions[] = new PageAction(
            self::ACTION_ACTIVATE_LICENSE,
            [
                self::class,
                'activateLicense',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_DEACTIVATE_LICENSE,
            [
                self::class,
                'deactivateLicense',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_CLEAR_KEY,
            [
                self::class,
                'clearLicenseKeyAction',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_CHANGE_VISIBILITY,
            [
                self::class,
                'changeLicenseVisibility',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_FORCE_REFRESH,
            [
                self::class,
                'forceRefresh',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );

        return $actions;
    }

    /**
     * Action that changes the license visibility
     *
     * @return array<string, mixed>
     */
    public static function changeLicenseVisibility(): array
    {
        DUP_PRO_Log::trace("CONTROLLER CHANGE LICENSE VISIBILITY ACTION: Changing license visibility");

        $result  = [
            'license_success' => false,
            'license_message' => '',
        ];
        $dGlobal = DynamicGlobalEntity::getInstance();

        $oldVisibility = $dGlobal->getValBool('license_key_visible');
        $newVisibility = filter_input(INPUT_POST, 'license_key_visible', FILTER_VALIDATE_INT);
        $newPassword   = SnapUtil::sanitizeInput(INPUT_POST, '_key_password', '');

        if ($oldVisibility === $newVisibility) {
            return $result;
        }

        switch ($newVisibility) {
            case License::VISIBILITY_ALL:
                if ($dGlobal->getValString('license_key_visible_pwd') !== $newPassword) {
                    $result['license_message'] = __("Wrong password entered. Please enter the correct password.", 'duplicator-pro');
                    return $result;
                }
                $newPassword = ''; // reset password
                break;
            case License::VISIBILITY_NONE:
            case License::VISIBILITY_INFO:
                if ($oldVisibility == License::VISIBILITY_ALL) {
                    $password_confirmation = SnapUtil::sanitizeInput(INPUT_POST, '_key_password_confirmation', '');

                    if (strlen($newPassword) === 0) {
                        $result['license_message'] = __('Password cannot be empty.', 'duplicator-pro');
                        return $result;
                    }

                    if ($newPassword !== $password_confirmation) {
                        $result['license_message'] = __("Passwords don't match.", 'duplicator-pro');
                        return $result;
                    }
                } else {
                    if ($dGlobal->getValString('license_key_visible_pwd') !== $newPassword) {
                        $result['license_message'] = __("Wrong password entered. Please enter the correct password.", 'duplicator-pro');
                        return $result;
                    }
                }
                break;
            default:
                throw new Exception(__('Invalid license visibility value.', 'duplicator-pro'));
        }

        $dGlobal->setValInt('license_key_visible', $newVisibility);
        $dGlobal->setValString('license_key_visible_pwd', $newPassword);

        if ($dGlobal->save()) {
            return [
                'license_success' => true,
                'license_message' => __("License visibility changed", 'duplicator-pro'),
            ];
        } else {
            return [
                'license_success' => false,
                'license_message' => __("Couldn't change licnse vilisiblity.", 'duplicator-pro'),
            ];
        }
    }

    /**
     * Action that clears the license key
     *
     * @return array<string, mixed>
     */
    public static function clearLicenseKeyAction()
    {
        DUP_PRO_Log::trace("CONTROLLER CLEAR LICENSE KEY ACTION: Clearing license key");

        LicenseData::resetLastRequestFailure();
        $result = self::clearLicenseKey();
        LicenseData::resetLastRequestFailure();
        return $result;
    }


    /**
     * Action that clears the license key
     *
     * @return array<string, mixed>
     */
    protected static function clearLicenseKey()
    {
        $dGlobal = DynamicGlobalEntity::getInstance();

        LicenseData::getInstance()->setKey('');
        License::clearVersionCache(true);

        $dGlobal->setValInt('license_key_visible', License::VISIBILITY_ALL);
        $dGlobal->setValString('license_key_visible_pwd', '');

        if ($dGlobal->save()) {
            return [
                'license_success' => true,
                'license_message' => __("License key cleared", 'duplicator-pro'),
            ];
        } else {
            return [
                'license_success' => false,
                'license_message' => __("Couldn't save changes", 'duplicator-pro'),
            ];
        }
    }

    /**
     * Action that deactivates the license
     *
     * @return array<string, mixed>
     */
    public static function deactivateLicense(): array
    {
        $result = [
            'license_success' => true,
            'license_message' => '',
        ];

        try {
            $lData = LicenseData::getInstance();

            DUP_PRO_Log::trace("CONTROLLER DEACTIVATE LICENSE ACTION: Deactivating license");

            if ($lData->getStatus() !== LicenseData::STATUS_VALID) {
                return [
                    'license_success' => true,
                    'license_message' => __('License already deactivated.', 'duplicator-pro'),
                ];
            }

            $licenseKey = $lData->getKey();

            switch ($lData->deactivate()) {
                case LicenseData::ACTIVATION_RESPONSE_OK:
                    $result['license_message'] = sprintf(
                        _x(
                            'License %1$s %2$sDeactivated%3$s',
                            '%1$s is the license key, %2$s and %3$s are opening and closing HTML tags',
                            'duplicator-pro'
                        ),
                        substr($licenseKey, 0, 5) . "***",
                        '<b class="alert-color">',
                        '</b>'
                    );
                    break;
                case LicenseData::ACTIVATION_RESPONSE_INVALID:
                    throw new Exception(__('Invalid license key.', 'duplicator-pro'));
                case LicenseData::ACTIVATION_REQUEST_ERROR:
                    $result['license_request_error'] = $lData->getLastRequestError();
                    throw new Exception(self::getRequestErrorMessage());
                default:
                    throw new Exception(__('Error activating license.', 'duplicator-pro'));
            }
        } catch (Exception $e) {
            $result['license_success'] = false;
            $result['license_message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Return template file path
     *
     * @param string $path    path to the template file
     * @param string $slugTpl slug of the template
     *
     * @return string
     */
    public static function getTemplateFile($path, $slugTpl)
    {
        if (strpos($slugTpl, 'licensing/') === 0) {
            return ProBase::getAddonPath() . '/template/' . $slugTpl . '.php';
        }
        return $path;
    }

    /**
     * Action that activates the license
     *
     * @return array<string, mixed>
     */
    public static function activateLicense(): array
    {
        $result = [
            'license_success' => true,
            'license_message' => '',
        ];

        try {
            if (($licenseKey = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, '_license_key')) === false) {
                throw new Exception(__('Please enter a valid key. Key should be 32 characters long.', 'duplicator-pro'));
            }

            if (!preg_match('/^[a-f0-9]{32}$/i', $licenseKey)) {
                throw new Exception(__('Please enter a valid key. Key should be 32 characters long.', 'duplicator-pro'));
            }

            DUP_PRO_Log::trace("CONTROLLER ACTIVATE LICENSE ACTION: Setting license key to " . substr($licenseKey, 0, 5) . "***");

            $lData = LicenseData::getInstance();
            // make sure reset old license key if exists
            self::clearLicenseKey();
            $lData->setKey($licenseKey);

            switch ($lData->activate()) {
                case LicenseData::ACTIVATION_RESPONSE_OK:
                    $result['license_message'] = sprintf(
                        _x(
                            'License %1$s %2$sActivated%3$s',
                            '%1$s is the license key, %2$s and %3$s are opening and closing HTML tags',
                            'duplicator-pro'
                        ),
                        substr($licenseKey, 0, 5) . "***",
                        '<b class="green-color">',
                        '</b>'
                    );
                    break;
                case LicenseData::ACTIVATION_RESPONSE_INVALID:
                    throw new Exception(__('Invalid license key.', 'duplicator-pro'));
                case LicenseData::ACTIVATION_REQUEST_ERROR:
                    $result['license_request_error'] = $lData->getLastRequestError();
                    DUP_PRO_Log::traceObject('License request error', $result['license_request_error']);
                    throw new Exception(self::getRequestErrorMessage());
                default:
                    throw new Exception(__('Error activating license.', 'duplicator-pro'));
            }
        } catch (Exception $e) {
            $result['license_success'] = false;
            $result['license_message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Force a refresh of the license data action
     *
     * @return array<string,mixed>
     */
    public static function forceRefresh(): array
    {
        DUP_PRO_Log::trace("CONTROLLER FORCE REFRESH ACTION: Force refreshing license data");

        $result = [
            'license_success' => true,
            'license_message' => __("License data reloaded.", 'duplicator-pro'),
        ];

        try {
            $lData = LicenseData::getInstance();
            $lData->resetLastRequestFailure();
            $lData->getLicenseData(true);
        } catch (Exception $e) {
            $result['license_success'] = false;
            $result['license_message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Render page content
     *
     * @return void
     */
    public static function renderLicenseContent(): void
    {
        if (!CapMng::getInstance()->can(CapMng::CAP_LICENSE, false)) {
            return;
        }
        self::renderLicenseMessage();
        TplMng::getInstance()->render('licensing/main');
    }

    /**
     * Render activation/deactivation license message
     *
     * @return void
     */
    protected static function renderLicenseMessage()
    {
        if (!CapMng::getInstance()->can(CapMng::CAP_LICENSE, false)) {
            return;
        }

        $tplData = TplMng::getInstance()->getGlobalData();
        if (empty($tplData['license_message'])) {
            return;
        }

        $success = (isset($tplData['license_success']) && $tplData['license_success'] === true);
        AdminNotices::displayGeneralAdminNotice(
            TplMng::getInstance()->render('licensing/notices/activation_message', [], false),
            ($success ? AdminNotices::GEN_SUCCESS_NOTICE : AdminNotices::GEN_ERROR_NOTICE),
            false,
            [],
            [],
            true
        );
    }

    /**
     * Returns the communication error message
     *
     * @return string
     */
    private static function getRequestErrorMessage(): string
    {
        $result  = '<b>' . __('License data request failed.', 'duplicator-pro') . '</b>';
        $result .= '<br>';
        return $result . sprintf(
            _x(
                'Please see %1$sthis FAQ entry%2$s for possible causes and resolutions.',
                '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                'duplicator-pro'
            ),
            '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-to-resolve-license-activation-issues/" target="_blank">',
            '</a>'
        );
    }
}
