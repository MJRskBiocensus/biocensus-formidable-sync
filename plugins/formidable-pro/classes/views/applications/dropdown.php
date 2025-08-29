<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p><label for="form-applications"><?php esc_html_e( 'Include form into applications', 'formidable-pro' ); ?></label></p>
<select class="frm_multiselect" name="form_applications[]" id="form-applications" multiple="multiple">
	<?php
	foreach ( $application_ids as $application ) {
		?>
		<option value="<?php echo esc_attr( $application['termId'] ); ?>" <?php FrmProAppHelper::selected( $selected_application_ids, $application['termId'] ); ?>><?php echo esc_html( $application['name'] ); ?></option>
		<?php
	}
	?>
</select>
