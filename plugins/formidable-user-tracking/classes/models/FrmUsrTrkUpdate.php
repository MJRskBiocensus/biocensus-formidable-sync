<?php

class FrmUsrTrkUpdate extends FrmAddon {

	/**
	 * @var string $plugin_file
	 */
	public $plugin_file;

	/**
	 * @var string $plugin_name
	 */
	public $plugin_name = 'User Tracking';

	/**
	 * @var int $download_id
	 */
	public $download_id = 170649;

	/**
	 * @var string $version
	 */
	public $version;

	public function __construct() {
		$this->plugin_file = FrmUsrTrkAppHelper::plugin_path() . '/formidable-user-tracking.php';
		$this->version     = FrmUsrTrkAppHelper::$plug_version;
		parent::__construct();
	}

}
