<?php

/**
 * Flywheel custom hosting class
 *
 * Standard: PSR-2
 *
 * @package SC\DUPX\HOST
 * @link    http://www.php-fig.org/psr/psr-2/
 */

use Duplicator\Libs\Snap\SnapWP;

class DUP_PRO_Flywheel_Host implements DUP_PRO_Host_interface
{
    public static function getIdentifier(): string
    {
        return DUP_PRO_Custom_Host_Manager::HOST_FLYWHEEL;
    }

    public function isHosting()
    {
        return apply_filters('duplicator_pro_host_check', file_exists(self::getFlywheelMainPluginPaht()), self::getIdentifier());
    }

    public function init(): void
    {
        add_filter('duplicator_pro_overwrite_params_data', [self::class, 'installerParams']);
        add_filter('duplicator_pro_global_file_filters', [self::class, 'filterPluginFile']);
    }

    /**
     * Get the path to the main plugin file
     *
     * @return string
     */
    public static function getFlywheelMainPluginPaht(): string
    {
        return trailingslashit(SnapWP::getHomePath()) . '.fw-config.php';
    }

    /**
     * Filter plugin file
     *
     * @param array<string> $globalsFileFilters Global file filters
     *
     * @return array<string>
     */
    public static function filterPluginFile($globalsFileFilters)
    {
        $globalsFileFilters[] = self::getFlywheelMainPluginPaht();
        return $globalsFileFilters;
    }

    /**
     * Add installer params
     *
     * @param array<string,array{formStatus?:string,value:mixed}> $data Data
     *
     * @return array<string,array{formStatus?:string,value:mixed}>
     */
    public static function installerParams($data)
    {
        // generare new wp-config.php file
        $data['wp_config'] = [
            'value'      => 'new',
            'formStatus' => 'st_infoonly',
        ];

        return $data;
    }
}
