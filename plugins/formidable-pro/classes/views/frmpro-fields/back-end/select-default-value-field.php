<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

$select_attributes = array(
	'id'    => 'frm-default-value-select-' . $field['id'],
	'class' => 'frm-default-value-select frm-mb-xs',
);
?>

<select <?php FrmAppHelper::array_to_html_params( $select_attributes, true ); ?>>
	<?php
	if ( ! empty( $options ) && is_array( $options ) ) {
		foreach ( $options as $value => $option ) {
			FrmHtmlHelper::echo_dropdown_option( $option['label'], $selected === $value, array_merge( array( 'value' => $value ), $option['attributes'] ?? array() ) );
		}
	}
	?>
</select>

<?php
include FrmAppHelper::plugin_path() . '/classes/views/frm-fields/back-end/default-value-field.php';
