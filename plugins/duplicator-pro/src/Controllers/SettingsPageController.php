<?php

/**
 * Settings page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use DUP_PRO_DB;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Server_Load_Reduction;
use Duplicator\Core\CapMng;
use Duplicator\Core\Constants;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Controllers\SubMenuItem;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Utils\Logging\TraceLogMng;
use Duplicator\Utils\Settings\MigrateSettings;
use Exception;

class SettingsPageController extends AbstractMenuPageController
{
    const NONCE_ACTION = 'duppro-settings-package';

    /**
     * tabs menu
     */
    const L2_SLUG_GENERAL         = 'general';
    const L2_SLUG_GENERAL_MIGRATE = 'migrate';
    const L2_SLUG_PACKAGE_BRAND   = 'brand';
    const L2_SLUG_PACKAGE         = 'package';
    const L2_SLUG_SCHEDULE        = 'schedule';
    const L2_SLUG_STORAGE         = 'storage';
    const L2_SLUG_IMPORT          = 'import';
    const L2_SLUG_CAPABILITIES    = 'capabilities';

    const BRAND_INNER_PAGE_LIST = 'list';
    const BRAND_INNER_PAGE_EDIT = 'edit';

    /*
     * action types
     */
    const ACTION_GENERAL_SAVE          = 'save';
    const ACTION_GENERAL_TRACE         = 'trace';
    const ACTION_CAPABILITIES_SAVE     = 'cap-save';
    const ACTION_CAPABILITIES_RESET    = 'cap-reset';
    const ACTION_IMPORT_SAVE_SETTINGS  = 'import-save-set';
    const ACTION_PACKAGE_ADVANCED_SAVE = 'pack-adv-save';
    const ACTION_PACKAGE_BASIC_SAVE    = 'pack-basic-save';
    const ACTION_RESET_SETTINGS        = 'reset-settings';
    const ACTION_SAVE_SCHEDULE         = 'save-schedule';
    const ACTION_SAVE_STORAGE          = 'save-storage';
    const ACTION_SAVE_STORAGE_SSL      = 'save-storage-ssl';
    const ACTION_SAVE_STORAGE_OPTIONS  = 'save-storage-options';
    const ACTION_IMPORT_SETTINGS       = 'import-settings';
    const ACTION_BRAND_SAVE            = 'save-brand';
    const ACTION_BRAND_DELETE          = 'delete-brand';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = ControllersManager::SETTINGS_SUBMENU_SLUG;
        $this->pageTitle    = __('Settings', 'duplicator-pro');
        $this->menuLabel    = __('Settings', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_SETTINGS;
        $this->menuPos      = 60;

        add_filter('duplicator_sub_menu_items_' . $this->pageSlug, [$this, 'getBasicSubMenus']);
        add_filter('duplicator_sub_level_default_tab_' . $this->pageSlug, [$this, 'getSubMenuDefaults'], 10, 2);
        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
        add_filter('duplicator_page_actions_' . $this->pageSlug, [$this, 'pageActions']);
    }

    /**
     * Return sub menus for current page
     *
     * @param SubMenuItem[] $subMenus sub menus list
     *
     * @return SubMenuItem[]
     */
    public function getBasicSubMenus($subMenus)
    {
        $subMenus[] = new SubMenuItem(self::L2_SLUG_GENERAL, __('General', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_PACKAGE, __('Backups', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_PACKAGE_BRAND, __('Installer Branding', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_SCHEDULE, __('Schedules', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_STORAGE, __('Storage', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_IMPORT, __('Import', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_GENERAL_MIGRATE, __('Import/Export Settings', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_CAPABILITIES, __('Access', 'duplicator-pro'));

        return $subMenus;
    }

    /**
     * Return slug default for parent menu slug
     *
     * @param string $slug   current default
     * @param string $parent parent for default
     *
     * @return string default slug
     */
    public function getSubMenuDefaults($slug, $parent)
    {
        switch ($parent) {
            case '':
                return self::L2_SLUG_GENERAL;
            default:
                return $slug;
        }
    }

    /**
     * Return actions for current page
     *
     * @param PageAction[] $actions actions lists
     *
     * @return PageAction[]
     */
    public function pageActions($actions)
    {
        $actions[] = new PageAction(
            self::ACTION_GENERAL_SAVE,
            [
                $this,
                'saveGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_GENERAL_TRACE,
            [
                $this,
                'traceGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_CAPABILITIES_SAVE,
            [
                $this,
                'saveCapabilities',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_CAPABILITIES,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_CAPABILITIES_RESET,
            [
                $this,
                'resetCapabilities',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_CAPABILITIES,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_PACKAGE_BASIC_SAVE,
            [
                $this,
                'savePackage',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_PACKAGE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_IMPORT_SAVE_SETTINGS,
            [
                $this,
                'saveImportSettngs',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_IMPORT,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_RESET_SETTINGS,
            [
                $this,
                'resetSettings',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_SAVE_STORAGE,
            [
                $this,
                'saveStorageGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_STORAGE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_SAVE_SCHEDULE,
            [
                $this,
                'saveSchedule',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_SCHEDULE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_IMPORT_SETTINGS,
            [
                $this,
                'importSettings',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL_MIGRATE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_BRAND_SAVE,
            [
                $this,
                'brandSave',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_PACKAGE_BRAND,
            ],
            self::BRAND_INNER_PAGE_EDIT
        );
        $actions[] = new PageAction(
            self::ACTION_BRAND_DELETE,
            [
                $this,
                'brandDelete',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_PACKAGE_BRAND,
            ]
        );

        return $actions;
    }


    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs current menu slugs
     * @param string   $innerPage         current inner page, empty if not set
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs, $innerPage): void
    {
        switch ($currentLevelSlugs[1]) {
            case self::L2_SLUG_GENERAL:
                TplMng::getInstance()->render('admin_pages/settings/general/general');
                break;
            case self::L2_SLUG_PACKAGE_BRAND:
                switch ($innerPage) {
                    case self::BRAND_INNER_PAGE_EDIT:
                        $brandId = TplMng::getInstance()->getGlobalValue(
                            'actionBrandId',
                            SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'id', -1)
                        );

                        if ($brandId === -1) {
                            $brand = new BrandEntity();
                        } elseif ($brandId === 0) {
                            $brand = BrandEntity::getDefaultBrand();
                        } else {
                            $brand = BrandEntity::getById($brandId);
                        }
                        DUP_PRO_Log::trace("IS DEFAULT: " . $brand->isDefault());
                        TplMng::getInstance()->render(
                            'admin_pages/settings/brand/brand_edit',
                            ['brand' => $brand]
                        );
                        break;
                    case self::BRAND_INNER_PAGE_LIST:
                    default:
                        TplMng::getInstance()->render('admin_pages/settings/brand/brand_list');
                        break;
                }
                break;
            case self::L2_SLUG_GENERAL_MIGRATE:
                TplMng::getInstance()->render('admin_pages/settings/migrate_settings/migrate_page');
                break;
            case self::L2_SLUG_PACKAGE:
                TplMng::getInstance()->render('admin_pages/settings/backup/backup_settings');
                break;
            case self::L2_SLUG_IMPORT:
                TplMng::getInstance()->render('admin_pages/settings/import/import');
                break;
            case self::L2_SLUG_SCHEDULE:
                TplMng::getInstance()->render('admin_pages/settings/schedule/schedule');
                break;
            case self::L2_SLUG_STORAGE:
                TplMng::getInstance()->render('admin_pages/settings/storage/storage_settings');
                break;
            case self::L2_SLUG_CAPABILITIES:
                TplMng::getInstance()->render('admin_pages/settings/capabilities/capabilites');
                break;
        }
    }

    /**
     * Save general settings
     *
     * @return array<string, mixed>
     */
    public function saveGeneral(): array
    {
        $result = ['saveSuccess' => false];
        $global = DUP_PRO_Global_Entity::getInstance();

        $global->uninstall_settings = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'uninstall_settings');
        $global->uninstall_packages = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'uninstall_packages');

        $cryptSettingChanged = (SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'crypt') != $global->isEncryptionEnabled());

        if ($cryptSettingChanged) {
            do_action('duplicator_before_update_crypt_setting');
        }

        $global->setEncryption(SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'crypt'));
        $global->unhook_third_party_js  = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_unhook_third_party_js');
        $global->unhook_third_party_css = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_unhook_third_party_css');

        $this->updateLoggingModeOptions();

        $global->setEmailSummaryFrequency(SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, '_email_summary_frequency'));
        $emailRecipients = filter_input(INPUT_POST, '_email_summary_recipients', FILTER_SANITIZE_EMAIL, [
            'flags'   => FILTER_REQUIRE_ARRAY,
            'options' => [
                'default' => [],
            ],
        ]);
        if ($emailRecipients !== []) {
            $emailRecipients = array_map('sanitize_email', $emailRecipients);
        }
        $global->setEmailSummaryRecipients($emailRecipients);
        $global->setUsageTracking(SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'usage_tracking'));
        $global->setAmNotices(!SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'dup_am_notices'));

        $maxSizeMB = SnapUtil::sanitizeIntInput(INPUT_POST, 'trace_max_size', TraceLogMng::DEFAULT_MAX_TOTAL_SIZE / MB_IN_BYTES);
        TraceLogMng::getInstance()->setMaxTotalSize($maxSizeMB * MB_IN_BYTES);

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t update general settings', 'duplicator-pro');
            return $result;
        } else {
            $result['successMessage'] = __("General settings updated.", 'duplicator-pro');
        }

        // Save activity log retention setting in DynamicGlobalEntity
        if ($result['saveSuccess']) {
            $dGlobal                     = DynamicGlobalEntity::getInstance();
            $activityLogRetentionMonths  = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'activity_log_retention_months', 0);
            $activityLogRetentionSeconds = $activityLogRetentionMonths * MONTH_IN_SECONDS;
            $dGlobal->setValInt('activity_log_retention', $activityLogRetentionSeconds);

            if (($result['saveSuccess'] = $dGlobal->save()) == false) {
                $result['errorMessage'] = __('Can\'t update activity log retention settings', 'duplicator-pro');
            }
        }

        if ($cryptSettingChanged) {
            do_action('duplicator_after_update_crypt_setting');
        }

        return $result;
    }

    /**
     * Save capabilities settings
     *
     * @return array<string, mixed>
     */
    public function saveCapabilities()
    {
        $result = ['saveSuccess' => false];

        $capabilities = [];
        foreach (CapMng::getCapsList() as $capName) {
            $capabilities[$capName] = [
                'roles' => [],
                'users' => [],
            ];

            $inputName = TplMng::getInputName('cap', $capName);
            $result    = filter_input(INPUT_POST, $inputName, FILTER_UNSAFE_RAW, [
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ]);
            if ($result === []) {
                continue;
            }

            foreach ($result as $roles) {
                $roles = SnapUtil::sanitizeNSCharsNewlineTrim($roles);
                if (is_numeric($roles)) {
                    $capabilities[$capName]['users'][] = (int) $roles;
                } else {
                    $capabilities[$capName]['roles'][] = $roles;
                }
            }
        }

        if (CapMng::getInstance()->update($capabilities) == false) {
            $result['saveSuccess']  = false;
            $result['errorMessage'] = __('Can\'t update capabilities.', 'duplicator-pro');
            return $result;
        } else {
            $result['successMessage'] = __('Capabilities updated.', 'duplicator-pro');
            $result['saveSuccess']    = true;
        }

        return $result;
    }

    /**
     * Reset capabilities settings
     *
     * @return array<string, mixed>
     */
    public function resetCapabilities(): array
    {
        $result = ['saveSuccess' => false];

        $capabilities = CapMng::getDefaultCaps();
        if (!CapMng::can(CapMng::CAP_LICENSE)) {
            // Can't reset license capability if current user can't manage license
            unset($capabilities[CapMng::CAP_LICENSE]);
        }

        if (CapMng::getInstance()->update($capabilities) == false) {
            $result['saveSuccess']  = false;
            $result['errorMessage'] = __('Can\'t update capabilities.', 'duplicator-pro');
        } else {
            $result['successMessage'] = __('Capabilities updated.', 'duplicator-pro');
            $result['saveSuccess']    = true;
        }

        return $result;
    }

    /**
     * Save storage general settings
     *
     * @return array<string, mixed>
     */
    public function saveStorageGeneral(): array
    {
        $result = ['saveSuccess' => false];

        $global                       = DUP_PRO_Global_Entity::getInstance();
        $global->storage_htaccess_off = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_storage_htaccess_off');
        $global->max_storage_retries  = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_storage_retries', 10);
        $global->ssl_useservercerts   = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'ssl_useservercerts');
        $global->ssl_disableverify    = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'ssl_disableverify');
        $global->ipv4_only            = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'ipv4_only');

        $global->setPurgeBackupRecords(SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'purge_backup_records', 0));

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t update storage settings.', 'duplicator-pro');
        } else {
            $result['successMessage'] = __('Storage settings updated.', 'duplicator-pro');
        }

        if ($result['saveSuccess']) {
            do_action('duplicator_update_global_storage_settings');

            $dGlobal = DynamicGlobalEntity::getInstance();
            if (($result['saveSuccess'] = $dGlobal->save()) == false) {
                $result['errorMessage'] = __('Can\'t update storage settings.', 'duplicator-pro');
            } else {
                $result['successMessage'] = __('Storage settings updated.', 'duplicator-pro');
            }
        }

        return $result;
    }

    /**
     * Save schedule general settings
     *
     * @return array<string, mixed>
     */
    public function saveSchedule()
    {
        $global                             = DUP_PRO_Global_Entity::getInstance();
        $global->send_email_on_build_mode   = (int)$_REQUEST['send_email_on_build_mode'];
        $global->notification_email_address = stripslashes($_REQUEST['notification_email_address']);

        if ($global->save()) {
            $result['successMessage'] = __('Schedule settings updated.', 'duplicator-pro');
        } else {
            $result['errorMessage'] = __('Can\'t update schedule settings.', 'duplicator-pro');
        }

        return $result;
    }

    /**
     * Migrate settings
     *
     * @return array<string, mixed>
     */
    public function importSettings(): array
    {
        $inputData = filter_input_array(INPUT_POST, [
            'import-opts' => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ],
        ]);

        if (empty($inputData['import-opts'])) {
            return ['errorMessage' => __('No import options selected.', 'duplicator-pro')];
        }

        if (!isset($_FILES['import-file']['tmp_name'])) {
            return ['errorMessage' => __('No file uploaded.', 'duplicator-pro')];
        }

        $filePath = SnapUtil::sanitizeNSCharsNewlineTabs($_FILES["import-file"]["tmp_name"]);
        try {
            if (MigrateSettings::import($filePath, $inputData['import-opts']) == false) {
                return ['errorMessage' => __('Couldn\'t import settings.', 'duplicator-pro')];
            }
        } catch (Exception $ex) {
            return ['errorMessage' => sprintf(__('Couldn\'t import settings. Error: %s', 'duplicator-pro'), $ex->getMessage())];
        }

        return ['successMessage' => __('Settings imported.', 'duplicator-pro')];
    }

    /**
     * Brand save
     *
     * @return array<string, mixed>
     */
    public function brandSave(): array
    {
        $id    = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'id', -1);
        $brand = $id === -1 ? new BrandEntity() : BrandEntity::getById($id);

        $result = [];
        $brand->setFromInput(SnapUtil::INPUT_REQUEST);
        if ($brand->save()) {
            $result['successMessage'] = __('Brand saved.', 'duplicator-pro');
            $result['actionBrandId']  = $brand->getId();
        } else {
            $result['errorMessage'] = __('Couldn\'t save brand.', 'duplicator-pro');
        }

        return $result;
    }

    /**
     * Brand delete
     *
     * @return array<string, mixed>
     */
    public function brandDelete(): array
    {
        if (!isset($_REQUEST['selected_id'])) {
            return ['errorMessage' => __("No Brand selected.", 'duplicator-pro')];
        }

        $brandIds = filter_var_array($_REQUEST, [
            'selected_id' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
        ])['selected_id'];

        if ($brandIds === false) {
            return ['errorMessage' => __("No Brand selected.", 'duplicator-pro')];
        }

        foreach ($brandIds as $id) {
            BrandEntity::deleteById($id);
        }

        return ['successMessage' => __("Brand deleted.", 'duplicator-pro')];
    }

    /**
     * Reset all user settings and redirects to the settings page
     *
     * @return array<string, mixed>
     */
    public function resetSettings(): array
    {
        $result = ['saveSuccess' => false];

        $global = DUP_PRO_Global_Entity::getInstance();
        if ($global->resetUserSettings() && $global->save()) {
            $result['successMessage'] = __('Settings reset to defaults successfully', 'duplicator-pro');
            $result['saveSuccess']    = true;
        } else {
            $result['errorMessage'] = __('Failed to reset settings.', 'duplicator-pro');
            $result['saveSuccess']  = false;
        }

        TraceLogMng::getInstance()->setMaxTotalSize(TraceLogMng::DEFAULT_MAX_TOTAL_SIZE);
        return $result;
    }

    /**
     * Update trace mode
     *
     * @return array<string, mixed>
     */
    public function traceGeneral(): array
    {
        $result = ['saveSuccess' => false];

        switch (SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, '_logging_mode')) {
            case 'off':
                $this->updateLoggingModeOptions();
                $result = [
                    'saveSuccess'    => true,
                    'successMessage' => __("Trace settings have been turned off.", 'duplicator-pro'),
                ];
                break;
            case 'on':
                $this->updateLoggingModeOptions();
                $result = [
                    'saveSuccess'    => true,
                    'successMessage' => __("Trace settings have been turned on.", 'duplicator-pro'),
                ];
                break;
            default:
                $result = [
                    'saveSuccess'  => false,
                    'errorMessage' => __("Trace mode not valid.", 'duplicator-pro'),
                ];
                break;
        }

        return $result;
    }

    /**
     * Upate loggin modes options
     *
     * @return void
     */
    protected function updateLoggingModeOptions()
    {
        switch (SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, '_logging_mode')) {
            case 'off':
                update_option('duplicator_pro_trace_log_enabled', false, true);
                update_option('duplicator_pro_send_trace_to_error_log', false);
                break;
            case 'on':
                if ((bool) get_option('duplicator_pro_trace_log_enabled') == false) {
                    DUP_PRO_Log::deleteTraceLog();
                }
                update_option('duplicator_pro_trace_log_enabled', true, true);
                update_option('duplicator_pro_send_trace_to_error_log', false);
                break;
            case 'enhanced':
                if (
                    ((bool) get_option('duplicator_pro_trace_log_enabled') == false) ||
                    ((bool) get_option('duplicator_pro_send_trace_to_error_log') == false)
                ) {
                    DUP_PRO_Log::deleteTraceLog();
                }

                update_option('duplicator_pro_trace_log_enabled', true, true);
                update_option('duplicator_pro_send_trace_to_error_log', true);
                break;
            default:
                break;
        }
    }



    /**
     * Save Backup basic settings
     *
     * @return array<string, mixed>
     */
    public function savePackage(): array
    {
        $result          = ['saveSuccess' => false];
        $global          = DUP_PRO_Global_Entity::getInstance();
        $dGlobal         = DynamicGlobalEntity::getInstance();
        $packageBuild    = Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN;
        $defaultTransfer = Constants::DEFAULT_MAX_PACKAGE_TRANSFER_TIME_IN_MIN;

        $global->setDbMode();
        $global->setArchiveMode();
        $global->max_package_runtime_in_min       = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_package_runtime_in_min', $packageBuild);
        $global->server_load_reduction            = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'server_load_reduction', 0);
        $global->max_package_transfer_time_in_min = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_package_transfer_time_in_min', $defaultTransfer);
        $global->max_package_runtime_in_min       = SnapUtil::sanitizeIntInput(
            INPUT_POST,
            'max_package_runtime_in_min',
            Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN
        );
        $global->server_load_reduction            = SnapUtil::sanitizeIntInput(
            INPUT_POST,
            'server_load_reduction',
            DUP_PRO_Server_Load_Reduction::None
        );

        switch (SnapUtil::sanitizeDefaultInput(INPUT_POST, 'installer_name_mode')) {
            case DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_WITH_HASH:
                $global->installer_name_mode = DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_WITH_HASH;
                break;
            case DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_SIMPLE:
            default:
                $global->installer_name_mode = DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_SIMPLE;
                break;
        }

        $global->lock_mode       = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'lock_mode', 0);
        $global->ajax_protocol   = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'ajax_protocol', 'admin');
        $global->custom_ajax_url = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'custom_ajax_url', $global->custom_ajax_url);
        $clientSideKickoff       = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_clientside_kickoff', false);
        $global->setClientsideKickoff($clientSideKickoff);
        $global->homepath_as_abspath        = SnapUtil::sanitizeBoolInput(INPUT_POST, 'homepath_as_abspath', false);
        $installer_base_name                = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, '_installer_base_name', 'installer.php');
        $global->installer_base_name        = stripslashes($installer_base_name);
        $global->skip_archive_scan          = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_skip_archive_scan', false);
        $global->php_max_worker_time_in_sec = SnapUtil::sanitizeIntInput(
            SnapUtil::INPUT_REQUEST,
            'php_max_worker_time_in_sec',
            Constants::DEFAULT_MAX_WORKER_TIME
        );

        // CLEANUP
        $global->setCleanupFields();

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t Save Backup Settings', 'duplicator-pro');
            return $result;
        } else {
            $result['successMessage'] = __("Backup Settings Saved.", 'duplicator-pro');
        }

        $basicAuthEnabled = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_basic_auth_enabled');
        if ($basicAuthEnabled == true) {
            $basicAuthUser = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'basic_auth_user', '');
        } else {
            $basicAuthUser = '';
        }

        $basicAuthPassword = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'basic_auth_password', '');
        $basicAuthPassword = stripslashes(SnapUtil::sanitizeNSCharsNewlineTrim($basicAuthPassword));

        $dGlobal->setValBool('basic_auth_enabled', $basicAuthEnabled);
        $dGlobal->setValString('basic_auth_user', $basicAuthUser);
        $dGlobal->setValString('basic_auth_password', $basicAuthPassword);

        $dGlobal->save();

        return $result;
    }

    /**
     * Save import settings
     *
     * @return array<string, mixed>
     */
    public function saveImportSettngs(): array
    {
        $result = ['saveSuccess' => false];
        $global = DUP_PRO_Global_Entity::getInstance();

        $global->import_chunk_size  = filter_input(
            INPUT_POST,
            'import_chunk_size',
            FILTER_VALIDATE_INT,
            [
                'options' => ['default' => DUPLICATOR_PRO_DEFAULT_CHUNK_UPLOAD_SIZE],
            ]
        );
        $global->import_custom_path = filter_input(
            INPUT_POST,
            'import_custom_path',
            FILTER_CALLBACK,
            [
                'options' => [
                    SnapUtil::class,
                    'sanitizeNSCharsNewlineTrim',
                ],
            ]
        );
        $newRecoveryCustomPath      = filter_input(
            INPUT_POST,
            'recovery_custom_path',
            FILTER_CALLBACK,
            [
                'options' => [
                    SnapUtil::class,
                    'sanitizeNSCharsNewlineTrim',
                ],
            ]
        );

        if (
            strlen($global->import_custom_path) > 0 &&
            (
                !is_dir($global->import_custom_path) ||
                !is_readable($global->import_custom_path)
            )
        ) {
            $result['errorMessage']     = __(
                'The custom path isn\'t a valid directory. Check that it exists or that access to it is not restricted by PHP\'s open_basedir setting.',
                'duplicator-pro'
            );
            $global->import_custom_path = '';
            $result['saveSuccess']      = false;
            return $result;
        }

        $failMessage = '';
        if ($global->setRecoveryCustomPath($newRecoveryCustomPath, $failMessage) == false) {
            $result['saveSuccess']  = false;
            $result['errorMessage'] = $failMessage;
            return $result;
        }

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t save settings data', 'duplicator-pro');
        } else {
            $result['successMessage'] = __('Settings updated.', 'duplicator-pro');
        }

        return $result;
    }

    /**
     * Mysql dump message
     *
     * @param bool   $mysqlDumpFound Found
     * @param string $mysqlDumpPath  mysqldump path
     *
     * @return void
     */
    public static function getMySQLDumpMessage($mysqlDumpFound = false, $mysqlDumpPath = ''): void
    {
        ?>
        <?php if ($mysqlDumpFound) :
            ?>
            <span class="dup-feature-found success-color">
                <?php echo esc_html($mysqlDumpPath) ?> &nbsp;
                <small>
                    <i class="fa fa-check-circle"></i>&nbsp;<i><?php esc_html_e("Successfully Found", 'duplicator-pro'); ?></i>
                </small>
            </span>
            <?php
        else :
            ?>
            <span class="dup-feature-notfound alert-color">
                <i class="fa fa-exclamation-triangle fa-sm" aria-hidden="true"></i>
                <?php
                self::getMySqlDumpPathProblems($mysqlDumpPath, !empty($mysqlDumpPath));
                ?>
            </span>
            <?php
        endif;
    }

    /**
     * Return purge orphan Backups action URL
     *
     * @param bool $on true turn on, false turn off
     *
     * @return string
     */
    public function getTraceActionUrl($on)
    {
        $action = $this->getActionByKey(self::ACTION_GENERAL_TRACE);
        return $this->getMenuLink(
            self::L2_SLUG_GENERAL,
            null,
            [
                'action'        => $action->getKey(),
                '_wpnonce'      => $action->getNonce(),
                '_logging_mode' => ($on ? 'on' : 'off'),
            ]
        );
    }

    /**
     * Display mysql dump path problems
     *
     * @param string $path      mysqldump path
     * @param bool   $is_custom is custom path
     *
     * @return void
     */
    public static function getMySqlDumpPathProblems($path = '', $is_custom = false): void
    {
        $available = DUP_PRO_DB::getMySqlDumpPath();
        $default   = false;
        if ($available) {
            if ($is_custom) {
                if (!Shell::isExecutable($path)) {
                    printf(
                        esc_html_x(
                            'The mysqldump program at custom path exists but is not executable. Please check file permission 
                            to resolve this problem. Please check this %1$sFAQ page%2$s for possible solution.',
                            '%1$s and %2$s are html anchor tags or link',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks') . '" target="_blank">',
                        '</a>'
                    );
                } else {
                    $default = true;
                }
            } else {
                if (!Shell::isExecutable($available)) {
                    printf(
                        esc_html_x(
                            'The mysqldump program at its default location exists but is not executable. 
                            Please check file permission to resolve this problem. Please check this %1$sFAQ page%2$s for possible solution.',
                            '%1$s and %2$s are html anchor tags or link',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks') . '" target="_blank">',
                        '</a>'
                    );
                } else {
                    $default = true;
                }
            }
        } else {
            if ($is_custom) {
                printf(
                    esc_html_x(
                        'The mysqldump program was not found at its custom path location. 
                        Please check is there some typo mistake or mysqldump program exists on that location. 
                        Also you can leave custom path empty to force automatic settings. If the problem persist 
                        contact your server admin for the correct path. For a list of approved providers that support mysqldump %1$sclick here%2$s.',
                        '%1$s and %2$s are html anchor tags or links',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'what-host-providers-are-recommended-for-duplicator/') . '" target="_blank">',
                    '</a>'
                );
            } else {
                esc_html_e(
                    'The mysqldump program was not found at its default location. 
                    To use mysqldump, ask your host to install it or for a custom mysqldump path.',
                    'duplicator-pro'
                );
            }
        }

        if ($default) {
            printf(
                esc_html_x(
                    'The mysqldump program was not found at its default location or the custom path below. 
                    Please enter a valid path where mysqldump can run. If the problem persist contact your 
                    server admin for the correct path. For a list of approved providers that support mysqldump %1$sclick here%2$s.',
                    '%1$s and %2$s are html anchor tags or links',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'what-host-providers-are-recommended-for-duplicator/') . '" target="_blank">',
                '</a>'
            );
        }
    }
}
