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
<p class="frm_form_field hide_save_draft">
	<label for="frm_auto_save_draft">
		<input type="checkbox" name="options[auto_save_draft]" id="frm_auto_save_draft" value="1" <?php checked( $values['auto_save_draft'], 1 ); ?> />
		<?php esc_html_e( 'Save drafts automatically', 'formidable-abandonment' ); ?>
	</label>
</p>

<p class="frm4 frm_form_field frm_indent_opt hide_save_draft">
	<span class="frm_primary_label">
		<?php esc_html_e( 'Create a save and resume email', 'formidable-abandonment' ); ?>
	</span>
</p>
<p class="frm8 frm_form_field hide_save_draft">
	<a href="javascript:void(0)" class="button frm-button-secondary" id="abd-draft-email-action" >
		<?php esc_html_e( 'Create new email notification', 'formidable-abandonment' ); ?>
	</a>
</p>
