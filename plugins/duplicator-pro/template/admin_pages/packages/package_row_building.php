<?php

/**
 * @package Duplicator
 */

use Duplicator\Core\CapMng;
use Duplicator\Package\AbstractPackage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var ?DUP_PRO_Package $package
 */
$package = $tplData['package'];

/** @var int */
$status = $tplData['status'];

if ($status <= AbstractPackage::STATUS_PRE_PROCESS || $status >= AbstractPackage::STATUS_COMPLETE) {
    return;
}
?>

<tr class="dup-row-progress" data-package-id="<?php echo (int) $package->getId(); ?>">
    <td colspan="11">
        <div class="wp-filter dup-build-msg">
            <?php if ($status < AbstractPackage::STATUS_STORAGE_PROCESSING) : ?>
                <!-- BUILDING PROGRESS-->
                <div class="dpro-progress-status-message-build">
                    <div class="status-hdr">
                        <?php esc_html_e('Creating Backup', 'duplicator-pro'); ?>&nbsp;
                        <i class="fa fa-cog fa-sm fa-spin"></i>&nbsp;
                        <span class="status-<?php echo (int) $package->getId(); ?>"><?php echo (int) $status; ?></span>%
                    </div>
                    <small class="xsmall">
                        <?php esc_html_e('Please allow it to finish before creating another one.', 'duplicator-pro'); ?>
                    </small>
                </div>
            <?php else : ?>
                <!-- TRANSFER PROGRESS -->
                <div class="dpro-progress-status-message-transfer">
                    <div class="status-hdr">
                        <?php if ($package->isDownloadInProgress()) : ?>
                            <?php esc_html_e('Downloading Backup', 'duplicator-pro'); ?>&nbsp;
                        <?php else : ?>
                            <?php esc_html_e('Transferring Backup', 'duplicator-pro'); ?>&nbsp;
                        <?php endif; ?>
                        <i class="fa fa-sync fa-sm fa-spin"></i>&nbsp;
                        <span class="status-progress-<?php echo (int) $package->getId(); ?>">0</span>%
                        <span class="status-<?php echo (int) $package->getId(); ?> no-display">
                            <?php echo (int) $status; ?>
                        </span>
                    </div>
                    <small class="dpro-progress-status-message-transfer-msg">
                        <?php esc_html_e('Getting Transfer State...', 'duplicator-pro'); ?>
                    </small>
                </div>
            <?php endif; ?>
            <div class="dup-progress-bar-area">
                <div class="dup-pro-meter-wrapper">
                    <div class="dup-pro-meter green dup-pro-fullsize">
                        <span></span>
                    </div>
                    <span class="text"></span>
                </div>
            </div>
            <?php if (CapMng::can(CapMng::CAP_CREATE, false)) { ?>
                <button
                    onclick="DupPro.Pack.StopBuild(<?php echo (int) $package->getId(); ?>); return false;"
                    class="button hollow secondary small dup-build-stop-btn display-inline">
                    <i class="fa fa-times fa-sm"></i>&nbsp;
                    <?php
                    if ($status >= AbstractPackage::STATUS_STORAGE_PROCESSING) {
                        esc_html_e('Stop Transfer', 'duplicator-pro');
                    } elseif ($status > AbstractPackage::STATUS_PRE_PROCESS) {
                        esc_html_e('Stop Build', 'duplicator-pro');
                    } else {
                        esc_html_e('Cancel Pending', 'duplicator-pro');
                    }
                    ?>
                </button>
            <?php } ?>
        </div>
    </td>
</tr>