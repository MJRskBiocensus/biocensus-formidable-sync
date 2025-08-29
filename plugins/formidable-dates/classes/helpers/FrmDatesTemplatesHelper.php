<?php
class FrmDatesTemplatesHelper {

	public static function settings_render_dates_list( $args = array() ) {
		$defaults = array(
			'items'     => array(),
			'field_id'  => 0,
			'date_type' => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$items     = $args['items'];
		$field_id  = absint( $args['field_id'] );
		$date_type = $args['date_type'];

		ob_start();
		include FrmDatesAppHelper::get_path( '/views/dates-list.php' );
		return ob_get_clean();
	}

	public static function settings_render_dates_list_item( $args = array() ) {
		$defaults = array(
			'date'           => '',
			'formatted_date' => '',
			'date_type'      => '',
			'field_id'       => 0,
			'css_classes'    => '',
			'input_name'     => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$date           = $args['date'];
		$formatted_date = empty( $args['formatted_date'] ) ? FrmProAppHelper::convert_date( $date, 'Y-m-d', 'db' ) : $args['formatted_date'];
		$date_type      = $args['date_type'];
		$field_id       = absint( $args['field_id'] );
		$css_classes    = $args['css_classes'];
		$input_name     = empty( $args['input_name'] ) ? $date_type . '_' . $field_id : $args['input_name'];

		ob_start();
		include FrmDatesAppHelper::get_path( '/views/dates-list-item.php' );
		return ob_get_clean();
	}
}
