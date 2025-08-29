<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
$datepicker_class = FrmProFieldsHelper::get_datepicker_class();
?>
.<?php echo esc_html( $datepicker_class ); ?>:not(.frm-datepicker-custom-theme) .flatpickr-monthDropdown-months,
.<?php echo esc_html( $datepicker_class ); ?>:not(.frm-datepicker-custom-theme) .numInputWrapper {
	min-height: unset;
	padding: 4px 5px 5px;
	line-height: 14px;
	margin: 0;
	color: var(--text-color);
	max-width: 49%;
	height: auto;
}
.<?php echo esc_html( $datepicker_class ); ?> .flatpickr-monthDropdown-months {
	background-color: #fff;
}