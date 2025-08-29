<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmGeoUpdate extends FrmAddon {

	/**
	 * @var string
	 */
	public $plugin_file;

	/**
	 * @var string
	 */
	public $plugin_name = 'Geolocation';

	/**
	 * @var int
	 */
	public $download_id = 28118399;

	/**
	 * @var string
	 */
	public $version;

	public function __construct() {
		$this->plugin_file = FrmGeoAppHelper::plugin_file();
		$this->version     = FrmGeoAppHelper::$plug_version;
		parent::__construct();
	}

	/**
	 * @return void
	 */
	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new FrmGeoUpdate();
	}
}
