<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ImportPageController;
use Duplicator\Package\Import\PackageImporter;
use Duplicator\Package\Recovery\RecoveryPackage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var PackageImporter $importObj
 */
$importObj = $tplData['importObj'];
/** @var string $iframeSrc */
$iframeSrc         = $tplData['iframeSrc'];
$importFailMessage = '';

if (!$importObj->isImportable($importFailMessage)) {
    ?>
    <div class="wrap dup-styles">
        <h1>
            <?php esc_html_e("Install Backup", 'duplicator-pro'); ?>
        </h1>
        <div class="dpro-pro-import-installer-content-wrapper" >
            <p class="orangered">
                <?php echo esc_html($importFailMessage); ?>
            </p>
        </div>
    </div>
<?php } else { ?>
    <div id="dpro-pro-import-installer-wrapper" class="dup-styles" >
        <div id="dpro-pro-import-installer-top-bar" class="dup-pro-recovery-details-max-width-wrapper" >
            <a href="<?php echo esc_url(ImportPageController::getImportPageLink()); ?>" class="button secondary hollow small margin-bottom-0" >
                <i class="fa fa-caret-left"></i> <?php esc_html_e("Back to Import", 'duplicator-pro'); ?>
            </a>&nbsp;
            <span class="link-style no-decoration recovery-copy-top-wrapper" >
                <?php if (($recoverPackage = RecoveryPackage::getRecoverPackage()) !== false) { ?>
                    <span class="button secondary hollow small margin-bottom-0" 
                          data-tooltip-placement="right"
                          data-dup-copy-value="<?php echo esc_url($recoverPackage->getInstallLink()); ?>"
                          data-dup-copy-title="<?php esc_attr_e("Copy Recovery URL to clipboard", 'duplicator-pro'); ?>"
                          data-dup-copied-title="<?php esc_attr_e("Recovery URL copied to clipboard", 'duplicator-pro'); ?>" >
                              <?php esc_html_e("Copy Recovery URL", 'duplicator-pro'); ?>
                    </span>
                <?php } else { ?>
                    <span class="button secondary hollow disabled small margin-bottom-0">
                        <i class="fas fa-exclamation-circle"></i> <?php esc_html_e("Recovery Point Not Set", 'duplicator-pro'); ?>
                    </span>
                <?php } ?>
            </span>
        </div>
        <div id="dup-pro-import-installer-modal" class="no-display"></div>
        <iframe id="dpro-pro-import-installer-iframe" src="<?php echo esc_url($iframeSrc); ?>" ></iframe>
    </div>
    <?php
}
