<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * Trait FrmProFieldTypeTrait.
 *
 * @since 6.20
 */
trait FrmProFieldTypeTrait {

	/**
	 * Appends formatted range attributes (min, max, and step) to the input HTML.
	 *
	 * @since 6.20
	 *
	 * @param array  $args        Field arguments.
	 * @param string &$input_html The HTML string to which the attributes will be appended.
	 * @return void
	 */
	protected function add_formatted_min_max( $args, &$input_html ) {
		$min = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, FrmField::get_option( $this->field, 'minnum' ) );
		if ( ! is_numeric( $min ) ) {
			$min = 0;
		}

		$max = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, FrmField::get_option( $this->field, 'maxnum' ) );
		if ( ! is_numeric( $max ) ) {
			$max = 9999999;
		}

		$step = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, FrmField::get_option( $this->field, 'step' ) );
		if ( ! is_numeric( $step ) && $step !== 'any' ) {
			$step = 1;
		}

		$input_html .= ' min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '"';
	}
}
