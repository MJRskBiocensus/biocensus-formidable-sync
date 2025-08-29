<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\StoragePageController;
use Duplicator\Core\CapMng;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
$storageList        = AbstractStorageEntity::getAll(0, 0, [StoragesUtil::class, 'sortByPriority']);
$filteredStorageIds = $tplData['filteredStorageIds'] ?? [];
$selectedStorageIds = $tplData['selectedStorageIds'] ?? [];
$showAddNew         = $tplData['showAddNew'] ?? true;
$minCheck           = $tplData['minCheck'] ?? true;
$recoveryPointMsg   = $tplData['recoveryPointMsg'] ?? false;
$newStorageUrl      = StoragePageController::getEditUrl();
?>
<table class="widefat dup-table-list storage-select-list small striped">
    <thead>
        <tr>
            <th></th>
            <th><?php esc_html_e('Type', 'duplicator-pro') ?></th>
            <th><?php esc_html_e('Name', 'duplicator-pro') ?></th>
            <th><?php esc_html_e('Location', 'duplicator-pro') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($storageList as $storage) {
            try {
                if (!$storage->isSupported() || $storage->isHidden()) {
                    continue;
                }

                if (in_array($storage->getId(), $filteredStorageIds)) {
                    continue;
                }

                $is_valid       = $storage->isValid();
                $is_checked     = in_array($storage->getId(), $selectedStorageIds) && $is_valid;
                $storageEditUrl = StoragePageController::getEditUrl($storage);
                ?>
                <tr class="package-row">
                    <td class="storage-checkbox">
                        <input
                            class="duppro-storage-input margin-bottom-0"
                            id="dup-chkbox-<?php echo (int) $storage->getId(); ?>"
                            name="_storage_ids[]"
                            data-parsley-errors-container="#storage_error_container"
                            <?php if ($minCheck) : ?>
                            data-parsley-mincheck="1"
                            data-parsley-required="true"
                            <?php endif; ?>
                            type="checkbox"
                            value="<?php echo (int) $storage->getId(); ?>"
                            <?php disabled($is_valid == false); ?>
                            <?php checked($is_checked); ?>>
                    </td>
                    <td class="storage-type">
                        <label for="dup-chkbox-<?php echo (int) $storage->getId(); ?>" class="dup-store-lbl">
                            <?php
                            echo wp_kses(
                                $storage->getStypeIcon(),
                                [
                                    'i'   => [
                                        'class' => [],
                                    ],
                                    'img' => [
                                        'src'   => [],
                                        'class' => [],
                                        'alt'   => [],
                                    ],
                                ]
                            );
                            echo '&nbsp;' . esc_html($storage->getStypeName());
                            if ($recoveryPointMsg && $storage->isLocal()) {
                                ?>
                                <sup title="<?php esc_attr_e('Recovery Point Capable', 'duplicator-pro'); ?>">
                                    <i class="fas fa-house-fire fa-fw fa-sm"></i>
                                </sup>
                                <?php
                            }
                            ?>
                        </label>
                    </td>
                    <td class="storage-name">
                        <a href="<?php echo esc_attr($storageEditUrl); ?>" target="_blank">
                            <?php if (!$is_valid) : ?>
                                <i class="fa fa-exclamation-triangle fa-sm"></i>
                            <?php endif; ?>
                            <?php echo esc_html($storage->getName()); ?>
                        </a>
                    </td>
                    <td class="storage-location">
                        <?php
                        echo wp_kses(
                            $storage->getHtmlLocationLink(),
                            [
                                'a' => [
                                    'href'   => [],
                                    'target' => [],
                                ],
                            ]
                        );
                        ?>
                    </td>
                </tr>
                <?php
            } catch (Exception $e) {
                ?>
                <tr>
                    <td colspan='5'>
                        <i><?php esc_html_e('Unable to load storage type. Please validate the setup.', 'duplicator-pro'); ?></i>
                    </td>
                </tr>
                <?php
            }
        }
        ?>
    </tbody>
</table>
<div id="storage_error_container" class="duplicator-error-container"></div>
<?php if ($showAddNew) { ?>
    <div class="text-right">
        <?php if (CapMng::can(CapMng::CAP_STORAGE, false)) { ?>
            <a href="<?php echo esc_url($newStorageUrl); ?>" target="_blank">
                [<?php esc_html_e('Add Storage', 'duplicator-pro') ?>]
            </a>
        <?php } else { ?>
            &nbsp;
        <?php } ?>
    </div>
<?php } ?>