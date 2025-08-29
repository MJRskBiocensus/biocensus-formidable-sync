<?php
/**
 * Addon update class
 *
 * @package formidable-abandonment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrmAbandonmentUpdate
 */
class FrmAbandonmentUpdate extends FrmAddon {

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
	public $plugin_name = 'Abandonment';

	/**
	 * Download ID.
	 *
	 * @var int
	 */
	public $download_id = 28217763;

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
		$this->plugin_file = FrmAbandonmentAppHelper::plugin_file();
		$this->version     = FrmAbandonmentAppHelper::$plug_version;
		parent::__construct();
	}
}
