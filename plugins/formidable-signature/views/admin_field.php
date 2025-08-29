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
<div class="frm_html_field_placeholder">
	<div class="howto button-secondary frm_html_field">
		<?php
		/* translators: %1$s: html line break */
		printf( esc_html__( 'This is a placeholder for your signature field. %1$sView your form to see it in action.', 'frmsig' ), '<br/>' );
		?>
	</div>
</div>
