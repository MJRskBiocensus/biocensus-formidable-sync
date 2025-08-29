<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use DUP_PRO_Package;
use Duplicator\Ajax\AjaxWrapper;
use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Views\DashboardWidget;

class ServicesDashboard extends AbstractAjaxService
{
    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall('wp_ajax_duplicator_pro_dashboad_widget_info', 'dashboardWidgetInfo');
        $this->addAjaxCall('wp_ajax_duplicator_pro_dismiss_recommended_plugin', 'dismissRecommendedPlugin');
    }

    /**
     * Set recovery callback
     *
     * @return array<string, mixed>
     */
    public static function dashboardWidgetInfoCallback(): array
    {
        return [
            'isRunning'      => DUP_PRO_Package::isPackageRunning() || DUP_PRO_Package::isPackageCancelling(),
            'lastBackupInfo' => DashboardWidget::getLastBackupString(),
        ];
    }

    /**
     * Set recovery action
     *
     * @return void
     */
    public function dashboardWidgetInfo(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'dashboardWidgetInfoCallback',
            ],
            'duplicator_pro_dashboad_widget_info',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }

    /**
     * Set dismiss recommended callback
     *
     * @return bool
     */
    public static function dismissRecommendedPluginCallback(): bool
    {
        return (update_user_meta(get_current_user_id(), DashboardWidget::RECOMMENDED_PLUGIN_DISMISSED_OPT_KEY, true) !== false);
    }

    /**
     * Set recovery action
     *
     * @return void
     */
    public function dismissRecommendedPlugin(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'dismissRecommendedPluginCallback',
            ],
            'duplicator_pro_dashboad_widget_dismiss_recommended',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }
}
