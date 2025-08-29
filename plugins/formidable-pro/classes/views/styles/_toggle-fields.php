<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
// TODO: Remove the 'else' block when the majority of active LITE version installations are above 6.14.
if ( class_exists( 'FrmStyleComponent' ) ) : ?>
	<div class="frm5 frm_form_field">
		<label 
			for="frm_toggle_on_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'On Color', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'toggle_on_color' ),
			$style->post_content['toggle_on_color'],
			array(
				'id'          => 'frm_toggle_on_color',
				'action_slug' => 'toggle_on_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label 
			for="frm_toggle_off_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Off Color', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'toggle_off_color' ),
			$style->post_content['toggle_off_color'],
			array(
				'id'          => 'frm_toggle_off_color',
				'action_slug' => 'toggle_off_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label 
			for="frm_toggle_font_size"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Font Size', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmSliderStyleComponent(
			$frm_style->get_field_name( 'toggle_font_size' ),
			$style->post_content['toggle_font_size'],
			array(
				'id'        => 'frm_toggle_font_size',
				'max_value' => 100,
			)
		);
		?>
	</div>
<?php else : ?>
<p class="frm4 frm_first frm_form_field">
	<label for="frm_toggle_on_color"><?php esc_html_e( 'On Color', 'formidable-pro' ); ?></label>
	<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'toggle_on_color' ) ); ?>" id="frm_toggle_on_color" class="hex" value="<?php echo esc_attr( $style->post_content['toggle_on_color'] ); ?>" size="4" <?php do_action( 'frm_style_settings_input_atts', 'toggle_on_color' ); ?> />
</p>

<p class="frm4 frm_form_field">
	<label for="frm_toggle_off_color"><?php esc_html_e( 'Off Color', 'formidable-pro' ); ?></label>
	<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'toggle_off_color' ) ); ?>" id="frm_toggle_off_color" class="hex" value="<?php echo esc_attr( $style->post_content['toggle_off_color'] ); ?>" <?php do_action( 'frm_style_settings_input_atts', 'toggle_off_color' ); ?> />
</p>

<p class="frm4 frm_form_field">
	<label><?php esc_html_e( 'Font Size', 'formidable-pro' ); ?></label>
	<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'toggle_font_size' ) ); ?>" id="frm_toggle_font_size" value="<?php echo esc_attr( $style->post_content['toggle_font_size'] ); ?>" size="3" />
</p>
<?php endif; ?>
