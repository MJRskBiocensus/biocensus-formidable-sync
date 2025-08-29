<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
// TODO: Remove the 'else' block when the majority of active LITE version installations are above 6.14.
if ( class_exists( 'FrmStyleComponent' ) ) : ?>
	<div class="frm5 frm_form_field">
		<label
			for="frm_progress_bg_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'BG Color', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'progress_bg_color' ),
			$style->post_content['progress_bg_color'],
			array(
				'id'          => 'frm_progress_bg_color',
				'action_slug' => 'progress_bg_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label
			for="frm_progress_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Font Color', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'progress_color' ),
			$style->post_content['progress_color'],
			array(
				'id'          => 'frm_progress_color',
				'action_slug' => 'progress_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label
			for="frm_progress_active_bg_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Active BG', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'progress_active_bg_color' ),
			$style->post_content['progress_active_bg_color'],
			array(
				'id'          => 'frm_progress_active_bg_color',
				'action_slug' => 'progress_active_bg_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label
			for="frm_progress_active_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Active Font', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'progress_active_color' ),
			$style->post_content['progress_active_color'],
			array(
				'id'          => 'frm_progress_active_color',
				'action_slug' => 'progress_active_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label
			for="frm_progress_border_size"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Border Width', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmSliderStyleComponent(
			$frm_style->get_field_name( 'progress_border_size' ),
			$style->post_content['progress_border_size'],
			array(
				'id'        => 'frm_progress_border_size',
				'max_value' => 50,
			)
		);
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label
			for="frm_progress_border_color"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Border Color', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php
		new FrmColorpickerStyleComponent(
			$frm_style->get_field_name( 'progress_border_color' ),
			$style->post_content['progress_border_color'],
			array(
				'id'          => 'frm_progress_border_color',
				'action_slug' => 'progress_border_color',
			)
		); 
		?>
	</div>

	<div class="frm5 frm_form_field">
		<label
			for="frm_progress_size"
			class="frm-style-item-heading">
			<?php esc_html_e( 'Circle Size', 'formidable-pro' ); ?>
		</label>
	</div>
	<div class="frm7 frm_form_field">
		<?php 
		new FrmSliderStyleComponent(
			$frm_style->get_field_name( 'progress_size' ),
			$style->post_content['progress_size'],
			array(
				'id'        => 'frm_progress_size',
				'max_value' => 50,
			)
		);
		?>
	</div>

<?php else : ?>

	<p class="frm4 frm_first frm_form_field">
		<label for="frm_progress_bg_color"><?php esc_html_e( 'BG Color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'progress_bg_color' ) ); ?>" id="frm_progress_bg_color" class="hex" value="<?php echo esc_attr( $style->post_content['progress_bg_color'] ); ?>" size="4" <?php do_action( 'frm_style_settings_input_atts', 'progress_bg_color' ); ?> />
	</p>

	<p class="frm4 frm_form_field">
		<label for="frm_progress_color"><?php esc_html_e( 'Text Color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'progress_color' ) ); ?>" id="frm_progress_color" class="hex" value="<?php echo esc_attr( $style->post_content['progress_color'] ); ?>" <?php do_action( 'frm_style_settings_input_atts', 'progress_color' ); ?> />
	</p>

	<p class="frm4 frm_first frm_form_field">
		<label for="frm_progress_active_bg_color"><?php esc_html_e( 'Active BG', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'progress_active_bg_color' ) ); ?>" id="frm_progress_active_bg_color_color" class="hex" value="<?php echo esc_attr( $style->post_content['progress_active_bg_color'] ); ?>" size="4" <?php do_action( 'frm_style_settings_input_atts', 'progress_active_bg_color' ); ?> />
	</p>

	<p class="frm4 frm_form_field">
		<label for="frm_progress_active_color"><?php esc_html_e( 'Active Text', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'progress_active_color' ) ); ?>" id="frm_progress_active_color" class="hex" value="<?php echo esc_attr( $style->post_content['progress_active_color'] ); ?>" size="4" <?php do_action( 'frm_style_settings_input_atts', 'progress_active_color' ); ?> />
	</p>

	<p class="frm4 frm_first frm_form_field">
		<label for="frm_progress_border_color"><?php esc_html_e( 'Border Color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'progress_border_color' ) ); ?>" id="frm_progress_border_color" class="hex" value="<?php echo esc_attr( $style->post_content['progress_border_color'] ); ?>" size="4" <?php do_action( 'frm_style_settings_input_atts', 'progress_border_color' ); ?> />
	</p>

	<p class="frm4 frm_form_field">
		<label for="frm_progress_border_size"><?php esc_html_e( 'Border Size', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'progress_border_size' ) ); ?>" id="frm_progress_border_size" value="<?php echo esc_attr( $style->post_content['progress_border_size'] ); ?>" size="4" />
	</p>

	<p class="frm4 frm_form_field">
		<label for="frm_progress_size"><?php esc_html_e( 'Circle Size', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'progress_size' ) ); ?>" id="frm_progress_size" value="<?php echo esc_attr( $style->post_content['progress_size'] ); ?>" size="4" />
	</p>

<?php endif; ?>
