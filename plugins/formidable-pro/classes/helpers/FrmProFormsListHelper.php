<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 5.3.1
 */
class FrmProFormsListHelper extends FrmFormsListHelper {

	/**
	 * All application IDs are queried once and stored for later use.
	 *
	 * @since 6.16
	 *
	 * @var array|null
	 */
	private $application_ids_by_form_id;

	/**
	 * @param array $args
	 * @return void
	 */
	public function __construct( $args ) {
		parent::__construct( $args );
		wp_enqueue_style( 'frm_applications_common' );
	}

	/**
	 * @param stdClass $form
	 * @return string
	 */
	public function column_application( $form ) {
		$application_ids = $this->get_application_ids_for_form_id( $form->id );
		return FrmProApplicationsHelper::get_application_tags_html( array_unique( $application_ids ) );
	}

	/**
	 * @since 6.16
	 *
	 * @param int|string $form_id
	 * @return array
	 */
	private function get_application_ids_for_form_id( $form_id ) {
		if ( ! isset( $this->application_ids_by_form_id ) ) {
			$this->init_application_ids_by_form_id();
		}
		if ( ! is_array( $this->application_ids_by_form_id ) || ! array_key_exists( $form_id, $this->application_ids_by_form_id ) ) {
			return array();
		}

		$application_ids = $this->application_ids_by_form_id[ $form_id ];
		return is_array( $application_ids ) ? $application_ids : array();
	}

	/**
	 * Query for application ID data for all forms instead of querying for every form in the list.
	 *
	 * @since 6.16
	 *
	 * @return void
	 */
	private function init_application_ids_by_form_id() {
		global $wpdb;
		$this->application_ids_by_form_id = array();

		if ( empty( $this->items ) || ! is_array( $this->items ) ) {
			return;
		}

		$form_ids = array_unique( wp_list_pluck( $this->items, 'id' ) );
		if ( ! $form_ids ) {
			return;
		}

		$where               = array(
			'meta_key'   => '_frm_form_id',
			'meta_value' => $form_ids,
		);
		$application_id_data = FrmDb::get_results( $wpdb->termmeta, $where, 'term_id, meta_value' );

		foreach ( $application_id_data as $row ) {
			$application_id = $row->term_id;
			$form_id        = $row->meta_value;

			if ( ! array_key_exists( $form_id, $this->application_ids_by_form_id ) ) {
				$this->application_ids_by_form_id[ $form_id ] = array();
			}

			$this->application_ids_by_form_id[ $form_id ][] = $application_id;
		}
	}
}
