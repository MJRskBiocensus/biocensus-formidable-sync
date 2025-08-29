<?php
/**
 * Date calculation settings box
 *
 * @package formidable-dates
 * @since 2.0
 *
 * @var array $field Field array.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<div class="frm_grid_container">
	<p class="frm_form_field">
		<label for="frm_date_calc_<?php echo esc_attr( $field['id'] ); ?>"><?php esc_html_e( 'Start date', 'frmdates' ); ?></label>
		<span class="frm-with-right-icon">
			<?php
			FrmProAppHelper::icon_by_class(
				'frm_icon_font frm_more_horiz_solid_icon frm_dates_show_calc_start_shortcodes_box frm-show-inline-modal',
				array(
					'data-open' => 'frm_dates_shortcodes_box',
					'data-fid'  => $field['id'],
				)
			);
			?>
			<input
				type="text"
				id="frm_date_calc_<?php echo esc_attr( $field['id'] ); ?>"
				name="field_options[date_calc_<?php echo esc_attr( $field['id'] ); ?>]"
				value="<?php echo esc_attr( $field['date_calc'] ); ?>"
			/>
		</span>
	</p>

	<p class="frm_form_field">
		<label for="frm_date_calc_diff_<?php echo esc_attr( $field['id'] ); ?>">
			<?php
			esc_html_e( 'Date Difference', 'frmdates' );
			FrmDatesAppController::show_svg_tooltip( __( 'Difference between start date and desired date. Can be in days, weeks, months, or years.', 'frmdates' ), array( 'data-placement' => 'right' ) );
			?>
		</label>
		<span class="frm-with-right-icon">
			<?php
			FrmProAppHelper::icon_by_class(
				'frm_icon_font frm_more_horiz_solid_icon frm_dates_show_calc_diff_shortcodes_box frm-show-inline-modal',
				array(
					'data-open' => 'frm_dates_shortcodes_box',
					'data-fid'  => $field['id'],
				)
			);
			?>
			<input
				type="text"
				id="frm_date_calc_diff_<?php echo esc_attr( $field['id'] ); ?>"
				name="field_options[date_calc_diff_<?php echo esc_attr( $field['id'] ); ?>]"
				value="<?php echo esc_attr( $field['date_calc_diff'] ); ?>"
			/>
		</span>
	</p>
</div>

<p class="howto frm_no_bottom_margin">
	<?php esc_html_e( 'Dynamically set the value of this field from another date.', 'frmdates' ); ?>
</p>

<?php
FrmDatesCalculationHelper::maybe_print_shortcodes_modal( $field );
