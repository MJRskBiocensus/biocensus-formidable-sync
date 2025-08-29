<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 1.3
 */
class FrmGeoMapViewController {

	/**
	 * @var array|null
	 */
	private static $lat_longs_by_view_id;

	/**
	 * @return void
	 */
	public static function load_hooks() {
		add_filter( 'frm_display_entries_content', array( __CLASS__, 'display_entries_content' ), 10, 6 );
		add_filter( 'frm_display_entry_content', array( __CLASS__, 'display_entry_content' ), 11, 7 );
		add_filter( 'frm_display_inner_content_before_add_wrapper', array( __CLASS__, 'display_inner_content_before_add_wrapper' ), 10, 3 );
		add_filter( 'frm_valid_view_types', array( __CLASS__, 'valid_view_types' ) );
	}

	/**
	 * @return void
	 */
	public static function load_admin_hooks() {
		add_action( 'frm_views_enqueue_editor_scripts', array( __CLASS__, 'enqueue_editor_scripts' ) );
		add_action( 'frm_views_editor_init', array( __CLASS__, 'views_editor_init' ) );

		if ( defined( 'DOING_AJAX' ) ) {
			add_filter( 'frm_views_should_preview_shortcode', array( __CLASS__, 'should_preview_shortcode' ), 10, 2 );
			add_filter( 'frm_views_editor_data_processor_response_data', array( __CLASS__, 'views_editor_data_processor_response_data' ), 10, 3 );
			add_filter( 'frm_views_editor_options_before_update', array( __CLASS__, 'views_editor_options_before_update' ), 10, 2 );
			add_filter( 'wp_ajax_frm_views_process_box_preview', array( __CLASS__, 'before_views_editor_preview' ), 0 );
		}
	}

	/**
	 * @param string $content
	 * @param array  $entry_ids
	 * @param array  $shortcodes
	 * @param object $view
	 * @param string $show_count
	 * @param array  $args
	 * @return string
	 */
	public static function display_entries_content( $content, $entry_ids, $shortcodes, $view, $show_count, $args ) {
		if ( empty( $args['is_map_view'] ) ) {
			return $content;
		}

		if ( ! isset( self::$lat_longs_by_view_id ) ) {
			self::$lat_longs_by_view_id = array();
		}

		if ( ! array_key_exists( $view->ID, self::$lat_longs_by_view_id ) ) {
			self::$lat_longs_by_view_id[ $view->ID ] = array();
		}

		return $content;
	}

	/**
	 * @param string   $content
	 * @param stdClass $entry
	 * @param array    $shortcodes
	 * @param WP_Post  $view
	 * @param string   $show_count 'all', 'one', 'dynamic' or 'calendar'.
	 * @param string   $odd 'odd' or 'even'.
	 * @param array    $args
	 */
	public static function display_entry_content( $content, $entry, $shortcodes, $view, $show_count, $odd, $args ) {
		if ( empty( $args['is_map_view'] ) ) {
			return $content;
		}

		$meta = FrmGeoAppController::get_geo_entry_meta( $entry->id );
		if ( ! $meta ) {
			return $content;
		}

		$options = get_post_meta( $view->ID, 'frm_options', true );
		FrmAppHelper::unserialize_or_decode( $options );

		/**
		 * @since 1.3
		 *
		 * @param array   $options
		 * @param WP_Post $view
		 */
		$options = apply_filters( 'frm_map_views_options', $options, $view );

		if ( empty( $options['map_address_fields'] ) || ! is_array( $options['map_address_fields'] ) ) {
			return $content;
		}

		$detail_page_on_marker_click = ! $content && ! empty( $view->frm_dyncontent );
		if ( $detail_page_on_marker_click ) {
			$detail_link = '[detaillink]';
			FrmProContent::do_shortcode_detaillink(
				$detail_link,
				array(),
				array( array( $detail_link ) ),
				0,
				compact( 'entry' ),
				$view
			);
		} else {
			$detail_link = '';
		}

		$allowed_address_fields = array_map( 'absint', $options['map_address_fields'] );

		foreach ( $meta as $key => $value ) {
			if ( ! is_numeric( $value ) || 0 !== strpos( $key, 'latitude_' ) ) {
				continue;
			}

			$field_id = explode( '_', $key )[1];
			if ( ! is_numeric( $field_id ) || ! isset( $meta[ 'longitude_' . $field_id ] ) || ! is_numeric( $meta[ 'longitude_' . $field_id ] ) ) {
				continue;
			}

			if ( ! in_array( (int) $field_id, $allowed_address_fields, true ) ) {
				continue;
			}

			if ( isset( self::$lat_longs_by_view_id[ $view->ID ] ) && is_array( self::$lat_longs_by_view_id[ $view->ID ] ) ) {
				$popup_content = $content;

				FrmViewsDisplaysController::maybe_filter_content(
					array(
						'filter' => self::get_filter( $view->ID ),
					),
					$popup_content
				);
				$lat_long_data = array(
					'lat'     => $value,
					'long'    => $meta[ 'longitude_' . $field_id ],
					'content' => rawurlencode( $popup_content ),
				);
				if ( $detail_link ) {
					$lat_long_data['detailLink'] = $detail_link;
				}
				self::$lat_longs_by_view_id[ $view->ID ][] = $lat_long_data;
			}
		}

		return $content;
	}

	/**
	 * Get the filter setting to use for the Map view pop up content.
	 *
	 * @since 1.3.1
	 *
	 * @param int|string $view_id
	 * @return string
	 */
	private static function get_filter( $view_id ) {
		if ( FrmAppHelper::doing_ajax() && 'frm_views_process_box_preview' === FrmAppHelper::get_param( 'action', '', 'post', 'sanitize_key' ) ) {
			// When previewing from the editor, use the preview value in POST data.
			$filter = FrmAppHelper::get_param( 'activePreviewFilter', '', 'post', 'sanitize_text_field' );
			return self::maybe_use_default_filter( $filter );
		}

		// Get the filter from the page state because we do not have access to $atts.
		$filter = FrmViewsPageState::get_from_request( 'filter', '' );
		if ( ! self::filter_value_is_valid( $filter ) ) {
			// Fallback to the post meta setting when there is no shortcode attribute defined.
			$filter = get_post_meta( $view_id, 'frm_active_preview_filter', true );
			$filter = self::maybe_use_default_filter( $filter );
		}
		return $filter;
	}

	/**
	 * Check if the $filter option matches a valid expected value.
	 *
	 * @since 1.3.1
	 *
	 * @param string $filter
	 * @return bool
	 */
	private static function filter_value_is_valid( $filter ) {
		return in_array( $filter, array( '0', '1', 'limited' ), true );
	}

	/**
	 * Make sure that $filter is a valid value by validating it and using the default when necessary.
	 *
	 * @param string $filter
	 * @return string
	 */
	private static function maybe_use_default_filter( $filter ) {
		if ( ! self::filter_value_is_valid( $filter ) ) {
			$filter = FrmViewsAppHelper::get_default_content_filter();
		}
		return $filter;
	}

	/**
	 * @param string $content
	 * @param object $view
	 * @param array  $args
	 * @return string
	 */
	public static function display_inner_content_before_add_wrapper( $content, $view, $args ) {
		if ( empty( $args['is_map_view'] ) ) {
			return $content;
		}

		return self::get_map( $view->ID );
	}

	/**
	 * @param int $view_id
	 * @return string
	 */
	private static function get_map( $view_id ) {
		$lat_longs = isset( self::$lat_longs_by_view_id[ $view_id ] ) ? self::$lat_longs_by_view_id[ $view_id ] : array();

		FrmGeoAppController::enqueue_google_scripts();

		/**
		 * @param int   $zoom
		 * @param array $args
		 */
		$zoom = apply_filters(
			'frm_geo_map_zoom',
			9,
			array(
				'entry_id' => false,
				'view_id'  => $view_id,
			)
		);

		ob_start();
		$map_container_id = 'frmgeo-map-' . $view_id;
		?>
		<style>
			<?php echo '#' . sanitize_html_class( $map_container_id ); ?> .gm-style-iw-chr {
				display: none;
			}
			<?php echo '#' . sanitize_html_class( $map_container_id ); ?> .gm-style-iw-d p {
				margin-top: 1em;
				line-height: 1.5;
			}
		</style>
		<div id="frmgeo-map-<?php echo absint( $view_id ); ?>" style="height: 300px;"></div>
		<script>
			(function() {
				if ( document.readyState === 'complete' ) {
					setTimeout( loadMap, 0 );
				} else {
					window.addEventListener( 'load', loadMap );
				}
				function loadMap() {
					var bounds, mapElem, latLongs, length, mapConfig, map, i, location, markerContent, marker;
					if ( 'function' !== typeof google.maps.LatLngBounds ) {
						/*
						The map script loads asynchronously.
						If it makes it here it is because it has not loaded yet.
						So exit early and try again in 100ms.
						*/
						setTimeout( loadMap, 100 );
						return;
					}
					bounds    = new google.maps.LatLngBounds();
					mapElem   = document.getElementById( 'frmgeo-map-<?php echo absint( $view_id ); ?>' );
					latLongs  = <?php echo json_encode( $lat_longs ); ?>;
					length    = latLongs.length;
					mapConfig = { mapId: 'DEMO_MAP_ID' };
					if ( 1 === length ) {
						mapConfig = {
							zoom: <?php echo absint( $zoom ); ?>,
							center: new google.maps.LatLng( latLongs[0].lat, latLongs[0].long ),
							mapId: 'DEMO_MAP_ID'
						};
					}
					map = new google.maps.Map( mapElem, mapConfig );

					function getPlainMarkerContent() {
						var markerContentDiv = document.createElement( 'div' );
						markerContentDiv.innerHTML = markerContent;
						return markerContentDiv.textContent.trim();
					}

					for ( i = 0; i < length; ++i ) {
						location      = new google.maps.LatLng( latLongs[i].lat, latLongs[i].long );
						markerContent = decodeURIComponent( latLongs[i].content );
						marker        = new google.maps.marker.AdvancedMarkerElement(
							{
								position: location,
								map: map,
								title: getPlainMarkerContent()
							}
						);
						addMarkerClickListener( marker, latLongs[i] );
						bounds.extend( location );
						triggerCustomEvent( document, 'frmGeoAddedMapMarker', { map, marker, markerContent, i });
					}
					if ( length > 1 ) {
						map.fitBounds( bounds );
					}
					function addMarkerClickListener( marker, latLongData ) {
						var content = decodeURIComponent( latLongData.content );
						google.maps.event.addListener(
							marker,
							'click',
							function() {
								var infowindow, maybeCloseInfoWindow;
								if ( '' === content ) {
									if ( latLongData.detailLink ) {
										window.location.href = latLongData.detailLink;
									}
									return;
								}
								infowindow = new google.maps.InfoWindow({ content: content });
								infowindow.open( map, marker );
								maybeCloseInfoWindow = function( e ) {
									var infowindowContainer = document.querySelector( '.gm-style-iw');
									if ( marker.element.contains( e.target ) || ( infowindowContainer && infowindowContainer.contains( e.target ) ) ) {
										return;
									}
									infowindow.close();
									document.removeEventListener( 'click', maybeCloseInfoWindow );
								};
								document.addEventListener( 'click', maybeCloseInfoWindow );
							}
						);
					}
				}
				function triggerCustomEvent( el, eventName, data ) {
					var event;
					if ( typeof window.CustomEvent === 'function' ) {
						event = new CustomEvent( eventName );
					} else if ( document.createEvent ) {
						event = document.createEvent( 'HTMLEvents' );
						event.initEvent( eventName, false, true );
					} else {
						return;
					}
					event.frmData = data;
					el.dispatchEvent( event );
				}
			}());
		</script>
		<?php
		return str_replace(
			array( "\r", "\n", "\t" ),
			'',
			ob_get_clean()
		);
	}

	/**
	 * @param array<string> $view_types
	 * @return array<string>
	 */
	public static function valid_view_types( $view_types ) {
		$view_types[] = 'map';
		return $view_types;
	}

	/**
	 * @param bool   $should_preview_shortcode
	 * @param object $view
	 * @return bool
	 */
	public static function should_preview_shortcode( $should_preview_shortcode, $view ) {
		if ( FrmViewsDisplaysHelper::is_map_type( $view ) ) {
			$should_preview_shortcode = true;
		}
		return $should_preview_shortcode;
	}

	/**
	 * @param int $view_id
	 * @return void
	 */
	public static function enqueue_editor_scripts( $view_id ) {
		if ( ! FrmViewsDisplaysHelper::is_map_type( $view_id ) ) {
			return;
		}

		$url             = FrmGeoAppHelper::plugin_url() . '/js/views-editor.js';
		$js_dependencies = array( 'formidable_views_editor' );
		$version         = FrmGeoAppHelper::plugin_version();
		wp_register_script( 'formidable_views_editor_map_scripts', $url, $js_dependencies, $version, true );
		wp_enqueue_script( 'formidable_views_editor_map_scripts' );
	}

	/**
	 * @param array   $response_data
	 * @param object  $view
	 * @param Closure $get_option_value_function
	 * @return array
	 */
	public static function views_editor_data_processor_response_data( $response_data, $view, $get_option_value_function ) {
		if ( ! FrmViewsDisplaysHelper::is_map_type( $view ) ) {
			return $response_data;
		}

		$response_data = array_merge(
			$response_data,
			array(
				'mapAddressFields' => array_map( 'strval', $get_option_value_function( 'map_address_fields', array() ) ),
			)
		);
		return $response_data;
	}

	/**
	 * @param array   $options
	 * @param WP_Post $view
	 * @return array
	 */
	public static function views_editor_options_before_update( $options, $view ) {
		$is_map_type = FrmViewsDisplaysHelper::is_map_type( $view );
		if ( ! $is_map_type ) {
			return $options;
		}

		$options['map_address_fields'] = array_filter(
			FrmAppHelper::get_param( 'mapAddressFields', array(), 'post' ),
			'is_numeric'
		);
		return $options;
	}

	/**
	 * @return void
	 */
	public static function before_views_editor_preview() {
		if ( ! is_callable( 'FrmViewsDisplaysHelper::is_map_type' ) ) {
			return;
		}

		$view_id = FrmAppHelper::get_param( 'view', '', 'post', 'absint' );
		if ( ! $view_id || ! FrmViewsDisplaysHelper::is_map_type( $view_id ) ) {
			return;
		}

		add_filter(
			'frm_map_views_options',
			/**
			 * Use the mapped address field value from $_POST data for the map view preview.
			 *
			 * @param array  $options
			 * @param object $view
			 * @return array
			 */
			function ( $options, $view ) use ( $view_id ) {
				if ( $view->ID !== $view_id ) {
					return $options;
				}
				$options['map_address_fields'] = FrmAppHelper::get_param( 'mapAddressFields', array(), 'post' );
				return $options;
			},
			10,
			2
		);
	}

	/**
	 * @since 1.3
	 *
	 * @param array $args
	 * @return void
	 */
	public static function views_editor_init( $args ) {
		if ( empty( $args['is_map_type'] ) ) {
			return;
		}

		add_filter(
			'frm_forms_dropdown',
			/**
			 * Filter the forms dropdown for a Map view so only forms with address fields appear as data source options.
			 *
			 * @param array $where
			 * @return array
			 */
			function ( $where ) {
				$form_ids_with_address_fields = array_unique(
					FrmDb::get_col(
						'frm_fields',
						array( 'type' => 'address' ),
						'form_id'
					)
				);
				$where['id'] = $form_ids_with_address_fields;
				return $where;
			}
		);
	}
}
