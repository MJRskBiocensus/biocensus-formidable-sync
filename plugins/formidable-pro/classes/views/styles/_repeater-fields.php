<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
// TODO: Remove the 'else' block when the majority of active LITE version installations are above 6.14.
if ( class_exists( 'FrmStyleComponent' ) ) : ?>

	<div class="frm5 frm_form_field">
		<label
			for="frm_repeat_icon_color"
			class="frm-style-item-heading"><?php esc_html_e( 'Icon Color', 'formidable-pro' ); ?></label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'repeat_icon_color' ),
			$style->post_content['repeat_icon_color'],
			array(
				'id'          => 'frm_repeat_icon_color',
				'action_slug' => 'repeat_icon_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label class="frm-style-item-heading"><?php esc_html_e( 'Icons', 'formidable-pro' ); ?></label>
	</div>
	<div class="frm7 frm_form_field"><?php FrmStylesHelper::bs_icon_select( $style, $frm_style, 'minus' ); ?></div>

<?php else : ?>
	<div class="frm4 frm_form_field">
		<label class="frm_primary_label"><?php esc_html_e( 'Icons', 'formidable-pro' ); ?></label>
		<?php FrmStylesHelper::bs_icon_select( $style, $frm_style, 'minus' ); ?>
	</div>
	<div class="frm6 frm_form_field">
		<label class="frm_primary_label"><?php esc_html_e( 'Icon Color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'repeat_icon_color' ) ); ?>" id="frm_repeat_icon_color" class="hex" value="<?php echo esc_attr( $style->post_content['repeat_icon_color'] ); ?>" <?php do_action( 'frm_style_settings_input_atts', 'repeat_icon_color' ); ?> />
	</div>
<?php endif; ?>
