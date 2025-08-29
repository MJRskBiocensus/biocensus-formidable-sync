<?php
/**
 * Date calculation settings
 *
 * @package formidable-dates
 * @since 2.0
 *
 * @var array $field               Field data.
 * @var array $default_value_types Default value types data.
 * @var array $args                See {@see FrmDatesCalculationController::add_default_value_type_box()}.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

$label_class  = 'frm-has-modal frm-date-calc-box-label frm-date-calc-box-' . $field['id'];
$label_class .= ' default-value-section-' . $field['id'] . ( isset( $default_value_types['date_calc']['current'] ) ? '' : ' frm_hidden' );
?>
<p class="<?php echo esc_attr( $label_class ); ?>" style="padding-bottom:0;">
	<label for="frm_default_value_<?php echo esc_attr( $field['id'] ); ?>">
		<?php esc_html_e( 'Default Value', 'frmdates' ); ?>
		<span class="frm-sub-label">
			(<?php esc_html_e( 'Date calculation', 'frmdates' ); ?>)
		</span>
	</label>
</p>

<?php
FrmFieldsHelper::inline_modal(
	array(
		'title'        => __( 'Date calculation', 'frmdates' ),
		'callback'     => array( 'FrmDatesCalculationHelper', 'calc_settings_modal_callback' ),
		'args'         => $field,
		'id'           => 'frm-date-calc-box-' . $field['id'],
		'class'        => 'frm-date-calc-modal frm-date-calc-box-' . $field['id'],
		'show'         => ! empty( $field['date_calc'] ),
		'inside_class' => 'frm-dates-inside',
	)
);

unset( $label_class );
