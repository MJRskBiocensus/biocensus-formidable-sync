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
<div class="frm-typed-display">
	<?php echo wp_kses_post( $final_value ); ?>
</div>
