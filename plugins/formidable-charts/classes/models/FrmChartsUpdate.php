<?php
/**
 * Addon update class
 *
 * @package FrmCharts
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * Class FrmChartsUpdate
 */
class FrmChartsUpdate extends FrmAddon {

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	public $plugin_file;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	public $plugin_name = 'Charts';

	/**
	 * Download ID.
	 *
	 * @var int
	 */
	public $download_id = 28248560;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_file = FrmChartsAppHelper::plugin_file();
		$this->version     = FrmChartsAppHelper::$plug_version;
		parent::__construct();
	}
}
