<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
// TODO: Remove the 'else' block when the majority of active LITE version installations are above 6.14.
if ( class_exists( 'FrmStyleComponent' ) ) : ?>

	<div class="frm5 frm_form_field">
		<label 
			for="frm_slider_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Color', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'slider_color' ),
			$style->post_content['slider_color'],
			array(
				'id'          => 'frm_slider_color',
				'action_slug' => 'slider_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label 
			for="frm_slider_bar_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Bar Color', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'slider_bar_color' ),
			$style->post_content['slider_bar_color'],
			array(
				'id'          => 'frm_slider_bar_color',
				'action_slug' => 'slider_bar_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label 
			for="frm_slider_font_size"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Font Size', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmSliderStyleComponent(
			$frm_style->get_field_name( 'slider_font_size' ),
			$style->post_content['slider_font_size'],
			array(
				'id'        => 'frm_slider_font_size',
				'max_value' => 100,
			)
		);
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label 
			for="frm_slider_track_size"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Track Height', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmSliderStyleComponent(
			$frm_style->get_field_name( 'slider_track_size' ),
			$style->post_content['slider_track_size'],
			array(
				'id'        => 'frm_slider_track_size',
				'max_value' => 25,
			)
		);
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label 
			for="frm_slider_circle_size"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Circle Size', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmSliderStyleComponent(
			$frm_style->get_field_name( 'slider_circle_size' ),
			$style->post_content['slider_circle_size'],
			array(
				'id'        => 'frm_slider_circle_size',
				'max_value' => 50,
			)
		);
		?>
	</div>

<?php else : ?>

	<p class="frm6 frm_first frm_form_field">
		<label for="frm_progress_bg_color"><?php esc_html_e( 'Color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'slider_color' ) ); ?>" id="frm_slider_color" class="hex" value="<?php echo esc_attr( $style->post_content['slider_color'] ); ?>" size="4" <?php do_action( 'frm_style_settings_input_atts', 'slider_color' ); ?> />
	</p>

	<p class="frm6 frm_form_field">
		<label for="frm_progress_color"><?php esc_html_e( 'Bar Color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'slider_bar_color' ) ); ?>" id="frm_slider_bar_color" class="hex" value="<?php echo esc_attr( $style->post_content['slider_bar_color'] ); ?>" <?php do_action( 'frm_style_settings_input_atts', 'slider_bar_color' ); ?> />
	</p>

	<p class="frm4 frm_form_field">
		<label for="frm_slider_font_size"><?php esc_html_e( 'Font Size', 'formidable' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'slider_font_size' ) ); ?>" id="frm_slider_font_size" value="<?php echo esc_attr( $style->post_content['slider_font_size'] ); ?>" size="3" />
	</p>

	<p class="frm4 frm_form_field">
		<label for="frm_slider_track_size"><?php esc_html_e( 'Track Height', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'slider_track_size' ) ); ?>" id="frm_slider_track_size" value="<?php echo esc_attr( $style->post_content['slider_track_size'] ); ?>" size="3" />
	</p>

	<p class="frm4 frm_form_field">
		<label for="frm_slider_circle_size"><?php esc_html_e( 'Circle Size', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'slider_circle_size' ) ); ?>" id="frm_slider_circle_size" value="<?php echo esc_attr( $style->post_content['slider_circle_size'] ); ?>" size="3" />
	</p>

<?php endif; ?>
