<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Interactions with the database and data migrations
 *
 * @since 2.0
 */
class FrmSigDb {

	/**
	 * Db version.
	 *
	 * @var integer
	 */
	private $new_db_version = 5;

	/**
	 * Current DB version.
	 *
	 * @var integer
	 */
	private $current_db_version = 0;

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option_name = 'frm_sig_db';

	/**
	 * Migrations.
	 *
	 * @var array
	 */
	private $migrations = array( 1, 2, 3, 4, 5 );

	/**
	 * FrmSigDb constructor
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init_db_version();

		// Check if the database version needs updating or initializing.
		if ( $this->is_first_install() ) {
			$this->initialize();
			FrmSigAppController::update_stylesheet();
		} elseif ( $this->is_migration_needed() ) {
			$this->migrate();
		}
	}

	/**
	 * Set the db version properties.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	private function init_db_version() {
		$this->new_db_version     = (int) end( $this->migrations );
		$this->current_db_version = (int) get_option( $this->option_name );
	}

	/**
	 * Determine if this is an initial install.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	private function is_first_install() {
		$is_first_install = false;

		if ( 0 === $this->current_db_version ) {
			$signature_field  = FrmDb::get_var( 'frm_fields', array( 'type' => 'signature' ), 'id' );
			$is_first_install = empty( $signature_field );
		}

		return $is_first_install;
	}

	/**
	 * Initialize the database.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	private function initialize() {
		$this->update_db_version();
	}

	/**
	 * Save the db version to the database.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	private function update_db_version() {
		update_option( $this->option_name, $this->new_db_version );
		$this->current_db_version = $this->new_db_version;
	}

	/**
	 * Check if signature fields need migrating.
	 *
	 * @since 2.0
	 * @return bool
	 */
	private function is_migration_needed() {
		return $this->current_db_version < $this->new_db_version;
	}

	/**
	 * Migrate data to current version, if needed.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	private function migrate() {
		$this->migrate_to_new_version();
		$this->update_db_version();
	}

	/**
	 * Go through all necessary migrations in order to migrate db to the current version.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	private function migrate_to_new_version() {
		foreach ( $this->migrations as $migrate_to_version ) {
			if ( $this->current_db_version < $migrate_to_version ) {
				$function_name = 'migrate_to_' . $migrate_to_version;
				$this->$function_name();
			}
		}
	}

	/**
	 * Convert saved values to new format.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	private function migrate_to_1() {
		$signature_fields = FrmDb::get_col( 'frm_fields', array( 'type' => 'signature' ), 'id' );

		foreach ( $signature_fields as $field_id ) {
			$field     = FrmField::getOne( $field_id );
			$field_obj = FrmFieldFactory::get_field_object( $field );
			$meta_rows = FrmDb::get_results( 'frm_item_metas', array( 'field_id' => $field_id ), 'meta_value, item_id' );

			foreach ( $meta_rows as $meta_row ) {
				$meta_value = $this->format_meta_value( $meta_row );

				$meta_value = $field_obj->get_value_to_save(
					$meta_value,
					array(
						'entry_id' => $meta_row->item_id,
						'field_id' => $field->id,
					)
				);
				FrmEntryMeta::update_entry_meta( $meta_row->item_id, $field_id, null, $meta_value );
			}
		}
	}

	/**
	 * Migrate to version 2.
	 *
	 * @since 2.06
	 *
	 * @return void
	 */
	private function migrate_to_2() {
		FrmSigAppController::update_stylesheet();
	}

	/**
	 * Perform upgrade to version 3.0.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	private function migrate_to_3() {
		FrmSigAppController::update_stylesheet();
	}

	/**
	 * Perform upgrade to version 3.0.1.
	 * This method will remove empty signatures.
	 *
	 * @since 3.0.1
	 *
	 * @return void
	 */
	private function migrate_to_4() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE fi.* FROM {$wpdb->prefix}frm_item_metas as fi INNER JOIN {$wpdb->prefix}frm_fields as ff on (fi.field_id=ff.id) WHERE ff.type=%s AND fi.meta_value=%s", 'signature', 'a:0:{}' ) );

		FrmEntry::clear_cache();
	}

	/**
	 * Perform upgrade to version 3.0.4.
	 *
	 * @since 3.0.4
	 *
	 * @return void
	 */
	private function migrate_to_5() {
		FrmSigAppController::update_stylesheet();
	}

	/**
	 * Format the meta value before updating.
	 *
	 * @since 2.0
	 *
	 * @param stdClass $meta_row meta row.
	 *
	 * @return array
	 */
	private function format_meta_value( $meta_row ) {
		$meta_value = maybe_unserialize( $meta_row->meta_value );

		if ( ! is_array( $meta_value ) ) {
			// Fix values that were incorrectly saved as strings.
			if ( strpos( $meta_value, '[{"lx":' ) !== false ) {
				$meta_value = array( 'output' => $meta_value );
			} else {
				$meta_value = array( 'typed' => $meta_value );
			}
		} elseif ( isset( $meta_value['typed'] ) && strpos( $meta_value['typed'], '[{"lx":' ) !== false ) {
			if ( ! isset( $meta_value['output'] ) || empty( $meta_value['output'] ) ) {
				$meta_value['output'] = $meta_value['typed'];
			}
			$meta_value['typed'] = '';
		}

		return $meta_value;
	}
}
