<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmProAppController {

	public static function load_lang() {
		load_plugin_textdomain( 'formidable-pro', false, FrmProAppHelper::plugin_folder() . '/languages/' );
	}

	public static function create_taxonomies() {
		register_taxonomy(
			'frm_tag',
			'formidable',
			array(
				'hierarchical' => false,
				'labels'       => array(
					'name'          => __( 'Formidable Tags', 'formidable-pro' ),
					'singular_name' => __( 'Formidable Tag', 'formidable-pro' ),
				),
				'public'       => true,
				'show_ui'      => true,
			)
		);

		FrmProAddonsController::maybe_disable_form_actions();
	}

	/**
	 * Strings used in the admin javascript.
	 *
	 * @since 4.06
	 */
	public static function admin_js_strings( $strings ) {
		$strings['image_placeholder_icon'] = FrmProImages::get_image_icon_markup();
		$strings['jquery_ui_url']          = FrmProAppHelper::jquery_ui_base_url();
		return $strings;
	}

	/**
	 * Set the location for the combo js
	 *
	 * @since 3.01
	 */
	public static function pro_js_location( $location ) {
		$location['new_file_path'] = FrmProAppHelper::plugin_path() . '/js';
		return $location;
	}

	/**
	 * @param array $files
	 * @return array
	 */
	public static function combine_js_files( $files ) {
		$pro_js = self::get_pro_js_files( '.min', false );
		foreach ( $pro_js as $js ) {
			$files[] = FrmProAppHelper::plugin_path() . $js['file'];
		}
		return $files;
	}

	/**
	 * @since 3.01
	 */
	public static function has_combo_js_file() {
		return is_readable( FrmProAppHelper::plugin_path() . '/js/frm.min.js' );
	}

	/**
	 * @return void
	 */
	public static function register_scripts() {
		$suffix = FrmAppHelper::js_suffix();

		if ( ! $suffix || ! self::has_combo_js_file() ) {
			$pro_js = self::get_pro_js_files( '', true );
			foreach ( $pro_js as $js_key => $js ) {
				self::register_js( $js_key, $js );
			}
		} else {
			$version = FrmProDb::$plug_version;

			if ( FrmProAppHelper::use_jquery_datepicker() ) {
				$version .= '-jquery';
			} else {
				$version .= '-flatpickr';
			}

			global $pagenow;
			wp_deregister_script( 'formidable' );
			wp_register_script( 'formidable', FrmProAppHelper::plugin_url() . '/js/frm.min.js', array( 'jquery' ), $version, true );

			$additional_js = self::additional_js_files( 'unminified' );
			foreach ( $additional_js as $js_key => $js ) {
				self::register_js( $js_key, $js );
			}
		}
		FrmAppHelper::localize_script( 'front' );

		self::localize_global_messages();
		self::set_datepicker_library_type_js();
		self::add_password_checks_data_to_js();
		FrmProStrpLiteController::maybe_register_stripe_scripts();

		wp_localize_script(
			'formidable',
			'frmCheckboxI18n',
			array( 'errorMsg' => FrmProFieldCheckbox::get_error_messages() )
		);
	}

	/**
	 * Localizes the global settings messages.
	 *
	 * @since 6.4.1
	 *
	 * @return void
	 */
	private static function localize_global_messages() {

		/**
		 * Allows turning the repeater delete confirmation on/off.
		 *
		 * @since 6.4.1
		 *
		 * @param bool $enable_repeater_row_delete_confirmation Whether a confirmation is required before deleting a repeater row.
		 */
		$enable_repeater_row_delete_confirmation = apply_filters( 'frm_enable_repeater_row_delete_confirmation', true );

		if ( $enable_repeater_row_delete_confirmation ) {
			$frmpro_settings = FrmProAppHelper::get_settings();

			if ( ! empty( $frmpro_settings->repeater_row_delete_confirmation ) ) {
				wp_add_inline_script( 'formidable', 'window.frm_js.repeaterRowDeleteConfirmation = "' . esc_js( $frmpro_settings->repeater_row_delete_confirmation ) . '";' );
			}
		}
	}

	/**
	 * Sets the datepicker library type in JS. Possible values are 'flatpickr' or 'jquery'.
	 *
	 * @since 6.19
	 *
	 * @return void
	 */
	private static function set_datepicker_library_type_js() {
		$frmpro_settings = FrmProAppHelper::get_settings();
		if ( ! empty( $frmpro_settings->datepicker_library ) ) {
			wp_add_inline_script( 'formidable', 'window.frm_js.datepickerLibrary = "' . esc_js( $frmpro_settings->datepicker_library ) . '";', 'after' );
		}
	}

	/**
	 * Adds password checks data to JS.
	 *
	 * @since 5.5.3
	 */
	private static function add_password_checks_data_to_js() {
		$field          = new stdClass();
		$field->name    = 'Password';
		$field->type    = 'password';
		$password_field = new FrmProFieldPassword( $field, 'password' );

		wp_localize_script(
			'formidable',
			'frm_password_checks',
			$password_field->password_checks()
		);
	}

	/**
	 * @since 5.0.11
	 *
	 * @param string $key
	 * @param array  $details
	 * @return void
	 */
	public static function register_js( $key, $details ) {
		wp_register_script( $key, FrmProAppHelper::plugin_url() . $details['file'], $details['requires'], $details['version'], true );
	}

	/**
	 * @since 5.0.11 added $include_dropzone parameter.
	 * @since 5.0.15 renamed $include_dropzone to $include_excluded as dropzone is no longer the only script that can be excluded.
	 *
	 * @param string $suffix
	 * @param bool   $include_excluded if true it will include dropzone and maskedinput js in the list even if excluded from the minified js.
	 * @return array
	 */
	public static function get_pro_js_files( $suffix = '', $include_excluded = false ) {
		$version = FrmProDb::$plug_version;
		if ( $suffix == '' ) {
			$suffix = FrmAppHelper::js_suffix();
		}

		$files               = array();
		$pro_js_dependencies = array( 'jquery', 'formidable' );

		if ( ! FrmProAppHelper::use_jquery_datepicker() ) {

			$files['flatpickr'] = array(
				'file'     => '/js/utils/flatpickr/flatpickr.min.js',
				'requires' => array(),
				'version'  => $version,
			);

		}

		$files['formidablepro'] = array(
			'file'     => '/js/formidablepro' . $suffix . '.js',
			'requires' => $pro_js_dependencies,
			'version'  => $version,
		);

		if ( FrmProAppHelper::use_chosen_js() ) {
			$files['jquery-chosen'] = array(
				'file'     => '/js/chosen.jquery.min.js',
				'requires' => array( 'jquery' ),
				'version'  => '1.8.7',
			);
		} else {
			$files['slimselect'] = array(
				'file'     => '/js/slimselect.min.js',
				'requires' => array(),
				'version'  => '2.8.1',
			);
		}

		$files = array_merge( $files, self::additional_js_files( $include_excluded ? 'all' : 'minified' ) );

		return $files;
	}

	/**
	 * @since 5.0.15
	 *
	 * @param string $filter_type supports 'minified', 'unminified', 'all'.
	 * @return array
	 */
	private static function additional_js_files( $filter_type ) {
		if ( 'all' === $filter_type ) {
			$include_dropzone    = true;
			$include_maskedinput = true;
			$include_intl_phone  = false;
		} else {
			$dropzone_is_in_minified_js    = apply_filters( 'frm_include_dropzone_in_minified_js', ! self::dropzone_conflict_detected() );
			$maskedinput_is_in_minified_js = apply_filters( 'frm_include_maskedinput_in_minified_js', ! self::maskedinput_conflict_detected() );
			$intlphone_is_in_minified_js   = apply_filters( 'frm_include_intlphone_in_minified_js', ! self::intlphone_conflict_detected() );

			if ( 'minified' === $filter_type ) {
				$include_dropzone    = $dropzone_is_in_minified_js;
				$include_maskedinput = $maskedinput_is_in_minified_js;
				$include_intl_phone  = $intlphone_is_in_minified_js;
			} else {
				$include_dropzone    = ! $dropzone_is_in_minified_js;
				$include_maskedinput = ! $maskedinput_is_in_minified_js;
				$include_intl_phone  = false;
			}
		}

		$files = array();
		if ( $include_dropzone ) {
			$files['dropzone'] = self::get_dropzone_js_details();
		}

		if ( $include_maskedinput ) {
			$files['jquery-maskedinput'] = array(
				'file'     => '/js/jquery.maskedinput.min.js',
				'requires' => array( 'jquery' ),
				'version'  => '1.4',
			);
		}

		if ( $include_intl_phone ) {
			$files = array_merge( $files, self::get_intl_phone_js_details() );
		}

		return $files;
	}

	/**
	 * Get details for dropzone script including file path, dependencies, and version.
	 *
	 * @since 6.0
	 *
	 * @return array {
	 *     @type string $file
	 *     @type array  $requires
	 *     @type string $version
	 * }
	 */
	public static function get_dropzone_js_details() {
		return array(
			'file'     => '/js/dropzone.min.js',
			'requires' => array( 'jquery' ),
			'version'  => '5.9.3',
		);
	}

	/**
	 * @since 6.9.1
	 *
	 * @return array<array>
	 */
	public static function get_intl_phone_js_details() {
		return array(
			'intl-tel-input'       => array(
				'file'     => '/js/intl-tel-input.min.js',
				'requires' => array(),
				'version'  => '21.0.8',
			),
			'intl-tel-input-utils' => array(
				'file'     => '/js/intl-tel-input-utils.min.js',
				'requires' => array( 'intl-tel-input' ),
				'version'  => '21.0.8',
			),
		);
	}

	/**
	 * @since 5.0.15
	 *
	 * @return bool
	 */
	private static function dropzone_conflict_detected() {
		$buddyboss_active = function_exists( 'buddypress' );
		return $buddyboss_active;
	}

	/**
	 * @since 5.0.15
	 *
	 * @return bool
	 */
	private static function maskedinput_conflict_detected() {
		$woocommerce_stripe_gateway_active = function_exists( 'woocommerce_gateway_stripe' );
		return $woocommerce_stripe_gateway_active;
	}

	/**
	 * @since 6.9.1
	 *
	 * @return bool
	 */
	private static function intlphone_conflict_detected() {
		$memberpress_is_active = function_exists( 'mepr_plugin_info' );
		return $memberpress_is_active;
	}

	/**
	 * @since 2.05.07
	 */
	public static function admin_bar_configure() {
		if ( is_admin() || ! current_user_can( 'frm_edit_forms' ) ) {
			return;
		}

		self::maybe_change_post_link();

		$actions = array();

		self::add_entry_to_admin_bar( $actions );

		if ( empty( $actions ) ) {
			return;
		}

		self::maybe_add_parent_admin_bar();

		global $wp_admin_bar;

		foreach ( $actions as $id => $action ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'frm-forms',
					'title'  => $action['name'],
					'href'   => $action['url'],
					'id'     => 'edit_' . $id,
				)
			);
		}
	}

	/**
	 * If the post is edited by the entry, use the entry edit link
	 * instead of the post link.
	 *
	 * @since 4.0
	 */
	private static function maybe_change_post_link() {
		global $wp_admin_bar, $post;

		if ( ! $post ) {
			return;
		}

		$display_id = get_post_meta( $post->ID, 'frm_display_id', true );
		if ( empty( $display_id ) ) {
			return;
		}

		$entry_id  = FrmDb::get_var( 'frm_items', array( 'post_id' => $post->ID ) );
		$edit_node = $wp_admin_bar->get_node( 'edit' );
		if ( ! empty( $edit_node ) && $entry_id ) {
			$edit_node->href = admin_url( 'admin.php?page=formidable-entries&frm_action=edit&id=' . $entry_id );
			$wp_admin_bar->add_node( $edit_node );
		}
	}

	/**
	 * @since 2.05.07
	 */
	private static function maybe_add_parent_admin_bar() {
		global $wp_admin_bar;
		$has_node = $wp_admin_bar->get_node( 'frm-forms' );
		if ( ! $has_node ) {
			FrmFormsController::add_menu_to_admin_bar();
		}
	}

	/**
	 * @since 2.05.07
	 *
	 * @param array $actions
	 * @return void
	 */
	private static function add_entry_to_admin_bar( &$actions ) {
		global $post;

		if ( is_singular() && ! empty( $post ) ) {
			$entry_id = FrmDb::get_var( 'frm_items', array( 'post_id' => $post->ID ), 'id' );
			if ( ! empty( $entry_id ) ) {
				$actions[ 'entry_' . $entry_id ] = array(
					'name' => __( 'Edit Entry', 'formidable' ),
					'url'  => FrmProEntry::admin_edit_link( $entry_id ),
				);
			}
		}
	}

	public static function form_nav( $nav, $atts ) {
		$form_id = absint( $atts['form_id'] );

		$has_entries = FrmDb::get_var( 'frm_items', array( 'form_id' => $form_id ) );
		if ( $has_entries ) {
			$reports = array(
				'link'       => admin_url( 'admin.php?page=formidable&frm_action=reports&form=' . $form_id . '&show_nav=1' ),
				'label'      => __( 'Reports', 'formidable-pro' ),
				'current'    => array( 'reports' ),
				'page'       => 'formidable',
				'permission' => 'frm_view_reports',
			);
			$nav[]   = $reports;
		}

		return $nav;
	}

	/**
	 * Change the icon on the menu if set
	 *
	 * @since 3.05
	 */
	public static function whitelabel_icon( $icon, $use_svg = false ) {
		$class = self::get_icon_class();
		if ( empty( $class ) ) {
			return $icon;
		}

		$icon = str_replace( 'dashicons ', '', $class );
		$icon = str_replace( 'frmfont ', '', $icon );
		if ( $icon === 'frm_white_label_icon' ) {
			$svg = self::whitelabel_svg();
			if ( $use_svg ) {
				return $svg;
			}
			$icon = 'data:image/svg+xml;base64,' . base64_encode( $svg );
		}

		return $icon;
	}

	private static function whitelabel_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" fill="#929699" width="25" height="25"><path d="M2.777 0A2.776 2.776 0 0 0 0 2.777v19.446A2.796 2.796 0 0 0 2.777 25h19.446A2.796 2.796 0 0 0 25 22.223V2.777A2.776 2.776 0 0 0 22.223 0Zm1.391 2.777a1.388 1.388 0 0 1 0 2.778 1.388 1.388 0 1 1 0-2.778Zm4.164 0a1.389 1.389 0 1 1 .004 2.778 1.389 1.389 0 0 1-.004-2.778Zm4.168 0h9.723v2.778H12.5ZM2.777 8.332h19.446v13.89H2.777Zm2.778 2.777v2.782h13.613v-2.782Zm0 5.559v2.777H12.5v-2.777Zm0 0" style="stroke:none;fill-rule:nonzero;fill:#1b2023;fill-opacity:1"/></svg>';
	}

	/**
	 * Change the icon on the editor button if set
	 *
	 * @since 3.05
	 */
	public static function whitelabel_media_icon( $icon ) {
		$class = self::get_icon_class();
		if ( ! empty( $class ) ) {
			$icon = '<span class="' . esc_attr( $class ) . ' wp-media-buttons-icon"></span>';
		}
		return $icon;
	}

	/**
	 * @since 3.05
	 */
	private static function get_icon_class() {
		$settings = FrmProAppHelper::get_settings();
		return $settings->menu_icon;
	}

	public static function drop_tables( $tables ) {
		global $wpdb;
		$tables[] = $wpdb->prefix . 'frm_display';
		return $tables;
	}

	public static function set_get( $atts, $content = '' ) {
		if ( empty( $atts ) ) {
			return;
		}

		if ( isset( $atts['param'] ) && $content !== '' ) {
			$atts[ $atts['param'] ] = do_shortcode( $content );
			unset( $atts['param'] );
		}

		foreach ( $atts as $att => $val ) {
			$_GET[ $att ] = $val;
			unset( $att, $val );
		}
	}

	/**
	 * Returns an array of attribute names and associated methods for processing conditions
	 *
	 * @return array
	 */
	private static function get_methods_for_frm_condition_shortcode() {
		$methods = array(
			'stats'       => array( 'FrmProStatisticsController', 'stats_shortcode' ),
			'field-value' => array( 'FrmProEntriesController', 'get_field_value_shortcode' ),
			'param'       => array( 'FrmFieldsHelper', 'process_get_shortcode' ),
		);

		return apply_filters( 'frm_condition_methods', $methods );
	}

	/**
	 * Returns an array of atts with any conditions removed
	 *
	 * @return array
	 */
	private static function remove_conditions_from_atts( $atts ) {
		$conditions = FrmProContent::get_conditions();

		foreach ( $conditions as $condition ) {
			if ( isset( $atts[ $condition ] ) ) {
				unset( $atts[ $condition ] );
			}
		}
		unset( $condition );

		return $atts;
	}

	/**
	 * Retrieves the value of the left side of the conditional in the frm-condition shortcode
	 *
	 * @param $atts
	 *
	 * @return array|bool|mixed|object|string|null
	 */
	private static function get_value_for_frm_condition_shortcode( $atts ) {
		$value  = '';
		$source = 'stats';
		if ( isset( $atts['source'] ) ) {
			$source = $atts['source'] ? $atts['source'] : $source;
			unset( $atts['source'] );
		}

		$methods         = self::get_methods_for_frm_condition_shortcode();
		$processing_atts = self::remove_conditions_from_atts( $atts );

		if ( isset( $methods[ $source ] ) ) {
			$value = call_user_func( $methods[ $source ], $processing_atts );
		} else {
			global $shortcode_tags;
			if ( isset( $shortcode_tags[ $source ] ) && is_callable( $shortcode_tags[ $source ] ) ) {
				$content = isset( $atts['content'] ) ? $atts['content'] : '';
				$value   = call_user_func( $shortcode_tags[ $source ], $processing_atts, $content, $source );
			}
		}

		return $value;
	}

	/**
	 * Conditional shortcode, used with stats, field values, and params or any other shortcode.
	 *
	 * @since 3.01
	 *
	 * @param $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public static function frm_condition_shortcode( $atts, $content = '' ) {
		$value       = self::get_value_for_frm_condition_shortcode( $atts );
		$new_content = FrmProContent::conditional_replace_with_value( $value, $atts, '', 'custom' );

		if ( $new_content === '' ) {
			return '';
		}

		$content = do_shortcode( $content );
		return $content;
	}

	/**
	 * @return void
	 */
	public static function admin_init() {
		if ( FrmAppHelper::is_admin_page( 'formidable-entries' ) && 'destroy_all' === FrmAppHelper::get_param( 'frm_action' ) ) {
			FrmProEntriesController::destroy_all();
			die();
		}

		FrmProDashboardController::init();

		if ( FrmAppHelper::is_admin_page( 'formidable-entries' ) && 'duplicate' === FrmAppHelper::get_param( 'frm_action' ) ) {
			FrmProEntriesController::duplicate();
		}

		if ( ! FrmProAppHelper::views_is_installed() && self::there_are_views_in_the_database() ) {
			$action = FrmAppHelper::get_param( 'frm_action' );
			if ( ! $action ) {
				if ( ! get_option( 'frm_missing_views_dismissed' ) ) {
					add_filter( 'frm_message_list', 'FrmProAppController::missing_views_notice' );
				}
			} elseif ( 'frm_dismiss_missing_views_message' === $action ) {
				update_option( 'frm_missing_views_dismissed', true, 'no' );
				wp_safe_redirect( admin_url( 'admin.php?page=formidable' ) );
				exit;
			}
		}

		if ( FrmAppHelper::is_admin_page( 'formidable-settings' ) ) {
			$version = FrmProDb::$plug_version;
			wp_register_script( 'formidable_pro_license_listener', FrmProAppHelper::plugin_url() . '/js/admin/settings/license.js', array( 'wp-hooks' ), $version, true );
			add_action(
				'admin_enqueue_scripts',
				function () {
					wp_enqueue_script( 'formidable_pro_license_listener' );
				}
			);
		}

		self::maybe_load_admin_js();
		self::remove_upsells();
		self::set_inbox_notice_for_flatpickr();
	}

	/**
	 * Returns array of 'frm_action' values that trigger loading common admin js.
	 *
	 * @since 6.17
	 * @return array
	 */
	private static function pages_loading_common_admin_js() {
		return array( 'edit', 'settings' );
	}

	/**
	 * Loads admin js for common pages.
	 *
	 * @since 6.17
	 *
	 * @param string $action Value of 'frm_action' parameter.
	 * @return void
	 */
	private static function load_common_admin_js( $action ) {
		if ( ! in_array( $action, self::pages_loading_common_admin_js(), true ) ) {
			return;
		}
		wp_enqueue_script( 'formidable_pro_admin_common' );
	}

	/**
	 * Init admin head. It's called via action hook admin_head.
	 *
	 * @since 6.5.1
	 *
	 * @return void
	 */
	public static function admin_init_head() {
		FrmProAddonsController::show_warning_overlay_for_expired_or_null_license();
	}

	/**
	 * @since 5.0.17
	 *
	 * @return void
	 */
	private static function maybe_load_admin_js() {
		if ( FrmAppHelper::doing_ajax() ) {
			return;
		}
		self::maybe_enqueue_styles_for_admin_page_action( 'formidable-entries' );
		self::maybe_enqueue_styles_for_admin_page_action( 'formidable', 'reports' );

		// Check if we're on the 'Form Templates' page with the right permissions.
		if ( method_exists( 'FrmFormTemplatesController', 'is_templates_page' ) && FrmFormTemplatesController::is_templates_page() ) {
			self::enqueue_list_script();
		}

		if ( FrmAppHelper::is_admin_page( 'formidable-entries' ) ) {
			self::register_and_enqueue_admin_script( 'entries', array( 'formidable_admin', 'wp-i18n', 'jquery' ) );
			$form_id = FrmAppHelper::get_param( 'form', 0, 'absint' );

			wp_localize_script(
				'formidable_pro_entries',
				'frmEntriesData',
				array(
					'hasPostAction' => FrmFormAction::form_has_action_type( $form_id, 'wppost' ) ? 1 : 0,
				)
			);

			return;
		}

		// Exit if not on a Formidable admin page.
		if ( ! FrmAppHelper::is_admin_page( 'formidable' ) ) {
			return;
		}

		$action  = FrmAppHelper::get_param( 'frm_action' );
		$version = FrmProDb::$plug_version;
		wp_register_script( 'formidable_pro_admin_common', FrmProAppHelper::plugin_url() . '/js/admin/common.js', array(), $version, true );
		self::load_common_admin_js( $action );
		if ( in_array( $action, array( 'edit', 'duplicate' ), true ) ) {

			self::register_admin_script( 'builder', array( 'formidable_admin' ) );

			$form_id = FrmAppHelper::simple_get( 'id', 'absint' );
			$form    = FrmForm::getOne( $form_id );

			$currency                 = FrmProCurrencyHelper::get_currency( $form );
			$currency['symbol_left']  = html_entity_decode( $currency['symbol_left'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$currency['symbol_right'] = html_entity_decode( $currency['symbol_right'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			$vars = array(
				'currency' => $currency,
			);
			wp_localize_script( 'formidable_pro_builder', 'frmProBuilderVars', $vars );

			self::enqueue_script( 'builder' );

			self::register_and_enqueue_style( 'builder' );
			self::maybe_register_and_enqueue_expired_script();
		} elseif ( in_array( $action, array( 'settings', 'update_settings', 'reports' ), true ) ) {
			self::register_and_enqueue_admin_script( 'update_settings' === $action ? 'settings' : $action );
			self::maybe_register_and_enqueue_expired_script();
		} elseif ( self::on_form_listing_page() ) {
			self::enqueue_list_script();
		}
	}

	/**
	 * Enqueues list script for application management.
	 *
	 * @return void Exits early if the user lacks editing permissions.
	 */
	private static function enqueue_list_script() {
		// Exit if the user can't edit applications.
		if ( ! FrmProApplicationsHelper::current_user_can_edit_applications() ) {
			return;
		}

		// Register list script.
		self::register_admin_script( 'list' );

		// If 'applicationId' is in the URL, fetch application data.
		$application_id = FrmAppHelper::simple_get( 'applicationId', 'absint' );
		if ( $application_id ) {
			$application = get_term( $application_id, 'frm_application' );
			if ( $application instanceof WP_Term ) {
				wp_localize_script(
					'formidable_pro_list',
					'frmAutocompleteApplicationVars',
					array(
						'name' => $application->name,
					)
				);
			}
		}

		// Enqueue list script.
		self::enqueue_script( 'list' );
	}

	/**
	 * Enqueues style for specific admin page and also action, if it is provided.
	 *
	 * @param string $page
	 * @param string $frm_action
	 */
	private static function maybe_enqueue_styles_for_admin_page_action( $page, $frm_action = '' ) {
		if ( ! FrmAppHelper::is_admin_page( $page ) ) {
			return;
		}
		if ( $frm_action ) {
			if ( FrmAppHelper::simple_get( 'frm_action', 'sanitize_title' ) !== $frm_action ) {
				return;
			}
			$frm_action = '-' . $frm_action;
		}
		$plugin_url = FrmProAppHelper::plugin_url();
		$version    = FrmProDb::$plug_version;
		wp_enqueue_style( 'formidable-pro-admin', $plugin_url . '/css/admin/' . $page . $frm_action . '.css', array(), $version );
	}

	/**
	 * @since 5.5.1
	 *
	 * @return void
	 */
	private static function maybe_register_and_enqueue_expired_script() {
		if ( FrmProAddonsController::is_expired_outside_grace_period() ) {
			self::register_and_enqueue_admin_script( 'expired', array( 'formidable_dom' ) );
		}
	}

	/**
	 * Check if active page is the form list table page.
	 *
	 * @since 5.3.1
	 *
	 * @return bool
	 */
	private static function on_form_listing_page() {
		return FrmAppHelper::on_form_listing_page();
	}

	/**
	 * Add a script from the /js/admin folder for specific admin pages.
	 *
	 * @param string $script
	 * @param array  $dependencies
	 * @return void
	 */
	private static function register_and_enqueue_admin_script( $script, $dependencies = array( 'formidable_admin' ) ) {
		self::register_admin_script( $script, $dependencies );
		self::enqueue_script( $script );
	}

	/**
	 * Register JavaScript in /js/admin/ folder.
	 *
	 * @since 5.3
	 *
	 * @param string $script
	 * @param array  $dependencies
	 * @return void
	 */
	private static function register_admin_script( $script, $dependencies = array( 'formidable_admin' ) ) {
		$version = FrmProDb::$plug_version;
		wp_register_script( 'formidable_pro_' . $script, FrmProAppHelper::plugin_url() . '/js/admin/' . $script . '.js', $dependencies, $version, true );
	}

	/**
	 * Enqueue JavaScript
	 *
	 * @since 5.3
	 *
	 * @param string $script
	 * @return void
	 */
	private static function enqueue_script( $script ) {
		wp_enqueue_script( 'formidable_pro_' . $script );
	}

	/**
	 * @param string $style
	 * @return void
	 */
	private static function register_and_enqueue_style( $style ) {
		$version = FrmProDb::$plug_version;
		wp_register_style( 'formidable-pro-' . $style, FrmProAppHelper::plugin_url() . '/css/' . $style . '.css', array(), $version );
		wp_enqueue_style( 'formidable-pro-' . $style );
	}

	private static function there_are_views_in_the_database() {
		return (bool) FrmDb::get_var( 'posts', array( 'post_type' => 'frm_display' ) );
	}

	/**
	 * @since 4.09
	 * @param array $messages
	 * @return array
	 */
	public static function missing_views_notice( $messages ) {
		$download = FrmProAddonsController::install_link( 'views' );
		if ( ! $download ) {
			return $messages;
		}

		$is_url = isset( $download['url'] ) && $download['status'] === 'not-installed';
		if ( $is_url ) {
			$link        = '<a class="' . esc_attr( $download['class'] ) . ' button button-primary frm-button-primary" rel="' . esc_attr( $download['url'] ) . '" aria-label="' . esc_attr__( 'Install', 'formidable' ) . '">Install Views</a>';
			$link       .= '<span class="addon-status-label" id="frm-welcome"></a>';
			$dismiss_url = admin_url( 'admin.php?page=formidable&frm_action=frm_dismiss_missing_views_message' );
			$messages[]  = 'Formidable Views are not active! Download now or click <a href="' . esc_url( $dismiss_url ) . '">here</a> to dismiss this message. <br/><br/>' . $link;
		}
		return $messages;
	}

	/**
	 * @since 3.04.02
	 */
	public static function remove_upsells() {
		FrmAppController::remove_upsells();
	}

	/**
	 * Add an inbox notice if the user is using the "Default" Date Picker Library.
	 * This encourages people to try the Flatpickr beta.
	 *
	 * @since 6.19
	 *
	 * @return void
	 */
	public static function set_inbox_notice_for_flatpickr() {
		if ( ! FrmAppHelper::is_formidable_admin() ) {
			return;
		}

		$frmpro_settings = FrmProAppHelper::get_settings();
		if ( ! empty( $frmpro_settings->datepicker_library ) && 'default' !== $frmpro_settings->datepicker_library ) {
			return;
		}

		$message = array(
			'key'     => 'try-flatpickr',
			'subject' => 'New! - Try Flatpickr',
			'message' => __( 'New! Date fields now support Flatpickr. While flatpickr is in beta, fields will continue to use jQuery Datepicker by default. To use Flatpickr, please update the Date Picker Library setting in Global Settings.', 'formidable-pro' ),
			'cta'     => '<a href="' . esc_url( admin_url( 'admin.php?page=formidable-settings' ) ) . '">' . esc_html__( 'Update Now', 'formidable-pro' ) . '</a>',
			'type'    => 'news',
		);

		$inbox = new FrmInbox();
		$inbox->add_message( $message );
	}

	/**
	 * Show a message if Pro is installed but not activated.
	 *
	 * @since 3.06.02
	 *
	 * @return void
	 */
	public static function admin_notices() {
		$is_settings_page = FrmAppHelper::simple_get( 'page', 'sanitize_text_field' ) === 'formidable-settings';
		if ( $is_settings_page ) {
			return;
		}
		?>
		<div class="error">
			<p>
			<?php
			printf(
				/* translators: %1$s: Start link HTML, %2$s: End link HTML */
				esc_html__( 'Formidable Forms installed, but not yet activated. %1$sAdd your license key now%2$s to start enjoying all the premium features.', 'formidable' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=formidable-settings' ) ) . '">',
				'</a>'
			);
			?>
			</p>
		</div>
		<?php
	}

	/**
	 * Loads admin JS assets.
	 *
	 * @since 4.06.02
	 */
	public static function load_admin_js_assets() {
		/**
		 * We want these assets to load only on the `settings` page
		 * under form settings.
		 */
		if ( 'settings' === FrmAppHelper::simple_get( 'frm_action', 'sanitize_title' ) ) {
			wp_enqueue_media();
			wp_register_script( 'email-attachment', self::get_settings_js_url() . 'email-attachment.js', array( 'jquery' ), FrmProDb::$plug_version, true );
			wp_enqueue_script( 'email-attachment' );
		}
	}

	/**
	 * @return string
	 */
	private static function get_settings_js_url() {
		return FrmProAppHelper::plugin_url() . '/js/admin/settings/';
	}

	/**
	 * @return void
	 */
	public static function load_style_manager_js_assets() {
		$version = FrmProDb::$plug_version;
		wp_enqueue_media(); // Required for the bg image file upload.

		if ( FrmAppHelper::is_style_editor_page( 'edit' ) ) {
			wp_enqueue_script( 'wp-color-picker-alpha', self::get_settings_js_url() . 'wp-color-picker-alpha.js', array( 'wp-color-picker' ), '3.0.2', true );
		}

		$dependencies = array( 'jquery', 'wp-i18n', 'wp-hooks', 'formidable_dom' );
		if ( class_exists( 'FrmStylesPreviewHelper' ) ) { // This class only exists after the visual styler update in v6.0.
			$dependencies[] = 'formidable_style';
		}

		wp_register_script( 'formidable_pro_style_settings', self::get_settings_js_url() . 'style-settings.js', $dependencies, $version, true );

		self::preload_svgs_for_style_settings();

		wp_enqueue_script( 'formidable_pro_style_settings' );
	}

	/**
	 * Preloads SVG icons for live updating in style settings.
	 *
	 * @since 6.4.2
	 */
	private static function preload_svgs_for_style_settings() {
		$svgs      = array();
		$svg_names = array(
			'frm_plus_icon',
			'frm_plus1_icon',
			'frm_plus2_icon',
			'frm_plus3_icon',
			'frm_plus4_icon',
			'frm_minus_icon',
			'frm_minus1_icon',
			'frm_minus2_icon',
			'frm_minus3_icon',
			'frm_minus4_icon',
			'frm_arrowdown_icon',
			'frm_arrowdown1_icon',
			'frm_arrowdown2_icon',
			'frm_arrowdown3_icon',
			'frm_arrowdown4_icon',
			'frm_arrowdown5_icon',
			'frm_arrowdown6_icon',
		);

		foreach ( $svg_names as $svg_name ) {
			$svgs[ $svg_name ] = FrmProAppHelper::get_svg_icon( $svg_name, 'frmsvg' );
		}

		wp_localize_script( 'formidable_pro_style_settings', 'frmProStyleSettingsSVGs', $svgs );
	}

	/**
	 * Updates the default stylesheet.
	 *
	 * @since 6.4.1
	 */
	public static function update_stylesheet() {
		$frm_style = new FrmStyle();
		$frm_style->update( 'default' );
	}

	/**
	 * Load a script that is loaded whenever formidable_admin.js is.
	 * This is used for the Inbox Slide-In, and may be used for other features in the future.
	 *
	 * @since 6.8.4
	 *
	 * @return void
	 */
	public static function load_floating_links_js() {
		if ( ! wp_script_is( 'formidable_dom', 'registered' ) ) {
			wp_register_script( 'formidable_dom', FrmAppHelper::plugin_url() . '/js/admin/dom.js', array( 'jquery', 'jquery-ui-dialog', 'wp-i18n' ), FrmAppHelper::plugin_version(), true );
		}

		$plugin_url   = FrmProAppHelper::plugin_url();
		$dependencies = array( 's11-floating-links', 'wp-hooks', 'formidable_dom' );
		$version      = FrmProDb::$plug_version;
		wp_register_script( 'frm_pro_floating_links', $plugin_url . '/js/admin/floating-links.js', $dependencies, $version, true );
		wp_enqueue_script( 'frm_pro_floating_links' );
	}

	/**
	 * @since 6.8.4
	 *
	 * @param array $keys
	 * @return array
	 */
	public static function inbox_slidein_js_vars( $keys ) {
		$keys[] = 'image';
		return $keys;
	}
}
