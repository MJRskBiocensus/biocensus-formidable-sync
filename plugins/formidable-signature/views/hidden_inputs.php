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
<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[content]" value="<?php echo esc_attr( $field['value']['content'] ); ?>" />
<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[format]" value="<?php echo esc_attr( $format ); ?>" />
