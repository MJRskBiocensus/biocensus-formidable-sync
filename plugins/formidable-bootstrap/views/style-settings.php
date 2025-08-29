<div class="menu-settings">

<h3><?php esc_html_e( 'Bootstrap Styling', 'frmbtsp' ); ?></h3>
<p>
	<label for="frm_btsp_css"><?php esc_html_e( 'Bootstrap styling', 'frmbtsp' ); ?></label>
	<select id="frm_btsp_css" name="frm_btsp_css">
		<option value="all" <?php selected( $frm_settings->btsp_css, 'all' ); ?>><?php esc_html_e( 'load on every page', 'frmbtsp' ); ?></option>
		<option value="dynamic" <?php selected( $frm_settings->btsp_css, 'dynamic' ); ?>><?php esc_html_e( 'only load on applicable pages', 'frmbtsp' ); ?></option>
		<option value="none" <?php selected( $frm_settings->btsp_css, 'none' ); ?>><?php esc_html_e( 'do not load', 'frmbtsp' ); ?></option>
	</select>
</p>
<p>
	<label for="frm_btsp_version"><?php esc_html_e( 'Bootstrap version', 'frmbtsp' ); ?></label>
	<select id="frm_btsp_version" name="frm_btsp_version">
		<option value="3" <?php selected( $frm_settings->btsp_version, '3' ); ?>><?php esc_html_e( '3.3.7', 'frmbtsp' ); ?></option>
		<option value="5" <?php selected( $frm_settings->btsp_version, '5' ); ?>><?php esc_html_e( '5.0.2', 'frmbtsp' ); ?></option>
	</select>
</p>

</div>
