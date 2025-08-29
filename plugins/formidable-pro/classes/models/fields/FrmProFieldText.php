<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 3.0
 */
class FrmProFieldText extends FrmFieldText {
	use FrmProFieldAutocompleteField;

	protected function field_settings_for_type() {
		$settings = parent::field_settings_for_type();

		$settings['autopopulate'] = true;
		$settings['calc']         = true;
		$settings['read_only']    = true;
		$settings['unique']       = true;
		$settings['prefix']       = true;
		$settings['autocomplete'] = true;

		FrmProFieldsHelper::fill_default_field_display( $settings );
		return $settings;
	}

	/**
	 * @since 4.05
	 */
	protected function builder_text_field( $name = '' ) {
		$html  = FrmProFieldsHelper::builder_page_prepend( $this->field );
		$field = parent::builder_text_field( $name );
		return str_replace( '[input]', $field, $html );
	}

	/**
	 * @since 6.18
	 *
	 * {@inheritdoc}
	 */
	public function front_field_input( $args, $shortcode_atts ) {
		$input_html = $this->get_field_input_html_hook( $this->field );
		$this->add_aria_description( $args, $input_html );
		$this->add_extra_html_atts( $args, $input_html );

		if (
			FrmProCurrencyHelper::is_currency_format( FrmField::get_option( $this->field, 'format' ) )
			&& 'text' !== FrmField::get_option( $this->field, 'calc_type' )
			&& ! FrmField::get_option( $this->field, 'calc' )
		) {
			$this->field['value'] = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, $this->field['value'] );
		}

		$input_atts = array(
			'type'  => $this->html5_input_type(),
			'id'    => $args['html_id'],
			'name'  => $args['field_name'],
			'value' => $this->prepare_esc_value(),
		);

		return '<input ' . FrmAppHelper::array_to_html_params( $input_atts ) . ' ' . $input_html . ' />';
	}

	/**
	 * @since 6.20
	 *
	 * {@inheritdoc}
	 */
	public function set_value_before_save( $value ) {
		if ( FrmProCurrencyHelper::is_currency_format( FrmField::get_option( $this->field, 'format' ) ) ) {
			$value = FrmProCurrencyHelper::normalize_formatted_numbers( $this->field, $value );
		}

		return $value;
	}
}
