<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * @since 6.17
 *
 * @var array $field
 */
?>
<label for="frm_slide_field_<?php echo absint( $field['id'] ); ?>" class="frm_inline_label frm-mb-sm">
	<input type="checkbox" id="frm_slide_field_<?php echo absint( $field['id'] ); ?>" name="field_options[slide_<?php echo absint( $field['id'] ); ?>]" value="1" <?php checked( $field['slide'], 1 ); ?>/>
	<?php esc_html_e( 'Collapsible', 'formidable-pro' ); ?>
	<?php FrmProAppHelper::tooltip_icon( __( 'Collapsible: This section will slide open and closed.', 'formidable-pro' ) ); ?>
</label>
