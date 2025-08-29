<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Update class.
 */
class FrmSigUpdate extends FrmAddon {

	/**
	 * Plugin file.
	 *
	 * @var string
	 */
	public $plugin_file;

	/**
	 * Plugin Name.
	 *
	 * @var string
	 */
	public $plugin_name = 'Signature';

	/**
	 * Download id.
	 *
	 * @var integer
	 */
	public $download_id = 163248;

	/**
	 * Version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->plugin_file = FrmSigAppHelper::plugin_path() . '/signature.php';
		$this->version     = FrmSigAppHelper::plugin_version();
		parent::__construct();
	}

	/**
	 * Load Hooks.
	 *
	 * @return void
	 */
	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new FrmSigUpdate();
	}

}
