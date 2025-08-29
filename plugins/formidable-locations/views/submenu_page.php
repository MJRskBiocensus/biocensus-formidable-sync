<div class="wrap">
	<div id="icon-options-general" class="icon32"><br></div>
	<h2><?php esc_html_e( 'Locations', 'formidable' ); ?></h2>

	<div class="postbox">
		<div class="inside with_frm_style">
			<form method="post" id="frm_import_locations">
				<input type="hidden" name="frm_action" value="frm_import_locations" />
				<?php wp_nonce_field( 'import-locations-nonce', 'import-locations' ); ?>

				<label><?php esc_html_e( 'Which locations would you like to import?', 'formidable' ); ?></label><br/>

				<select name="frm_import_files">
				<?php
				foreach ( $import_options as $o => $opt ) {
					?>
					<option value="<?php echo esc_attr( $o ); ?>">
						<?php echo esc_html( $opt ); ?>
					</option>
					<?php
				}
				?>
				</select>

				<p class="submit">
					<input type="submit" value="<?php esc_attr_e( 'Import Selection', 'formidable' ); ?>" class="button-primary" />
				</p>
			</form>

			<a href="<?php echo esc_url( $reset_link ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete your locations forms and data?', 'formidable' ) ); ?>')">
				<?php esc_html_e( 'Reset Locations', 'formidable' ); ?>
			</a>
		</div>
	</div>
</div>
