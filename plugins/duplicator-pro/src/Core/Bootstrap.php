<?php

/**
 * Interface that collects the functions of initial duplicator Bootstrap
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use Duplicator\Core\MigrationMng;
use DUP_PRO_Package;
use Duplicator\Ajax\AjaxServicesUtils;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Addons\AddonsManager;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\Views\Notifications;
use Duplicator\Core\REST\RESTManager;
use Duplicator\Core\Upgrade\UpgradePlugin;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\ActivityLog\LogUtils;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\Import\PackageImporter;
use Duplicator\Package\PackageUtils;
use Duplicator\Package\Runner;
use Duplicator\Utils\CronUtils;
use Duplicator\Utils\Email\EmailSummaryBootstrap;
use Duplicator\Utils\ExpireOptions;
use Duplicator\Utils\Translations;
use Duplicator\Utils\UsageStatistics\StatsBootstrap;
use Duplicator\Views\AdminNotices;
use Duplicator\Views\DashboardWidget;
use Duplicator\Views\PackageScreen;
use Duplicator\Views\ScreenBase;
use Duplicator\Views\ViewHelper;
use Error;
use Exception;

class Bootstrap
{
    /**
     *
     * @var string
     */
    private static $addsHash = '';

    /**
     * Init plugin
     *
     * @param string $addsHash pugin hash
     *
     * @return void
     */
    public static function init($addsHash): void
    {
        self::$addsHash = $addsHash;

        register_activation_hook(DUPLICATOR____FILE, [UpgradePlugin::class, 'onActivationAction']);
        Unistall::registreHooks();

        add_action('duplicator_addons_loaded', [self::class, 'addonsLoaded']);
        add_action('plugins_loaded', [self::class, 'pluginsLoaded']);
        add_action(
            'plugins_loaded',
            function (): void {
                load_plugin_textdomain(
                    Constants::PLUGIN_SLUG,
                    false,
                    dirname(plugin_basename(DUPLICATOR____FILE)) . '/lang/'
                );
            },
            100
        );
        AddonsManager::getInstance()->inizializeAddons();

        if (defined('WP_CLI') && WP_CLI) {
            // If WP CLI is running, we don't want to load unnecessary resources
            return;
        }

        ControllersManager::getInstance();
        RESTManager::getInstance();

        if (is_admin()) {
            AdminNotices::init();
            MigrationMng::init();
            DashboardWidget::init();
        }

        add_action('init', [self::class, 'hookWpInit']);
        add_action('init', [self::class, 'renameInstallerFile'], 20);

        StatsBootstrap::init();
        EmailSummaryBootstrap::init();
    }

    /**
     * Method called on WordPress hook init action
     *
     * @return void
     */
    public static function hookWpInit(): void
    {
        if (!AddonsManager::getInstance()->isAddonsReady()) {
            return;
        }

        if (ControllersManager::getInstance()->isDuplicatorPage()) {
            add_filter('admin_footer_text', [self::class, 'adminFooterText'], 1);
            add_filter('update_footer', [self::class, 'adminFooterVersion'], 99999);
        }

        AjaxServicesUtils::loadServices();
        Notifications::init();

        self::initialChecks();

        add_action('admin_init', [self::class, 'adminInit']);
        add_action('admin_footer', [self::class, 'adminFooter']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueGlobalScripts']);

        add_action('wp_ajax_DUP_PRO_UI_ViewState_SaveByPost', ['\DUP_PRO_UI_ViewState', 'saveByPost']);

        if (is_multisite()) {
            add_action('network_admin_menu', [self::class, 'menu']);
            add_filter('network_admin_plugin_action_links', [self::class, 'manageLink'], 10, 2);
            add_filter('network_admin_plugin_row_meta', [self::class, 'metaLinks'], 10, 2);
        } else {
            add_action('admin_menu', [self::class, 'menu']);
            add_filter('plugin_action_links', [self::class, 'manageLink'], 10, 2);
            add_filter('plugin_row_meta', [self::class, 'metaLinks'], 10, 2);
        }
    }



    /**
     * This function is executed on both frontend and backend side.
     * It is in this function that is tested if the plugin should be updated or a schedule should be started.
     *
     * @return void
     */
    public static function initialChecks(): void
    {
        $exception = null;
        try {
            // Only start the Backup runner and tracing once it's been confirmed that everything has been installed
            if (get_option(UpgradePlugin::DUP_VERSION_OPT_KEY) != DUPLICATOR_PRO_VERSION) {
                return;
            }

            if (
                !is_admin() &&
                ExpireOptions::getUpdate(
                    DUPLICATOR_PRO_FRONTEND_TRANSIENT,
                    true,
                    DUPLICATOR_PRO_FRONTEND_ACTION_DELAY
                ) !== false
            ) {
                return;
            }

            if (MigrationMng::isFirstLoginAfterInstallOption()) {
                // Skip initial check on migration
                return;
            }

            if (
                ExpireOptions::getUpdate(
                    DUPLICATOR_TMP_CLEANUP_CHECK_KEY,
                    true,
                    DUPLICATOR_TMP_CLEANUP_CHECK_DELAY
                ) === false &&
                DUP_PRO_Package::isPackageRunning() === false
            ) {
                PackageUtils::safeTmpCleanup();
            }

            self::dailyActions();
            Runner::init();
        } catch (Exception | Error $e) {
            $exception = $e;
        }

        if (!is_null($exception)) {
            $msg = "Initial checks error " . $exception->getMessage() . "\n" . SnapLog::getTextException($exception);
            SnapUtil::errorLog($msg);
            DUP_PRO_Log::trace($msg);
        }
    }

    /**
     * Rename old installer file
     *
     * @return void
     */
    public static function renameInstallerFile(): void
    {
        $exception = null;
        try {
            if (ExpireOptions::getUpdate(DUPLICATOR_PRO_INSTALLER_RENAME_KEY, true, DUPLICATOR_PRO_INSTALLER_RENAME_DELAY) !== false) {
                return;
            }

            MigrationMng::renameInstallersPhpFiles(DUPLICATOR_PRO_INSTALLER_RENAME_DELAY);
        } catch (Exception | Error $e) {
            $exception = $e;
        }

        if (!is_null($exception)) {
            $msg = "Installer rename error " . $exception->getMessage() . "\n" . SnapLog::getTextException($exception);
            SnapUtil::errorLog($msg);
            DUP_PRO_Log::trace($msg);
        }
    }

    /**
     * Return plugin hash
     *
     * @return string
     */
    public static function getAddsHash()
    {
        return self::$addsHash;
    }

    /**
     * Method called on admin_init hook
     *
     * @return void
     */
    public static function adminInit(): void
    {
        self::startInitSettings();

        // custom host init
        \DUP_PRO_Custom_Host_Manager::getInstance()->init();

        self::registerJsCss();
        self::registerTranslations();

        $global = \DUP_PRO_Global_Entity::getInstance();
        if ($global->unhook_third_party_js || $global->unhook_third_party_css) {
            add_action('admin_enqueue_scripts', [self::class, 'unhookThirdPartyAssets'], 99999, 1);
        }

        add_action('in_admin_header', [ViewHelper::class, 'adminLogoHeader'], 100);
        add_filter('admin_body_class', [ViewHelper::class, 'addBodyClass'], 100, 1);
        add_action('admin_head', [ScreenBase::class, 'getCustomCss']);

        if (DUPLICATOR_CAPABILITIES_RESET) { // @phpstan-ignore-line
            CapMng::getInstance()->hardReset();
        }
    }

    /**
     * Daily duplicator actions
     *
     * @return void
     */
    protected static function dailyActions()
    {
        if (
            ExpireOptions::getUpdate(
                'daily_bootstrap_actions',
                true,
                DAY_IN_SECONDS
            ) !== false
        ) {
            return;
        }

        try {
            DUP_PRO_Log::trace("Doing daily actions");
            PackageImporter::purgeOldImports();
            do_action('duplicator_pro_daily_actions');
        } catch (Exception | Error $e) {
            DUP_PRO_Log::trace("DAILY BOOTSTRAP ACTIONS ERROR\n" . SnapLog::getTextException($e));
        }
    }

    /**
     * Check if is debug mode
     *
     * @return bool
     */
    public static function isDebug(): bool
    {
        return defined('DUPLICATOR_DEBUG') && DUPLICATOR_DEBUG; // @phpstan-ignore-line
    }

    /**
     * Get min prefix
     *
     * @return string
     */
    public static function getMinPrefix(): string
    {
        return self::isDebug() ? '' : '.min';
    }

    /**
     * Register styles and scripts
     *
     * @return void
     */
    protected static function registerJsCss()
    {
        if (wp_doing_ajax()) {
            return;
        }

        $min = self::getMinPrefix();

        wp_register_style(
            'duplicator-font-awesome',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/css/font-awesome/css/all.min.css',
            [],
            '6.4.2'
        );
        wp_register_style(
            'formstone',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/formstone/bundle.css',
            [],
            'v1.4.16-1'
        );
        wp_register_style(
            'jstree',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/jstree/themes/snap/style.css',
            [],
            '3.8.1'
        );
        wp_register_style(
            'dup-pro-select2',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/select2/css/select2.min.css',
            [],
            DUPLICATOR_PRO_VERSION
        );
        wp_register_style(
            'duplicator-main',
            DUPLICATOR_PRO_PLUGIN_URL . "assets/css/duplicator{$min}.css",
            [
                'duplicator-font-awesome',
                'jstree',
                'formstone',
                'dup-pro-select2',
            ],
            DUPLICATOR_PRO_VERSION
        );
        wp_register_style(
            'dup-plugin-global-style',
            DUPLICATOR_PRO_PLUGIN_URL . "assets/css/duplicator-global{$min}.css",
            [],
            DUPLICATOR_PRO_VERSION
        );

        //JS
        wp_register_script('dup-pro-handlebars', DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/handlebars.min.js', ['jquery'], '4.0.10');
        wp_register_script('parsley', DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/parsleyjs/parsley.min.js', ['jquery'], '2.9.2');
        wp_register_script('popper', DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/popper/popper.min.js', [], '2.4.4');
        wp_register_script('dup-pro-tippy', DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/tippy/tippy-bundle.umd.min.js', ['popper'], '6.2.6');
        wp_register_script('formstone', DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/formstone/bundle.js', ['jquery'], 'v1.4.16-1');
        wp_register_script('jstree', DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/jstree/jstree.min.js', [], '3.3.7');
        wp_register_script('jscookie', DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/jscookie/js.cookie.min.js', [], '3.0.0');
        wp_register_script(
            'dup-pro-select2',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/select2/js/select2.js',
            ['jquery'],
            DUPLICATOR_PRO_VERSION,
            true
        );
        wp_register_script(
            'duplicator-tooltip',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/duplicator-tooltip.js',
            ['dup-pro-tippy'],
            DUPLICATOR_PRO_VERSION
        );
        wp_register_script(
            'dup-pro-import-installer',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/import-installer.js',
            ['jquery'],
            DUPLICATOR_PRO_VERSION,
            true
        );
        wp_register_script(
            'dup-pro-dynamic-help',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/dynamic-help.js',
            ['jquery'],
            DUPLICATOR_PRO_VERSION,
            true
        );
    }

    /**
     * Register translations
     *
     * @return void
     */
    protected static function registerTranslations()
    {
        $translations = new Translations(
            Constants::PLUGIN_SLUG,
            Constants::TRANSLATIONS_API_URL,
            'plugin'
        );
        $translations->init();
    }

    /**
     * Enqueue CSS Styles:
     * Loads all CSS style libs/source for DupPro
     *
     * @return void
     */
    public static function enqueueStyles(): void
    {
        wp_enqueue_style('duplicator-main');
    }

    /**
     * Enqueue Global CSS Styles
     *
     * @return void
     */
    public static function enqueueGlobalStyles(): void
    {
        wp_enqueue_style('dup-plugin-global-style');
    }

    /**
     * Hooked into `admin_enqueue_scripts`.  Init routines for all admin pages
     *
     * @return void
     */
    public static function enqueueGlobalScripts(): void
    {
        wp_enqueue_script(
            'dup-pro-global-script',
            DUPLICATOR_PRO_PLUGIN_URL . 'assets/js/global-admin-script.js',
            ['jquery'],
            DUPLICATOR_PRO_VERSION,
            true
        );
        wp_localize_script(
            'dup-pro-global-script',
            'dup_pro_global_script_data',
            [
                'nonce_admin_notice_to_dismiss'              => wp_create_nonce('duplicator_pro_admin_notice_to_dismiss'),
                'nonce_dashboard_widged_info'                =>  wp_create_nonce("duplicator_pro_dashboad_widget_info"),
                'nonce_dashboard_widged_dismiss_recommended' => wp_create_nonce("duplicator_pro_dashboad_widget_dismiss_recommended"),
                'ajaxurl'                                    => admin_url('admin-ajax.php'),
            ]
        );
    }

    /**
     * Enqueue Scripts:
     * Loads all required javascript libs/source for DupPro
     *
     * @return void
     */
    public static function enqueueScripts(): void
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-color');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-progressbar');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('parsley');
        wp_enqueue_script('accordion');
        wp_enqueue_script('duplicator-tooltip');
        wp_enqueue_script('formstone');
        wp_enqueue_script('jstree');
        wp_enqueue_script('jscookie');
        wp_enqueue_script('dup-pro-select2');
        wp_enqueue_script('dup-pro-dynamic-help');

        wp_localize_script(
            'duplicator-tooltip',
            'l10nDupTooltip',
            [
                'copy'       => esc_html__('Copy to clipboard', 'duplicator-pro'),
                'copied'     => esc_html__('Copied to Clipboard', 'duplicator-pro'),
                'copyUnable' => esc_html__('Unable to copy', 'duplicator-pro'),
            ]
        );
        wp_localize_script(
            'dup-pro-dynamic-help',
            'l10nDupDynamicHelp',
            [
                'failedLoad' => esc_html__('Failed to load help content!', 'duplicator-pro'),
            ]
        );
    }

    /**
     * Plugins Loaded:
     * Hooked into `plugin_loaded`.  Called once any activated plugins have been loaded.
     *
     * @return void
     */
    public static function pluginsLoaded(): void
    {
        if (!is_admin()) {
            return;
        }

        if (DUPLICATOR_PRO_VERSION != get_option(UpgradePlugin::DUP_VERSION_OPT_KEY)) {
            UpgradePlugin::onActivationAction();
        }

        try {
            self::patchedDataInitialization();
        } catch (\Exception $ex) {
            DUP_PRO_Log::traceError("Could not do data initialization. " . $ex->getMessage());
        }
    }

    /**
     * Addons Loaded, called after all duplicator addons are loaded
     *
     * @return void
     */
    public static function addonsLoaded(): void
    {
        CapMng::getInstance();
        StoragesUtil::registerTypes();
        CronUtils::init();
        LogUtils::registerAllLogTypes();

        // Register package types must be called before everythin else
        add_action('duplicator_register_package_types', [PackageUtils::class, 'registerStandardPackageType']);
        do_action('duplicator_register_package_types');
    }

    /**
     * Init settings check
     *
     * @return void
     */
    public static function startInitSettings(): void
    {
        $nonce                = SnapUtil::sanitizeTextInput(INPUT_GET, 'nonce');
        $clearScheduleFailure = SnapUtil::sanitizeIntInput(INPUT_GET, 'dup_pro_clear_schedule_failure', 0);
        if (wp_verify_nonce($nonce, 'dup_pro_clear_schedule_failure') && $clearScheduleFailure === 1) {
            $system_global                  = SystemGlobalEntity::getInstance();
            $system_global->schedule_failed = false;
            $system_global->save();
        }

        if (!defined('WP_MAX_MEMORY_LIMIT')) {
            define('WP_MAX_MEMORY_LIMIT', '256M');
        }

        if (SnapUtil::isIniValChangeable('memory_limit')) {
            @ini_set('memory_limit', WP_MAX_MEMORY_LIMIT);
        }
    }

    /**
     * Action Hook:
     * Hooked into `admin_menu`.  Loads all of the admin menus for DupPro
     *
     * @return void
     */
    public static function menu(): void
    {
        ControllersManager::getInstance()->registerMenu();

        $page_packages = \Duplicator\Controllers\PackagesPageController::getInstance()->getMenuHookSuffix();
        if (($page_packages = \Duplicator\Controllers\PackagesPageController::getInstance()->getMenuHookSuffix())  != false) {
            add_action('admin_print_scripts-' . $page_packages, [self::class, 'enqueueScripts']);
            add_action('admin_print_styles-' . $page_packages, [self::class, 'enqueueStyles']);
            $packageScreen = new PackageScreen($page_packages); // Init hook on constructor
        }

        if (($page_import = \Duplicator\Controllers\ImportPageController::getInstance()->getMenuHookSuffix())  != false) {
            add_action('admin_print_scripts-' . $page_import, [self::class, 'enqueueScripts']);
            add_action('admin_print_styles-' . $page_import, [self::class, 'enqueueStyles']);
        }
        if (($page_schedules = \Duplicator\Controllers\SchedulePageController::getInstance()->getMenuHookSuffix())  != false) {
            add_action('admin_print_scripts-' . $page_schedules, [self::class, 'enqueueScripts']);
            add_action('admin_print_styles-' . $page_schedules, [self::class, 'enqueueStyles']);
        }
        if (($page_storage = \Duplicator\Controllers\StoragePageController::getInstance()->getMenuHookSuffix())  != false) {
            add_action('admin_print_scripts-' . $page_storage, [self::class, 'enqueueScripts']);
            add_action('admin_print_styles-' . $page_storage, [self::class, 'enqueueStyles']);
        }
        if (($page_settings = \Duplicator\Controllers\SettingsPageController::getInstance()->getMenuHookSuffix())  != false) {
            add_action('admin_print_scripts-' . $page_settings, [self::class, 'enqueueScripts']);
            add_action('admin_print_styles-' . $page_settings, [self::class, 'enqueueStyles']);
        }
        if (($page_tools = \Duplicator\Controllers\ToolsPageController::getInstance()->getMenuHookSuffix())  != false) {
            add_action('admin_print_scripts-' . $page_tools, [self::class, 'enqueueScripts']);
            add_action('admin_print_styles-' . $page_tools, [self::class, 'enqueueStyles']);
        }
        if (($page_activity_log = \Duplicator\Controllers\ActivityLogPageController::getInstance()->getMenuHookSuffix())  != false) {
            add_action('admin_print_scripts-' . $page_activity_log, [self::class, 'enqueueScripts']);
            add_action('admin_print_styles-' . $page_activity_log, [self::class, 'enqueueStyles']);
        }
        $page_installer = \Duplicator\Controllers\ImportInstallerPageController::getInstance()->getMenuHookSuffix();

        //Init Blank Pages
        \Duplicator\Controllers\HelpPageController::getInstance();

        add_action('admin_print_styles', [self::class, 'enqueueGlobalStyles']);
    }

    /**
     * Data Patches:
     * Handles data that needs to be initialized because of fixes etc
     *
     * @return void
     */
    protected static function patchedDataInitialization()
    {
        $global = \DUP_PRO_Global_Entity::getInstance();

        if ($global->initial_activation_timestamp == 0) {
            $global->initial_activation_timestamp = time();
            $global->save();
        }
    }

    /**
     * Remove all external styles and scripts coming from other plugins
     * which may cause compatibility issue, especially with React
     *
     * @param string $hook Hook string
     *
     * @return void
     */
    public static function unhookThirdPartyAssets($hook): void
    {
        if (!ControllersManager::getInstance()->isDuplicatorPage()) {
            return;
        }

        $global = \DUP_PRO_Global_Entity::getInstance();
        $assets = [];

        if ($global->unhook_third_party_css) {
            $assets['styles'] = wp_styles();
        }

        if ($global->unhook_third_party_js) {
            $assets['scripts'] = wp_scripts();
        }

        foreach ($assets as $type => $asset) {
            foreach ($asset->registered as $handle => $dep) {
                $src = $dep->src;
                // test if the src is coming from /wp-admin/ or /wp-includes/ or /wp-fsqm-pro/.
                if (
                    is_string($src) && // For some built-ins, $src is true|false
                    strpos($src, 'wp-admin') === false &&
                    strpos($src, 'wp-include') === false &&
                    // things below are specific to your plugin, so change them
                    strpos($src, 'duplicator-pro') === false &&
                    strpos($src, 'woocommerce') === false &&
                    strpos($src, 'jetpack') === false &&
                    strpos($src, 'debug-bar') === false
                ) {
                    'scripts' === $type ? wp_dequeue_script($handle) : wp_dequeue_style($handle);
                }
            }
        }
    }

    /**
     * Plugin MetaData:
     * Adds the manage link in the plugins list
     *
     * @param string[] $links links list
     * @param string   $file  plugin file
     *
     * @return string[] The manage link in the plugins list
     */
    public static function manageLink($links, $file)
    {
        static $this_plugin;

        if (!$this_plugin) {
            $this_plugin = plugin_basename(DUPLICATOR____FILE);
        }

        if ($file == $this_plugin) {
            $url           = ControllersManager::getMenuLink(ControllersManager::PACKAGES_SUBMENU_SLUG);
            $settings_link = "<a href='$url'>" . __('Manage', 'duplicator-pro') . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    /**
     * Plugin MetaData:
     * Adds links to the plugins manager page
     *
     * @param string[] $links links list
     * @param string   $file  plugin file
     *
     * @return string[] The meta help link data for the plugins manager
     */
    public static function metaLinks($links, $file)
    {
        $plugin = plugin_basename(DUPLICATOR____FILE);
        if ($file == $plugin) {
            $help_url = ControllersManager::getMenuLink(ControllersManager::TOOLS_SUBMENU_SLUG);
            $links[]  = sprintf('<a href="%1$s" title="%2$s">%3$s</a>', esc_url($help_url), __('Get Help', 'duplicator-pro'), __('Help', 'duplicator-pro'));

            return $links;
        }
        return $links;
    }

    /**
     * When user is on a Duplicator related admin pages, display admin footer text
     * that graciously asks them to rate us.
     *
     * @return string
     */
    public static function adminFooterText(): string
    {
        $text  = '';
        $text .= '<span class="dup-styles" ><i>';
        $text .= sprintf(
            wp_kses(
                _x(
                    'Please rate <strong>Duplicator</strong> %1$s on %2$sWordPress.org%3$s to help us spread the word.',
                    '%1$s represents 5 start symbols linked to wordpress.org review page, %2$s,%2$s represents one,close link',
                    'duplicator-pro'
                ),
                [
                    'a'      => [
                        'href'   => [],
                        'target' => [],
                        'rel'    => [],
                    ],
                    'strong' => [],
                ]
            ),
            '<a href="https://wordpress.org/support/plugin/duplicator/reviews/?filter=5#new-post"
            class="link-style" target="_blank" rel="noopener noreferrer">' .
                '&#9733;&#9733;&#9733;&#9733;&#9733;</a>',
            '<a href="https://wordpress.org/support/plugin/duplicator/reviews/?filter=5#new-post"
            class="link-style" target="_blank" rel="noopener">',
            '</a>'
        );
        return $text . '</i></span>';
    }

    /**
     * Updates admin footer text by adding Duplicator version
     *
     * @param string $defaultText Default WP footer text
     *
     * @return string Modified version text
     */
    public static function adminFooterVersion($defaultText)
    {
        if (!ControllersManager::getInstance()->isDuplicatorPage()) {
            return $defaultText;
        }

        return sprintf(
            '%1$s | Duplicator Pro %2$s',
            $defaultText,
            esc_html(DUPLICATOR_PRO_VERSION)
        );
    }

    /**
     * Footer Hook:
     * Hooked into `admin_footer`.  Returns display elements for the admin footer area
     *
     * @return void
     */
    public static function adminFooter(): void
    {
        if (
            !ControllersManager::getInstance()->isDuplicatorPage() ||
            !get_option('duplicator_pro_trace_log_enabled', false)
        ) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_SETTINGS, false) && !CapMng::can(CapMng::CAP_CREATE, false)) {
            return;
        }

        $txt_trace_zero = esc_html__('Download', 'duplicator-pro') . ' (0B)';
        $turnOffUrl     = SettingsPageController::getInstance()->getTraceActionUrl(false);
        $traceLogUrl    = ControllersManager::getMenuLink(
            ControllersManager::TOOLS_SUBMENU_SLUG,
            ToolsPageController::L2_SLUG_LOGS
        );

        $ajaxGetTraceUrl = admin_url('admin-ajax.php') . '?' . http_build_query([
            'action' => 'duplicator_pro_get_trace_log',
            'nonce'  => wp_create_nonce('duplicator_pro_get_trace_log'),
        ]);

        if (
            ControllersManager::isCurrentPage(
                ControllersManager::TOOLS_SUBMENU_SLUG,
                ToolsPageController::L2_SLUG_LOGS
            )
        ) {
            $clear_trace_log_js = 'DupPro.UI.ClearTraceLog(1);';
        } else {
            $clear_trace_log_js = 'DupPro.UI.ClearTraceLog(0); jQuery("#dup_pro_trace_txt").html(' . json_encode($txt_trace_zero) . '); ';
        }
        ?>
        <style>
            p#footer-upgrade {
                display: none
            }
        </style>
        <div class="dup-styles">
            <div id="dpro-monitor-trace-area">
                <b><?php esc_html_e('TRACE LOG OPTIONS', 'duplicator-pro'); ?></b><br />
                <?php if (CapMng::can(CapMng::CAP_CREATE, false)) { ?>
                    <a class="button tiny hollow gray margin-bottom-0" href="<?php echo esc_url($traceLogUrl); ?>" target="_duptracelog">
                        <i class="fa fa-file-alt"></i> <?php esc_html_e('View', 'duplicator-pro'); ?>
                    </a>
                    <a class="button tiny hollow gray margin-bottom-0" onclick="<?php echo esc_attr($clear_trace_log_js); ?>">
                        <i class="fa fa-times"></i> <?php esc_html_e('Clear', 'duplicator-pro'); ?>
                    </a>
                    <a
                        class="button tiny hollow gray margin-bottom-0"
                        onclick="<?php echo esc_attr('location.href = ' . json_encode($ajaxGetTraceUrl) . ';'); ?>">
                        <i class="fa fa-download"></i> <span id="dup_pro_trace_txt">
                            <?php echo esc_html__('Download', 'duplicator-pro') . ' (' . esc_html(DUP_PRO_Log::getTraceStatus()) . ')'; ?>
                        </span>
                    </a>
                <?php } ?>
                <?php if (CapMng::can(CapMng::CAP_SETTINGS, false)) { ?>
                    <a class="button tiny hollow gray margin-bottom-0" href="<?php echo esc_url($turnOffUrl); ?>">
                        <i class="fa fa-power-off"></i> <?php echo esc_html__('Turn Off', 'duplicator-pro'); ?>
                    </a>
                <?php } ?>
            </div>
        </div>
        <?php
    }
}
