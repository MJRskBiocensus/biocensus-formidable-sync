<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Core\Addons\AddonsManager;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AbstractStorageEntity $storage
 */
$storage = $tplData["storage"];

$sTypeSelected = ($storage->isSelectable() ? $storage->getSType() : -1);
$types         = AbstractStorageEntity::getResisteredTypesByPriority();

if ($storage->getId() < 0) {
    $supportedNotices = [];
    ?>
    <select id="change-mode" name="storage_type" onchange="DupPro.Storage.ChangeMode()" class="width-medium" >
        <?php foreach ($types as $type) {
            $class = AbstractStorageEntity::getSTypePHPClass($type);
            if (!call_user_func([$class, 'isSelectable'])) {
                continue;
            }
            if (!call_user_func([$class, 'isSupported'])) {
                $supportedNotices[] = call_user_func([$class, 'getNotSupportedNotice']);
                continue;
            }
            $name = call_user_func([$class, 'getStypeName']);
            ?>
            <option value="<?php echo (int) $type; ?>" <?php selected($sTypeSelected, $type); ?>>
                <?php echo esc_html($name) ?>
            </option>
        <?php } ?>
    </select>
    <?php
    if (count($supportedNotices) > 0) { ?>
        <div class="margin-top-1" >
        <small class="dpro-store-type-notice"><b><?php esc_html_e('Unsupported storages: ', 'duplicator-pro'); ?></b></small><br>
        <?php foreach ($supportedNotices as $notice) { ?>
            <small class="dpro-store-type-notice">
                <?php
                echo '- ',
                wp_kses(
                    $notice,
                    [
                        'a' => [
                            'href'   => [],
                            'target' => [],
                        ],
                    ]
                );
                ?>
            </small>
            <br>
        <?php } ?>
        </div>
        <?php
    }
} else {
    ?>
    <span id="dup-storage-mode-fixed" data-storage-type="<?php echo (int) $storage->getSType(); ?>">
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
        ?>&nbsp;
        <b>
            <?php echo esc_html($storage->getStypeName()); ?>
        </b>
    </span>
    <?php
} ?>

<script>
    jQuery(document).ready(function ($) {
        DupPro.Storage.BindParsley = function (node)
        {
            $('#dup-storage-form').parsley().destroy();
            $('#dup-storage-form .provider input').attr('data-parsley-excluded', 'true');

            node.find('input').removeAttr('data-parsley-excluded');

            $('#dup-storage-form').parsley();
        };

        DupPro.Storage.Autofill = function (mode) {
        <?php if (AddonsManager::getInstance()->isAddonEnabled('AmazonS3Addon')) : ?>
            switch (parseInt(mode)) {
                case <?php echo (int) \Duplicator\Addons\AmazonS3Addon\Models\BackblazeStorage::getSType(); ?>:
                    autoFillRegion(mode, 1);
                    break;
                case <?php echo (int) \Duplicator\Addons\AmazonS3Addon\Models\DreamStorage::getSType(); ?>:
                case <?php echo (int) \Duplicator\Addons\AmazonS3Addon\Models\VultrStorage::getSType(); ?>:
                case <?php echo (int) \Duplicator\Addons\AmazonS3Addon\Models\DigitalOceanStorage::getSType(); ?>:
                    autoFillRegion(mode, 0);
                    break;
                case <?php echo (int) \Duplicator\Addons\AmazonS3Addon\Models\WasabiStorage::getSType(); ?>:
                    let wasabiRegion   = $("#s3_region_" + mode);
                    let wasabiEndpoint = $("#s3_endpoint_" + mode);

                    if (wasabiRegion.val().length > 0) {
                        wasabiEndpoint.val("s3." + wasabiRegion.val() + ".wasabisys.com");
                    }

                    wasabiRegion.change(function(e) {
                        let regionVal = $(this).val();
                        if (regionVal.length > 0) {
                            wasabiEndpoint.val("s3." + regionVal + ".wasabisys.com");
                        } else {
                            wasabiEndpoint.val("");
                        }
                    });
                    break;
            }

            function autoFillRegion(type, regionPos) {
                let region      = $("#s3_region_" + type);
                let endpoint    = $("#s3_endpoint_" + type);

                bindEndpointToRegion(region, endpoint, regionPos);

                endpoint.change(function(e) {
                    bindEndpointToRegion(region, endpoint, regionPos);
                });
            }

            function bindEndpointToRegion(region, endpoint, pos) {
                if (endpoint.val().length > 0) {
                    let regionStr = endpoint.val().replace(/.*:\/\//g,'').split(".")[pos];
                    region.val(regionStr);
                } else {
                    region.val("");
                }
            }
        <?php else : ?>
            return;
        <?php endif; ?>
        }

        // GENERAL STORAGE LOGIC
        DupPro.Storage.ChangeMode = function (animateOverride = 400) {
            const mode = $('#dup-storage-mode-fixed').length > 0
                ? $('#dup-storage-mode-fixed').data('storage-type')
                : $('#change-mode option:selected').val();

            // Reset the copy source ID select
            $('#dup-copy-source-id-select').val(-1);

            // Disable copy controls for local storage
            const isCopyDisabled = parseInt(mode) === 0;
            $('#dup-copy-source-id-select, #dup-copy-storage-btn').prop('disabled', isCopyDisabled);

            // Disable non-matching options
            $('#dup-copy-source-id-select option').each(function () {
                const option = $(this);
                const optionType = option.data('stype');
                option.prop('disabled', optionType !== parseInt(mode));

                // Hide options that are disabled
                if (option.prop('disabled')) {
                    option.hide();
                } else {
                    option.show();
                }
            });

            const providerConfigNode =  $('#provider-' + mode);

            $('.provider').hide();
            providerConfigNode.show(animateOverride);

            DupPro.Storage.BindParsley(providerConfigNode);
            DupPro.Storage.Autofill(mode);
        }

        $('#dup-storage-form').parsley();
        DupPro.Storage.ChangeMode(0);
    });
</script>

