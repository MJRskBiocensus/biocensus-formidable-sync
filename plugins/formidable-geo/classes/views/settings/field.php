<?php
/**
 * Display field option.
 *
 * @var array $field Field data.
 *
 * @package formidable-geo
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p class="frm12 frm_form_field">
	<label>
		<input id="geo_auto_address_<?php echo absint( $field['id'] ); ?>" type="checkbox" name="field_options[auto_address_<?php echo absint( $field['id'] ); ?>]" value="1" <?php checked( 1, $field['auto_address'] ); ?>/>
		<?php esc_html_e( 'Add address autocomplete', 'formidable-geo' ); ?>
	</label>
</p>
<p id="geo_show_map_<?php echo absint( $field['id'] ); ?>_wrapper" class="frm6 frm_form_field" style="<?php echo esc_html( $field['auto_address'] ? '' : 'display: none;' ); ?>">
	<label>
		<input type="checkbox" name="field_options[geo_show_map_<?php echo absint( $field['id'] ); ?>]" value="1" <?php checked( 1, $field['geo_show_map'] ); ?>/>
		<?php esc_html_e( 'Show map', 'formidable-geo' ); ?>
	</label>
</p>
<p id="geo_detect_location_<?php echo absint( $field['id'] ); ?>_wrapper" class="frm6 frm_form_field" style="<?php echo esc_html( $field['auto_address'] ? '' : 'display: none;' ); ?>">
	<label class="frm_inline_label frm_help" title="<?php esc_attr_e( 'Turning on this will request permissions on page load.', 'formidable-geo' ); ?>">
		<input type="checkbox" name="field_options[geo_detect_location_<?php echo absint( $field['id'] ); ?>]" value="1" <?php checked( 0, $field['geo_avoid_autofill'] ); ?>/>
		<?php esc_html_e( 'Use visitor location', 'formidable-geo' ); ?>
	</label>
</p>
<script>
	/* Toggle show map input */
	document.getElementById( 'geo_auto_address_<?php echo absint( $field['id'] ); ?>' ).addEventListener(
		'change',
		event => {
			const showMap = document.getElementById( 'geo_show_map_<?php echo absint( $field['id'] ); ?>_wrapper' );
			const detectLocation = document.getElementById( 'geo_detect_location_<?php echo absint( $field['id'] ); ?>_wrapper' );
			const displayValue = event.target.checked ? 'block' : 'none';
			showMap.style.display = displayValue;
			detectLocation.style.display = displayValue;
		}
	);
</script>
