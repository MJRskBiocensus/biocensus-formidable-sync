<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Upgrade;

use DUP_PRO_Log;
use Duplicator\Core\CapMng;

/**
 * Upgrade logic of plugin resides here
 *
 * DUP_PRO_Plugin_Upgrade
 */
class UpgradePlugin
{
    const DUP_VERSION_OPT_KEY          = 'duplicator_pro_plugin_version';
    const DUP_INSTALL_INFO_OPT_KEY     = 'duplicator_pro_install_info';
    const DUP_PRO_INSTALL_TIME_OLD_KEY = 'duplicator_pro_install_time';

    /**
     * Perform activation action.
     *
     * @return void
     */
    public static function onActivationAction(): void
    {
        // Init capabilities
        CapMng::getInstance();

        $oldDupVersion = get_option(self::DUP_VERSION_OPT_KEY, false);
        $newDupVersion = DUPLICATOR_PRO_VERSION;
        UpgradeFunctions::performUpgrade($oldDupVersion, $newDupVersion);

        // WordPress Options Hooks
        self::updateOptionVersion();
        self::setInstallInfo($oldDupVersion);

        do_action('duplicator_pro_after_activation', $oldDupVersion, $newDupVersion);
        /*
        add_action(
            'init',
            function () use ($oldDupVersion, $newDupVersion) {
                DUP_PRO_Log::trace("DUPLICATOR PRO AFTER ACTIVATION HOOK FROM " . $oldDupVersion . " TO " . $newDupVersion);
                do_action('duplicator_pro_after_activation', $oldDupVersion, $newDupVersion);
            },
            1 // High priority to run at first on init hook
        );*/
    }

    /**
     * Update install info.
     *
     * @param false|string $oldVersion The last/previous installed version, is empty for new installs
     *
     * @return array{version:string,time:int,updateTime:int}
     */
    public static function setInstallInfo($oldVersion = ''): array
    {
        if (empty($oldVersion) || ($installInfo = get_option(self::DUP_INSTALL_INFO_OPT_KEY, false)) === false) {
            // If is new installation or install info is not set generate new install info
            $installInfo = [
                'version'    => DUPLICATOR_PRO_VERSION,
                'time'       => time(),
                'updateTime' => time(),
            ];
        } else {
            $installInfo['updateTime'] = time();
        }

        if (($installTime = get_option(self::DUP_PRO_INSTALL_TIME_OLD_KEY, false)) !== false) {
            // Migrate the previously used option to install info and remove old option if exists
            $installInfo['version'] = (string) $oldVersion;
            $installInfo['time']    = $installTime;
            delete_option(self::DUP_PRO_INSTALL_TIME_OLD_KEY);
        }

        delete_option(self::DUP_INSTALL_INFO_OPT_KEY);
        update_option(self::DUP_INSTALL_INFO_OPT_KEY, $installInfo, false);
        return $installInfo;
    }

    /**
     * Get install info.
     *
     * @return array{version:string,time:int,updateTime:int}
     */
    public static function getInstallInfo()
    {
        if (($installInfo = get_option(self::DUP_INSTALL_INFO_OPT_KEY, false)) === false) {
            $installInfo = self::setInstallInfo();
        }
        return $installInfo;
    }

    /**
     * Update option version.
     *
     * @return void
     */
    protected static function updateOptionVersion()
    {
        // WordPress Options Hooks
        if (update_option(self::DUP_VERSION_OPT_KEY, DUPLICATOR_PRO_VERSION, true) === false) {
            DUP_PRO_Log::trace("Couldn't update duplicator_pro_plugin_version so deleting it.");

            delete_option(self::DUP_VERSION_OPT_KEY);

            if (update_option(self::DUP_VERSION_OPT_KEY, DUPLICATOR_PRO_VERSION, true) === false) { // @phpstan-ignore-line
                DUP_PRO_Log::trace("Still couldn\'t update the option!");
            } else { // @phpstan-ignore-line
                DUP_PRO_Log::trace("Option updated.");
            }
        }
    }
}
