<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
// TODO: Remove the 'else' block when the majority of active LITE version installations are above 6.14.
if ( class_exists( 'FrmStyleComponent' ) ) : ?>

	<div class="frm-style-tabs-wrapper">
		<div class="frm-tabs-delimiter">
			<span data-initial-width="174" class="frm-tabs-active-underline frm-first"></span>
		</div>
		<div class="frm-tabs-navs">
			<ul class="frm-flex-box">
				<li class="frm-active"><?php esc_html_e( 'General', 'formidable-pro' ); ?></li>
				<li><?php esc_html_e( 'Collapse Options', 'formidable-pro' ); ?></li>
			</ul>
		</div>
		<div class="frm-tabs-container">
			<div class="frm-tabs-slide-track frm-flex-box">
				<div class="frm-active">
					<div class="frm_grid_container">

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_color"
								class="frm-style-item-heading"><?php esc_html_e( 'Color', 'formidable-pro' ); ?></label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmColorpickerStyleComponent(
								$frm_style->get_field_name( 'section_color' ),
								$style->post_content['section_color'],
								array(
									'id'          => 'frm_section_color',
									'action_slug' => 'section_color',
								)
							); 
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_success_font_size"
								class="frm-style-item-heading">
								<?php esc_html_e( 'Font Size', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmSliderStyleComponent(
								$frm_style->get_field_name( 'section_font_size' ),
								$style->post_content['section_font_size'],
								array(
									'id'        => 'frm_success_font_size',
									'max_value' => 100,
								)
							);
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_weight"
								class="frm-style-item-heading"><?php esc_html_e( 'Weight', 'formidable-pro' ); ?></label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmDropdownStyleComponent(
								$frm_style->get_field_name( 'section_weight' ),
								$style->post_content['section_weight'],
								array(
									'id'      => 'frm_section_weight',
									'options' => FrmStyle::get_bold_options(),
								)
							);
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_bg_color"
								class="frm-style-item-heading">
								<?php esc_html_e( 'BG Color', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmColorpickerStyleComponent(
								$frm_style->get_field_name( 'section_bg_color' ),
								$style->post_content['section_bg_color'],
								array(
									'id'          => 'frm_section_bg_color',
									'action_slug' => 'section_bg_color',
								)
							); 
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_pad"
								class="frm-style-item-heading"><?php esc_html_e( 'Padding', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmSliderStyleComponent(
								$frm_style->get_field_name( 'section_pad' ),
								$style->post_content['section_pad'],
								array(
									'id'        => 'frm_section_pad',
									'type'      => 'vertical-margin',
									'max_value' => 100,
								)
							);
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_margins"
								class="frm-style-item-heading">
								<?php esc_html_e( 'Margin', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmSliderStyleComponent(
								$frm_style->get_field_name( 'section_mar_top' ),
								$style->post_content['section_mar_top'],
								array(
									'id'                 => 'frm_section_margins',
									'type'               => 'vertical-margin',
									'max_value'          => 100,
									'independent_fields' => array(
										array(
											'name'  => $frm_style->get_field_name( 'section_mar_top' ),
											'value' => $style->post_content['section_mar_top'],
											'id'    => 'frm_section_mar_top',
											'type'  => 'top',
										),
										array(
											'name'  => $frm_style->get_field_name( 'section_mar_bottom' ),
											'value' => $style->post_content['section_mar_bottom'],
											'id'    => 'frm_section_mar_bottom',
											'type'  => 'bottom',
										),
									),
								)
							);
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_border_color"
								class="frm-style-item-heading">
								<?php esc_html_e( 'Border Color', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmColorpickerStyleComponent(
								$frm_style->get_field_name( 'section_border_color' ),
								$style->post_content['section_border_color'],
								array(
									'id'          => 'frm_section_border_color',
									'action_slug' => 'section_border_color',
								)
							); 
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_border_width"
								class="frm-style-item-heading">
								<?php esc_html_e( 'Border Width', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmSliderStyleComponent(
								$frm_style->get_field_name( 'section_border_width' ),
								$style->post_content['section_border_width'],
								array(
									'id'        => 'frm_section_border_width',
									'max_value' => 25,
								)
							);
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_border_style"
								class="frm-style-item-heading">
								<?php esc_html_e( 'Style', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmDropdownStyleComponent(
								$frm_style->get_field_name( 'section_border_style' ),
								$style->post_content['section_border_style'],
								array(
									'id'      => 'frm_section_border_style',
									'options' => array(
										'solid'  => esc_html__( 'solid', 'formidable-pro' ),
										'dotted' => esc_html__( 'dotted', 'formidable-pro' ),
										'dashed' => esc_html__( 'dashed', 'formidable-pro' ),
										'double' => esc_html__( 'double', 'formidable-pro' ),
									),
								)
							);
							?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_section_border_loc"
								class="frm-style-item-heading">
								<?php esc_html_e( 'Position', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmDropdownStyleComponent(
								$frm_style->get_field_name( 'section_border_loc' ),
								$style->post_content['section_border_loc'],
								array(
									'id'      => 'frm_section_border_loc',
									'options' => array(
										'-top'    => esc_html__( 'top', 'formidable-pro' ),
										'-bottom' => esc_html__( 'bottom', 'formidable-pro' ),
										'-left'   => esc_html__( 'left', 'formidable-pro' ),
										'-right'  => esc_html__( 'right', 'formidable-pro' ),
										''        => esc_html__( 'all', 'formidable-pro' ),
									),
								)
							);
							?>
						</div>

					</div>
				</div>

				<div class="frm-active">
					<div class="frm_grid_container">
						<div class="frm5 frm_form_field">
							<label class="frm-style-item-heading">
								<?php esc_html_e( 'Icons', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php FrmStylesHelper::bs_icon_select( $style, $frm_style, 'arrow' ); ?>
						</div>

						<div class="frm5 frm_form_field">
							<label
								for="frm_submit_weight"
								class="frm-style-item-heading">
								<?php esc_html_e( 'Icons Position', 'formidable-pro' ); ?>
							</label>
						</div>
						<div class="frm7 frm_form_field">
							<?php
							new FrmDropdownStyleComponent(
								$frm_style->get_field_name( 'collapse_pos' ),
								$style->post_content['collapse_pos'],
								array(
									'id'      => 'frm_collapse_pos',
									'options' => array(
										'after'  => __( 'After Heading', 'formidable-pro' ),
										'before' => __( 'Before Heading', 'formidable-pro' ),

									),
								)
							);
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php else : ?>
	<p class="frm4 frm_first frm_form_field">
		<label><?php esc_html_e( 'Color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_color' ) ); ?>" id="frm_section_color" class="hex" value="<?php echo esc_attr( $style->post_content['section_color'] ); ?>" <?php do_action( 'frm_style_settings_input_atts', 'section_color' ); ?> />
	</p>

	<p class="frm4 frm_form_field">
		<label><?php esc_html_e( 'Weight', 'formidable-pro' ); ?></label>
		<select name="<?php echo esc_attr( $frm_style->get_field_name( 'section_weight' ) ); ?>" id="frm_section_weight">
			<?php foreach ( FrmStyle::get_bold_options() as $value => $name ) { ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $style->post_content['section_weight'], $value ); ?>><?php echo esc_html( $name ); ?></option>
			<?php } ?>
		</select>
	</p>

	<p class="frm4 frm_form_field">
		<label><?php esc_html_e( 'Size', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_font_size' ) ); ?>" id="frm_section_font_size" value="<?php echo esc_attr( $style->post_content['section_font_size'] ); ?>" />
	</p>

	<p class="frm6 frm_first frm_form_field">
		<label class="background"><?php esc_html_e( 'BG color', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_bg_color' ) ); ?>" id="frm_section_bg_color" class="hex" value="<?php echo esc_attr( $style->post_content['section_bg_color'] ); ?>" <?php do_action( 'frm_style_settings_input_atts', 'section_bg_color' ); ?> />
	</p>

	<p class="frm6 frm_form_field">
		<label><?php esc_html_e( 'Padding', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_pad' ) ); ?>" id="frm_section_pad" value="<?php echo esc_attr( $style->post_content['section_pad'] ); ?>" />
	</p>

	<p class="frm6 frm_first frm_form_field">
		<label><?php esc_html_e( 'Top Margin', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_mar_top' ) ); ?>" id="frm_section_mar_top" value="<?php echo esc_attr( $style->post_content['section_mar_top'] ); ?>" />
	</p>

	<p class="frm6 frm_form_field">
		<label><?php esc_html_e( 'Bottom Margin', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_mar_bottom' ) ); ?>" id="frm_section_mar_bottom" value="<?php echo esc_attr( $style->post_content['section_mar_bottom'] ); ?>" />
	</p>

	<p class="frm4 frm_first frm_form_field">
		<label><?php esc_html_e( 'Border', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_border_color' ) ); ?>" id="frm_section_border_color" class="hex" value="<?php echo esc_attr( $style->post_content['section_border_color'] ); ?>" <?php do_action( 'frm_style_settings_input_atts', 'section_border_color' ); ?> />
	</p>

	<p class="frm4 frm_form_field">
		<label><?php esc_html_e( 'Thickness', 'formidable-pro' ); ?></label>
		<input type="text" name="<?php echo esc_attr( $frm_style->get_field_name( 'section_border_width' ) ); ?>" id="frm_section_border_width" value="<?php echo esc_attr( $style->post_content['section_border_width'] ); ?>" />
	</p>

	<p class="frm4 frm_form_field">
		<label><?php esc_html_e( 'Style', 'formidable-pro' ); ?></label>
		<select name="<?php echo esc_attr( $frm_style->get_field_name( 'section_border_style' ) ); ?>" id="frm_section_border_style">
			<option value="solid" <?php selected( $style->post_content['section_border_style'], 'solid' ); ?>>
				<?php esc_html_e( 'solid', 'formidable-pro' ); ?>
			</option>
			<option value="dotted" <?php selected( $style->post_content['section_border_style'], 'dotted' ); ?>>
				<?php esc_html_e( 'dotted', 'formidable-pro' ); ?>
			</option>
			<option value="dashed" <?php selected( $style->post_content['section_border_style'], 'dashed' ); ?>>
				<?php esc_html_e( 'dashed', 'formidable-pro' ); ?>
			</option>
			<option value="double" <?php selected( $style->post_content['section_border_style'], 'double' ); ?>>
				<?php esc_html_e( 'double', 'formidable-pro' ); ?>
			</option>
		</select>
	</p>

	<p class="frm4 frm_first frm_form_field">
		<label><?php esc_html_e( 'Border Position', 'formidable-pro' ); ?></label>
		<select name="<?php echo esc_attr( $frm_style->get_field_name( 'section_border_loc' ) ); ?>" id="frm_section_border_loc">
			<option value="-top" <?php selected( $style->post_content['section_border_loc'], '-top' ); ?>>
				<?php esc_html_e( 'top', 'formidable-pro' ); ?>
			</option>
			<option value="-bottom" <?php selected( $style->post_content['section_border_loc'], '-bottom' ); ?>>
				<?php esc_html_e( 'bottom', 'formidable-pro' ); ?>
			</option>
			<option value="-left" <?php selected( $style->post_content['section_border_loc'], '-left' ); ?>>
				<?php esc_html_e( 'left', 'formidable-pro' ); ?>
			</option>
			<option value="-right" <?php selected( $style->post_content['section_border_loc'], '-right' ); ?>>
				<?php esc_html_e( 'right', 'formidable-pro' ); ?>
			</option>
			<option value="" <?php selected( $style->post_content['section_border_loc'], '' ); ?>>
				<?php esc_html_e( 'all', 'formidable-pro' ); ?>
			</option>
		</select>
	</p>

	<h4 class="frm_clear">
		<span><?php esc_html_e( 'Collapse Icon', 'formidable-pro' ); ?></span>
	</h4>
	<div class="frm6 frm_first frm_form_field">
		<label class="frm_primary_label"><?php esc_html_e( 'Icons', 'formidable-pro' ); ?></label>
		<?php FrmStylesHelper::bs_icon_select( $style, $frm_style, 'arrow' ); ?>
	</div>

	<p class="frm6 frm_form_field">
		<label><?php esc_html_e( 'Icon Position', 'formidable-pro' ); ?></label>
		<select name="<?php echo esc_attr( $frm_style->get_field_name( 'collapse_pos' ) ); ?>" id="frm_collapse_pos">
			<option value="after" <?php selected( $style->post_content['collapse_pos'], 'after' ); ?>>
				<?php esc_html_e( 'After Heading', 'formidable-pro' ); ?>
			</option>
			<option value="before" <?php selected( $style->post_content['collapse_pos'], 'before' ); ?>>
				<?php esc_html_e( 'Before Heading', 'formidable-pro' ); ?>
			</option>
		</select>
	</p>
<?php endif; ?>
