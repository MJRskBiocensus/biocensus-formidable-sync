<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<?php if ( empty( $field['repeat'] ) ) { ?>
<p>
	<?php include FrmProAppHelper::plugin_path() . '/classes/views/frmpro-fields/collapsible-option.php'; ?>
</p>
<?php } ?>

<input type="hidden" id="frm_repeat_field_<?php echo absint( $field['id'] ); ?>" name="field_options[repeat_<?php echo absint( $field['id'] ); ?>]" class="frm_repeat_field" value="<?php echo esc_attr( $field['repeat'] ); ?>" />
