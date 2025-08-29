<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p class="howto">
	<?php esc_html_e( 'Remove references to Formidable Forms to provide an unbranded experience for your clients.', 'formidable-pro' ); ?>
</p>
<p>
	<label for="frm_menu" class="frm_left_label"><?php esc_html_e( 'Plugin Label', 'formidable-pro' ); ?></label>
	<input type="text" name="frm_menu" id="frm_menu" value="<?php echo esc_attr( $frm_settings->menu ); ?>" />
	<?php if ( is_multisite() && current_user_can( 'setup_network' ) ) { ?>
		<label for="frm_mu_menu">
			<input type="checkbox" name="frm_mu_menu" id="frm_mu_menu" value="1" <?php checked( $frm_settings->mu_menu, 1 ); ?> />
			<?php esc_html_e( 'Use this menu name site-wide', 'formidable-pro' ); ?>
		</label>
	<?php } ?>
</p>

<p class="frm_insert_form frm_form_field">
	<?php
	FrmProHtmlHelper::admin_toggle(
		'frm_menu_icon',
		'frm_menu_icon',
		array(
			'checked'     => ! empty( $frmpro_settings->menu_icon ),
			'echo'        => true,
			'show_labels' => true,
			'value'       => 'frm_white_label_icon',
			'on_label'    => __( 'Remove Logo', 'formidable-pro' ),
		)
	);
	?>
</p>
<p id="frm_hide_dashboard_videos_wrapper" class="<?php echo '' === $frmpro_settings->menu_icon || FrmAddonsController::is_license_expired() ? 'frm_hidden' : ''; ?> ">
	<label for="frm_hide_dashboard_videos">
		<input type="checkbox" value="1" <?php checked( $frmpro_settings->hide_dashboard_videos, 1 ); ?> id="frm_hide_dashboard_videos" name="frm_hide_dashboard_videos" style="margin-top:0;">
		<?php esc_html_e( 'Do not show Formidable videos on dashboard', 'formidable-pro' ); ?>
	</label>
</p>
