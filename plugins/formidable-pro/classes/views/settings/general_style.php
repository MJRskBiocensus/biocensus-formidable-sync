<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p>
	<label for="frm_fade_form">
		<input type="checkbox" value="1" id="frm_fade_form" name="frm_fade_form" <?php checked( $frm_settings->fade_form, 1 ); ?> />
		<?php esc_html_e( 'Fade in forms with conditional logic on page load', 'formidable-pro' ); ?>
		<?php FrmProAppHelper::tooltip_icon( __( 'If your form is near the top of the page, you may see a flash of the fields hidden with conditional logic. Check this box to fade in the whole form. Note: If you have javascript errors on your page, your form will remain hidden on the page.', 'formidable-pro' ) ); ?>
	</label>
</p>
