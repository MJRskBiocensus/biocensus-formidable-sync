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
<div class="sigPad<?php echo ! empty( $hidden ) ? ' frm_hidden ' : ''; ?>" <?php echo empty( $plus_id ) ? "id='sigPad" . esc_attr( (int) $field['id'] ) . "'" : ''; ?>
 style="max-width:<?php echo esc_attr( (int) $styles['width'] ); ?>px;">
	<div class="sig sigWrapper" style="<?php echo esc_attr( $styles['css'] ); ?>">

		<ul class="sigNav<?php echo ( $styles['hide_tabs'] ) ? ' frm_hidden ' : ''; ?>">
				<li class="drawIt">
					<a href="#" class="<?php echo empty( $field['type_it'] ) ? 'frm-active-sig-type' : ''; ?>" title="<?php echo esc_html( $field['label1'] ); ?>" aria-label="<?php echo esc_html( $field['label1'] ); ?>">
						<?php FrmSigAppHelper::get_svg_icon( 'frm-signature-icon', 'frmfont frm_signature_icon' ); ?>
					</a>
				</li>
				<li class="typeIt">
					<a href="#" class="<?php echo ! empty( $field['type_it'] ) ? 'frm-active-sig-type' : ''; ?>" title="<?php echo esc_html( $field['label2'] ); ?>" aria-label="<?php echo esc_html( $field['label2'] ); ?>">
						<?php FrmSigAppHelper::get_svg_icon( 'frm-keyboard-icon', 'frmfont frm_keyboard_icon' ); ?>
					</a>
				</li>
		</ul>

		<span class="frm-typed-drawline"></span>

		<div class="typed">
			<input type="text" name="<?php echo esc_attr( $field_name ); ?>[typed]" class="name" id="<?php echo esc_attr( $html_id ); ?>" autocomplete="off" value="<?php echo esc_attr( $typed_value ); ?>" <?php do_action( 'frm_field_input_html', $field ); ?> />
		</div>

		<canvas class="pad" data-fieldid="<?php echo esc_attr( $field['id'] ); ?>" data-fieldname="<?php echo esc_attr( $field_name ); ?>" width="<?php echo esc_attr( $styles['width'] - 4 ); ?>" height="<?php echo esc_attr( $styles['height'] ); ?>"></canvas>
		<div class="clearButton"><a href="#clear"><?php echo esc_html( $field['label3'] ); ?></a></div>

		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[output]" class="output" value="<?php echo esc_attr( $output ); ?>" />
	</div>
</div>
