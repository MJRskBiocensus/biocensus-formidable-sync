<?php
/**
 * Restrict direct access.
 *
 * @package frmsig
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<?php if ( $allow_edit ) : ?>
<div class="frm-clear-signature-container">
	<a href="#clear-<?php echo esc_attr( $field_name ); ?>" data-fieldid="<?php echo esc_attr( $field['id'] ); ?>" class="frm-clear-signature"><?php esc_html_e( 'Clear', 'frmsig' ); ?></a>
</div>
<?php endif; ?>
<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[delete_saved_image]" class="frm-delete-saved-image" value="0" />
