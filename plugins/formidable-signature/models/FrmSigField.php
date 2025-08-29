<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Signature field class.
 *
 * @since 2.0
 */
class FrmSigField extends FrmFieldType {

	/**
	 * Field type.
	 *
	 * @var string
	 * @since 2.0
	 */
	protected $type = 'signature';

	/**
	 * Field settings for type.
	 *
	 * @since 2.0
	 *
	 * return array
	 */
	protected function field_settings_for_type() {
		$settings = array();
		if ( is_callable( 'FrmProFieldsHelper::fill_default_field_display' ) ) {
			FrmProFieldsHelper::fill_default_field_display( $settings );
		}
		return $settings;
	}

	/**
	 * Extra field options.
	 *
	 * @since 2.0
	 *
	 * return array
	 */
	protected function extra_field_opts() {
		return array(
			'size'       => 400,
			'max'        => 150,
			'restrict'   => false,
			'allow_edit' => false,
			'type_it'    => false,
			'label1'     => __( 'Draw It', 'frmsig' ),
			'label2'     => __( 'Type It', 'frmsig' ),
			'label3'     => __( 'Clear', 'frmsig' ),
		);
	}

	/**
	 * Translatable strings.
	 *
	 * @since 2.03
	 *
	 * @return  array
	 */
	public function translatable_strings() {
		$strings = array();
		if ( is_callable( parent::class . '::translatable_strings' ) ) {
			$strings = parent::translatable_strings();
		}

		$strings[] = 'label1';
		$strings[] = 'label2';
		$strings[] = 'label3';
		return $strings;
	}

	/**
	 * Include form builder file.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	protected function include_form_builder_file() {
		return FrmSigAppHelper::plugin_path() . '/views/front_field.php';
	}

	/**
	 * Include on form builder.
	 *
	 * @since 2.01
	 *
	 * @param string $name name.
	 * @param array  $field field.
	 *
	 * @return void
	 */
	protected function include_on_form_builder( $name, $field ) {
		$field_name         = $this->html_name( $name );
		$html_id            = $this->html_id();
		$field['html_name'] = $field_name;
		$field['html_id']   = $html_id;
		$styles             = $this->get_style_settings( $field );

		$typed_value = '';
		$output      = '';

		include $this->include_form_builder_file();
	}

	/**
	 * Show options.
	 *
	 * @since 2.0
	 *
	 * @param array $field field.
	 * @param array $display display.
	 * @param array $values values.
	 *
	 * @return void
	 */
	public function show_options( $field, $display, $values ) {
		include FrmSigAppHelper::plugin_path() . '/views/options_form.php';

		parent::show_options( $field, $display, $values );
	}

	/**
	 * Validate.
	 *
	 * @since 2.0
	 *
	 * @param array $args args.
	 *
	 * @return array
	 */
	public function validate( $args ) {
		if ( ! $this->field->required ) {
			return;
		}

		if ( is_callable( 'FrmProEntryMeta::skip_required_validation' ) && FrmProEntryMeta::skip_required_validation( $this->field ) ) {
			return;
		}

		$errors = array();
		$value  = $args['value'];
		if ( empty( $value ) || ( isset( $value['output'] ) && empty( $value['output'] ) && empty( $value['typed'] ) && empty( $value['content'] ) ) ) {
			$errors[ 'field' . $args['id'] ] = FrmFieldsHelper::get_error_msg( $this->field, 'blank' );
		}
		return $errors;
	}

	/**
	 * Get value to save.
	 *
	 * @since 2.0
	 * @param array|string $value (the posted value).
	 * @param array        $atts atts.
	 *
	 * @return array|string $value
	 */
	public function get_value_to_save( $value, $atts ) {
		$entry_id = $atts['entry_id'];
		$value    = is_array( $value ) ? self::maybe_delete_saved_image( $value ) : array();

		if ( ! empty( $value['format'] ) ) {
			FrmEntriesHelper::set_posted_value( $this->field, $value, $atts );
			return $value;
		}

		if ( ! empty( $value['output'] ) ) {
			$this->convert_output_to_formatted_value( $value, $entry_id );
		} elseif ( ! empty( $value['typed'] ) ) {
			$this->format_typed_value( $value );
		} elseif ( ! empty( $value['url'] ) ) {
			$this->convert_url_to_drawn_value( $value, $entry_id );
		} else {
			$value = array();
		}

		FrmEntriesHelper::set_posted_value( $this->field, $value, $atts );

		return $value;
	}

	/**
	 * When appropriate, deletes saved image and resets some $value elements so the new value will be saved.
	 *
	 * @param array $value value.
	 *
	 * @since 2.04.
	 *
	 * @return array $value, possibly modified so a new value will be saved.
	 */
	private function maybe_delete_saved_image( $value ) {
		if ( empty( $value['delete_saved_image'] ) || empty( $value['format'] ) || 'drawn' !== $value['format'] || empty( $value['content'] ) ) {
			return $value;
		}

		FrmSigAppController::delete_sig_file( $value['content'] );
		unset( $value['format'], $value['content'], $value['delete_saved_image'] );

		return $value;
	}

	/**
	 * Convert output (points) to the formatted value.
	 * If the entry is being saved as a draft, keep the points.
	 * If not a draft, create the image and return the url.
	 * TODO: Don't switch to image with draft entry in multi-page form
	 *
	 * @since 2.0
	 *
	 * @param array      $value value.
	 * @param int|string $entry_id entry id.
	 *
	 * @return void
	 */
	private function convert_output_to_formatted_value( &$value, $entry_id ) {
		$is_draft_save = ( is_callable( 'FrmProFormsHelper::saving_draft' ) && FrmProFormsHelper::saving_draft() );

		if ( $is_draft_save ) {
			$this->format_points_value( $value );
		} else {
			$this->format_drawn_value( $value, $entry_id );
		}
	}

	/**
	 * Convert the URL to a drawn value
	 *
	 * Either the signature file should be copied and renamed
	 * or the same signature file should be used
	 *
	 * @since 2.0
	 *
	 * @param  array  $value value.
	 * @param object $entry_id entry id.
	 *
	 * @return void
	 */
	private function convert_url_to_drawn_value( &$value, $entry_id ) {
		$image_url     = trim( $value['url'] );
		$file_name     = basename( $image_url );
		$file_path     = FrmSigAppController::get_signature_file_directory() . '/' . $file_name;
		$new_file_name = $this->get_file_name( $entry_id );

		// TODO: delete the original and re-import in case it's a different signature
		// Do not change file name if it is correct and file exists.
		if ( $file_name != $new_file_name || ! file_exists( $file_path ) ) {
			$file_name = $this->copy_image( $image_url, $new_file_name );
		}

		$value = array();
		if ( $file_name ) {
			$value['format']  = 'drawn';
			$value['content'] = $file_name;
		}
	}

	/**
	 * Format the drawn value
	 *
	 * @since 2.0
	 *
	 * @param array      $value value.
	 * @param int|string $entry_id entry id.
	 *
	 * @return void
	 */
	private function format_drawn_value( &$value, $entry_id ) {
		$file_name = $this->create_signature_file( $value['output'], $entry_id );
		if ( $file_name ) {
			$value = array(
				'content' => $file_name,
				'output'  => $value['output'],
				'format'  => 'drawn',
			);
		} else {
			$this->format_points_value( $value );
		}
	}

	/**
	 * Format the points value
	 *
	 * @since 2.0
	 *
	 * @param array $meta_value meta value.
	 *
	 * @return void
	 */
	private function format_points_value( &$meta_value ) {
		$meta_value = array(
			'output' => $meta_value['output'],
			'format' => 'points',
		);
	}

	/**
	 * Create the signature file
	 * Returns the file name if a file is created. Otherwise returns empty string.
	 *
	 * @since 3.0 Refactor old way of generating signature file and deprecate sigJsonToImage() and drawThickLine() accordingly.
	 * @since 2.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem Subclass
	 *
	 * @param string $points points.
	 * @param int    $entry_id entry id.
	 *
	 * @return string
	 */
	private function create_signature_file( $points, $entry_id ) {
		$points    = sanitize_text_field( wp_unslash( $points ) );
		$file_name = $this->get_file_name( $entry_id );
		$file_path = FrmSigAppController::get_signature_file_directory() . '/' . $file_name;

		if ( file_exists( $file_path ) ) {
			// File was already created, possibly when a draft was saved.
			return $file_name;
		}

		$encoded_image = explode( ',', $points )[1];
		$decoded_image = base64_decode( $encoded_image );

		// While it's internal file creation we need to have direct access to the file system.
		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$wp_filesystem = new WP_Filesystem_Direct( false );

		if ( ! $wp_filesystem->put_contents( $file_path, $decoded_image, 0644 ) ) {
			$file_name = '';
		}

		return $file_name;
	}

	/**
	 * Get the signature file name
	 *
	 * @since 2.0
	 *
	 * @param int|object $entry_id entry id.
	 *
	 * @return string
	 */
	private function get_file_name( $entry_id ) {
		return 'signature-' . $this->field->id . '-' . $entry_id . '.png';
	}

	/**
	 * Get the width for the signature field
	 *
	 * @since 2.0
	 *
	 * @return int
	 */
	private function get_signature_image_width() {
		$width = FrmField::get_option( $this->field, 'size' );
		return $this->get_signature_image_dimension( $width, 'size' );
	}

	/**
	 * Get the height for a signature field
	 *
	 * @since 2.0
	 *
	 * @return int
	 */
	private function get_signature_image_height() {
		$height = FrmField::get_option( $this->field, 'max' );
		return $this->get_signature_image_dimension( $height, 'max' );
	}

	/**
	 * Get a dimension for the signature field.
	 *
	 * @since 2.0
	 *
	 * @param string $saved_setting saved setting.
	 * @param string $setting setting.
	 *
	 * @return string
	 */
	public function get_signature_image_dimension( $saved_setting, $setting ) {
		$defaults  = $this->extra_field_opts();
		$default   = $defaults[ $setting ];
		$dimension = ( ! empty( $saved_setting ) ) ? $saved_setting : $default;

		return str_replace( 'px', '', $dimension );
	}

	/**
	 * Copy an image and give it a new file name.
	 * Copy file from current site or remote site.
	 *
	 * @since 2.0
	 *
	 * @param string $image_url url.
	 * @param string $new_file_name new file name.
	 *
	 * @return string
	 */
	private function copy_image( $image_url, $new_file_name ) {
		if ( ! $this->validate_domain( $image_url ) ) {
			return '';
		}

		$upload_folder = FrmSigAppController::get_signature_file_directory();
		$file_name     = wp_unique_filename( $upload_folder, $new_file_name );
		$path          = trailingslashit( $upload_folder );

		$ch = curl_init( str_replace( array( ' ' ), array( '%20' ), $image_url ) );
		$fp = fopen( $path . $file_name, 'wb' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FILE, $fp );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) );
		curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
		$result = curl_exec( $ch );
		$code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		fclose( $fp );

		if ( ! $result || 200 !== $code ) {
			// Failed to download image.
			unlink( $path . $file_name );
			$file_name = '';
		}

		return $file_name;
	}

	/**
	 * Validate weather request is from allowlist domains and path extension ends with PNG file.
	 *
	 * @since 3.0.2
	 *
	 * @param string $url domain name.
	 *
	 * @return boolean
	 */
	private function validate_domain( $url ) {

		$parsed_domain = wp_parse_url( $url );

		if ( ! is_array( $parsed_domain ) ) {
			// URL is malformed.
			return false;
		}

		$ext = pathinfo( $parsed_domain['path'], PATHINFO_EXTENSION );
		if ( 'png' !== $ext ) {
			// The URL isn't to an PNG file.
			return false;
		}

		// Until it's a png file don't care about allowlist when it comes to the importing the signatures.
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return true;
		}

		/**
		 * Filter for allowlist domains as signature import resource,
		 * Since URL host name is getting compared for allowlist please pass the domain and subdomains like formidableforms.com or cdn.formidableforms.com
		 * without scheme for more information please visit following link https://www.php.net/manual/en/function.parse-url.php
		 *
		 * 3.0.2
		 *
		 * @param array $allowlist contains allowed URL host domains
		 * @return bool
		 */
		$allowlist = apply_filters( 'frm_signature_allowlist_domains', array( wp_parse_url( get_home_url(), PHP_URL_HOST ) ) );

		// Check if we match the domain exactly.
		if ( in_array( $parsed_domain['host'], $allowlist, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Format the typed value
	 *
	 * @since 2.0
	 *
	 * @param array $value value.
	 *
	 * @return void
	 */
	private function format_typed_value( &$value ) {
		$value = array(
			'content' => $value['typed'],
			'typed'   => $value['typed'], // for reverse compatibility.
			'format'  => 'typed',
		);
	}

	/**
	 * Prepare display value.
	 *
	 * @since 2.0
	 *
	 * @param mixed $value value.
	 * @param mixed $atts atts.
	 *
	 * @return mixed
	 */
	protected function prepare_display_value( $value, $atts ) {
		$atts['use_html'] = isset( $atts['use_html'] ) ? $atts['use_html'] : true;
		return FrmSigAppController::get_final_signature_value( $value, $atts );
	}

	/**
	 * Front field input.
	 *
	 * @since 2.0
	 *
	 * @param mixed $args argument.
	 * @param mixed $shortcode_atts attributes.
	 *
	 * @return mixed
	 */
	public function front_field_input( $args, $shortcode_atts ) {
		$field      = $this->field;
		$html_id    = $args['html_id'];
		$field_name = $args['field_name'];
		$plus_id    = ( isset( $args['field_plus_id'] ) ? $args['field_plus_id'] : '' );
		$hidden     = false;
		$allow_edit = ! empty( $field['allow_edit'] );

		// WARNING: This will be the posted value, if it has been posted, not the saved value.
		$field['value']   = stripslashes_deep( $field['value'] );
		$format           = is_array( $field['value'] ) && isset( $field['value']['format'] ) ? $field['value']['format'] : 'none';
		$show_front_field = $this->show_front_field( $format, $allow_edit, $field );

		ob_start();
		if ( $this->loading_first_page() && $show_front_field ) {
			FrmSigAppController::load_scripts();
		}

		$styles = $this->get_style_settings( $this->field );

		if ( 'drawn' === $format ) {
			FrmSigAppController::show_final_signature(
				$field['value'],
				array(
					'use_html' => true,
					'entry_id' => ! empty( $field['entry_id'] ) ? $field['entry_id'] : false,
				)
			);
			include FrmSigAppHelper::plugin_path() . '/views/drawn.php';
			include FrmSigAppHelper::plugin_path() . '/views/hidden_inputs.php';
			$typed_value = '';
			$output      = '';
			// Add frm_hidden class to signature input on page load.
			$hidden = true;
		} elseif ( 'typed' === $format ) {
			$typed_value = $field['value']['content'];
			$output      = '';
			if ( ! empty( $typed_value ) && ! $allow_edit ) {
				$final_value = FrmSigAppController::get_final_signature_value( $field['value'], $shortcode_atts );
				include FrmSigAppHelper::plugin_path() . '/views/typed_display.php';
				include FrmSigAppHelper::plugin_path() . '/views/hidden_inputs.php';
			}
		} elseif ( 'points' === $format ) {
			$typed_value = '';
			$output      = $field['value']['output'];
		} else {
			$typed_value = is_array( $field['value'] ) && isset( $field['value']['typed'] ) ? $field['value']['typed'] : '';
			$output      = is_array( $field['value'] ) && isset( $field['value']['output'] ) ? $field['value']['output'] : '';
		}

		if ( $show_front_field ) {
			include FrmSigAppHelper::plugin_path() . '/views/front_field.php';
		}

		$input_html = ob_get_contents();
		ob_end_clean();
		return $input_html;
	}

	/**
	 * Show front field.
	 *
	 * @param string  $format format.
	 * @param boolean $allow_edit allow edit.
	 * @param array   $field field.
	 *
	 * @return boolean
	 */
	private function show_front_field( $format, $allow_edit, $field ) {
		if ( 'drawn' === $format && ! $allow_edit ) {
			return false;
		}
		if ( 'typed' === $format && ! empty( $field['value']['content'] ) && ! $allow_edit ) {
			return false;
		}

		return true;
	}

	/**
	 * Get style settings.
	 *
	 * @since 2.01
	 *
	 * @param mixed $field field.
	 *
	 * @return string
	 */
	private function get_style_settings( $field ) {
		$form_id        = ! empty( $field['parent_form_id'] ) ? $field['parent_form_id'] : $field['form_id'];
		$style_settings = $this->prepare_style_settings( $form_id );

		$styles = array(
			'hide_tabs' => isset( $field['restrict'] ) ? $field['restrict'] : false,
			'width'     => $this->get_signature_image_width(),
			'height'    => $this->get_signature_image_height(),
		);

		$font_size     = $styles['hide_tabs'] ? 16 : $this->get_button_font_size( $styles['height'] );
		$button_size   = $font_size * 2; // font size + padding.
		$button_top    = floor( ( $styles['height'] - 4 - ( $button_size * 2 ) ) / 3 ); // height - border - buttons.
		$button_margin = max( $button_top, $styles['width'] * 0.05 );

		$active_color  = '--active:' . $style_settings['progress_active_bg_color'];
		$toggle_colors = $active_color . ';--inactive:' . $style_settings['progress_bg_color'] . ';--active-text:' . $style_settings['progress_active_color'] . ';--inactive-text:' . $style_settings['progress_color'];
		$button_style  = '--button-margin:' . $button_top . 'px;--button-size:' . $font_size . 'px;--button-padding:' . ( $font_size / 2 ) . 'px;--button-side-margin:' . $button_margin . 'px';

		if ( $font_size < 16 ) {
			$button_style .= ';--icon:' . floor( $font_size * 1.25 ) . 'px';
		} else {
			$button_style .= ';--icon:20px';
		}

		$styles['css'] = 'height:' . $styles['height'] . 'px;' . $style_settings['border_color'] . $style_settings['bg_color'] . $toggle_colors . ';' . $button_style;

		return $styles;
	}

	/**
	 * Prepare style settings.
	 *
	 * @since 3.0.1
	 *
	 * @param mixed $form_id form id.
	 *
	 * @return array<string>
	 */
	private function prepare_style_settings( $form_id ) {
		$style_settings = FrmSigAppController::get_style_settings( $form_id );

		// All index needed for signature from styler.
		$styles_arg = array(
			'progress_active_bg_color',
			'progress_bg_color',
			'progress_active_color',
			'progress_color',
			'border_color',
			'bg_color',
		);

		// Add the sharp whether it's HEX value.
		foreach ( $styles_arg as $key => $color ) {
			if ( strpos( $style_settings[ $color ], 'rgb' ) === false ) {
				$style_settings[ $color ] = '#' . $style_settings[ $color ];
			}
		}

		$style_settings['bg_color']     = '--bg-color:' . $style_settings['bg_color'] . ';';
		$style_settings['border_color'] = 'border-color:' . $style_settings['border_color'] . ';';

		/**
		 * Disable bg color and border color of styler for signature field.
		 *
		 * @since 3.0.1
		 */
		if ( ! apply_filters( 'frm_sig_styler', true ) ) {
			$style_settings['bg_color']     = '--bg-color:rgba(0,0,0,0);';
			$style_settings['border_color'] = '';
		}

		return $style_settings;
	}

	/**
	 * Get button font size.
	 *
	 * @since 2.01
	 *
	 * @param int|float $height height.
	 * @param int       $font_size font size.
	 *
	 * @return int|float
	 */
	private function get_button_font_size( $height, $font_size = 12 ) {
		$font_max = 20;
		if ( $font_size >= $font_max ) {
			return $font_size;
		}

		$button_size = ( $font_size * 2 ); // font size + padding.
		if ( $button_size / $height < .3 ) {
			$font_size = $this->get_button_font_size( $height, $font_size + 2 );
		}

		return $font_size;
	}

	/**
	 * Check if this is the first page of an ajax-loaded form
	 *
	 * @since 2.0
	 */
	private function loading_first_page() {
		global $frm_vars;
		$ajax_now = ! FrmAppHelper::doing_ajax();
		if ( ! $ajax_now && isset( $frm_vars['inplace_edit'] ) && $frm_vars['inplace_edit'] ) {
			$ajax_now = true;
		}
		return $ajax_now;
	}

	/**
	 * Prepare import value.
	 *
	 * @since 2.0
	 *
	 * @param string $value value.
	 * @param string $atts atts.
	 *
	 * @return string
	 */
	protected function prepare_import_value( $value, $atts ) {

		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( strpos( $value, 'http' ) === 0 ) {
			$value = array(
				'url' => $value,
			);
		} elseif ( '' !== $value ) {
			$value = array(
				'format'  => 'typed',
				'content' => $value,
			);
		}

		return $value;
	}
}
