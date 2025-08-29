<?php

/**
 * @package Duplicator
 */

use Duplicator\Controllers\PackagesPageController;
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
?>
<div class="progress-error text-center">
    <?php
    switch ($status) {
        case AbstractPackage::STATUS_ERROR:
            $packageDetailsURL = PackagesPageController::getInstance()->getPackageDetailsURL($package->getId());
            ?>
            <a type="button" class="dup-cell-err-btn" href="<?php echo esc_url($packageDetailsURL) ?>">
                <i class="fa fa-exclamation-triangle fa-xs"></i>&nbsp;
                <?php esc_html_e('Error Processing', 'duplicator-pro') ?>
            </a>
            <?php
            break;
        case AbstractPackage::STATUS_BUILD_CANCELLED:
            ?>
            <i class="fas fa-info-circle  fa-sm"></i>&nbsp;
            <?php esc_html_e('Build Cancelled', 'duplicator-pro') ?>
            <?php
            break;
        case AbstractPackage::STATUS_PENDING_CANCEL:
            ?>
            <i class="fas fa-info-circle  fa-sm"></i>
            <?php esc_html_e('Cancelling Build', 'duplicator-pro') ?>
            <?php
            break;
        case AbstractPackage::STATUS_STORAGE_CANCELLED:
            ?>
            <i class="fas fa-info-circle  fa-sm"></i>&nbsp;
            <?php esc_html_e('Storage Cancelled', 'duplicator-pro') ?>
            <?php
            break;
        case AbstractPackage::STATUS_REQUIREMENTS_FAILED:
            $packageLogFile = dirname($package->StorePath) . '/' . $package->getNameHash() . '_log.txt';
            if (file_exists($packageLogFile)) {
                $link_log = $package->StoreURL . $package->getNameHash() . "_log.txt";
            } else {
                // .log is for backward compatibility
                $link_log = $package->StoreURL . $package->getNameHash() . ".log";
            }
            ?>
            <a href="<?php echo esc_url($link_log) ?>" target="_blank">
                <i class="fas fa-info-circle"></i> <?php esc_html_e('Requirements Failed', 'duplicator-pro') ?>
            </a>
            <?php
            break;
        default:
            break;
    }
    ?>
</div>