<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * App Controller.
 */
class FrmSigAppController {

	/**
	 * Load lang.
	 *
	 * @return void
	 */
	public static function load_lang() {
		$plugin_folder = FrmSigAppHelper::plugin_folder();
		load_plugin_textdomain( 'frmsig', false, $plugin_folder . '/languages/' );
	}

	/**
	 * Migrate settings if needed.
	 *
	 * @since 2.0
	 *
	 * @return  void
	 */
	public static function initialize() {
		if ( ! FrmSigAppHelper::is_formidable_compatible() ) {
			return;
		}

		new FrmSigDb();

		add_action( 'admin_head-toplevel_page_formidable', 'FrmSigAppController::enqueue_styles' );
	}

	/**
	 * Display a warning in the admin if Formidable is not activated or if it is not compatible.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public static function display_admin_notices() {
		// Don't display notices as we're upgrading.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'upgrade-plugin' === $action && ! isset( $_GET['activate'] ) ) {
			return;
		}

		// Show message if Formidable is not compatible.
		if ( ! FrmSigAppHelper::is_formidable_compatible() ) {
			include FrmSigAppHelper::plugin_path() . '/views/update_formidable.php';
		}
	}

	/**
	 * Print a notice if Formidable is too old to be compatible with the signature add-on.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public static function min_version_notice() {
		if ( FrmSigAppHelper::is_formidable_compatible() ) {
			return;
		}

		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		echo '<tr class="plugin-update-tr active"><th colspan="' . absint( $wp_list_table->get_column_count() ) . '" class="check-column plugin-update colspanchange"><div class="update-message">' .
			 esc_html__( 'You are running an outdated version of Formidable. This plugin will not work correctly if you do not update Formidable.', 'frmsig' ) .
			 '</div></td></tr>';
	}

	/**
	 * Register scripts.
	 *
	 * @param array $form form.
	 * @return array
	 */
	public static function register_scripts( $form ) {
		add_action( 'frm_load_ajax_field_scripts', 'FrmSigAppController::maybe_load_scripts' );
		add_action( 'wp_print_footer_scripts', 'FrmSigAppController::footer_js', 0 );
		add_action( 'admin_footer', 'FrmSigAppController::footer_js', 20 );

		self::register_assets();

		return $form;
	}

	/**
	 * Register signature field assets.
	 *
	 * @since 3.0 Removed old signature script.
	 * @since 2.01
	 *
	 * @return void
	 */
	private static function register_assets() {
		$url = FrmSigAppHelper::plugin_url();

		$version = FrmSigAppHelper::plugin_version();
		wp_register_script( 'frm-signature', $url . '/js/frm.signature.min.js', array( 'jquery' ), $version, true );
	}

	/**
	 * Used for the form builder page so the styles will be ready for a new field.
	 *
	 * @since 2.01
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		self::register_assets();

		$url     = FrmSigAppHelper::plugin_url();
		$version = FrmSigAppHelper::plugin_version();
		wp_register_style( 'jquery-signaturepad', $url . '/css/jquery.signaturepad.css', array(), $version );
		wp_enqueue_style( 'jquery-signaturepad' );
	}

	/**
	 * Load the scripts on the first page of the form.
	 *
	 * @since 2.0
	 *
	 * @param array|object $atts attributes.
	 *
	 * @return void
	 */
	public static function maybe_load_scripts( $atts ) {
		if ( 'signature' !== $atts['field']->type ) {
			return;
		}

		// load field info on page before script during in-place-edit.
		add_action( 'frm_enqueue_form_scripts', 'FrmSigAppController::footer_js', 5 );

		if ( $atts['is_first'] ) {
			self::load_scripts();
		}
	}

	/**
	 * Maybe add field.
	 *
	 * @param array $fields fields.
	 * @return array fields.
	 */
	public static function maybe_add_field( $fields ) {
		if ( FrmAppHelper::pro_is_installed() ) {
			add_filter( 'frm_pro_available_fields', 'FrmSigAppController::add_field' );
		} else {
			$fields = self::add_field( $fields );
		}

		return $fields;
	}

	/**
	 * Add field.
	 *
	 * @param array $fields fields.
	 * @return array fields.
	 */
	public static function add_field( $fields ) {
		$fields['signature'] = array(
			'name' => __( 'Signature', 'frmsig' ),
			'icon' => 'frm_icon_font frm_signature_icon',
		);

		return $fields;
	}

	/**
	 * Initiate the signature field class.
	 *
	 * @since 2.0
	 *
	 * @param array $class class.
	 * @param array $field_type field type.
	 *
	 * @return object
	 */
	public static function get_signature_object_class( $class, $field_type ) {
		if ( 'signature' === $field_type ) {
			$class = 'FrmSigField';
		}
		return $class;
	}

	/**
	 * If this form uses ajax, we need all the signature field info in advance.
	 *
	 * @param array|object $values values.
	 * @param object       $field values.
	 * @param boolean|int  $entry_id entry id.
	 *
	 * @return mixed
	 */
	public static function check_signature_fields( $values, $field, $entry_id = false ) {
		if ( 'signature' !== $field->type && ! isset( $field->field_options['label1'] ) ) {
			return $values;
		}

		$values['value'] = maybe_unserialize( $values['value'] );

		// If the signature has already been saved and editing isn't allowed for this field, don't load scripts for this field.
		if ( is_array( $values['value'] ) && isset( $values['value']['format'] ) && 'drawn' == $values['value']['format'] && empty( $values['allow_edit'] ) ) {
			return $values;
		}

		global $frm_vars;
		if ( ! is_array( $frm_vars ) ) {
			$frm_vars = array();
		}

		if ( ! isset( $frm_vars['sig_fields'] ) || empty( $frm_vars['sig_fields'] ) ) {
			$frm_vars['sig_fields'] = array();
		}

		$field_obj      = new FrmSigField();
		$style_settings = self::get_style_settings( $field->form_id );

		$values['size'] = $field_obj->get_signature_image_dimension( $values['size'], 'size' );
		$values['max']  = $field_obj->get_signature_image_dimension( $values['max'], 'max' );
		$hide_tabs      = isset( $values['restrict'] ) ? $values['restrict'] : false;

		$border_color = '';
		if ( isset( $style_settings['border_color'] ) ) {
			if ( strpos( $style_settings['border_color'], 'rgba' ) === false ) {
				$border_color = '#' . $style_settings['border_color'];
			} else {
				$border_color = $style_settings['border_color'];
			}
		} else {
			$border_color = '#eee';
		}

		$signature_opts = array(
			'id'          => $field->id,
			'width'       => $values['size'],
			'height'      => $values['max'],
			'line_top'    => round( $values['max'] * 0.7 ) + 0.5, // use .5 for crisp line.
			'line_margin' => $values['size'] * 0.05,
			'line_color'  => $border_color,
			'line_width'  => $hide_tabs ? 0 : 1,
		);

		$signature_opts['default_tab'] = self::get_current_tab( $values );

		$signature_output             = array();
		$signature_output['bgColour'] = 'rgba(0,0,0,0)'; // This BG color will override styles bg color and despite styler bg color it will affect the output background color too.

		/**
		 * Signature option for output.
		 * Note: This filter exists on versions before 3.0.0 and moved from version 3.0.1 and it's not working on version 3.0.0.
		 *
		 * @since 3.0.1
		 */
		$signature_output = apply_filters( 'frm_sig_output_options', $signature_output, array( 'field' => $field ) );

		foreach ( array( 'bg_color', 'text_color', 'border_color' ) as $color ) {
			if ( ! empty( $style_settings[ $color ] ) ) {
				if ( strpos( $style_settings[ $color ], 'rgb' ) === false ) {
					$style_settings[ $color ] = '#' . $style_settings[ $color ];
				}
				$signature_opts[ $color ] = $style_settings[ $color ];
			}
		}

		$frm_vars['sig_fields'][] = array_merge( $signature_output, $signature_opts );

		$values['invalid'] = ''; // unset invalid message so data-invmsg doesn't get added to HTML.

		return $values;
	}

	/**
	 * Get the tab that should show in a Signature field on page load.
	 *
	 * @since 2.0
	 *
	 * @param array $values values.
	 *
	 * @return string
	 */
	private static function get_current_tab( $values ) {
		$current_tab = ! empty( $values['type_it'] ) ? 'typeIt' : 'drawIt';
		if ( is_array( $values['value'] ) ) {
			$typed = ( isset( $values['value']['typed'] ) && $values['value']['typed'] ) || ( isset( $values['value']['format'] ) && 'typed' === $values['value']['format'] );
			if ( $typed ) {
				$current_tab = 'typeIt';
			}
			if ( isset( $values['value']['format'] ) && 'drawn' === $values['value']['format'] ) {
				$current_tab = 'drawIt';
			}
		}

		return $current_tab;
	}

	/**
	 * Load scripts.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	public static function load_scripts() {
		wp_enqueue_style( 'font-awesome-5' );
		wp_enqueue_script( 'frm-signature' );
	}

	/**
	 * Footer js.
	 *
	 * @return void
	 */
	public static function footer_js() {
		global $frm_vars;

		if ( ! is_array( $frm_vars ) || empty( $frm_vars['sig_fields'] ) ) {
			return;
		}

		require FrmSigAppHelper::plugin_path() . '/views/footer_js.php';
	}

	/**
	 * Validate.
	 *
	 * @param array    $errors errors.
	 * @param stdClass $field field.
	 * @param mixed    $value value.
	 *
	 * @return array
	 */
	public static function validate( $errors, $field, $value ) {
		if ( 'signature' !== $field->type || '1' != $field->required || isset( $errors[ 'field' . $field->id ] ) ) {
			return $errors;
		}

		return $errors;
	}

	/**
	 * Get the signature value for the email message and frm-show-entry shortcode.
	 *
	 * @param mixed  $value value.
	 * @param object $meta meta.
	 *
	 * @return mixed $value
	 */
	public static function email_value( $value, $meta ) {
		if ( 'signature' === $meta->field_type ) {
			$value = self::get_final_signature_value( $value, array( 'use_html' => true ) );
		}

		return $value;
	}

	/**
	 * Adjust the signature display attributes.
	 * Used when displaying a signature in the entries tab.
	 *
	 * @since 2.0
	 *
	 * @param array  $atts atts.
	 * @param object $field field.
	 *
	 * @return mixed
	 */
	public static function signature_display_atts( $atts, $field ) {
		if ( 'signature' === $field->type ) {
			$atts['return_array'] = true;
			$atts['truncate']     = false;
		}

		return $atts;
	}

	/**
	 * Sanitize the signature image before display.
	 *
	 * @since 2.0
	 *
	 * @param array $value value.
	 * @param array $args args.
	 * @return void
	 */
	public static function show_final_signature( $value, $args ) {
		echo FrmAppHelper::kses( self::get_final_signature_value( $value, $args ), 'all' ); // WPCS: XSS ok.
	}

	/**
	 * Get the typed or written signature value with or without HTML added.
	 *
	 * @since 1.07.03
	 *
	 * @param array|string $value value.
	 * @param array        $args args.
	 *
	 * @return string - the typed string or img html.
	 */
	public static function get_final_signature_value( $value, $args ) {
		$value = maybe_unserialize( $value );

		if ( ! is_array( $value ) ) {
			if ( is_string( $value ) && $value ) {
				return $value;
			}
			return '';
		}

		if ( ! isset( $value['format'] ) ) {
			return '';
		}

		if ( 'drawn' === $value['format'] ) {
			$file_path = self::get_signature_file_directory() . '/' . $value['content'];

			if ( file_exists( $file_path ) ) {
				$value    = self::get_signature_url( $file_path );
				$entry_id = self::get_entry_id( $args );
				// To avoid image caching, a param with a timestamp will be added to img src if the entry has been updated recently.
				$add_to_src = self::add_to_image_src( $entry_id );

				if ( '' !== $value && $args['use_html'] ) {
					$value = '<img src="' . esc_attr( $value . $add_to_src ) . '" />';
				}
			} else {
				$value = '';
			}
		} elseif ( 'typed' === $value['format'] ) {
			$value = $value['content'];
		} else {
			$value = '';
		}

		return $value;
	}

	/**
	 * @since 3.0.5
	 *
	 * @param array $args
	 *
	 * @return int
	 */
	private static function get_entry_id( $args ) {
		if ( ! empty( $args['entry']->id ) ) {
			return $args['entry']->id;
		} elseif ( ! empty( $args['entry_id'] ) ) {
			return $args['entry_id'];
		}

		return 0;
	}

	/**
	 * Prepares a param with a timestamp to be added to the signature img src attribute if signature image recently updated.
	 *
	 * When sig images are edited, a new image is created with the same filename as the previous one.
	 * To prevent image caching, a timestamp param is added to the img tag's src attribute if the entry has been updated in the last few minutes.
	 *
	 * @since 2.04
	 *
	 * @param int $entry_id entry id.
	 *
	 * @return string A param with a timestamp or an empty string.
	 */
	private static function add_to_image_src( $entry_id ) {
		if ( ! $entry_id ) {
			return '';
		}

		$entry = FrmEntry::getOne( $entry_id );
		if ( empty( $entry->updated_at ) ) {
			return '';
		}

		$now             = strtotime( 'now' );
		$diff_in_seconds = $now - strtotime( $entry->updated_at );

		if ( $diff_in_seconds < 300 ) {
			return '?timestamp=' . $now;
		}

		return '';
	}

	/**
	 * When get_final_signature_value gets called more than once it shouldn't clear a valid signature url.
	 *
	 * @param string $value value.
	 *
	 * @return string
	 */
	private static function return_value_if_it_validates_as_a_signature_url( $value ) {
		return self::is_a_valid_signature_url( $value ) ? $value : '';
	}

	/**
	 * URL validation.
	 *
	 * @param string $value value.
	 *
	 * @return bool
	 */
	private static function is_a_valid_signature_url( $value ) {
		return is_string( $value ) && 0 === strpos( $value, self::get_signature_directory_url() );
	}

	/**
	 * Signature directory.
	 *
	 * @return string
	 */
	private static function get_signature_directory_url() {
		$file_path = self::get_signature_file_directory();
		return self::get_signature_url( $file_path );
	}

	/**
	 * Prepare the Signature value for an XML export.
	 *
	 * @since 2.0
	 *
	 * @param array|string $value value.
	 * @param stdClass     $field field.
	 *
	 * @return string
	 */
	public static function xml_value( $value, $field ) {
		if ( 'signature' === $field->type ) {
			$value = self::get_final_signature_value( $value, array( 'use_html' => false ) );
		}

		return $value;
	}

	/**
	 * CSV Value.
	 *
	 * @param mixed $value value.
	 * @param mixed $atts atts.
	 * @return string
	 */
	public static function csv_value( $value, $atts ) {
		return self::xml_value( $value, $atts['field'] );
	}

	/**
	 * Get the typed signature for a graph.
	 *
	 * @param array $values values.
	 * @param array $field field.
	 *
	 * @return array|string
	 */
	public static function graph_value( $values, $field ) {
		if ( is_object( $field ) && 'signature' === $field->type ) {
			$values = self::get_typed_value( $values );
		}

		return $values;
	}

	/**
	 * Get the typed signature value or the Drawn signature text.
	 *
	 * @since 1.09
	 *
	 * @param array|string $value value.
	 *
	 * @return string
	 */
	private static function get_typed_value( $value ) {
		if ( is_array( $value ) ) {
			if ( 'typed' === $value['format'] ) {
				$value = $value['content'];
			} else {
				$value = __( 'Drawn signatures', 'frmsig' );
			}
		}

		return $value;
	}

	/**
	 * Delete Images.
	 *
	 * @param int $entry_id entry id.
	 * @return null|string Path to folder where signature images are stored.
	 */
	public static function delete_images( $entry_id ) {
		global $wpdb;

		$fields = $wpdb->get_col( $wpdb->prepare( "SELECT fi.id FROM {$wpdb->prefix}frm_fields fi LEFT JOIN {$wpdb->prefix}frm_items it ON (it.form_id=fi.form_id) WHERE fi.type=%s AND it.id=%d", 'signature', $entry_id ) );

		if ( ! $fields ) {
			return;
		}

		return self::get_target_path();
	}

	/**
	 * Returns path to folder where signature images are stored.
	 *
	 * @since 2.04
	 *
	 * @return string Path to folder where signature images are stored.
	 */
	private static function get_target_path() {
		$uploads      = wp_upload_dir();
		$target_path  = $uploads['basedir'] . '/';
		$target_path .= apply_filters( 'frm_sig_upload_folder', 'formidable/signatures' );
		return untrailingslashit( $target_path );
	}

	/**
	 * Returns the path to the sig file for a given entry and field.
	 *
	 * @since 2.04
	 *
	 * @param string $target_path Path to the folder where signatures are saved.
	 * @param int    $field_id Id of signature field.
	 * @param int    $entry_id Id of entry.
	 *
	 * @return string File path to signature file for given entry and field.
	 */
	private static function get_sig_file_path( $target_path, $field_id, $entry_id ) {
		return $target_path . '/signature-' . $field_id . '-' . $entry_id . '.png';
	}

	/**
	 * Deletes an individual file with the specified path.
	 *
	 * @since 2.04
	 *
	 * @param string $file_path Path to signature image file.
	 *
	 * @return void
	 */
	private static function delete_file( $file_path ) {
		if ( file_exists( $file_path ) ) {
			unlink( $file_path );
		}
	}

	/**
	 * Deletes signature file with the specified filename.
	 *
	 * @since 2.04.
	 *
	 * @param string $filename Name of signature file to be deleted.
	 * @return void
	 */
	public static function delete_sig_file( $filename ) {
		$path = self::get_target_path() . '/' . $filename;
		self::delete_file( $path );
	}

	/**
	 * Include add on updater.
	 *
	 * @return void
	 */
	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			include_once FrmSigAppHelper::plugin_path() . '/models/FrmSigUpdate.php';
			FrmSigUpdate::load_hooks();
		}
	}

	/**
	 * Get style settings.
	 *
	 * @since 2.0
	 *
	 * @param int $form_id form id.
	 * @return mixed
	 */
	public static function get_style_settings( $form_id ) {
		if ( is_callable( 'FrmStylesController::get_form_style' ) ) {
			$style_settings = FrmStylesController::get_form_style( $form_id );
			if ( empty( $style_settings ) ) {
				return array();
			}
			$style_settings = $style_settings->post_content;
		} else {
			global $frmpro_settings;
			if ( ! $frmpro_settings && class_exists( 'FrmProSettings' ) ) {
				$frmpro_settings = new FrmProSettings();
			}
			$style_settings = (array) $frmpro_settings;
		}

		return $style_settings;
	}

	/**
	 * Get the URL for a signature.
	 *
	 * @since 1.07.03
	 *
	 * @param string $file_path file path.
	 *
	 * @return string $url
	 */
	private static function get_signature_url( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			$url = '';
		} else {
			$uploads = wp_upload_dir();
			$url     = set_url_scheme( str_replace( $uploads['basedir'], $uploads['baseurl'], $file_path ) );
		}

		return $url;
	}

	/**
	 * Get the folder that holds signature files.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function get_signature_file_directory() {
		$uploads     = wp_upload_dir();
		$target_path = $uploads['basedir'];

		self::maybe_make_directory( $target_path );

		$relative_path = apply_filters( 'frm_sig_upload_folder', 'formidable/signatures' );
		$relative_path = untrailingslashit( $relative_path );
		$folders       = explode( '/', $relative_path );

		foreach ( $folders as $folder ) {
			$target_path .= '/' . $folder;
			self::maybe_make_directory( $target_path );
		}

		return $target_path;
	}

	/**
	 * Create a directory if it doesn't exist.
	 *
	 * @since 1.07.03
	 *
	 * @param string $target_path target path.
	 *
	 * @return void
	 */
	private static function maybe_make_directory( $target_path ) {
		if ( ! file_exists( $target_path ) ) {
			@mkdir( $target_path . '/' );
		}
	}

	/**
	 * Include CSS signature.
	 *
	 * @since 2.06
	 *
	 * @return void
	 */
	public static function include_signature_css() {
		readfile( FrmSigAppHelper::plugin_path() . '/css/jquery.signaturepad.css' );
	}

	/**
	 * Update style.
	 *
	 * @since 2.06
	 *
	 * @return void
	 */
	public static function update_stylesheet() {
		$frm_style = new FrmStyle();
		$frm_style->update( 'default' );
	}

	/**
	 * Adds support for show="url" option in field shortcodes.
	 *
	 * @param array  $replace_with
	 * @param string $tag
	 * @param array  $atts
	 * @param object $field
	 *
	 * @return string $replace_with
	 */
	public static function signature_url_attribute( $replace_with, $tag, $atts, $field ) {
		if ( is_array( $replace_with ) && ! empty( $atts['show'] ) && 'url' === $atts['show'] && 'signature' === $field->type ) {
			$uploads      = wp_upload_dir();
			$replace_with = self::get_final_signature_value( $replace_with, array( 'use_html' => false ) );
		}

		return $replace_with;
	}

	/**
	 * Display the signature.
	 * Used for field ID shortcode in pro version.
	 *
	 * @deprecated 2.02
	 * @param mixed  $value value.
	 * @param string $tag tag.
	 * @param array  $atts atts.
	 * @param object $field field.
	 *
	 * @return mixed
	 */
	public static function custom_display_signature( $value, $tag, $atts, $field ) {
		_deprecated_function( __METHOD__, '2.02', 'FrmSigAppController::get_final_signature_value' );
		if ( 'signature' !== $field->type ) {
			return $value;
		}

		return self::display_signature( $value, $field, $atts );
	}

	/**
	 * Display the signature.
	 * Used for field ID shortcode and entries tab.
	 *
	 * @deprecated 2.02
	 * @param mixed  $value value.
	 * @param object $field field.
	 * @param array  $atts atts.
	 *
	 * @return mixed
	 */
	public static function display_signature( $value, $field, $atts ) {
		_deprecated_function( __METHOD__, '2.02', 'FrmSigAppController::get_final_signature_value' );
		if ( 'signature' !== $field->type || empty( $value ) ) {
			return $value;
		}

		return self::get_final_signature_value( $value, array( 'use_html' => true ) );
	}

	/**
	 * Admin field.
	 *
	 * @deprecated 2.0
	 *
	 * @param mixed $field field.
	 *
	 * @return void
	 */
	public static function admin_field( $field ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField::include_on_form_builder' );
	}

	/**
	 * Option form.
	 *
	 * @deprecated 2.0
	 *
	 * @param array $field field.
	 *
	 * @return void
	 */
	public static function options_form( $field ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField::show_options' );
	}

	/**
	 * Update.
	 *
	 * @deprecated 2.0
	 *
	 * @param array $field_options field_options.
	 * @param array $field field.
	 * @param array $values values.
	 *
	 * @return array
	 */
	public static function update( $field_options, $field, $values ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField::include_on_form_builder' );

		return $field_options;
	}

	/**
	 * Front field.
	 *
	 * @deprecated 2.0
	 *
	 * @param array  $field field.
	 * @param string $field_name field name.
	 *
	 * @return void
	 */
	public static function front_field( $field, $field_name ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField::front_field_input' );
	}

	/**
	 * Set default.
	 *
	 * @deprecated 2.0
	 *
	 * @param array $field_data field data.
	 *
	 * @return array
	 */
	public static function set_defaults( $field_data ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField class' );

		return $field_data;
	}

	/**
	 * Get default attributes.
	 *
	 * @deprecated 2.0
	 *
	 * @return array
	 */
	public static function get_defaults() {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField::extra_field_opts' );
		return array(
			'size'     => 400,
			'max'      => 150,
			'restrict' => false,
			'label1'   => __( 'Draw It', 'frmsig' ),
			'label2'   => __( 'Type It', 'frmsig' ),
			'label3'   => __( 'Clear', 'frmsig' ),
		);
	}

	/**
	 * Create the signature file.
	 * Returns the file name if a file is created. Otherwise returns empty string.
	 *
	 * @since 1.07.03
	 *
	 * @param string $points points.
	 * @param string $field   field.
	 * @param string $entry_id entry id.
	 *
	 * @return string
	 */
	public static function create_signature_file( $points, $field, $entry_id ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField::create_signature_file' );
		return '';
	}

	/**
	 * Create the signature file before saving to db.
	 *
	 * @since 1.13
	 * @deprecated 2.0
	 *
	 * @param array|string $meta_value meta value.
	 * @param int          $field_id field id.
	 * @param int          $entry_id entry id.
	 * @param array        $atts attributes.
	 *
	 * @return array $atts
	 */
	public static function format_value_before_save( $meta_value, $field_id, $entry_id, $atts ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigField::get_value_to_save' );
		return $meta_value;
	}

	/**
	 * Make sure these scripts are loaded on ajax page change if enqueued.
	 *
	 * @deprecated 2.0
	 *
	 * @param string $scripts scripts.
	 *
	 * @return string
	 */
	public static function ajax_load_scripts( $scripts ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigAppController::maybe_load_scripts' );

		$scripts = array_merge( $scripts, array( 'flashcanvas', 'frm-signature' ) );

		return $scripts;
	}

	/**
	 * Make sure these styles are loaded on ajax page change if enqueued.
	 *
	 * @deprecated 2.0
	 *
	 * @param array $styles styles.
	 *
	 * @return array
	 */
	public static function ajax_load_styles( $styles ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigAppController::maybe_load_scripts' );

		$styles[] = 'jquery-signaturepad';

		return $styles;
	}

	/**
	 * Check if there are any signatures that need to be created when the entry is created.
	 *
	 * @since 1.07.03
	 * @deprecated 2.0
	 *
	 * @param mixed $entry_id entry id.
	 * @param mixed $form_id form id.
	 *
	 * @return void
	 */
	public static function maybe_create_signature_files( $entry_id, $form_id ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmSigAppController::format_value_before_save' );
	}
}
