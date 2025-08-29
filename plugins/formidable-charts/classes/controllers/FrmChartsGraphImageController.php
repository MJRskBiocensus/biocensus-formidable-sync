<?php
/**
 * Graph image controller
 *
 * @since 2.0
 * @package FrmCharts
 */

/**
 * Class FrmChartsGraphImageController
 */
class FrmChartsGraphImageController {

	/**
	 * Whether the current request is processing an email.
	 *
	 * @var bool
	 */
	public static $is_processing_email = false;

	/**
	 * Adds the graph image attributes to the defaults.
	 *
	 * @param array $defaults The default attributes.
	 * @return array
	 */
	public static function add_graph_image_atts( $defaults ) {
		if ( ! isset( $defaults['format'] ) ) {
			$defaults['format']         = '';
			$defaults['x_title_margin'] = '';
			$defaults['y_title_margin'] = '';
			$defaults['legend_columns'] = '';
			$defaults['output_image']   = ''; // This flag is used to output the image directly to the browser.
		}

		if ( self::$is_processing_email || method_exists( 'FrmPdfsAppHelper', 'is_pdf' ) && FrmPdfsAppHelper::is_pdf() ) {
			// If no graph format is specified in PDF or email content, use image as default.
			$defaults['format'] = 'image';
		}

		return $defaults;
	}

	/**
	 * Returns the graph HTML.
	 *
	 * @param string $custom_html The custom HTML.
	 * @param array  $args        The arguments.
	 * @return string
	 */
	public static function graph_html( $custom_html, $args ) {
		if ( ! isset( $args['atts']['format'] ) || 'image' !== $args['atts']['format'] || ! self::is_gd_supported() ) {
			return $custom_html;
		}

		if ( ! empty( $args['atts']['x_title_margin'] ) ) {
			$args['graph_data']['options']['hAxis']['title_margin'] = $args['atts']['x_title_margin'];
		}
		if ( ! empty( $args['atts']['y_title_margin'] ) ) {
			$args['graph_data']['options']['vAxis']['title_margin'] = $args['atts']['y_title_margin'];
		}

		if ( ! empty( $args['atts']['legend_columns'] ) ) {
			$args['graph_data']['options']['legend']['legend_columns'] = $args['atts']['legend_columns'];
		}

		try {
			$graph_image = new FrmChartsGraphImage( $args['graph_data'] );
			return $graph_image->get_output( ! empty( $args['atts']['output_image'] ) );
		} catch ( Exception $e ) {
			return self::get_error_html( $e );
		}
	}

	/**
	 * Returns the error HTML.
	 *
	 * @param Exception $e The exception.
	 * @return string
	 */
	private static function get_error_html( $e ) {
		$output = '<div class="frm-charts-error" style="color:#c00;font-size:0.8em;border:1px solid #c00;padding:1em;"><b>';
		$output .= __( 'Graph image error', 'formidable-charts' ) . ':</b> ';
		$output .= $e->getMessage();
		$output .= '</div>';
		return $output;
	}

	/**
	 * Sets is_processing_email flag to true before processing email content.
	 *
	 * @param string $value The filter value.
	 * @return string
	 */
	public static function before_process_email_content( $value ) {
		self::$is_processing_email = true;
		return $value;
	}

	/**
	 * Sets is_processing_email flag to false after processing email content.
	 *
	 * @param string $value The filter value.
	 * @return string
	 */
	public static function after_process_email_content( $value ) {
		self::$is_processing_email = false;
		return $value;
	}

	/**
	 * Checks if the current server has the GD extension installed.
	 *
	 * @return bool
	 */
	public static function is_gd_supported() {
		return function_exists( 'gd_info' );
	}

	/**
	 * Shows the admin notice.
	 */
	public static function show_admin_notice() {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					// translators: %s: The plugin name.
					esc_html__( '%s: The graph "image" format requires the GD Image library for WordPress. Please contact your hosting provider to enable it.', 'frm-charts' ),
					'Formidable Charts'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns the graph image directory path.
	 *
	 * @return string
	 */
	public static function get_graph_image_dir_path() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/frm-charts/';
	}

	/**
	 * Creates the graph image directory if it doesn't exist.
	 *
	 * @param string $dir_path The directory path.
	 */
	public static function maybe_create_graph_image_dir( $dir_path ) {
		if ( ! is_dir( $dir_path ) ) {
			wp_mkdir_p( $dir_path );
		}

		$index_file = $dir_path . 'index.php';
		if ( file_exists( $index_file ) ) {
			return;
		}

		file_put_contents( $index_file, '<?php' . PHP_EOL . '// Silence is golden.' );
	}

	/**
	 * Returns the graph image directory URL.
	 *
	 * @return string
	 */
	public static function get_graph_image_dir_url() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/frm-charts/';
	}

	/**
	 * Handles the graph request.
	 */
	public static function handle_graph_request() {
		$payload = FrmAppHelper::get_param( 'frm_graph' );
		if ( ! $payload ) {
			return;
		}

		$file_name = base64_decode( $payload );
		if ( ! $file_name ) {
			return;
		}

		$file_path = self::get_graph_image_dir_path() . $file_name;
		if ( ! file_exists( $file_path ) ) {
			return;
		}

		// Temporarily set image to read only.
		FrmProFileField::set_to_read_only( $file_path );

		header( 'Content-Type: image/jpeg' );
		header( 'Content-Disposition: inline; filename="' . $file_name . '"' );
		readfile( $file_path );

		// Restore image to write only.
		FrmProFileField::set_to_write_only( $file_path );
		exit;
	}
}
