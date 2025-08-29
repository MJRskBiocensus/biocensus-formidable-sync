<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * @since 6.20
 *
 * @var array $field
 */
?>
<p class="frm_form_field">
	<label class="frm_primary_label frm-mb-0">
		<?php esc_html_e( 'Value Position', 'formidable-pro' ); ?>
		<select name="field_options[value_position_<?php echo esc_attr( $field['id'] ); ?>]">
			<option value="top-left" <?php selected( $value_position, 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'formidable-pro' ); ?></option>
			<option value="top-center" <?php selected( $value_position, 'top-center' ); ?>><?php esc_html_e( 'Top Center', 'formidable-pro' ); ?></option>
			<option value="top-right" <?php selected( $value_position, 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'formidable-pro' ); ?></option>
			<option value="bottom-left" <?php selected( $value_position, 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'formidable-pro' ); ?></option>
			<option value="bottom-center" <?php selected( $value_position, 'bottom-center' ); ?>><?php esc_html_e( 'Bottom Center', 'formidable-pro' ); ?></option>
			<option value="bottom-right" <?php selected( $value_position, 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'formidable-pro' ); ?></option>
		</select>
	</label>
</p>
<p class="frm_form_field frm-mb-0">
	<label class="frm_primary_label frm-mb-0">
		<input type="checkbox" value="1" name="field_options[show_slider_range_<?php echo esc_attr( $field['id'] ); ?>]" <?php checked( $show_range_checked ); ?> />
		<?php esc_html_e( 'Show range on slider', 'formidable-pro' ); ?>
	</label>
</p>
