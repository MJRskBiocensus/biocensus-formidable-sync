<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p class="frm_grid_container">
	<label for="lookup_saved_value_<?php echo absint( $field['id'] ); ?>" class="frm6">
		<?php esc_html_e( 'Saved Value', 'formidable-pro' ); ?>
		<select name="field_options[lookup_saved_value_<?php echo absint( $field['id'] ); ?>]" id="lookup_saved_value_<?php echo absint( $field['id'] ); ?>">
			<option value="value" <?php selected( $field['lookup_saved_value'], 'value' ); ?>><?php esc_html_e( 'Option Value', 'formidable-pro' ); ?></option>
			<option value="label" <?php selected( $field['lookup_saved_value'], 'label' ); ?>><?php esc_html_e( 'Option Label', 'formidable-pro' ); ?></option>
		</select>
	</label>
	<label for="lookup_displayed_value_<?php echo absint( $field['id'] ); ?>" class="frm6">
		<?php esc_html_e( 'Displayed Value', 'formidable-pro' ); ?>
		<select name="field_options[lookup_displayed_value_<?php echo absint( $field['id'] ); ?>]" id="lookup_displayed_value_<?php echo absint( $field['id'] ); ?>">
			<option value="value" <?php selected( $field['lookup_displayed_value'], 'value' ); ?>><?php esc_html_e( 'Option Value', 'formidable-pro' ); ?></option>
			<option value="label" <?php selected( $field['lookup_displayed_value'], 'label' ); ?>><?php esc_html_e( 'Option Label', 'formidable-pro' ); ?></option>
		</select>
	</label>
</p>
