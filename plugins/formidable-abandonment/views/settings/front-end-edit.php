<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Formidable abandonment settings.
 *
 * @var array $values Settings data.
 *
 * @package formidable-abandonment
 */
?>
<p class="frm4 frm_form_field frm_indent_opt hide_editable<?php echo esc_attr( $values['editable'] ? '' : ' frm_hidden' ); ?>">
	<span class="frm_primary_label">
		<?php esc_html_e( 'Create an edit link email', 'formidable-abandonment' ); ?>
	</span>
</p>
<p class="frm8 frm_form_field hide_editable<?php echo esc_attr( $values['editable'] ? '' : ' frm_hidden' ); ?>">
	<a href="javascript:void(0)" class="button frm-button-secondary" id="abd-edit-link-email-action" >
		<?php esc_html_e( 'Create new email notification', 'formidable-abandonment' ); ?>
	</a>
</p>
