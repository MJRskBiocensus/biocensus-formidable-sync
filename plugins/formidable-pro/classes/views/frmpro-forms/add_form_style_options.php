<?php
/**
 * @deprecated 6.16.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

_deprecated_file( __FILE__, '6.16.3' );
?>
<tr>
	<td colspan="2">
		<div class="frm_note_style" style="margin-top: 0;">
			<?php esc_html_e( 'Page Turn Transitions setting was moved to the page break field settings in the form builder.', 'formidable' ); ?>
			<input type="hidden" name="options[transition]" value="<?php echo esc_attr( $values['transition'] ); ?>" />
		</div>
	</td>
</tr>
