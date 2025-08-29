<?php
class FrmDatesAppController {

	public static function pro_not_installed_notice() {
		?>
	<div class="error">
		<p><?php esc_html_e( 'Formidable Dates requires Formidable Forms Pro to be installed.', 'frmdates' ); ?></p>
	</div>
		<?php
	}

	public static function enqueue_scripts() {
		$suffix = FrmAppHelper::js_suffix();
		wp_enqueue_script( 'frmdates', FrmDatesAppHelper::get_url( '/js/frmdates' . $suffix . '.js' ), array( 'jquery' ), FrmDatesAppHelper::plugin_version(), true );
	}

	public static function load_date_js( $args ) {
		if ( 'date' === $args['field']->type ) {
			global $frm_vars;

			if ( isset( $frm_vars['datepicker_loaded'] ) && ! empty( $frm_vars['datepicker_loaded'] ) ) {
				self::enqueue_scripts();
			}
		}
	}

	/**
	 * Maybe enqueue date JS when rendering form to fix the date calculation error in a multi-pages form.
	 *
	 * @since 2.0.4
	 *
	 * @param array $params See `frm_enqueue_form_scripts` hook.
	 */
	public static function maybe_enqueue_date_js_earlier( $params ) {
		// Check if is a multi-pages form.
		if ( ! FrmProFormsHelper::has_field( 'break', $params['form_id'] ) ) {
			return;
		}

		// Get all dates field, enqueue if one of them has date calculation.
		$date_fields = FrmField::get_all_types_in_form( $params['form_id'], 'date' );
		if ( ! $date_fields ) {
			return;
		}

		foreach ( $date_fields as $date_field ) {
			if ( ! empty( $date_field->field_options['date_calc'] ) ) {
				self::enqueue_scripts();
				break;
			}
		}
	}

	/**
	 * Maybe enqueue frontend scripts on backend entry pages. This should run before FrmAppController::admin_js() to
	 * enqueue date JS before frm.min.js.
	 *
	 * @since 2.0.4
	 */
	public static function maybe_enqueue_frontend_scripts() {
		if ( ! FrmAppHelper::js_suffix() || ! FrmProAppController::has_combo_js_file() ) {
			// This issue only happen with frm.min.js.
			return;
		}

		$page = FrmAppHelper::simple_get( 'page', 'sanitize_title' );
		if ( 'formidable-entries' === $page ) {
			self::enqueue_scripts();
		}
	}

	public static function enqueue_admin_assets() {
		if ( ! FrmAppHelper::is_admin_page( 'formidable' ) ) {
			return;
		}

		wp_enqueue_style( 'jquery-theme', FrmProStylesController::jquery_css_url( '' ), array(), FrmAppHelper::plugin_version() );
		wp_enqueue_style( 'formidable-pro-fields', admin_url( 'admin-ajax.php?action=pro_fields_css' ), array(), FrmAppHelper::plugin_version() );
		wp_enqueue_style( 'frmdates_admin', FrmDatesAppHelper::get_url( '/css/admin.css' ), array(), FrmDatesAppHelper::plugin_version() );

		wp_register_script( 'frmdates_admin', FrmDatesAppHelper::get_url( '/js/admin.js' ), array( 'jquery-ui-datepicker', 'jquery-effects-highlight', 'wp-hooks' ), FrmDatesAppHelper::plugin_version() );

		$frmpro_settings = FrmProAppHelper::get_settings();
		$script_strings = array(
			'itemTemplate' => FrmDatesTemplatesHelper::settings_render_dates_list_item(
				array(
					'date'           => '%DATE%',
					'formatted_date' => '%DATE_WITH_FORMAT%',
					'input_name'     => '%DATE_TYPE%_%FIELD_ID%',
				)
			),
			'dateFormat'   => $frmpro_settings->cal_date_format,
		);
		wp_localize_script( 'frmdates_admin', 'frmdates_admin_js', $script_strings );

		wp_enqueue_script( 'frmdates_admin' );
	}

	public static function add_settings_to_form( $field, $display, $values ) {
		$field_id = absint( $field['id'] );
		$form_id  = absint( $field['form_id'] );

		$date_fields = array();
		foreach ( FrmField::get_all_types_in_form( $form_id, 'date', '', 'include' ) as $date_field ) {
			if ( $date_field->id == $field_id ) {
				continue;
			}

			$date_fields[ $date_field->field_key ] = $date_field->name;
		}

		$min_max_dates_labels = array(
			'minimum_date' => __( 'Minimum', 'frmdates' ),
			'maximum_date' => __( 'Maximum', 'frmdates' ),
		);

		$hide_min_max         = empty( $field['minimum_date_cond'] ) && empty( $field['maximum_date_cond'] );
		$all_days_of_the_week = empty( $field['days_of_the_week'] ) || 7 === count( $field['days_of_the_week'] );

		if ( ! empty( $field['locale'] ) && 'en' !== $field['locale'] ) {
			wp_enqueue_script( 'jquery-ui-i18n-' . $field['locale'], FrmProAppHelper::plugin_url() . '/js/jquery-ui-i18n/datepicker-' . $field['locale'] . '.min.js', array( 'jquery-ui-core', 'jquery-ui-datepicker' ), '1.13.2' );
		}

		include FrmDatesAppHelper::get_path( '/views/date-field-settings.php' );
	}

	public static function date_field_options_js( $js_options, $extra ) {
		$field_key = str_replace( array( 'field_', '^' ), '', $extra['field_id'] );
		$field     = FrmField::getOne( $field_key );

		if ( ! $field ) {
			return $js_options;
		}

		$js_options['fieldId'] = $field->id;

		if ( ! FrmDatesField::field_has_custom_opts( $field ) && empty( $field->field_options['unique'] ) ) {
			return $js_options;
		}

		$inline = FrmField::get_option( $field, 'display_inline' );
		if ( $inline ) {
			// TODO: Adjust for repeating sections.
			$js_options['options']['altField'] = '#field_' . $field->field_key . '_alt';
		}

		$constraints                    = self::get_constraints_for_field( $field );
		$js_options['formidable_dates'] = array(
			'inline'             => (bool) $inline,
			'daysEnabled'        => $constraints['days'],
			'datesEnabled'       => $constraints['exceptions'],
			'datesDisabled'      => apply_filters( 'frm_dates_disabled', $constraints['blackout_dates'], $field ),
			'minimum_date_cond'  => FrmField::get_option( $field, 'minimum_date_cond' ),
			'minimum_date_val'   => FrmField::get_option( $field, 'minimum_date_val' ),
			'maximum_date_cond'  => FrmField::get_option( $field, 'maximum_date_cond' ),
			'maximum_date_val'   => FrmField::get_option( $field, 'maximum_date_val' ),
			'selectableResponse' => apply_filters( 'frm_dates_selectable_response', true, $field ),

			/**
			 * Skips blocked dates from date calculation.
			 *
			 * @since 2.0.4
			 *
			 * @param bool   $skip  Set to `true` to skip.
			 * @param object $field Field object.
			 */
			'skipBlockedDatesFromCalc' => apply_filters( 'frm_dates_skip_blocked_dates_from_calc', true, $field ),
		);

		return $js_options;
	}

	private static function get_constraints_for_field( $field ) {
		$days           = FrmField::get_option( $field, 'days_of_the_week' );
		$blackout_dates = FrmField::get_option( $field, 'blackout_dates' );
		$exceptions     = FrmField::get_option( $field, 'excepted_dates' );

		if ( empty( $days ) ) {
			$days = range( 0, 6 );
		} else {
			$days = array_map( 'absint', $days );
		}

		return compact( 'days', 'blackout_dates', 'exceptions' );
	}

	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			include FrmDatesAppHelper::get_path( '/classes/models/FrmDatesUpdate.php' );
			FrmDatesUpdate::load_hooks();
		}
	}

	/**
	 * Show a tooltip icon with the message passed.
	 *
	 * @since 2.1
	 *
	 * @param string $message The message to be displayed in the tooltip.
	 * @param array  $atts    The attributes to be added to the tooltip.
	 *
	 * @return void
	 */
	public static function show_svg_tooltip( $message, $atts = array() ) {
		if ( ! is_callable( 'FrmAppHelper::tooltip_icon' ) ) {
			return;
		}
		FrmAppHelper::tooltip_icon( $message, $atts );
	}
}
