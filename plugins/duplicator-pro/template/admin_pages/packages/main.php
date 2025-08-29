<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || exit;

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\Views\TplMng;
use Duplicator\Core\Views\Notifications;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\PackageUtils;
use Duplicator\Views\PackageListTable;

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var bool $blur
 */

$tplMng        = TplMng::getInstance();
$system_global = SystemGlobalEntity::getInstance();

$totalElements = PackageUtils::getNumPackages([DUP_PRO_Package::getBackupType()]);
$statusActive  = DUP_PRO_Package::isPackageRunning();
$activePackage = DUP_PRO_Package::getNextActive();
$isTransfer    = $activePackage === null ? false : $activePackage->getStatus() == AbstractPackage::STATUS_STORAGE_PROCESSING;

$pager       = new PackageListTable();
$perPage     = $pager->get_per_page();
$currentPage = $statusActive && !$isTransfer ? 1 : $pager->get_pagenum();
$offset      = ($currentPage - 1) * $perPage;

$global = DUP_PRO_Global_Entity::getInstance();

do_action(Notifications::DUPLICATOR_PRO_BEFORE_PACKAGES_HOOK);
?>

<form
    id="form-duplicator"
    method="post"
    class="<?php echo esc_attr($tplData['blur'] ? 'dup-mock-blur' : ''); ?>">
    <?php PackagesPageController::getInstance()
        ->getActionByKey(PackagesPageController::ACTION_STOP_BUILD)->getActionNonceFileds(); ?>
    <input type="hidden" id="stop-backup-id" name="stop-backup-id" />
    <?php $tplMng->render('admin_pages/packages/toolbar'); ?>

    <table class="widefat dup-table-list dup-packtbl striped" aria-label="Backup List">
        <?php
        $tplMng->render(
            'admin_pages/packages/packages_table_head',
            ['totalElements' => $totalElements]
        );

        if ($totalElements == 0) {
            $tplMng->render('admin_pages/packages/no_elements_row');
        } else {
            DUP_PRO_Package::dbSelectByStatusCallback(
                function (\DUP_PRO_Package $package): void {
                    TplMng::getInstance()->render(
                        'admin_pages/packages/package_row',
                        ['package' => $package]
                    );
                },
                [],
                $perPage,
                $offset,
                '`id` DESC',
                [
                    PackageUtils::DEFAULT_BACKUP_TYPE,
                ]
            );
        }
        $tplMng->render(
            'admin_pages/packages/packages_table_foot',
            ['totalElements' => $totalElements]
        ); ?>
    </table>
</form>

<?php if ($totalElements > $perPage) { ?>
    <form id="form-duplicator-nav" method="post">
        <?php wp_nonce_field('dpro_package_form_nonce'); ?>
        <div class="dup-paged-nav tablenav">
            <?php if ($statusActive > 0) : ?>
                <div id="dpro-paged-progress" style="padding-right: 10px">
                    <i class="fas fa-circle-notch fa-spin fa-lg fa-fw"></i>
                    <i><?php esc_html_e('Paging disabled during build...', 'duplicator-pro'); ?></i>
                </div>
            <?php else : ?>
                <div id="dpro-paged-buttons">
                    <?php $pager->display_pagination($totalElements, $perPage); ?>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php } else { ?>
    <div style="float:right; padding:10px 5px">
        <?php echo esc_html(sprintf(_n('%s item', '%s items', $totalElements, 'duplicator-pro'), $totalElements)); ?>
    </div>
    <?php
}

$tplMng->render(
    'admin_pages/packages/packages_scripts',
    [
        'perPage'          => $perPage,
        'offset'           => $offset,
        'currentPage'      => $currentPage,
        'stattiBackupType' => DUP_PRO_Package::getBackupType(),
    ]
);
