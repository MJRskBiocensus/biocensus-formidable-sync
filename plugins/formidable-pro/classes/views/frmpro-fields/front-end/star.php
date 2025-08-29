<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<div class="frm-star-group">
<?php
$max = FrmField::get_option( $field, 'maxnum' );
if ( ! empty( $max ) ) {
	$field['options'] = range( 1, $max );
}

if ( is_array( $field['options'] ) ) {
	if ( ! isset( $field['value'] ) ) {
		$field['value'] = $field['default_value'];
		FrmProAppHelper::unserialize_or_decode( $field['value'] );
	}

	foreach ( $field['options'] as $opt_key => $opt ) {
		$class = 'star-rating';
		if ( $opt <= $field['value'] ) {
			$class .= ' star-rating-on';
		}

		$opt = apply_filters( 'frm_field_label_seen', $opt, $opt_key, $field );

		if ( is_numeric( $opt ) ) {
			$opt = (string) $opt;
		}
		if ( is_numeric( $field['value'] ) ) {
			$field['value'] = (string) $field['value'];
		}

		$last       = end( $field['options'] ) == $opt ? ' frm_last' : '';
		$count      = absint( $opt_key ) + 1;
		$aria_label = sprintf( _n( '%1$s Star', '%1$s Stars', $count, 'formidable-pro' ), $count );

		$label_attrs = array(
			'for'   => $html_id . '-' . $opt_key,
			'class' => $class,
		);
		?>
		<input type="radio" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $html_id . '-' . $opt_key ); ?>" value="<?php echo esc_attr( $opt ); ?>" <?php
		checked( $field['value'], $opt ) . ' ';
		if ( $opt === $field['value'] ) {
			echo 'data-frm-star-selected ';
		}
		do_action( 'frm_field_input_html', $field );
		?> /><label <?php FrmAppHelper::array_to_html_params( $label_attrs, true ); ?>>
				<?php
				FrmProAppHelper::get_svg_icon( 'frm-star-icon', 'frmsvg', array( 'echo' => true ) );
				FrmProAppHelper::get_svg_icon( 'frm-star-full-icon', 'frmsvg', array( 'echo' => true ) );
				?>
				<span class="frm_screen_reader"><?php echo esc_html( $aria_label ); ?></span>
			</label>
<?php
	}
}
?>
<div style="clear:both;"></div>
</div>
