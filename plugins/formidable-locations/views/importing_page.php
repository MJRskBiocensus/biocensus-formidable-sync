<div class="wrap">
	<div class="frmicon icon32"><br/></div>
	<h2><?php esc_html_e( 'Locations', 'formidable' ); ?></h2>

	<?php include FrmAppHelper::plugin_path() . '/classes/views/shared/errors.php'; ?>
	<div id="poststuff" class="metabox-holder">
		<div id="post-body">
			<div id="post-body-content">
				<div class="postbox ">
					<h3 class="hndle"><span><?php esc_html_e( 'Importing Locations', 'formidable' ); ?></span></h3>
					<div class="inside">
						<div class="with_frm_style" id="frm_import_message"><span class="frm_message" style="padding:7px;"><?php printf( esc_html__( '%1$s entries are importing', 'formidable' ), '<span class="frm_csv_remaining">' . absint( $remaining ) . '</span>' ); ?></span></div>

						<div class="frm_progress">
							<div class="frm_progress_bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $remaining ); ?>" style="width:0%;">
							</div>
						</div>
						<!-- what about nonce? -->
						<script>
							function frmImportLocationsCsv() {
								jQuery.ajax({
									type: "POST",
									url: ajaxurl,
									data: 'action=frm_import_locations_csv&frm_skip_cookie=1&data_to_import=<?php echo esc_js( $data_to_import ); ?>',
									success: imported => {
										const max       = jQuery( '.frm_progress_bar' ).attr( 'aria-valuemax' );
										const percent   = ( imported / max ) * 100;
										const remaining = max - imported;
										jQuery('.frm_progress_bar').css( 'width', percent +'%' ).attr( 'aria-valuenow', imported );
										if ( parseInt( remaining ) > 0 ) {
											jQuery( '.frm_csv_remaining' ).text( remaining );
											frmImportLocationsCsv();
										} else {
											jQuery( document.getElementById( 'frm_import_message' ) ).text( 'Import complete' );
											setTimeout( () => location.href = '?page=formidable', 2000 );
										}
									}
								});	
							}
							jQuery( document ).ready( frmImportLocationsCsv );
						</script>						
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
