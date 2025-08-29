<?php if ( in_array( $display['type'], $field_types, true ) || ( isset( $field['data_type'] ) && in_array( $field['data_type'], $field_types, true ) ) ) { ?>
<tr><td><label><?php esc_html_e( 'Prepend and Append', 'frmbtsp' ); ?></label></td>
	<td><?php esc_html_e( 'Include before input:', 'frmbtsp' ); ?>
		<input type="text" name="field_options[btsp_<?php echo esc_attr( $field['id'] ); ?>][prepend]" value="<?php echo esc_attr( $field['btsp']['prepend'] ); ?>" size="3" />
		<?php esc_html_e( 'Include after input:', 'frmbtsp' ); ?>
		<input type="text" name="field_options[btsp_<?php echo esc_attr( $field['id'] ); ?>][append]" value="<?php echo esc_attr( $field['btsp']['append'] ); ?>" size="3" />
	</td>
</tr>
<?php } ?>
