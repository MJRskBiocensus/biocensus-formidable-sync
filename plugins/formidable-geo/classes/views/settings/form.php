<?php
/**
 * Geo settings.
 *
 * @var FrmGeoSettings $frm_geo_settings Settings data.
 *
 * @package formidable-geo
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>

<p class="frm_insert_form">
	<label for="frm_geo_api_key" class="frm_left_label">
		<?php esc_html_e( 'Google Maps API Key', 'formidable-geo' ); ?>
	</label>
	<input type="text" name="frm_geo_api_key" id="frm_geo_api_key" class="frm_with_left_label" value="<?php echo esc_attr( $frm_geo_settings->api_key ); ?>" />
</p>
