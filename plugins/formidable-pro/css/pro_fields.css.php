<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

$use_chosen_js    = FrmProAppHelper::use_chosen_js();
$datepicker_class = FrmProFieldsHelper::get_datepicker_class();
?>
.with_frm_style, .frm_forms {
<?php
if ( ! empty( $vars ) && is_callable( 'FrmStylesHelper::output_vars' ) ) {
	FrmStylesHelper::output_vars( $defaults, array(), $vars );
}
	?>
	--progress-border-color-b: <?php echo esc_html( FrmStylesHelper::adjust_brightness( $defaults['progress_border_color'], -10 ) ); ?>;
	--image-size: 150px;
}

.js .frm_logic_form:not(.frm_no_hide) {
	display:none;
}

.with_frm_style .frm_conf_field.frm_half label.frm_conf_label {
	overflow: hidden;
	white-space: nowrap;
}

.with_frm_style .frm_time_wrap{
	white-space:nowrap;
}

.with_frm_style select.frm_time_select{
	white-space:pre;
	display:inline;
}

<?php if ( function_exists( 'twenty_twenty_one_setup' ) ) : ?>
	body:not(.wp-admin) .frm_time_select {
		padding-left: var(--form--spacing-unit) !important;
		padding-right: calc(3 * var(--form--spacing-unit)) !important;
	}
<?php endif; ?>

.with_frm_style .frm-show-form {
	overflow-x:clip;
}

/* Sections */

.with_frm_style .frm-show-form  .frm_section_heading h3[class*="frm_pos_"] {
	padding: var(--section-pad)<?php echo esc_html( $important ); ?>;
	margin: 0<?php echo esc_html( $important ); ?>;
	font-size: var(--section-font-size)<?php echo esc_html( $important ); ?>;
	<?php if ( ! empty( $defaults['font'] ) ) { ?>
	font-family: var(--font);
	<?php } ?>
	font-weight: var(--section-weight)<?php echo esc_html( $important ); ?>;
	color: var(--section-color)<?php echo esc_html( $important ); ?>;
	border: none<?php echo esc_html( $important ); ?>;
	background-color:var(--section-bg-color)<?php echo esc_html( $important ); ?>;
}

.frm_trigger .frmsvg {
	width: 16px;
	height: 16px;
	color: #667085;
	margin: 0 2px;
}

.frm_trigger > svg.frmsvg:nth-child(1) {
	display:inline-block;
}
.frm_trigger > svg.frmsvg:nth-child(2) {
	display:none;
}

.frm_trigger.active > svg.frmsvg:nth-child(2) {
	display:inline-block;
}
.frm_trigger.active > svg.frmsvg:nth-child(1) {
	display:none;
}

.with_frm_style .frm_repeat_sec {
	margin-bottom: var(--field-margin)<?php echo esc_html( $important ); ?>;
	margin-top: var(--field-margin)<?php echo esc_html( $important ); ?>;
	padding-bottom: 15px;
	border-bottom-width: var(--section-border-width)<?php echo esc_html( $important ); ?>;
	border-bottom-style: var(--section-border-style)<?php echo esc_html( $important ); ?>;
	border-color: var(--section-border-color)<?php echo esc_html( $important ); ?>;
}

.with_frm_style .frm_repeat_sec:last-child{
	border-bottom:none<?php echo esc_html( $important ); ?>;
	padding-bottom:0;
}

.with_frm_style .frm_repeat_inline{
	clear:both;
}

.frm_invisible_section .frm_form_field,
.frm_invisible_section{
	display:none !important;
	visibility:hidden !important;
	height:0;
	margin:0;
}

.frm_form_field .frm_repeat_sec .frm_add_form_row,
.frm_section_heading div.frm_repeat_sec:last-child .frm_add_form_row.frm_hide_add_button,
.frm_form_field div.frm_repeat_grid .frm_add_form_row.frm_hide_add_button,
.frm_form_field div.frm_repeat_inline .frm_add_form_row.frm_hide_add_button {
	transition: opacity .15s ease-in-out;
	pointer-events: none;
}

.frm_form_field .frm_repeat_sec .frm_add_form_row,
.frm_section_heading div.frm_repeat_sec:last-child .frm_add_form_row.frm_hide_add_button {
	display: none;
}

.frm_hide_remove_button.frm_remove_form_row {
	display: none !important;
}

.frm_form_field div.frm_repeat_grid .frm_add_form_row.frm_hide_add_button,
.frm_form_field div.frm_repeat_inline .frm_add_form_row.frm_hide_add_button {
	visibility: hidden;
}

.frm_form_field div.frm_repeat_grid .frm_add_form_row,
.frm_form_field div.frm_repeat_inline .frm_add_form_row,
.frm_section_heading div.frm_repeat_sec:last-child .frm_add_form_row {
	display: inline-flex;
	visibility: visible;
	pointer-events: auto;
}

.frm_form_fields .frm_section_heading.frm_hidden {
	display: none;
}
.frm_repeat_buttons a.frm_remove_form_row,
.frm_repeat_buttons a.frm_add_form_row {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	line-height: normal;
}

.frm_repeat_buttons .frmsvg {
	width: 12px;
	height: 12px;
}

.frm_repeat_grid .frm_button,
.frm_repeat_inline .frm_button,
.frm_repeat_sec .frm_button{
	display: inline-block;
	line-height:1;
}

.frm_form_field .frm_repeat_grid ~ .frm_repeat_grid .frm_form_field .frm_primary_label{
	display:none !important;
}

.frm_section_heading.frm_no_border_top h3[class*="frm_pos_"] {
	border-top: none !important;
}

/* Prefix */

.with_frm_style .frm_input_group {
	position: relative;
	display: flex;
	align-items: stretch;
	width: 100%;
}

.with_frm_style .frm_input_group.frm_hidden {
	display: none;
}

.with_frm_style .frm_inline_box {
	display: flex;
	text-align: center;
	align-items: center;
	font-size: var(--field-font-size);
	padding: 0 12px;
	color: var(--form-desc-color);
	border-width: var(--field-border-width);
	border-style: var(--field-border-style);
	border-color: var(--border-color);
	background-color: var(--bg-color-disabled);
	border-radius: var(--border-radius);
	width: auto;
}

.with_frm_style .frm_input_group .frm_inline_box:first-child {
	margin-right: -1px;
	border-top-right-radius: 0 !important;
	border-bottom-right-radius: 0 !important;
}

<?php if ( $use_chosen_js ) { ?>
.with_frm_style .frm_input_group .chosen-container + .frm_inline_box,
<?php } ?>
.with_frm_style .frm_input_group .frm_slimselect + .frm_inline_box,
.with_frm_style .frm_input_group select + .frm_inline_box,
.with_frm_style .frm_input_group .frm_slimselect + .frm_inline_box,
.with_frm_style .frm_input_group input + .frm_inline_box {
	margin-left: -1px;
	border-top-left-radius: 0 !important;
	border-bottom-left-radius: 0 !important;
}

<?php if ( $use_chosen_js ) { ?>
.with_frm_style .frm_input_group .chosen-container,
<?php } ?>
.with_frm_style .frm_input_group .frm_slimselect,
.with_frm_style .frm_input_group > select,
.with_frm_style .frm_input_group > input {
	position: relative;
	flex: 1 1 auto;
	width: 1% !important;
	min-width: 0;
	margin-top: 0;
	margin-bottom: 0;
	display: block;
}

<?php if ( $use_chosen_js ) { ?>
.with_frm_style .frm_input_group.frm_with_pre .chosen-container-multi .chosen-choices,
.with_frm_style .frm_input_group.frm_with_pre .chosen-single,
<?php } ?>
.with_frm_style .frm_input_group.frm_with_pre .frm_slimselect,
.with_frm_style .frm_input_group.frm_with_pre > select,
.with_frm_style .frm_input_group.frm_with_pre > input {
	border-top-left-radius: 0 !important;
	border-bottom-left-radius: 0 !important;
}

<?php if ( $use_chosen_js ) { ?>
.with_frm_style .frm_input_group.frm_with_post .chosen-container-multi .chosen-choices,
.with_frm_style .frm_input_group.frm_with_post .chosen-single,
<?php } ?>
.with_frm_style .frm_input_group.frm_with_post .frm_slimselect,
.with_frm_style .frm_input_group.frm_with_post > select,
.with_frm_style .frm_input_group.frm_with_post > input {
	border-top-right-radius: 0 !important;
	border-bottom-right-radius: 0 !important;
}

/* Custom SlimSelect CSS */
.ss-content.frm_slimselect {
	padding: 0 !important;
}
.with_frm_style .ss-value-delete {
	border-left: none !important;
	padding-left: 0 !important;
}
.with_frm_style .ss-main {
	display:flex !important;
	--ss-font-color: var(--text-color);
	--ss-border-color: var(--border-color);
	--ss-border-radius: var(--border-radius);
	--ss-bg-color: var(--bg-color);
	/* TODO stop hard coding spacing. --field-pad is problematic because it's something like 6px 10px and ss-spacing-m gets used with ss-spacing-s */
	--ss-spacing-s: 6px;
	--ss-spacing-m: 10px;
	--ss-main-height: var(--field-height);
	border-width: var(--fieldset);
	font-size: var(--field-font-size);
	line-height: 1.3;
	align-self: baseline;
	margin-top: 3px; /* This is to account for the difference in vertical alignment for being display: flex. */
	font-family: var(--font)<?php echo esc_html( $important ); ?>;
}
.ss-content.frm_slimselect {
	font-size: var(--field-font-size);
	<?php if ( '' !== $defaults['font'] ) : ?>
	font-family: var(--font);
	<?php endif; ?>
	/* These rules fix conflicts with Bootstrap 3 */
	height: auto;
	display: flex;
}
.with_frm_style .ss-main .ss-values .ss-value .ss-value-text {
	padding: 2px var(--ss-spacing-s);
}
.ss-content.frm_slimselect.frm_slimselect_rtl {
	direction: rtl;
}
.ss-content.frm_slimselect.frm_slimselect_rtl .ss-search input {
	text-align: right;
}
.ss-main.frm_slimselect.frm_slimselect_rtl .ss-value-delete {
	margin-left: var(--ss-spacing-m);
}
.ss-main.frm_slimselect .ss-values .ss-placeholder {
	padding: 0;
}
.frm_fields_container .frm_form_field .ss-main.auto_width {
	width: auto !important;
	display: inline-flex !important;
}
.ss-main.frm_slimselect .ss-arrow {
	margin-left: var(--ss-spacing-s);
	margin-right: 0;
}
.frm_input_group.frm_slimselect_wrapper {
	flex-wrap: nowrap;
}
.frm_input_group.frm_slimselect_wrapper .frm_slimselect {
	border-radius: 0;
}
.frm_input_group.frm_slimselect_wrapper .ss-main.frm_slimselect {
	margin-top: 0;
	height: 1.7rem;
}
/* End SlimSelect CSS */

.with_frm_style .frm_total input,
.with_frm_style .frm_total_big input {
	background-color:transparent;
	border:none;
	width:auto;
	box-shadow: none !important;
}

.with_frm_style .frm_total .frm_inline_box,
.with_frm_style .frm_total_big .frm_inline_box {
	background-color:transparent !important;
	border-width: 0 !important;
	box-shadow:none !important;
	color:var(--text-color);
	padding:0 3px 0 1px !important;
}

.with_frm_style .frm_inline_total {
	padding:0 3px;
}

/* Datepicker */
.flatpickr-calendar,
#ui-datepicker-div {
	background:white;
	position: absolute;
	display:none;
	z-index:999999 !important;
}

<?php
$use_default_date = ( empty( $defaults['theme_css'] ) || 'ui-lightness' === $defaults['theme_css'] );
$arrow_left       = FrmProStylesController::base64_encode_image( FrmProAppHelper::plugin_path() . '/images/arrow-left.svg', 'image/svg+xml' );
?>
.<?php echo esc_html( $datepicker_class ); ?>.flatpickr-calendar,
.<?php echo esc_html( $datepicker_class ); ?>.ui-datepicker {
	z-index: 999999 !important;
	margin-top: 6px;
}
.<?php echo esc_html( $datepicker_class ); ?> .flatpickr-calendar,
.<?php echo esc_html( $datepicker_class ); ?>.flatpickr-calendar,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker, /* Sample form selector */
.<?php echo esc_html( $datepicker_class ); ?>.ui-datepicker {
	box-sizing: border-box;
	min-width: 282px;
	border-radius: var(--border-radius);
	padding: 16px 18px;
	box-shadow: 0px 11.3px 22.6px -5.65px #1018282E;
}
.<?php echo esc_html( $datepicker_class ); ?>.flatpickr-calendar:not(.frm-datepicker-custom-theme),
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker, /* Sample form selector */
.<?php echo esc_html( $datepicker_class ); ?>.ui-datepicker{
	border: 1px solid #F2F4F7;
}

.<?php echo esc_html( $datepicker_class ); ?>.ui-datepicker {
	display: none;
}
.<?php echo esc_html( $datepicker_class ); ?>.flatpickr-calendar.inline {
	max-width: 40em;
}
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker .ui-datepicker-header, /* Sample form selector */
.<?php echo esc_html( $datepicker_class ); ?>.ui-datepicker .ui-datepicker-header {
	padding: 6px 0 12px;
	position: relative;
}
.<?php echo esc_html( $datepicker_class ); ?>.flatpickr-calendar .flatpickr-monthDropdown-months,
.<?php echo esc_html( $datepicker_class ); ?>.flatpickr-calendar .numInputWrapper,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-title select.ui-datepicker-month,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-title select.ui-datepicker-year {
	min-height: unset;
	width: <?php echo esc_html( $use_default_date ? '33' : '45' ); ?>% <?php echo esc_html( $important ); ?>;
	padding: 4px 5px 5px;
	line-height: 14px;
	margin: 0;
}
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-title select.ui-datepicker-month,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-title select.ui-datepicker-year {
	background-color: #fff;
}
.<?php echo esc_html( $datepicker_class ); ?>.flatpickr-calendar:not(.inline) .flatpickr-monthDropdown-months {
	width: 53% <?php echo esc_html( $important ); ?>;
}
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-title select.ui-datepicker-month,
.<?php echo esc_html( $datepicker_class ); ?> select.ui-datepicker-month{
	margin-right: 3px;
}
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-month,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-year {
	max-width: 100%;
	max-height: 2em;
	padding: 6px 10px;
	box-sizing: border-box;
	display: inline;
	color: <?php echo esc_html( $defaults['text_color'] ); ?>;
}
.<?php echo esc_html( $datepicker_class ); ?> span.ui-datepicker-month, .<?php echo esc_html( $datepicker_class ); ?> span.ui-datepicker-year {
	line-height: 25px;
	font-weight: 600;
}

.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-calendar {
	margin: 0 !important;
}

.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-calendar thead {
	color: var(--text-color)<?php echo esc_html( $important ); ?>;
	background-color: var(--bg-color)<?php echo esc_html( $important ); ?>;
}

.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-calendar thead th {
	padding: 8px;
	font-weight: 400;
	font-size: var(--field-font-size);
	color: var(--description-color);
}

.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-prev {
	transform: rotate(0deg) !important;
}
.<?php echo esc_html( $datepicker_class ); ?> .flatpickr-months .flatpickr-next-month,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-next {
	transform: rotate(180deg) !important;
}
.<?php echo esc_html( $datepicker_class ); ?> .flatpickr-months .flatpickr-prev-month svg,
.<?php echo esc_html( $datepicker_class ); ?> .flatpickr-months .flatpickr-next-month svg {
	display: none;
}
.<?php echo esc_html( $datepicker_class ); ?> .flatpickr-months .flatpickr-prev-month:before,
.<?php echo esc_html( $datepicker_class ); ?> .flatpickr-months .flatpickr-next-month:before,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-prev:before,
.<?php echo esc_html( $datepicker_class ); ?> .ui-datepicker-next:before {
	content: '' !important;
	position: absolute;
	top: 0;
	left: 0;
	width: 100% !important;
	height: 100% !important;
	background-color: var(--date-head-color);
	mask: url( <?php echo esc_html( $arrow_left ); ?> ) no-repeat center;
	padding: 0 !important;
}
.<?php echo esc_html( $datepicker_class ); ?>.frm-datepicker-custom-theme .flatpickr-prev-month:before,
.<?php echo esc_html( $datepicker_class ); ?>.frm-datepicker-custom-theme .flatpickr-next-month:before {
	background-color: #fff;
}
.<?php echo esc_html( $datepicker_class ); ?>.frm-date-no-month-select .flatpickr-prev-month,
.<?php echo esc_html( $datepicker_class ); ?>.frm-date-no-month-select .flatpickr-next-month {
	display: none;
}
/* Graphs */
.google-visualization-tooltip-item-list,
.google-visualization-tooltip-item-list .google-visualization-tooltip-item:first-child {
	margin: 1em 0 !important;
}

.google-visualization-tooltip-item {
	list-style-type: none !important;
	margin: 0.65em 0 !important;
}

[id^="chart__frm_pie"] .google-visualization-tooltip {
	pointer-events: none;
}

/* Radio Scale */

.with_frm_style .frm_scale{
	margin-right:15px;
	text-align:center;
	float:left;
}

.with_frm_style .frm_scale input[type=radio]{
	display:block !important;
	margin:0;
}

.with_frm_style .frm_scale input[type=radio]:checked:before {
	transform: scale(1);
}

/* Star ratings */

.frm-star-group {
	white-space: nowrap;
	display: inline-block;
}

.frm-star-group + p {
	display: inline-block;
}

.frm-star-group input {
	opacity: 0;
	position: absolute !important;
	z-index: -1;
}

.frm-show-form .frm-star-group .frmsvg,
.frm-show-form .frm-star-group input + label.star-rating {
	float: none;
	font-size: 20px;
	line-height: 1;
	cursor: pointer;
	background: transparent;
	overflow: hidden !important;
	clear: none;
	font-style: normal;
	vertical-align: top;
	position: relative;
	width: auto;
}

.frm-star-group .frmsvg {
	display: inline-block;
	width: 20px;
	height: 20px;
	fill: #FDB022;
	vertical-align: text-bottom;
}

.frm-show-form .frm-star-group input + label.star-rating {
	display: inline-flex;
	color: transparent;
}

.frm-show-form .frm-star-group input + label.star-rating:before {
	content: '';
}

.frm-star-group input[type=radio]:checked + label:before,
.frm-star-group:not(.frm-star-hovered) input[type=radio]:checked + label:before{
	color:#F0AD4E;
}

.frm-star-group:not(.frm-star-hovered) input[type=radio]:checked + label,
.frm-star-group input + label:hover,
.frm-star-group:hover input + label:hover ,
.frm-star-group .star-rating-on,
.frm-star-group .star-rating-hover {
	color:#F0AD4E;
}

.frm-star-group .star-rating-readonly{
	cursor:default !important;
}

.frm-star-group > svg + svg {
	margin-left: 5px;
}

.frm-star-group .star-rating .frmsvg:last-of-type,
.frm-star-group .star-rating-on .frmsvg:first-of-type,
.frm-star-group .star-rating-hover .frmsvg:first-of-type {
	display: none;
}

.frm-star-group .star-rating-on .frmsvg:last-of-type,
.frm-star-group .star-rating-hover .frmsvg:last-of-type {
	display: inline;
}

/* Other input */
.with_frm_style .frm_other_input.frm_other_full{
	margin-top:10px;
}

.frm_left_container .frm_other_input{
	grid-column:2;
}

.frm_inline_container.frm_other_container .frm_other_input,
.frm_left_container.frm_other_container .frm_other_input{
	margin-left:5px;
}

.frm_right_container.frm_other_container .frm_other_input{
	margin-right:5px;
}

.frm_inline_container.frm_other_container select ~ .frm_other_input,
.frm_right_container.frm_other_container select ~ .frm_other_input,
.frm_left_container.frm_other_container select ~ .frm_other_input{
	margin:0;
}

/* File Upload */

.with_frm_style input[type=file]::-webkit-file-upload-button {
	color: var(--text-color)<?php echo esc_html( $important ); ?>;
	background-color: var(--bg_color)<?php echo esc_html( $important ); ?>;
	padding: var(--field-pad)<?php echo esc_html( $important ); ?>;
	border-radius: var(--border-radius)<?php echo esc_html( $important ); ?>;
	border-color: var(--border-color)<?php echo esc_html( $important ); ?>;
	border-width: var(--field-border-width)<?php echo esc_html( $important ); ?>;
	border-style: var(--field-border-style)<?php echo esc_html( $important ); ?>;
}

/* Pagination */
.frm_pagination_cont ul.frm_pagination{
	display:inline-block;
	list-style:none;
	margin-left:0 !important;
}

.frm_pagination_cont ul.frm_pagination > li{
	display:inline;
	list-style:none;
	margin:2px;
	background-image:none;
}

ul.frm_pagination > li.active a{
	text-decoration:none;
}

.frm_pagination_cont ul.frm_pagination > li:first-child{
	margin-left:0;
}

.archive-pagination.frm_pagination_cont ul.frm_pagination > li{
	margin:0;
}

/* Start Toggle Styling */
.frm_switch_opt {
	padding: 0 8px 0 0;
	white-space: normal;
	display: inline;
	vertical-align: middle;
	font-size: var(--toggle-font-size)<?php echo esc_html( $important ); ?>;
	font-weight: var(--check-weight)<?php echo esc_html( $important ); ?>;
}

.frm_on_label{
	padding:0 0 0 8px;
}

.frm_on_label,
.frm_off_label {
	color: var(--check-label-color)<?php echo esc_html( $important ); ?>;
}

.frm_switch {
	position: relative;
	display: inline-block;
	width: 40px;
	height: 25px;
	vertical-align: middle;
}

.frm_switch_block input {
	display:none !important;
}

.frm_slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: var(--toggle-off-color)<?php echo esc_html( $important ); ?>;
	transition: 0.4s;
	border-radius: 30px;
}

.frm_slider:before {
	border-radius: 50%;
	position: absolute;
	content: "";
	height: 23px;
	width: 23px;
	left: 1px;
	bottom: 1px;
	background-color: white;
	transition: .4s;
	box-shadow:0 2px 6px rgba(41, 58, 82, 0.31);
}

input:checked + .frm_switch .frm_slider {
	background-color: var(--toggle-on-color)<?php echo esc_html( $important ); ?>;
}

input:focus + .frm_switch .frm_slider {
	box-shadow: 0 0 1px #3177c7;
}

input:checked + .frm_switch .frm_slider:before {
	transform: translateX(15px);
}

.frm_rtl .frm_switch_opt {
	padding: 0 8px;
}

.frm_rtl .frm_slider:before {
	left: 16px;
}

.frm_rtl input:checked + .frm_switch .frm_slider:before {
	transform: none!important;
	left: 1px;
}

/* End Toggle */

/* Range slider */

<?php
$bg_color    = '#ccc' . $important;
$thumb_color = '#4199FD' . $important;
$text_color  = '#ffffff' . $important;
?>
.with_frm_style .frm_range_container {
	padding-top: 5px;
}

.with_frm_style input[type=range] {
	-webkit-appearance: none;
	display: block;
	width: 100%;
	height: var(--slider-track-size)<?php echo esc_html( $important ); ?>;
	font-size: var(--description-font-size)<?php echo esc_html( $important ); ?>;
	border-radius: calc(var(--border-radius) / 2)<?php echo esc_html( $important ); ?>;
	margin: 10px 0;
	outline: none;

	/* Add a default background, it will overwrite with JS */
	background: var(--slider-bar-color);
}

.with_frm_style input[type=range]:focus,
.with_frm_style input[type=range]:active {
	background: var(--slider-bar-color);
}

<?php
$thumb_size  = 'height: var(--slider-circle-size)' . $important . ';';
$thumb_size .= 'width: var(--slider-circle-size)' . $important . ';';

$thumb = '
border: 2px solid ' . $thumb_color . ';
color:' . $text_color . ';
background: #fff;
cursor: pointer;
border-radius: 50%;
box-shadow: 0px 4px 8px -2px rgba(16, 24, 40, 0.1);';
?>
.with_frm_style input[type=range]::-webkit-slider-thumb {
	-webkit-appearance: none;
	<?php echo esc_html( $thumb_size . $thumb ); ?>
}

.with_frm_style input[type=range]::-moz-range-thumb {
	<?php echo esc_html( $thumb_size . $thumb ); ?>
}

.with_frm_style input[type=range]::-ms-thumb {
	<?php echo esc_html( $thumb_size . $thumb ); ?>
}

.with_frm_style .frm_range_unit,
.with_frm_style .frm_range_value{
	display:inline-block;
	padding: 0 2px;
}

.with_frm_style [class^="frm-text-"] .frm_range_unit,
.with_frm_style .frm_range_container > .frm_range_unit,
.with_frm_style .frm_range_value {
	font-size: var(--slider-font-size);
	color: var(--text-color)<?php echo esc_html( $important ); ?>;
	font-weight: bold;
}

.with_frm_style .frm_range_max {
	float: right;
}

.with_frm_style .frm_range_container input + .frm_range_value {
	display: block;
}

.frm-text-left {
	text-align: left;
}

.frm-text-center {
	text-align: center;
}

.frm-text-right {
	text-align: right;
}

/* Dropzone */

.with_frm_style .frm_dropzone {
	border-color: var(--border-color)<?php echo esc_html( $important ); ?>;
	border-radius: var(--border-radius)<?php echo esc_html( $important ); ?>;
	color: var(--text-color)<?php echo esc_html( $important ); ?>;
	background-color: var(--bg-color)<?php echo esc_html( $important ); ?>;
}

.with_frm_style .frm_dropzone .frm_upload_icon,
.with_frm_style .frm_dropzone .dz-remove {
	color: var(--description-color)<?php echo esc_html( $important ); ?>;
}

.with_frm_style .frm_compact .frm_dropzone .frm_upload_icon {
	color: var(--submit-text-color)<?php echo esc_html( $important ); ?>;
}

.with_frm_style .frm_compact .frm_dropzone .frmsvg {
	width: 18px;
	height: 18px;
}

.with_frm_style .frm_form_field:not(.frm_compact) .frm_dropzone .frmsvg {
	width: 24px;
	height: 24px;
	display: block;
	margin: 0 auto 8px;
	color: #667085;
}

.with_frm_style .frm_dropzone .frm_remove_link .frmsvg {
	width: 18px;
	height: 18px;
	cursor: pointer;
}

.with_frm_style .frm_blank_field .frm_dropzone {
	border-color: var(--border-color-error)<?php echo esc_html( $important ); ?>;
	color: var(--text-color-error)<?php echo esc_html( $important ); ?>;
	background-color: var(--bg-color-error)<?php echo esc_html( $important ); ?>;
}


.with_frm_style .frm_dropzone .dz-preview .dz-progress {
	background: var(--progress-bg-color)<?php echo esc_html( $important ); ?>;
}

.with_frm_style .frm_dropzone .dz-preview .dz-progress .dz-upload,
.with_frm_style .frm_dropzone .dz-preview.dz-complete .dz-progress {
	background: var(--progress-active-bg-color)<?php echo esc_html( $important ); ?>;
}

/**
 * Radio Button and Checkbox Images
 */

.frm_image_size_medium {
	--image-size:250px;
}

.frm_image_size_large {
	--image-size:320px;
}

.frm_image_size_xlarge {
	--image-size:400px;
}

.frm_image_options .frm_opt_container {
	display: inline-flex;
	flex-flow: wrap;
	flex-direction:row;
	margin: 0 -10px;
}

.frm_image_options .frm_radio input[type=radio],
.frm_image_options .frm_checkbox input[type=checkbox]{
	position: absolute !important;
	top: 9px;
	right: 10px;
	z-index: 2;
	margin: 0;
}

.frm_image_options .frm_image_option_container {
	border-width: var(--field-border-width);
	border-style: solid;
	border-color: var(--border-color);
	border-radius: var(--border-radius);
	display: flex;
	flex-wrap: wrap;
	box-sizing: border-box;
	position: relative;
	height: 100%;
	width: 100%;
	overflow: hidden;
}

.frm_image_options .frm_image_option_container.frm_label_with_image .frm_empty_url,
.frm_image_options .frm_image_option_container.frm_label_with_image img {
	border-bottom-left-radius:0;
	border-bottom-right-radius:0;
}

.with_frm_style .vertical_radio.frm_image_options .frm_image_option > label {
	text-indent: 0;
	padding-left: 0;
}

.frm_show_images.frm_image_option_container {
	display: inline-flex;
	flex-wrap: nowrap;
	flex-direction: column;
	text-align: center;
	align-items: center;
	width: 150px;
	margin-right: 10px;
	margin-bottom: 10px;
}

.frm-summary-page-wrapper .frm_image_option_container img{
	width: 100%;
	height: 150px;
	object-fit: cover;
}

<?php // Hide the checkmark for now, can be removed in future. ?>
.frm_image_option_container .frm_selected_checkmark{
	display: none;
}

.frm_image_option_container .frm_image_placeholder_icon {
	position: absolute;
}

.frm_image_option_container .frm_image_placeholder_icon svg{
	width: 63px;
	opacity: .2;
}

.frm_text_label_for_image {
	font-size: var(--description-font-size);
	color: var(--description-color);
	text-align: center;
	width: 100%;
	padding: 10px;
	word-break: keep-all;
}

.frm_image_options input[type="radio"]:not([disabled]) + .frm_image_option_container:hover,
.frm_image_options input[type="checkbox"]:not([disabled]) + .frm_image_option_container:hover,
input[type="radio"]:checked + .frm_image_option_container,
input[type="checkbox"]:checked + .frm_image_option_container {
	border-color: var(--border-color-active);
}

input[type="radio"]:disabled + .frm_image_option_container,
input[type="checkbox"]:disabled + .frm_image_option_container{
	opacity: .7;
	border-color:var(--border-color-disabled);
}

.frm_blank_field.frm_image_options .frm_image_option_container {
	border-color: var(--border-color-error);
}

.frm_image_options .frm_image_option_container .frm_empty_url,
.frm_image_options .frm_image_option_container img {
	width: 100%;
	height: 150px;
	height: var(--image-size);
	object-fit: cover;
	border-radius:var(--border-radius);
}

.frm_image_option_container .frm_empty_url {
	background: <?php echo esc_html( FrmStylesHelper::adjust_brightness( $defaults['border_color'], 45 ) ); ?>;
	display: flex;
	justify-content: center;
	align-items: center;
}

.horizontal_radio .frm_checkbox.frm_image_option,
.horizontal_radio .frm_radio.frm_image_option {
	padding-left: 0;
}

.frm_checkbox.frm_image_option,
.frm_radio.frm_image_option {
	width:var(--image-size) !important; /* Overrides grid classes */
	position: relative;
}

.frm_form_field .frm_checkbox.frm_image_option,
.frm_form_field .frm_checkbox.frm_image_option + .frm_checkbox,
.frm_form_field .frm_radio.frm_image_option,
.frm_form_field .frm_radio.frm_image_option + .frm_radio {
	margin:10px; /* Override for inline options */
}

.frm_checkbox.frm_image_option label,
.frm_radio.frm_image_option label{
	padding-left: 0;
	margin-left: 0;
	min-height: 0;
	visibility: visible<?php echo esc_html( $important ); ?>; /* Overrides grid classes */
}

/**
 * Background image
 */
.frm_with_bg_image .frm_form_fields > fieldset {
	position: relative;
}

.frm_with_bg_image .frm_form_fields > fieldset:before {
	content: ' ';
	display: block;
	position: absolute;
	top: 0;
	height: 100%;
	background-position: 50% 0;
	left: 0;
	width: 100%;
	background-image: var(--bg-image-url);
	background-repeat: no-repeat;
	background-size: cover;
}

.frm_with_bg_image .frm_form_fields > fieldset > *:not(.frm_screen_reader) {
	z-index: 1;
	position: relative;
}

/**
 * Password strength meter CSS
 */

@media screen and (max-width: 768px) {
	.frm-pass-req, .frm-pass-verified {
		width: 50% !important;
		white-space: nowrap;
	}
}

.frm-pass-req, .frm-pass-verified {
	display: flex;
	align-items: center;
	float: left;
	width: 20%;
	line-height: 20px;
	font-size: 12px;
	padding-top: 4px;
	min-width: 175px;
}

.frm-pass-req .frmsvg, .frm-pass-verified .frmsvg {
	width: 12px;
	height: 12px;
	padding-right: 4px;
}

.passed_svg, .failed_svg {
	display: none !important;
}
.frm-pass-verified .passed_svg,
.frm-pass-req .failed_svg {
	display: inline-block !important;
}

div.frm-password-strength {
	width: 100%;
	float: left;
}

.frm_show_password_wrapper {
	position: relative;
	display: inline-block;
}

.frm_show_password_wrapper input[type=password],
.frm_show_password_wrapper input[type=text] {
	padding-right: 44px; /* Avoid overlapping password text with eye icon. */
	display: block;
}

.frm_show_password_wrapper button {
	position: absolute;
	top: 50%;
	height: 32px;
	height: var(--field-height);
	right: 0;
	border: 0;
	background: transparent !important;
	cursor: pointer;
	transform: translateY(-50%);
	padding: 0 10px;
	color: #BFC3C8;
	color: var(--border-color);
	display: flex;
	align-items: center;
}

.frm_show_password_wrapper button:hover {
	color: #BFC3C8;
	color: var(--border-color);
	background: transparent !important;
}

.frm_show_password_wrapper button svg {
	width: 24px;
	height: 24px;
}

input[type="text"] + .frm_show_password_btn svg:first-child,
input[type="password"] + .frm_show_password_btn svg:last-child {
	display: none;
}

div.frm_repeat_grid:after, div.frm_repeat_inline:after, div.frm_repeat_sec:after {
	content: '';
	display: table;
	clear: both;
}

.with_frm_style .frm-summary-page-wrapper {
	padding: 50px;
	margin: 25px 0 50px;
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius);
}

.with_frm_style .frm-summary-page-wrapper .frm-edit-page-btn {
	float: right;
	margin: 0;
	padding: 3px 10px;
	font-size: 13px;
}

.frm-summary-page-wrapper .frm-line-table th {
	width: 40%;
}

button .frm-icon {
	display: inline-block;
	color: inherit;
	width: 12px;
	height: 12px;
	fill: currentColor;
}

.frm-line-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 0.5em;
	font-size: var(--font-size);
}

.frm-line-table tr {
	background-color: transparent;
	border-bottom: 1px solid rgba(<?php echo esc_html( FrmStylesHelper::hex2rgb( $defaults['border_color'] ) ); ?>,0.6);
}

.frm-line-table td,
.frm-line-table th {
	border: 0;
	padding: 20px 15px;
	background-color: transparent;
	vertical-align: top;
	color: var(--label-color);
}

.frm-line-table th {
	opacity: .7;
	font-size: 1.1em;
	font-weight: 500;
}

.frm-line-table h3 {
	font-size: 21px;
	font-weight: 500;
	margin: 0;
}

.frm_form_field .frm_total_formatted {
	display: inline-block;
	margin: 5px 0 0;
}

.frm_form_field.frm_total_big .frm_total_formatted {
	margin: 0;
}

.frm_form_field.frm_total_big .frm_total_formatted,
.frm_form_field.frm_total_big input[type=text],
.frm_form_field.frm_total_big input[type=number],
.frm_form_field.frm_total_big input,
.frm_form_field.frm_total_big textarea{
	font-size: 32px;
	font-weight: bold;
	line-height: 44px;
}

/* Views */

.frm_round{
	border-radius:50%;
}

.frm_round.frm_color_block{
	padding:3px;
}

.frm_square {
	border-radius:var(--border-radius);
	object-fit:cover;
	width:150px;
	height:150px;
}

.frmsvg{
	max-width:100%;
	fill:currentColor;
	vertical-align:sub;
	display:inline-block;
}

.frm_smaller{
	font-size:90%;
}

.frm_small{
	font-size:14px;
	font-weight:normal;
}

.frm_bigger{
	font-size:110%;
}

ul.frm_plain_list,
ul.frm_plain_list li{
	list-style:none<?php echo esc_html( $important ); ?>;
	list-style-type:none<?php echo esc_html( $important ); ?>;
	margin-left:0<?php echo esc_html( $important ); ?>;
	margin-right:0<?php echo esc_html( $important ); ?>;
	padding-left:0;
	padding-right:0;
}

ul.frm_inline_list li{
	display:inline;
	padding:2px;
}

.frm_flex,
.frm_full_row{
	display:flex;
	flex-direction:row;
	flex-wrap:wrap;
}

.frm_full_row > li,
.frm_full_row > div{
	flex:1;
	text-align:center;
}

.frm_tiles > li,
.frm_tiles > div {
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius);
	margin-top: 20px;
	padding: 25px;
<?php if ( isset( $defaults['box_shadow'] ) && $defaults['box_shadow'] !== 'none' ) { ?>
	box-shadow:0 0 5px 1px rgba(0,0,0,0.075);
<?php } ?>
}

/* Repeater Fields */

.with_frm_style .frm_repeat_sec .frm_form_field.frm_repeat_buttons svg.frm-svg-icon {
	fill: var(--repeat-icon-color)<?php echo esc_html( $important ); ?>;
}
.with_frm_style .frm_remove_form_row:hover,
.with_frm_style .frm_add_form_row:hover {
	border-color: var(--submit-hover-border-color);
	color: var(--submit-hover-color);
}
.with_frm_style .frm_remove_form_row.frm_button:hover,
.with_frm_style .frm_add_form_row.frm_button:hover {
	background: var(--submit-hover-bg-color);
}
.with_frm_style .frm_form_field.frm_repeat_buttons .frm_add_form_row:hover svg.frm-svg-icon,
.with_frm_style .frm_repeat_sec .frm_form_field.frm_repeat_buttons .frm_remove_form_row:hover svg.frm-svg-icon,
.with_frm_style .frm_repeat_sec .frm_form_field.frm_repeat_buttons .frm_add_form_row:hover svg.frmsvg,
.with_frm_style .frm_repeat_sec .frm_form_field.frm_repeat_buttons .frm_remove_form_row:hover svg.frmsvg {
	fill: var(--submit-hover-color);
}
/* End Repeater Fields */

.frm_tiles h3{
	margin-top:5px;
}

/* Submit Button Position */

.frm_forms.frm_full_submit .frm_submit button {
	width: 100%<?php echo esc_html( $important ); ?>;
}

.frm_forms.frm_full_submit .frm_submit.frm_flex button {
	width: auto<?php echo esc_html( $important ); ?>;
	flex: 1;
}

/* Look ups */
select.frm_loading_lookup[multiple="multiple"] {
	background-image: none !important;
}

/* Draft buttons */
.with_frm_style .frm_submit button.frm_save_draft {
	color: var(--submit-bg-color) <?php echo esc_html( $important ); ?>;
	background: var(--submit-text-color) <?php echo esc_html( $important ); ?>;
	border-color: var(--submit-bg-color) <?php echo esc_html( $important ); ?>;
}

/* Slide in */
.frm_slidein .frm_form_fields  > fieldset{
	animation-name: frmSlideInRight;
	animation-duration: 1s;
}

.frm_slidein.frm_going_back .frm_form_fields  > fieldset {
	animation-name: frmSlideInLeft;
}

.frm_slidein.frm_slideout .frm_form_fields  > fieldset {
	animation-name: frmSlideOutLeft !important;
}

.frm_slidein.frm_slideout.frm_going_back .frm_form_fields  > fieldset {
	animation-name: frmSlideOutRight !important;
}

.frm_slidein .frm-g-recaptcha .grecaptcha-badge{
	animation-name: fadeIn;
	animation-duration: 2s;
	animation-fill-mode: both;
}

@keyframes frmSlideInLeft {
	0% {
		opacity: 0;
		transform: translate3d(-3000px, 0, 0);
	}
	100% {
		opacity: 1;
		transform: none;
	}
}

@keyframes frmSlideInRight {
	0% {
		opacity: 0;
		transform: translate3d(3000px, 0, 0);
	}
	100% {
		opacity: 1;
		transform: none;
	}
}

@keyframes frmSlideOutLeft {
	0% {
		opacity: 1;
		transform: none;
	}
	100% {
		opacity: 0;
		transform: translate3d(-2000px, 0, 0);
	}
}

@keyframes frmSlideOutRight {
	0% {
		opacity: 1;
		transform: none;
	}
	100% {
		opacity: 0;
		transform: translate3d(2000px, 0, 0);
	}
}

/* Slide Up */
.frm_slideup .frm_form_fields  > fieldset {
	animation-name: frmSlideDown;
	animation-duration: 1s;
	animation-fill-mode: both;
}

.frm_slideup.frm_going_back .frm_form_fields  > fieldset {
	animation-name: frmSlideUp;
}

.frm_slideup.frm_slideout .frm_form_fields  > fieldset {
	animation-name: frmSlideOutUp !important;
}

.frm_slideup.frm_slideout.frm_going_back .frm_form_fields  > fieldset {
	animation-name: frmSlideOutDown !important;
}

@keyframes frmSlideUp {
	0% {
		opacity: 0;
		transform: translate3d(0, -200px, 0);
	}
	100% {
		opacity: 1;
		transform: none;
	}
}

@keyframes frmSlideDown {
	0% {
		opacity: 0;
		transform: translate3d(0, 200px, 0);
	}
	100% {
		opacity: 1;
		transform: none;
	}
}

@keyframes frmSlideOutUp {
	0% {
		opacity: 1;
		transform: none;
	}
	100% {
		opacity: 0;
		transform: translate3d(0, -200px, 0);
	}
}

@keyframes frmSlideOutDown {
	0% {
		opacity: 1;
		transform: none;
	}
	100% {
		opacity: 0;
		transform: translate3d(0, 200px, 0);
	}
}