<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Core\Views\TplMng;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var Duplicator\Models\Storages\AbstractStorageEntity $storage
 */
$storage = $tplData['storage'];
/** @var int */
$index = $tplData['index'];

$type_name = $storage->getStypeName();
$type_id   = $storage->getSType();
?>
<tr id="main-view-<?php echo (int) $storage->getId() ?>"
    class="storage-row <?php echo ($index % 2) ? 'alternate' : ''; ?>"
    data-delete-view="<?php echo esc_attr($storage->getDeleteView(false)); ?>"
>
    <td>
        <?php if ($storage->isDefault()) : ?>
            <input type="checkbox" disabled="disabled" />
        <?php else : ?>
            <input name="selected_id[]" type="checkbox" value="<?php echo (int) $storage->getId(); ?>" class="item-chk" />
        <?php endif; ?>
    </td>
    <td>                                             
        <a href="javascript:void(0);" onclick="DupPro.Storage.Edit('<?php echo (int) $storage->getId(); ?>')">
            <b><?php echo esc_html($storage->getName()); ?></b>
        </a>
        <div class="sub-menu">
            <a href="javascript:void(0);" onclick="DupPro.Storage.Edit('<?php echo (int) $storage->getId(); ?>')">
                <?php esc_html_e('Edit', 'duplicator-pro'); ?>
            </a> 
            |
            <a href="javascript:void(0);" onclick="DupPro.Storage.View('<?php echo (int) $storage->getId(); ?>');">
                <?php esc_html_e('Quick View', 'duplicator-pro'); ?>
            </a> 
            <?php if (!$storage->isDefault()) : ?>
                <?php if (!$storage->isLocal()) : ?>
                |
                <a href="javascript:void(0);" onclick="DupPro.Storage.CopyEdit('<?php echo (int) $storage->getId(); ?>');">
                    <?php esc_html_e('Copy', 'duplicator-pro'); ?>
                </a>
                <?php endif; ?>
                |
                <a href="javascript:void(0);" onclick="DupPro.Storage.deleteSingle('<?php echo (int) $storage->getId(); ?>');">
                    <?php esc_html_e('Delete', 'duplicator-pro'); ?>
                </a>
            <?php endif; ?>
        </div>
    </td>
    <td>
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
        ),
        '&nbsp;',
        esc_html($storage->getStypeName());
        ?>
    </td>
</tr>
<?php
ob_start();
try { ?>
    <tr id='quick-view-<?php echo (int) $storage->getId(); ?>' class='<?php echo ($index % 2) ? 'alternate' : ''; ?> storage-detail'>
        <td colspan="3">
            <b><?php esc_html_e('QUICK VIEW', 'duplicator-pro') ?></b> <br/>
            <div>
                <label><?php esc_html_e('Name', 'duplicator-pro') ?>:</label>
                <?php echo esc_html($storage->getName()); ?>
            </div>
            <div>
                <label><?php esc_html_e('Notes', 'duplicator-pro') ?>:</label>
                <?php echo (strlen($storage->getNotes())) ? esc_html($storage->getNotes()) : esc_html__('(no notes)', 'duplicator-pro'); ?>
            </div>
            <div>
                <label><?php esc_html_e('Type', 'duplicator-pro') ?>:</label>
                <?php echo esc_html($storage->getStypeName()); ?>
            </div>
            <?php $storage->getListQuickView(); ?>
            <button type="button" class="button secondary hollow tiny" onclick="DupPro.Storage.View('<?php echo (int) $storage->getId(); ?>');">
                <?php esc_html_e('Close', 'duplicator-pro') ?>
            </button>
        </td>
    </tr>
    <?php
} catch (Exception $e) {
    ob_clean(); ?>
    <tr id='quick-view-<?php echo intval($storage->getId()); ?>' class='<?php echo ($index % 2) ? 'alternate' : ''; ?>'>
        <td colspan="3">
            <?php TplMng::getInstance()->render(
                'admin_pages/storages/parts/storage_error',
                ['exception' => $e]
            ); ?>
            <br><br>
            <button type="button" class="button" onclick="DupPro.Storage.View('<?php echo intval($storage->getId()); ?>');">
            <?php esc_html_e('Close', 'duplicator-pro') ?>
            </button>
        </td>
    </tr>
    <?php
}
ob_end_flush();