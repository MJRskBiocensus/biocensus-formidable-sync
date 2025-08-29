<?php
/**
 * REST API controller
 *
 * @package FrmCharts
 */

/**
 * Class FrmChartsRESTController
 */
class FrmChartsRESTController {

	const NAMESPACE = 'frm-charts/v1';

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_forms' ),
				'permission_callback' => function() {
					return true;
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/form-fields/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_form_fields' ),
				'permission_callback' => function() {
					return true;
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/graph',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'get_graph' ),
				'permission_callback' => function() {
					return true;
				},
			)
		);
	}

	/**
	 * Gets forms from REST.
	 *
	 * @return array|WP_REST_Response|WP_Error
	 */
	public static function get_forms() {
		$forms = FrmForm::get_published_forms();
		return $forms;
	}

	/**
	 * Gets fields of a form from REST.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array|WP_REST_Response|WP_Error
	 */
	public static function get_form_fields( WP_REST_Request $request ) {
		$form_id = $request->get_param( 'id' );
		if ( ! $form_id ) {
			return new WP_Error( 'no_form_id', __( 'No form ID', 'frm-charts' ), array( 'status' => 404 ) );
		}

		return FrmField::getAll(
			array(
				'fi.type not' => FrmField::no_save_fields(),
				'fi.form_id'  => $form_id,
			)
		);
	}

	/**
	 * Gets graph from REST.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array|WP_REST_Response|WP_Error
	 */
	public static function get_graph( WP_REST_Request $request ) {
		$attributes = $request->get_param( 'attributes' );

		FrmChartsGraphController::set_custom_graph_id( $request->get_param( 'graph_id' ) );
		add_filter( 'frm_graph_id', array( 'FrmChartsGraphController', 'change_graph_id' ) );
		$response = array(
			'graphContent' => FrmChartsGraphController::render( $attributes ),
		);
		remove_filter( 'frm_graph_id', array( 'FrmChartsGraphController', 'change_graph_id' ) );

		global $frm_vars;
		if ( isset( $frm_vars['google_graphs'] ) ) {
			$response['graphData'] = $frm_vars['google_graphs'];
		}

		return $response;
	}
}
