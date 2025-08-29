<?php
/**
 * Handle the database interactions.
 *
 * @package formidable-abandonment
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * Database class.
 *
 * @since 1.1
 */
class FrmAbdnDb {

	/**
	 * New DB version. Change me when the CSS changes.
	 *
	 * @var int
	 */
	private $new_db_version = 1;

	/**
	 * Option name used for storing DB version.
	 *
	 * @var string
	 */
	private $option_name = 'frm_abdn_db_version';

	/**
	 * Maybe migrate the database.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		if ( $this->need_to_migrate() ) {
			$this->migrate();
		}
	}

	/**
	 * Check if need migrating.
	 *
	 * @return bool
	 */
	public function need_to_migrate() {
		return FrmAppController::compare_for_update(
			array(
				'option'             => $this->option_name,
				'new_db_version'     => $this->new_db_version,
				'new_plugin_version' => FrmAbandonmentAppHelper::plugin_version(),
			)
		);
	}

	/**
	 * Migrate data to current version, if needed.
	 *
	 * @return void
	 */
	private function migrate() {
		$this->update_db_version();
	}

	/**
	 * Save the db version to the database.
	 *
	 * @return void
	 */
	private function update_db_version() {
		update_option( $this->option_name, FrmAbandonmentAppHelper::plugin_version() . '-' . $this->new_db_version );
	}
}
