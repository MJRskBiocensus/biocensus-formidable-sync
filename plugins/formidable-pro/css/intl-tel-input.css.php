<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
.with_frm_style .iti {
	width: var(--field-width);
	--iti-spacer-horizontal: 12px;
	--iti-arrow-padding: 4px;
	<?php // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong ?>
	--iti-selected-country-arrow-padding: calc(var(--iti-arrow-padding) + var(--iti-arrow-padding) + var(--iti-flag-width) + var(--iti-spacer-horizontal) + var(--iti-arrow-width) + var(--iti-input-padding) + 8px);
	--iti-hover-color: var(--bg-color-disabled);
}
@media only screen and (max-width: 782px) {
	.with_frm_style .iti {
		width: auto;
	}
}
.with_frm_style .iti__country {
	font-size: var(--field-font-size);
}
.with_frm_style .iti__selected-country {
	background-color: unset !important;
}
.with_frm_style .iti__flag {
	background-image: url('<?php echo esc_url( FrmProAppHelper::relative_plugin_url() ); ?>/images/intl-tel-input/flags.png?1');
	transform: scale(0.9);
}
@media (min-resolution: 2x) {
	.with_frm_style .iti__flag {
		background-image: url('<?php echo esc_url( FrmProAppHelper::relative_plugin_url() ); ?>/images/intl-tel-input/flags@2x.png?1');
	}
}
.with_frm_style .iti__globe {
	background-image: url('<?php echo esc_url( FrmProAppHelper::relative_plugin_url() ); ?>/images/intl-tel-input/globe.png');
}
@media (min-resolution: 2x) {
	.with_frm_style .iti__globe {
		background-image: url('<?php echo esc_url( FrmProAppHelper::relative_plugin_url() ); ?>/images/intl-tel-input/globe@2x.png');
	}
}
.with_frm_style .iti__arrow {
	border: 0;
	width: 16px;
	height: 16px;
	<?php // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong ?>
	background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M10.1667 7.16669L8.00004 9.50002L5.83337 7.16669' stroke='%2398A2B3' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
}

.with_frm_style .iti--container {
	position: absolute;
}

.with_frm_style .iti--fullscreen-popup .iti__dropdown-content {
	padding: 20px 15px;
}
