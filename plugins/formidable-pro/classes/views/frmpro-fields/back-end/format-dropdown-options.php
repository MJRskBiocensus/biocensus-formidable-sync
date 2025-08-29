<?php
/**
 * @package Formidable
 * @since 6.18
 *
 * @var array        $field Field data.
 * @var array        $args  Includes 'field', 'display', and 'values' settings.
 * @var FrmFieldType $this  Field type object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

$field_type = $field['type'];
$format     = FrmField::get_option( $field, 'format' );

if ( isset( $field['calc_dec'] ) && isset( $field['field_options']['calc_dec'] ) && $field['calc_dec'] !== $field['field_options']['calc_dec'] ) {
	// Settings are defined in both the field and field options, and the values may differ.
	// Help keep the data in-sync.
	$field['calc_dec'] = $field['field_options']['calc_dec'];
}

if ( FrmProCurrencyHelper::uses_legacy_decimal_places_calc( $field ) ) {
	// For backward compatibility, when a calculation used the decimal places calculation setting,
	// set the format from None to Number and remove the default thousand separator setting.
	$format                             = 'number';
	$field['custom_thousand_separator'] = '';
}

FrmHtmlHelper::echo_dropdown_option( __( 'None', 'formidable' ), '' === $format, array( 'value' => 'none' ) );

FrmHtmlHelper::echo_dropdown_option(
	in_array( $field_type, array( 'number', 'range' ), true ) ? __( 'Custom', 'formidable' ) : __( 'Number', 'formidable' ),
	'number' === $format,
	array(
		'value'           => 'number',
		'data-dependency' => '#frm-field-format-currency-' . $field['id'],
	)
);

FrmHtmlHelper::echo_dropdown_option(
	__( 'Currency', 'formidable' ),
	'currency' === $format || FrmField::get_option( $field, 'is_currency' ),
	array(
		'value'           => 'currency',
		'data-dependency' => '#frm-field-format-global-currency-' . $field['id'],
	)
);

if ( 'text' === $field_type ) {
	FrmHtmlHelper::echo_dropdown_option(
		__( 'Custom', 'formidable' ),
		! empty( $format ) && ! FrmProCurrencyHelper::is_currency_format( $format ),
		array(
			'value'           => 'custom',
			'data-dependency' => '#frm-field-format-custom-' . $field_id,
		)
	);
}
