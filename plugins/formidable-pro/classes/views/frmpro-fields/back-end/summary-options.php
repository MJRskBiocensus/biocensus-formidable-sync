<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p class="frm-has-modal">
	<label for="frm_calc_<?php echo esc_attr( $field['id'] ); ?>">
		<?php esc_html_e( 'Exclude Fields from Summary', 'formidable-pro' ); ?>
	</label>
	<span class="frm-with-right-icon">
		<?php
		FrmProAppHelper::icon_by_class(
			'frm_icon_font frm_more_horiz_solid_icon frm-show-inline-modal frm-open-calc',
			array(
				'data-open' => 'frm-calc-box-' . $field['id'],
			)
	 	);
		?>
		<input type="text" value="<?php echo esc_attr( $field['exclude_fields'] ); ?>" id="frm_calc_<?php echo esc_attr( $field['id'] ); ?>" name="field_options[exclude_fields_<?php echo absint( $field['id'] ); ?>]" class="frm-calc-field" data-sep="," />
	</span>
</p>

<?php
FrmFieldsHelper::inline_modal(
	array(
		'title'    => __( 'Exclude Fields', 'formidable-pro' ),
		'callback' => array( 'FrmProFieldSummary', 'exclude_fields_settings' ),
		'args'     => compact( 'field' ),
		'id'       => 'frm-calc-box-' . $field['id'],
	)
);
?>

<p>
	<label><?php esc_html_e( 'Show these automatically excluded field types', 'formidable-pro' ); ?></label>
	<?php foreach ( FrmProFieldSummary::include_extra_field_types() as $key => $label ) { ?>
		<label for="frm_include_extras_field_<?php echo esc_attr( $key ); ?>">
			<input type="checkbox" id="frm_include_extras_field_<?php echo esc_attr( $key ); ?>" class="frm_include_extras_field" name="field_options[include_extras_<?php echo esc_attr( $field['id'] ); ?>][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $field['include_extras'], true ) ); ?> />
			<?php echo esc_html( $label ); ?>
		</label>
	<?php } ?>
</p>

<?php
if ( FrmProFormsHelper::has_field( 'file', $field['form_id'], true ) ) {
	$file_display_format = $field['file_display_format'] ? $field['file_display_format'] : 'thumbnail+filename';
	?>
	<p>
		<label><?php esc_html_e( 'File field display format', 'formidable-pro' ); ?>
			<select name="field_options[file_display_format_<?php echo esc_attr( $field['id'] ); ?>]" id="frm_file_display_format_<?php echo esc_attr( $field['id'] ); ?>">
				<option value="thumbnail+filename" <?php selected( $file_display_format === 'thumbnail+filename' ); ?>><?php esc_html_e( 'Show thumbnail and file name', 'formidable-pro' ); ?></option>
				<option value="thumbnail" <?php selected( $file_display_format === 'thumbnail' ); ?>><?php esc_html_e( 'Show thumbnail only', 'formidable-pro' ); ?></option>
				<option value="filename" <?php selected( $file_display_format === 'filename' ); ?>><?php esc_html_e( 'Show file name only', 'formidable-pro' ); ?></option>
			</select>
		</label>
	</p>
	<?php
}
