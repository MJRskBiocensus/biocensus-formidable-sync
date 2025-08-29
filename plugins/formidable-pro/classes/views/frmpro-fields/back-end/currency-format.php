<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

$decimals_type = FrmProCurrencyHelper::get_decimal_setting_type( $field );

if ( 'currency' === FrmField::get_option( $field, 'format' ) && $field['use_global_currency'] ) {
	$global_currency = FrmProCurrencyHelper::get_global_currency( $field );
}

$currency_decimals = $global_currency['decimals'] ?? $field['custom_decimals'] ?? 2;
?>
<div class="frm_form_field frm_hidden" id="frm-field-format-global-currency-<?php echo esc_attr( $field['id'] ); ?>">
	<label class="frm_primary_label" for="frm_use_global_currency_<?php echo esc_attr( $field['id'] ); ?>">
		<input type="checkbox" id="frm_use_global_currency_<?php echo esc_attr( $field['id'] ); ?>" class="frm-global-currency-checkbox" value="1" <?php checked( ! $field['custom_currency'] && $field['use_global_currency'] ); ?> />
		<input type="hidden" name="field_options[use_global_currency_<?php echo esc_attr( $field['id'] ); ?>]" value="0" />
		<?php esc_html_e( 'Use Global Currency Settings', 'formidable-pro' ); ?>
	</label>
</div>

<div class="frm_form_field frm_hidden" id="frm-field-format-currency-<?php echo esc_attr( $field['id'] ); ?>">
	<div class="frm_grid_container frm_custom_format_options_wrapper">
		<p class="frm_form_field frm4">
			<label class="frm_primary_label frm-mb-0">
				<input type="text" value="<?php echo esc_attr( $global_currency['thousand_separator'] ?? $field['custom_thousand_separator'] ?? '' ); ?>" name="field_options[custom_thousand_separator_<?php echo esc_attr( $field['id'] ); ?>]" />
				<?php esc_html_e( 'Thousand separator', 'formidable-pro' ); ?>
			</label>
		</p>

		<p class="frm_form_field frm4">
			<label class="frm_primary_label">
				<input type="text" value="<?php echo esc_attr( $global_currency['decimal_separator'] ?? $field['custom_decimal_separator'] ?? '' ); ?>" name="field_options[custom_decimal_separator_<?php echo esc_attr( $field['id'] ); ?>]" />
				<?php esc_html_e( 'Decimal separator', 'formidable-pro' ); ?>
			</label>
		</p>

		<p class="frm_form_field frm4">
			<label class="frm_primary_label">
				<select class="<?php echo $decimals_type === 'select' ? '' : 'frm_hidden'; ?>" name="field_options[custom_decimals_<?php echo esc_attr( $field['id'] ); ?>]">
					<option value="0" <?php selected( $currency_decimals, 0 ); ?>>0</option>
					<option value="2" <?php selected( $currency_decimals, 2 ); ?>>2</option>
				</select>
				<input class="<?php echo $decimals_type === 'text' ? '' : 'frm_hidden'; ?>" name="field_options[calc_dec_<?php echo esc_attr( $field['id'] ); ?>]" type="text" value="<?php echo isset( $field['calc_dec'] ) && is_numeric( $field['calc_dec'] ) ? esc_attr( $field['calc_dec'] ) : '2'; ?>" />
				<?php esc_html_e( 'Decimals', 'formidable-pro' ); ?>
			</label>
		</p>
	</div>

	<div class="frm_grid_container frm_custom_currency_options_wrapper">
		<p class="frm_form_field frm6">
			<label class="frm_primary_label">
				<?php
				$left_symbol_value = $global_currency['symbol_left'] ?? $field['custom_symbol_left'] ?? '';

				// Maintain compatibility for older users with currency checkboxes.
				if ( ! $left_symbol_value && ! empty( $field['is_currency'] ) && empty( $field['custom_currency'] ) ) {
					$left_symbol_value = FrmProCurrencyHelper::get_currency( $field['form_id'] )['symbol_left'];
				}
				?>

				<input type="text" value="<?php echo esc_attr( $left_symbol_value ); ?>" name="field_options[custom_symbol_left_<?php echo esc_attr( $field['id'] ); ?>]" />
				<?php esc_html_e( 'Left symbol', 'formidable-pro' ); ?>
			</label>
		</p>

		<p class="frm_form_field frm6">
			<label class="frm_primary_label">
				<input type="text" value="<?php echo esc_attr( $global_currency['symbol_right'] ?? $field['custom_symbol_right'] ?? '' ); ?>" name="field_options[custom_symbol_right_<?php echo esc_attr( $field['id'] ); ?>]" />
				<?php esc_html_e( 'Right symbol', 'formidable-pro' ); ?>
			</label>
		</p>
	</div>
</div>
