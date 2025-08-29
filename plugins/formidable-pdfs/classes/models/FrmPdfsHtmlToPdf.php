<?php
/**
 * Handle converting HTML to PDF
 *
 * @package FrmPdfs
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

use Dompdf\Dompdf;

/**
 * Class FrmPdfsHtmlToPdf
 */
class FrmPdfsHtmlToPdf {

	/**
	 * HTML to PDF converter.
	 *
	 * @var object
	 */
	protected $converter;

	/**
	 * The args.
	 *
	 * @since 2.0.5
	 *
	 * @var array
	 */
	protected $args;

	/**
	 * Constructor.
	 *
	 * @since 2.0.5 Added `$args` param.
	 *
	 * @param array $args Render args. Optional.
	 */
	public function __construct( $args = array() ) {
		if ( ! class_exists( 'Dompdf\Dompdf', false ) ) {
			require_once FrmPdfsAppHelper::plugin_path() . '/classes/lib/dompdf/autoload.inc.php';
		}

		$this->args = $args;

		$upload_dir = wp_upload_dir();

		$dompdf_args = array(
			'enable_remote'       => true,
			'enable_html5_parser' => true,
			'temp_dir'            => get_temp_dir(),
			'font_dir'            => $upload_dir['basedir'],
			// Allow using image path for internal images.
			'chroot'              => $upload_dir['basedir'],
			'enable_font_subsetting' => true,
			'http_context'        => array(
				'ssl'  => array(
					'verify_peer' => true,
				),
				'http' => array(
					'follow_location' => false,
					'user_agent'      => 'Formidable PDFs ' . FrmPdfsAppHelper::$plug_version,
					'header'          => 'Referer: ' . esc_url( home_url() ) . "\r\n",
				),
			),
		);

		/**
		 * Filters the args passed to the DOMPDF constructor.
		 *
		 * @param array $dompdf_args DOMPDF args.
		 */
		$dompdf_args = apply_filters( 'frm_pdfs_dompdf_args', $dompdf_args );

		$this->maybe_polyfill_ctype_alpha();

		$this->converter = new Dompdf( $dompdf_args );
	}

	/**
	 * Define ctype_alpha if it doesn't exist.
	 */
	private function maybe_polyfill_ctype_alpha() {
		if ( function_exists( 'ctype_alpha' ) ) {
			return;
		}

		/**
		 * Returns TRUE if every character in text is a letter, FALSE otherwise.
		 *
		 * @see https://php.net/ctype-alpha
		 *
		 * @param mixed $text The text to check.
		 *
		 * @return bool
		 */
		function ctype_alpha( $text ) {

			$convert_int_to_char_for_ctype = function ( $int ) {
				if ( ! \is_int( $int ) ) {
					return $int;
				}

				if ( $int < -128 || $int > 255 ) {
					return (string) $int;
				}

				if ( $int < 0 ) {
					$int += 256;
				}

				return \chr( $int );
			};

			$text = $convert_int_to_char_for_ctype( $text );

			return \is_string( $text ) && '' !== $text && ! preg_match( '/[^A-Za-z]/', $text );
		}
	}

	/**
	 * Loads an HTML string
	 * Parse errors are stored in the global array _dompdf_warnings.
	 *
	 * @param string $html     HTML text to load.
	 * @param string $encoding Encoding of the HTML.
	 * @return void
	 */
	public function load_html( $html, $encoding = null ) {
		$this->maybe_replace_total_pages( $html, $encoding );

		$this->converter->loadHtml( $html, $encoding );
	}

	/**
	 * Maybe replace the total pages value.
	 *
	 * @since 2.0.5
	 *
	 * @param string $html     The HTML.
	 * @param string $encoding Encoding of the HTML.
	 *
	 * @return void
	 */
	protected function maybe_replace_total_pages( &$html, $encoding = null ) {
		if ( empty( $this->args['show_pagination'] ) ) {
			return;
		}

		// Render once to get the total pages.
		$new_args = $this->args;
		unset( $new_args['show_pagination'] );
		$new_dompdf = new FrmPdfsHtmlToPdf( $new_args );
		$new_dompdf->load_html( $html, $encoding );
		$new_dompdf->render();

		$html = str_replace(
			FrmPdfsAppHelper::get_pagination_total_pages_placeholder(),
			$new_dompdf->get_total_pages(),
			$html
		);
	}

	/**
	 * Set the paper size. Can be 'letter', 'legal', 'A4', etc.
	 * Orientation can be 'portrait' or 'landscape'.
	 *
	 * @since 2.0
	 * @since 2.0.5 The `$args` param is optional.
	 *
	 * @param array $args Paper arguments including 'paper_size' and 'orientation'.
	 * @return void
	 */
	public function set_paper( $args = array() ) {
		$this->parse_args( $args );

		$paper_size  = isset( $args['paper_size'] ) ? $args['paper_size'] : 'letter';
		$orientation = isset( $args['orientation'] ) ? $args['orientation'] : 'portrait';
		$this->converter->setPaper( $paper_size, $orientation );
	}

	/**
	 * Parses args.
	 *
	 * @since 2.0.5
	 *
	 * @param array $args Render args.
	 *
	 * @return void
	 */
	protected function parse_args( &$args = array() ) {
		if ( ! $args ) {
			$args = $this->args;
		}
	}

	/**
	 * Renders the HTML to PDF.
	 *
	 * @since 2.0.2 Added `$args` parameter.
	 *
	 * @param array $args See {@see FrmPdfsAppController::generate_entry_pdf()} for more details.
	 */
	public function render( $args = array() ) {
		$this->before_render( $args );

		$this->converter->render();

		$this->after_render( $args );
	}

	/**
	 * Before render the HTML to PDF.
	 *
	 * @since 2.0.2 Added `$args` parameter.
	 * @since 2.0.5 The `$args` is optional.
	 *
	 * @param array $args See {@see FrmPdfsAppController::generate_entry_pdf()} for more details.
	 */
	protected function before_render( $args = array() ) {
		$this->parse_args( $args );
		FrmPdfsFileProtectionHelper::process_queue();
		$this->set_paper( $args );

		/**
		 * Fires before rendering the PDF content.
		 *
		 * @since 2.0.2
		 *
		 * @param \Dompdf\Dompdf $dompdf DOMPDF object.
		 * @param array          $args   See {@see FrmPdfsAppController::generate_entry_pdf()} for more details.
		 */
		do_action( 'frm_pdfs_before_render', $this->converter, $args );
	}

	/**
	 * Gets total pages value.
	 *
	 * @since 2.0.5
	 *
	 * @return int
	 */
	public function get_total_pages() {
		return $this->converter->getCanvas()->get_page_count();
	}

	/**
	 * After render the HTML to PDF.
	 *
	 * @since 2.0.2 Added `$args` parameter.
	 * @since 2.0.5 The `$args` is optional.
	 *
	 * @param array $args See {@see FrmPdfsAppController::generate_entry_pdf()} for more details.
	 */
	protected function after_render( $args = array() ) {
		$this->parse_args( $args );

		/**
		 * Fires after rendering the PDF content, before showing the file.
		 *
		 * @since 2.0.2
		 *
		 * @param \Dompdf\Dompdf $dompdf DOMPDF object.
		 * @param array          $args   See {@see FrmPdfsAppController::generate_entry_pdf()} for more details.
		 */
		do_action( 'frm_pdfs_after_render', $this->converter, $args );

		FrmPdfsFileProtectionHelper::restore_chmod();
	}

	/**
	 * Maybe replace pagination total pages.
	 *
	 * @since 2.0.2
	 *
	 * @depcrecated 2.0.5
	 *
	 * @param array $args See {@see FrmPdfsAppController::generate_entry_pdf()} for more details.
	 */
	protected function replace_pagination_total_pages( $args ) {
		_deprecated_function( __METHOD__, '2.0.5' );
		if ( empty( $args['show_pagination'] ) ) {
			return;
		}

		$pdf = $this->converter->getCanvas()->get_cpdf();

		foreach ( $pdf->objects as &$o ) {
			if ( 'contents' === $o['t'] ) {
				$o['c'] = str_replace(
					FrmPdfsAppHelper::get_pagination_total_pages_placeholder(),
					$this->converter->getCanvas()->get_page_count(),
					$o['c']
				);
			}
		}
	}

	/**
	 * Streams the PDF to the client.
	 *
	 * The file will open a download dialog by default. The options
	 * parameter controls the output. Accepted options (array keys) are:
	 *
	 * 'compress' = > 1 (=default) or 0:
	 *   Apply content stream compression
	 *
	 * 'Attachment' => 1 (=default) or 0:
	 *   Set the 'Content-Disposition:' HTTP header to 'attachment'
	 *   (thereby causing the browser to open a download dialog)
	 *
	 * @param string $filename The name of the streamed file.
	 * @param array  $options  Header options (see above).
	 * @return void
	 */
	public function stream( $filename, $options = array() ) {
		$this->converter->stream( $filename, $options );
	}

	/**
	 * Returns the PDF as a string.
	 *
	 * The options parameter controls the output. Accepted options are:
	 *
	 * 'compress' = > 1 or 0 - apply content stream compression, this is
	 *    on (1) by default
	 *
	 * @param array $options options (see above).
	 *
	 * @return string|null
	 */
	public function output( $options = array() ) {
		return $this->converter->output( $options );
	}
}
