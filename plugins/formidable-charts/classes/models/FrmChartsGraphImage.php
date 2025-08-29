<?php
/**
 * Graph image model
 *
 * @since 2.0
 * @package FrmCharts
 */

/**
 * Class FrmChartsGraphImage
 */
class FrmChartsGraphImage {

	/**
	 * Graph data
	 *
	 * @var array
	 */
	private $graph_data;

	/**
	 * Font family
	 *
	 * @var string
	 */
	private $font_family;

	/**
	 * Font size
	 *
	 * @var int
	 */
	private $font_size = 10;

	/**
	 * Width
	 *
	 * @var int
	 */
	private $width = 400;

	/**
	 * Height
	 *
	 * @var int
	 */
	private $height = 400;

	/**
	 * Background color
	 *
	 * @var string
	 */
	private $bg_color = 'white';

	/**
	 * Graph margin
	 *
	 * @var array
	 */
	private $margin = array(
		'left'   => 120,
		'right'  => 120,
		'top'    => 120,
		'bottom' => 120,
	);

	/**
	 * X-axis data
	 *
	 * @var array
	 */
	private $x_data = array();

	/**
	 * Y-axis data
	 *
	 * @var array[]
	 */
	private $y_datas = array();

	/**
	 * JPGraph object
	 *
	 * @var Graph
	 */
	private $jpgraph;

	/**
	 * Plots
	 *
	 * @var Plot
	 */
	private $plots = array();

	/**
	 * Constructor.
	 *
	 * @param array $graph_data Graph data.
	 */
	public function __construct( $graph_data ) {
		$this->graph_data = $graph_data;
		if ( ! empty( $this->graph_data['options']['width'] ) ) {
			$this->width = intval( $this->graph_data['options']['width'] );
		}

		if ( ! empty( $this->graph_data['options']['height'] ) ) {
			$this->height = intval( $this->graph_data['options']['height'] );
		}

		if ( ! empty( $this->graph_data['options']['backgroundColor'] ) ) {
			$this->bg_color = $this->graph_data['options']['backgroundColor'];
		}

		require_once FrmChartsAppHelper::plugin_path() . '/lib/jpgraph/src/jpgraph.php';

		$this->font_family = FF_DV_SANSSERIF;

		$this->build_axis_data();
		$this->init_graph_obj();

		foreach ( $this->plots as $plot ) {
			$this->jpgraph->Add( $plot );
		}
	}

	/**
	 * Initializes the JPGraph object.
	 */
	private function init_graph_obj() {
		$graph_class   = $this->get_graph_class_name();
		$this->jpgraph = new $graph_class( $this->width, $this->height );

		$this->jpgraph->clearTheme();
		$this->jpgraph->setScale( $this->get_scale() );

		$this->process_bg_color_and_grid();

		$this->process_margin();

		$this->jpgraph->SetMargin( $this->margin['left'], $this->margin['right'], $this->margin['top'], $this->margin['bottom'] );

		$this->jpgraph->xaxis->SetTickLabels( $this->x_data );

		$this->init_plots();

		$options = $this->graph_data['options'];

		// Graph title.
		$this->process_title( $this->jpgraph->title, $options );

		if ( $this->has_axis() ) {
			if ( ! empty( $options['hAxis'] ) ) {
				// X-axis title.
				$this->process_title( $this->jpgraph->xaxis->title, $options['hAxis'] );

				// X-axis labels.
				if ( ! empty( $options['hAxis']['textStyle'] ) ) {
					$this->process_text_style( $this->jpgraph->xaxis, $options['hAxis']['textStyle'] );
				}

				$this->process_xaxis_text_angle();
			}

			if ( ! empty( $options['vAxis'] ) ) {
				// Y-axis title.
				$this->process_title( $this->jpgraph->yaxis->title, $options['vAxis'] );

				// Y-axis labels.
				if ( ! empty( $options['vAxis']['textStyle'] ) ) {
					$this->process_text_style( $this->jpgraph->yaxis, $options['vAxis']['textStyle'] );
				}
			}

			if ( ! empty( $options['hAxis']['showTextEvery'] ) ) {
				$this->jpgraph->xaxis->SetTextTickInterval( $options['hAxis']['showTextEvery'] );
			}

			if ( 'bar' === $this->graph_data['type'] ) {
				$this->jpgraph->Set90AndMargin( $this->margin['left'], $this->margin['right'], $this->margin['top'], $this->margin['bottom'] );
			}
		}

		$this->process_plots();
		$this->process_legend_styling();
		$this->process_colors();
	}

	/**
	 * Initializes the plots.
	 */
	private function init_plots() {
		$plot_class = $this->get_plot_class_name();
		if ( ! $this->y_datas ) {
			return;
		}

		if ( 1 === count( $this->y_datas ) ) {
			$this->plots = array( new $plot_class( $this->y_datas[0] ) );
		}

		// Currently, multiple y data only supports bar charts.
		$plots = array_map(
			function( $y_data ) use ( $plot_class ) {
				return new $plot_class( $y_data );
			},
			$this->y_datas
		);

		if ( 'BarPlot' === $plot_class && 1 < count( $plots ) ) {
			// Multi-column chart needs a specific class.
			$plots = array( new GroupBarPlot( $plots ) );
		}

		$this->plots = $plots;
	}

	/**
	 * Processes the plots.
	 */
	private function process_plots() {
		foreach ( $this->plots as $index => $plot ) {
			if ( 'scatter' === $this->graph_data['type'] ) {
				$plot->mark->SetType( MARK_FILLEDCIRCLE );
				$plot->mark->SetWidth( 4 );
				continue;
			}

			if ( 'pie' === $this->graph_data['type'] ) {
				$plot->SetStartAngle( 90 );

				$plot_class = get_class( $plot );

				if ( 'PiePlot3D' === $plot_class ) {
					$plot->SetAngle( 70 );
				} elseif ( 'PiePlotC' === $plot_class ) {
					$plot->SetMidSize( floatval( $this->graph_data['options']['pieHole'] ) );
					$plot->SetMidColor( $this->graph_data['options']['backgroundColor'] );
				}

				continue;
			}
		}
	}

	/**
	 * Processes the background color and grid.
	 */
	private function process_bg_color_and_grid() {
		$this->jpgraph->SetFrame( true, $this->bg_color, 5 );
		$this->jpgraph->SetColor( $this->bg_color );
		$this->jpgraph->SetMarginColor( $this->bg_color );

		$this->jpgraph->ygrid->Show( true, true );

		$ygrid_color = ! empty( $this->graph_data['options']['vAxis']['gridlines']['color'] ) ? $this->graph_data['options']['vAxis']['gridlines']['color'] : '#cccccc';
		$this->jpgraph->ygrid->SetColor( $ygrid_color, $ygrid_color . '@0.5' );
		$this->jpgraph->ygrid->SetFill( true, $this->bg_color, $this->bg_color );
	}

	/**
	 * Processes the margin.
	 */
	private function process_margin() {
		if ( empty( $this->graph_data['options']['chartArea'] ) ) {
			return;
		}

		$chart_area = $this->graph_data['options']['chartArea'];

		if ( empty( $chart_area['top'] ) ) {
			$top = 100;
		} else {
			$top_value = FrmChartsAppHelper::parse_unit( $chart_area['top'] );
			if ( '%' === $top_value['unit'] ) {
				$top = $top_value['number'] * $this->height / 100;
			} else {
				$top = $top_value['number'];
			}
		}

		if ( empty( $chart_area['left'] ) ) {
			$left = 100;
		} else {
			$left_value = FrmChartsAppHelper::parse_unit( $chart_area['left'] );
			if ( '%' === $left_value['unit'] ) {
				$left = $left_value['number'] * $this->width / 100;
			} else {
				$left = $left_value['number'];
			}
		}

		if ( ! empty( $chart_area['width'] ) ) {
			$width_value = FrmChartsAppHelper::parse_unit( $chart_area['width'] );
			if ( '%' === $width_value['unit'] ) {
				$width = $width_value['number'] * $this->width / 100;
			} else {
				$width = $width_value['number'];
			}

			$right = $this->width - $left - $width;
			if ( $right < 0 ) {
				$right = 0;
			}
		} else {
			$right = 100;
		}

		if ( ! empty( $chart_area['height'] ) ) {
			$height_value = FrmChartsAppHelper::parse_unit( $chart_area['height'] );
			if ( '%' === $height_value['unit'] ) {
				$height = $height_value['number'] * $this->height / 100;
			} else {
				$height = $height_value['number'];
			}

			$bottom = $this->height - $top - $height;
			if ( $bottom < 0 ) {
				$bottom = 0;
			}
		} else {
			$bottom = 100;
		}

		$this->margin = compact( 'left', 'right', 'top', 'bottom' );
	}

	/**
	 * Gets the class name of the graph.
	 *
	 * @return string
	 */
	private function get_graph_class_name() {
		if ( 'pie' === $this->graph_data['type'] ) {
			require_once FrmChartsAppHelper::plugin_path() . '/lib/jpgraph/src/jpgraph_pie.php';
			return 'PieGraph';
		}
		return 'Graph';
	}

	/**
	 * Gets the scale of the graph.
	 *
	 * @return string
	 */
	private function get_scale() {
		if ( 'area' === $this->graph_data['type'] || 'steppedArea' === $this->graph_data['type'] ) {
			return 'intlin';
		}
		return 'textlin';
	}

	/**
	 * Checks if this graph type supports axis.
	 *
	 * @return bool
	 */
	private function has_axis() {
		return 'pie' !== $this->graph_data['type'];
	}

	/**
	 * Gets the colors from the axis data.
	 *
	 * @return array
	 */
	private function get_colors_from_axis_data() {
		$colors = array();

		foreach ( $this->graph_data['data'] as $index => $data ) {
			if ( 0 === $index ) {
				continue;
			}

			$colors[] = end( $data );
		}

		return $colors;
	}

	/**
	 * Processes the colors.
	 */
	private function process_colors() {
		$maybe_role_color = end( $this->graph_data['data'][0] );
		if ( is_array( $maybe_role_color ) && isset( $maybe_role_color['role'] ) && 'style' === $maybe_role_color['role'] ) {
			$colors = $this->get_colors_from_axis_data();
		} elseif ( ! empty( $this->graph_data['options']['colors'] ) ) {
			$colors = $this->graph_data['options']['colors'];
		}

		if ( empty( $colors ) ) {
			return;
		}

		$colors_count = count( $colors );

		$is_multi_column_bar = ! empty( $this->plots[0]->plots );
		if ( $is_multi_column_bar ) {
			// If this is a multi-column bar chart, use the subplots.
			$plots = $this->plots[0]->plots;
		} else {
			$plots = $this->plots;
		}

		foreach ( $plots as $index => $plot ) {
			if ( 'pie' === $this->graph_data['type'] ) {
				$plot->SetSliceColors( $colors );
				continue;
			}

			if ( $colors_count < $index ) {
				$color_index = ( $index - $colors_count ) % $colors_count;
			} else {
				$color_index = $index;
			}

			$color = $colors[ $color_index ];

			$plot->SetColor( $color );
			if ( method_exists( $plot, 'SetFillColor' ) && 'line' !== $this->graph_data['type'] ) {
				// If is multi-column bar, set single color to each plot, otherwise set all colors to the plot.
				$plot->SetFillColor( $is_multi_column_bar ? $color : $colors );
			}

			if ( 'scatter' === $this->graph_data['type'] ) {
				$plot->mark->SetFillColor( $color );
				$plot->mark->SetColor( $color );
				continue;
			}

			if ( 'area' === $this->graph_data['type'] ) {
				$plot->SetFillColor( $color . '@0.7' );
				continue;
			}

			if ( 'steppedArea' === $this->graph_data['type'] ) {
				$plot->SetStepStyle( true );
				$plot->SetFillColor( $color . '@0.7' );
			}
		}
	}

	/**
	 * Processes the legend styling.
	 */
	private function process_legend_styling() {
		$position = ! empty( $this->graph_data['options']['legend']['position'] ) ? $this->graph_data['options']['legend']['position'] : '';
		if ( ! $position || 'none' === $position ) {
			$this->jpgraph->legend->Hide( true );
			return;
		}

		$top_offset = ! empty( $this->graph_data['options']['title'] ) ? 0.08 : 0.03;
		foreach ( $this->plots as $index => $plot ) {
			if ( 'pie' !== $this->graph_data['type'] ) {
				if ( ! empty( $plot->plots ) ) {
					foreach ( $plot->plots as $subindex => $subplot ) {
						$subplot->SetLegend( $this->graph_data['data'][0][ $subindex + 1 ] );
					}
				} else {
					$plot->SetLegend( $this->graph_data['data'][0][ $index + 1 ] );
				}
				continue;
			}

			$plot->SetLegends( $this->x_data );

			if ( 'top' === $position ) {
				$plot->SetCenter( 0.5, 0.6 );
			} elseif ( 'bottom' === $position ) {
				$plot->SetCenter( 0.5, 0.5 );
			} else {
				$plot->SetSize( 0.3 );
				$plot->SetCenter( 'left' === $position ? 0.6 : 0.4, 0.5 );
			}
		}

		if ( ! empty( $this->graph_data['options']['legend']['textStyle'] ) ) {
			$this->process_text_style( $this->jpgraph->legend, $this->graph_data['options']['legend']['textStyle'] );
		}

		if ( 'bottom' === $position ) {
			$this->jpgraph->legend->SetPos( 0.5, 0.97, 'center', 'bottom' );
		} elseif ( 'top' === $position ) {
			$this->jpgraph->legend->SetPos( 0.5, $top_offset, 'center', 'top' );
		} else {
			$this->jpgraph->legend->SetColumns( 1 );
			$this->jpgraph->legend->SetPos( 0.03, $top_offset, $position, 'top' );
		}

		if ( ! empty( $this->graph_data['options']['legend']['legend_columns'] ) ) {
			$this->jpgraph->legend->SetColumns( intval( $this->graph_data['options']['legend']['legend_columns'] ) );
		}
	}

	/**
	 * Processes the title.
	 *
	 * @param object $obj        The object to process.
	 * @param array  $title_data The title data.
	 */
	private function process_title( $obj, $title_data ) {
		if ( ! empty( $title_data['title'] ) ) {
			$obj->Set( $title_data['title'] );
		}

		if ( ! empty( $title_data['title_margin'] ) ) {
			$obj->SetMargin( floatval( $title_data['title_margin'] ) );
		}

		if ( isset( $title_data['titleTextStyle'] ) ) {
			$this->process_text_style( $obj, $title_data['titleTextStyle'] );
		}
	}

	/**
	 * Processes the text style.
	 *
	 * @param object $obj        The object to process.
	 * @param array  $text_style The text style.
	 */
	private function process_text_style( $obj, $text_style ) {
		if ( ! empty( $text_style['color'] ) ) {
			$obj->SetColor( $text_style['color'] );
		}

		$font_size = ! empty( $text_style['fontSize'] ) ? floatval( $text_style['fontSize'] ) : $this->font_size;
		$obj->SetFont(
			$this->font_family,
			$this->get_font_style(
				! empty( $text_style['bold'] ),
				! empty( $text_style['italic'] )
			),
			floatval( $font_size )
		);
	}

	/**
	 * Processes the x-axis text angle.
	 */
	private function process_xaxis_text_angle() {
		$gd_info = gd_info();
		if ( empty( $gd_info['FreeType Support'] ) ) {
			return;
		}

		$options = $this->graph_data['options'];
		if ( empty( $options['hAxis']['slantedText'] ) ) {
			return;
		}

		$angle = isset( $options['hAxis']['slantedTextAngle'] ) ? intval( $options['hAxis']['slantedTextAngle'] ) : 30;
		$this->jpgraph->xaxis->SetLabelAngle( $angle );
	}

	/**
	 * Gets the font style.
	 *
	 * @param bool $bold   Whether the font is bold.
	 * @param bool $italic Whether the font is italic.
	 * @return int
	 */
	private function get_font_style( $bold, $italic ) {
		if ( $bold && $italic ) {
			return FS_BOLDITALIC;
		}

		if ( $bold ) {
			return FS_BOLD;
		}

		if ( $italic ) {
			return FS_ITALIC;
		}

		return FS_NORMAL;
	}

	/**
	 * Gets the plot class name.
	 *
	 * @return string
	 */
	private function get_plot_class_name() {
		switch ( $this->graph_data['type'] ) {
			case 'area':
			case 'steppedArea':
			case 'line':
				require_once FrmChartsAppHelper::plugin_path() . '/lib/jpgraph/src/jpgraph_line.php';
				return 'LinePlot';
			case 'pie':
				require_once FrmChartsAppHelper::plugin_path() . '/lib/jpgraph/src/jpgraph_pie.php';

				if ( ! empty( $this->graph_data['options']['is3D'] ) ) {
					require_once FrmChartsAppHelper::plugin_path() . '/lib/jpgraph/src/jpgraph_pie3d.php';
					return 'PiePlot3D';
				}

				if ( ! empty( $this->graph_data['options']['pieHole'] ) ) {
					return 'PiePlotC';
				}

				return 'PiePlot';
			case 'scatter':
				require_once FrmChartsAppHelper::plugin_path() . '/lib/jpgraph/src/jpgraph_scatter.php';
				return 'ScatterPlot';
			default:
				require_once FrmChartsAppHelper::plugin_path() . '/lib/jpgraph/src/jpgraph_bar.php';
				return 'BarPlot';
		}
	}

	/**
	 * Builds the axis data.
	 */
	private function build_axis_data() {
		foreach ( $this->graph_data['data'] as $index => $data ) {
			// The first item includes the axis labels.
			if ( 0 === $index ) {
				continue;
			}

			foreach ( $data as $subindex => $subdata ) {
				if ( 0 === $subindex ) {
					// The first item is for the x-axis.
					$this->x_data[] = $subdata;
				} else {
					// Skip if the value is empty or an array.
					if ( empty( $this->graph_data['data'][0][ $subindex ] ) || is_array( $this->graph_data['data'][0][ $subindex ] ) ) {
						continue;
					}

					// Remaining items will be added to the corresponding y-axis data.
					if ( ! isset( $this->y_datas[ $subindex - 1 ] ) ) {
						$this->y_datas[ $subindex - 1 ] = array();
					}

					$this->y_datas[ $subindex - 1 ][] = $subdata;
				}
			}
		}
	}

	/**
	 * Gets the JPGraph object.
	 *
	 * @return Graph
	 */
	private function get_jpgraph() {
		return $this->jpgraph;
	}

	/**
	 * Gets the output.
	 *
	 * @param bool $output_image Whether to output the image directly to the browser.
	 * @return string
	 */
	public function get_output( $output_image = false ) {
		$this->jpgraph->img->SetImgFormat( 'jpeg' );
		if ( $output_image ) {
			$this->jpgraph->Stroke();
			die();
		}

		$image_handler = $this->jpgraph->Stroke( _IMG_HANDLER );
		if ( FrmChartsGraphImageController::$is_processing_email ) {
			// In email, we save the image to a file because some clients don't support base64 images.
			$upload_path = FrmChartsGraphImageController::get_graph_image_dir_path();
			FrmChartsGraphImageController::maybe_create_graph_image_dir( $upload_path );

			$file_name = 'graph_' . wp_generate_password( 10, false ) . '.jpeg';
			$file_path = $upload_path . $file_name;

			imagejpeg( $image_handler, $file_path );

			// Set image to write only.
			FrmProFileField::set_to_write_only( $file_path );

			// Use a dynamic URL to handle file permission.
			$image_src = add_query_arg( 'frm_graph', base64_encode( $file_name ), site_url() );
			// $image_src = FrmChartsGraphImageController::get_graph_image_dir_url() . $file_name;
		} else {
			ob_start();
			imagejpeg( $image_handler );
			$image_output = ob_get_clean();

			$image_src = 'data:image/jpeg;base64,' . base64_encode( $image_output );
		}

		imagedestroy( $image_handler );

		return sprintf(
			'<img src="%s" alt="%s" width="%d" height="%d" />',
			esc_attr( $image_src ),
			esc_attr( 'graph_' . $this->graph_data['graph_id'] ),
			$this->width,
			$this->height
		);
	}
}
