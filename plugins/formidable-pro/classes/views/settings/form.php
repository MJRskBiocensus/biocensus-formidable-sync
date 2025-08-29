<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p class="frm_grid_container">
	<label for="frm_date_format" class="frm4 frm_form_field">
		<?php esc_html_e( 'Date Format', 'formidable-pro' ); ?>
		<?php FrmProAppHelper::tooltip_icon( __( 'Change the format of the date used in the date field.', 'formidable-pro' ) ); ?>
	</label>
	<?php $formats = array_keys( FrmProAppHelper::display_to_datepicker_format() ); ?>
	<select id="frm_date_format" name="frm_date_format" class="frm8 frm_form_field">
		<?php foreach ( $formats as $f ) { ?>
			<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $frmpro_settings->date_format, $f ); ?>>
				<?php echo esc_html( $f . ' &nbsp; &nbsp; ' . gmdate( $f ) ); ?>
			</option>
		<?php } ?>
	</select>
</p>

<p class="frm_grid_container">
	<label for="frm_datepicker_library" class="frm4 frm_form_field">
		<?php esc_html_e( 'Date Picker Library', 'formidable-pro' ); ?>
	</label>
	<select id="frm_datepicker_library" name="frm_datepicker_library" class="frm8 frm_form_field">
		<?php
			foreach ( $datepicker_libraries as $key => $name ) {
				FrmProHtmlHelper::echo_dropdown_option( $name, $frmpro_settings->datepicker_library === $key, array( 'value' => $key ) );
			}
		?>
	</select>
</p>
