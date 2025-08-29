<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
// TODO: Remove the 'else' block when the majority of active LITE version installations are above 6.14.
if ( class_exists( 'FrmStyleComponent' ) ) : ?>
<div class="frm5 frm_form_field <?php echo esc_attr( $class ); ?>">
	<label class="frm-style-item-heading">
		<?php esc_html_e( 'Image Opacity', 'formidable-pro' ); ?>
	</label>
</div>
<div id="frm-bg-image-opacity-slider" class="frm7 frm_form_field <?php echo esc_attr( $class ); ?>">
	<?php
	new FrmSliderStyleComponent(
		'frm_style_setting[post_content][bg_image_opacity]',
		$bg_image_opacity,
		array(
			'id'        => 'frm_bg_image_opacity',
			'max_value' => 100,
			'units'     => array( '%' ),
		)
	);
	?>
</div>
<?php else : ?>
	<div class="frm6">
		<div class="<?php echo esc_attr( $class ); ?>">
			<label><?php esc_html_e( 'Image Opacity', 'formidable-pro' ); ?></label>
			<input type="text" name="frm_style_setting[post_content][bg_image_opacity]" value="<?php echo esc_attr( $bg_image_opacity ); ?>" />
		</div>
	</div>
<?php endif; ?>