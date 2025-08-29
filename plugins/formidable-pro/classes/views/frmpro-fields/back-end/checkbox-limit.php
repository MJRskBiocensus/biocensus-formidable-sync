<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p class="frm6 frm_form_field frm_first">
	<label>
		<?php esc_html_e( 'Selection Minimum', 'formidable-pro' ); ?>
		<?php FrmProAppHelper::tooltip_icon( __( 'The minimum number of options in this field that the end user is allowed to select', 'formidable-pro' ), array( 'data-placement' => 'right' ) ); ?>
	</label>
	<input type="number" class="frm_js_checkbox_limit" name="field_options[min_selections_<?php echo absint( $field['id'] ); ?>]" value="<?php
		if ( isset( $field['min_selections'] ) ) {
			echo esc_attr( $field['min_selections'] );
		}
		?>" size="3" min="0" step="1" max="999" />
</p>

<p class="frm6 frm_form_field frm_last">
	<label>
		<?php esc_html_e( 'Selection Maximum', 'formidable-pro' ); ?>
		<?php FrmProAppHelper::tooltip_icon( __( 'The maximum number of options in this field that the end user is allowed to select', 'formidable-pro' ), array( 'data-placement' => 'right' ) ); ?>
	</label>
	<input type="number" class="frm_js_checkbox_limit" name="field_options[limit_selections_<?php echo absint( $field['id'] ); ?>]" value="<?php
		if ( isset( $field['limit_selections'] ) ) {
			echo esc_attr( $field['limit_selections'] );
		}
		?>" size="3" min="2" step="1" max="999" />
</p>
