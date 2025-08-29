<?php
/**
 * Date calculation controller
 *
 * @package formidable-dates
 * @since 2.0
 */

/**
 * Class FrmDatesCalculationController
 */
class FrmDatesCalculationController {

	/**
	 * Adds a new setting tab for the Default value setting.
	 *
	 * @param array $types Default value setting types.
	 * @param array $atts  Field display atts.
	 * @return array
	 */
	public static function add_default_value_type( $types, $atts ) {
		if ( isset( $atts['display']['type'] ) && 'date' === $atts['display']['type'] ) {
			$types['date_calc'] = array(
				'class' => 'frm-show-inline-modal',
				'title' => __( 'Default Value (Date Calculation)', 'frmdates' ),
				'icon'  => 'frm_icon_font frm_calculator_icon',
				'data'  => array(
					'open'    => 'frm-date-calc-box-',
					'frmshow' => '.frm-date-calc-box-',
					'frmhide' => '.frm-inline-modal,.default-value-section-' . $atts['display']['field_data']->id . ',.frm-lookup-box-',
				),
			);
		}

		return $types;
	}

	/**
	 * Adds default value type box.
	 *
	 * @param array $args Includes 'field', 'display', 'default_value_types'.
	 */
	public static function add_default_value_type_box( $args ) {
		if ( ! isset( $args['field']['type'] ) || 'date' !== $args['field']['type'] ) {
			return;
		}

		$field               = $args['field'];
		$default_value_types = $args['default_value_types'];

		if ( ! empty( $args['display']['date_calc'] ) ) {
			include FrmDatesAppHelper::get_path( '/views/calc-settings.php' );
		}
	}

	/**
	 * Overwrite the calculation for [date_calc] and [age] shortcodes.
	 * This way we can exclude blocked days from the calculation.
	 *
	 * @since 2.0.4
	 *
	 * @param string $calc
	 * @param array  $filter_args {
	 *     Filter arguments.
	 *
	 *     @type string $id
	 *     @type string $compare
	 *     @type string $format
	 *     @type array  $args
	 * }
	 * @return string
	 */
	public static function maybe_build_date_diff_calc( $calc, $filter_args ) {
		$id      = $filter_args['id'];
		$compare = $filter_args['compare'];
		$format  = $filter_args['format'];
		$args    = $filter_args['args'];

		if ( ! empty( $args['count_blackout_dates'] ) ) {
			// Use the Pro calculation when using [date_calc count_blackout_dates="1"].
			return $calc;
		}

		$field_id = is_numeric( $id ) ? absint( $id ) : FrmField::get_id_by_key( $id );
		if ( ! $field_id ) {
			return $calc;
		}

		$compare_field_id = is_numeric( $args['compare'] ) ? absint( $args['compare'] ) : FrmField::get_id_by_key( $args['compare'] );
		if ( ! $compare_field_id ) {
			return $calc;
		}

		return "calculateDateDifference( [$id], {$compare}, '{$format}', {$field_id}, {$compare_field_id} )";
	}
}
