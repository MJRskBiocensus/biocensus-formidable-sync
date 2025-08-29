<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmGeoAppController {

	/**
	 * @return void
	 */
	public static function load_hooks() {
		add_action( 'init', array( __CLASS__, 'load_lang' ) );

		add_filter( 'frm_field_extra_html', array( __CLASS__, 'add_input_attributes' ), 10, 2 );
		add_action( 'frm_before_replace_shortcodes', array( __CLASS__, 'add_lat_lng_fields' ), 10, 2 );
		add_action( 'frm_get_field_scripts', array( __CLASS__, 'add_hidden_lat_lng_fields' ), 10, 3 );
		add_action( 'frm_after_create_entry', array( __CLASS__, 'save_lat_lng' ), 10, 3 );
		add_action( 'frm_after_update_entry', array( __CLASS__, 'save_lat_lng' ), 10, 2 );
		add_action( 'frm_entry_shared_sidebar', array( __CLASS__, 'show_lat_lng' ), 20 );
		add_action( 'frm_after_show_entry', array( __CLASS__, 'show_entry_map' ), 1 );
		add_filter( 'frm_address_sub_fields', array( __CLASS__, 'extend_address_sub_fields' ) );
		add_filter( 'frm_address_empty_value_array', array( __CLASS__, 'extend_address_empty_value_array' ) );
		add_filter( 'frm_setup_new_fields_vars', array( __CLASS__, 'fill_address_options' ), 1 );
		add_filter( 'frm_setup_edit_fields_vars', array( __CLASS__, 'fill_address_options' ), 1 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'skip_other_google_map_scripts_api' ), 9999, 3 );
		add_filter( 'frmpro_fields_replace_shortcodes', array( __CLASS__, 'on_replace_shortcodes' ), 10, 4 );
		add_filter( 'frm_importing_xml', array( 'FrmGeoXMLController', 'post_address_coordinates' ), 10, 2 );

		FrmGeoMapViewController::load_hooks();

		if ( is_admin() ) {
			self::load_admin_hooks();
			FrmGeoMapViewController::load_admin_hooks();
		}
	}

	/**
	 * Adds state abbreviation field to the prepopulated list of fields.
	 *
	 * @since 1.1
	 *
	 * @param array $fields
	 * @return array
	 */
	public static function extend_address_sub_fields( $fields ) {
		$fields['state_abbreviation'] = array(
			'type'     => 'hidden',
			'classes'  => '',
			'label'    => 1,
			'optional' => true,
		);
		return $fields;
	}

	/**
	 * @since 1.1
	 *
	 * @param array $empty_value_array
	 * @return array
	 */
	public static function extend_address_empty_value_array( $empty_value_array ) {
		$empty_value_array['state_abbreviation'] = '';
		return $empty_value_array;
	}

	/**
	 * Sets state abbreviation value in field.
	 *
	 * @since 1.1
	 *
	 * @param array $field
	 * @return array
	 */
	public static function fill_address_options( $field ) {
		if ( 'address' !== $field['type'] ) {
			return $field;
		}

		$field['state_abbreviation_desc'] = '';
		return $field;
	}

	/**
	 * Returns plugin directory's path.
	 *
	 * @since 1.0
	 *
	 * @return string plugin directory's path.
	 */
	public static function path() {
		return dirname( __DIR__ );
	}

	/**
	 * @return void
	 */
	private static function load_admin_hooks() {
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
	}

	/**
	 * @return void
	 */
	public static function on_admin_init() {
		self::include_updater();

		if ( is_callable( 'FrmViewsAppHelper::view_editor_is_active' ) && FrmViewsAppHelper::view_editor_is_active() ) {
			self::enqueue_scripts( new stdClass() );
		}
	}

	/**
	 * @return void
	 */
	public static function load_lang() {
		$plugin_folder_name = basename( FrmGeoAppHelper::path() );
		load_plugin_textdomain( 'formidable-geo', false, $plugin_folder_name . '/languages/' );
	}

	/**
	 * @return void
	 */
	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			include FrmGeoAppHelper::path() . '/classes/models/FrmGeoUpdate.php';
			FrmGeoUpdate::load_hooks();
		}
	}

	/**
	 * Adds attributes to existing field.
	 *
	 * @param array $html input attributes.
	 * @param array $field field name.
	 * @return array input attributes
	 */
	public static function add_input_attributes( $html, $field ) {
		$is_allowed = FrmField::is_field_type( $field, 'text' ) || FrmField::is_field_type( $field, 'address' );
		$field_id   = $field['id'];
		if ( FrmAppHelper::is_form_builder_page() || ! $is_allowed || ! FrmField::get_option( $field, 'auto_address' ) ) {
			return $html;
		}

		$html['data-geoautocomplete'] = 'data-geoautocomplete="1"';
		$html['data-geofieldid']      = 'data-geofieldid="' . esc_attr( $field_id ) . '"';
		if ( FrmField::is_field_type( $field, 'address' ) ) {
			$html['data-geoisaddress'] = 'data-geoisaddress="1"';
		}
		if ( FrmField::get_option( $field, 'geo_show_map' ) ) {
			$html['data-geoshowmap'] = 'data-geoshowmap="1"';
		}

		if ( ! empty( $field['value']['country'] ) && is_callable( 'FrmProAddressesController::get_country_code' ) ) {
			$html['data-default-country'] = 'data-default-country="' . FrmProAddressesController::get_country_code( $field['value']['country'] ) . '"';
		}

		self::enqueue_scripts( $field );

		return $html;
	}

	/**
	 * Enqueues scripts.
	 *
	 * @since 1.0
	 *
	 * @param array $field Field.
	 * @return void
	 */
	private static function enqueue_scripts( $field ) {
		wp_enqueue_script(
			'frm-google-geo',
			FrmGeoAppHelper::plugin_url() . '/js/frm-google-geo' . FrmAppHelper::js_suffix() . '.js',
			array( 'jquery' ),
			FrmGeoAppHelper::$plug_version,
			false
		);

		// get Google API Key from settings.
		self::enqueue_google_scripts( array( 'frm-google-geo' ), 'FrmGeolocationInitGooglePlacesAPI' );

		/**
		 * @param array $location
		 */
		$default_location = apply_filters(
			'frm_geo_default_location',
			array(
				'lat' => 40.7831,
				'lng' => -73.9712,
			)
		);

		$script_vars = array(
			'zoom'                => absint( apply_filters( 'frm_geo_map_zoom', 9, 'field' ) ),
			'default_location'    => $default_location,
			'current_location'    => ! FrmField::get_option( $field, 'geo_avoid_autofill' ),
			'states'              => '',
			'autoCompleteOptions' => self::get_autocomplete_options(),
		);

		wp_localize_script( 'frm-google-geo', 'frmGeoSettings', $script_vars );
	}

	/**
	 * Get the options array for Autocomplete JS constructor.
	 *
	 * @since 1.2.1
	 *
	 * @return array
	 */
	private static function get_autocomplete_options() {
		$options = array(
			'fields' => array( 'address_components', 'name', 'geometry', 'formatted_address' ),
		);

		/**
		 * By default autocomplete is not filtered.
		 * In v1.2 (and older), the autcocomplete was filtering for the 'geocode' type only.
		 * The 'geocode' type excludes businesses and other locations like the Eiffel Tower and Great Wall of China.
		 *
		 * @since 1.2.1
		 *
		 * @param array $options
		 */
		$filtered_options = apply_filters( 'frm_geo_autocomplete_options', $options );

		if ( is_array( $filtered_options ) ) {
			$options = $filtered_options;
		} else {
			_doing_it_wrong( __METHOD__, 'Only arrays should be returned when using the frm_geo_autocomplete_options filter.', '1.2.1' );
		}

		return $options;
	}

	/**
	 * This function changes the Autocomplete options, flagging it to exclude business results.
	 * This is a helper function, intended for use with the frm_geo_autocomplete_options filter.
	 * This can be used as add_filter( 'frm_geo_autocomplete_options', 'FrmGeoAppController::use_geocode_autocomplete_type' );
	 *
	 * @since 1.2.1
	 *
	 * @param array $options
	 * @return array
	 */
	public static function use_geocode_autocomplete_type( $options ) {
		return self::add_type_to_options( $options, 'geocode' );
	}

	/**
	 * This function changes the Autocomplete options to only show company options.
	 * This is a helper function, intended for use with the frm_geo_autocomplete_options filter.
	 * This can be used as add_filter( 'frm_geo_autocomplete_options', 'FrmGeoAppController::use_establishment_autocomplete_type' );
	 *
	 * @since 1.2.1
	 *
	 * @param array $options
	 * @return array
	 */
	public static function use_establishment_autocomplete_type( $options ) {
		return self::add_type_to_options( $options, 'establishment' );
	}

	/**
	 * This function changes the Autocomplete options to only show options with a precise address.
	 * This is a helper function, intended for use with the frm_geo_autocomplete_options filter.
	 * This can be used as add_filter( 'frm_geo_autocomplete_options', 'FrmGeoAppController::use_address_autocomplete_type' );
	 *
	 * @since 1.2.1
	 *
	 * @param array $options
	 * @return array
	 */
	public static function use_address_autocomplete_type( $options ) {
		return self::add_type_to_options( $options, 'address' );
	}

	/**
	 * Add a type to autocomplete options.
	 *
	 * @since 1.2.1
	 *
	 * @param array  $options
	 * @param string $type
	 * @return array
	 */
	private static function add_type_to_options( $options, $type ) {
		$options = self::maybe_add_types_key_to_options( $options );
		$options['types'][] = $type;
		return $options;
	}

	/**
	 * Check if the types key is set, and set it an array if it is not.
	 *
	 * @since 1.2.1
	 *
	 * @param array $options
	 * @return array
	 */
	private static function maybe_add_types_key_to_options( $options ) {
		if ( ! array_key_exists( 'types', $options ) ) {
			$options['types'] = array();
		}
		return $options;
	}

	/**
	 * Adds latitude and longitude hidden inputs.
	 *
	 * @since 1.0
	 *
	 * @param string $html The HTMl for the field.
	 * @param array  $field Field.
	 *
	 * @return string
	 */
	public static function add_lat_lng_fields( $html, $field ) {
		// Check if auto_address is set.
		if ( empty( $field['auto_address'] ) ) {
			return $html;
		}

		$field_id  = $field['id'];
		$entry_id  = $field['entry_id'];
		$latitude  = '';
		$longitude = '';

		$field_html = self::get_field_name_and_id( $field );

		// Check if adding a row to a repeater.
		$action = FrmAppHelper::get_param( 'action', '', 'post', 'sanitize_text_field' );
		if ( 'frm_add_form_row' === $action ) {
			$entry_id = FrmAppHelper::get_param( 'i', $entry_id, 'post', 'sanitize_text_field' );
		}

		// check if updating entry.
		if ( $entry_id ) {
			// get entry latitude and longitude.
			$metas = self::get_geo_entry_meta( $entry_id );
			if ( is_array( $metas ) ) {
				$latitude  = isset( $metas[ 'latitude_' . $field_id ] ) ? $metas[ 'latitude_' . $field_id ] : '';
				$longitude = isset( $metas[ 'longitude_' . $field_id ] ) ? $metas[ 'longitude_' . $field_id ] : '';
			}
		}

		// check if is a POST and has lat/lng values.
		$frm_action = FrmAppHelper::get_param( 'frm_action', '', 'post', 'sanitize_text_field' );
		if ( $frm_action && ( 'create' === $frm_action || $entry_id ) ) {
			$latitude_post  = FrmAppHelper::get_param( 'geo_lat' . $field_html['field_name'], '', 'post', 'sanitize_text_field' );
			$longitude_post = FrmAppHelper::get_param( 'geo_lng' . $field_html['field_name'], '', 'post', 'sanitize_text_field' );
			if ( $latitude_post && $longitude_post ) {
				$latitude  = is_array( $latitude_post ) ? $latitude_post[ $entry_id ] : $latitude_post;
				$longitude = is_array( $longitude_post ) ? $longitude_post[ $entry_id ] : $longitude_post;
			}
		}

		// print hidden inputs.
		$maps  = sprintf( '<input id="geo-lat-%s" name="geo_lat%s" class="frm-geo-lat" type="hidden" value="%s">', esc_attr( $field_html['html_id'] ), esc_attr( $field_html['field_name'] ), esc_attr( $latitude ) );
		$maps .= sprintf( '<input id="geo-lng-%s" name="geo_lng%s" class="frm-geo-lng" type="hidden" value="%s">', esc_attr( $field_html['html_id'] ), esc_attr( $field_html['field_name'] ), esc_attr( $longitude ) );

		// Show map if field not hidden and show map option is active.
		if ( 'hidden' !== $field['type'] && ! empty( $field['geo_show_map'] ) ) {
			$map_height = apply_filters( 'frm_geo_map_height', 300 );
			$maps      .= sprintf( '<div class="frm-geolocation-map" style="height: %dpx;margin-top:20px"></div>', absint( $map_height ) );
		}

		$append_me = strpos( $html, '[/if error]' ) ? '[/if error]' : '[/if description]';
		if ( strpos( $html, $append_me ) ) {
			$html = str_replace( $append_me, $append_me . $maps, $html );
		} else {
			$html .= $maps;
		}
		return $html;
	}

	/**
	 * @param array $field
	 *
	 * @return void
	 */
	public static function add_hidden_lat_lng_fields( $field ) {
		if ( $field['type'] !== 'hidden' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::add_lat_lng_fields( '', $field );
	}

	/**
	 * @param array $field
	 *
	 * @return array
	 */
	private static function get_field_name_and_id( $field ) {
		$field_name  = '[' . $field['id'] . ']';
		$html_id     = $field['id'];
		$is_sub_form = $field['parent_form_id'] !== $field['form_id'];
		if ( ! $is_sub_form ) {
			return compact( 'field_name', 'html_id' );
		}

		$entry_id = self::get_entry_id( $field );
		if ( $entry_id !== '' ) {
			$field_name .= '[' . $entry_id . ']';
			$html_id    .= '-' . $entry_id;
		}
		return compact( 'field_name', 'html_id' );
	}

	/**
	 * @param array $field
	 *
	 * @return int|string
	 */
	private static function get_entry_id( $field ) {
		$is_sub_form = $field['parent_form_id'] !== $field['form_id'];
		$entry_id    = isset( $field['entry_id'] ) ? $field['entry_id'] : '';
		if ( $entry_id === 0 && ! $is_sub_form ) {
			$entry_id = '';
		}

		// Check if adding a row to a repeater.
		$action = FrmAppHelper::get_param( 'action', '', 'post', 'sanitize_text_field' );
		if ( 'frm_add_form_row' === $action ) {
			$entry_id = FrmAppHelper::get_param( 'i', $entry_id, 'post', 'sanitize_text_field' );
		}

		return $entry_id;
	}

	/**
	 * Returns location coordinates from $_POST.
	 *
	 * @since 1.3.1
	 *
	 * @return array
	 */
	private static function get_location_data() {
		$lats = FrmAppHelper::get_param( 'geo_lat', '', 'post', 'sanitize_text_field' );
		$lngs = FrmAppHelper::get_param( 'geo_lng', '', 'post', 'sanitize_text_field' );

		if ( empty( $lats[0] ) || ! defined( 'WP_IMPORTING' ) || ! WP_IMPORTING ) {
			return array( $lats, $lngs );
		}

		// Importing entries from XML, locations are posted during import.
		$latitudes  = $lats;
		$longitudes = $lngs;
		array_shift( $latitudes );
		array_shift( $longitudes );
		$_POST['geo_lat'] = $latitudes;
		$_POST['geo_lng'] = $longitudes;

		return array( $lats[0], $lngs[0] );
	}

	/**
	 * Saves latitude and longitude metadata.
	 *
	 * @since 1.0
	 *
	 * @param int   $entry_id Entry ID.
	 * @param int   $form_id Form ID.
	 * @param array $args Entry args.
	 * @return void
	 */
	public static function save_lat_lng( $entry_id, $form_id, $args = array() ) {
		list( $lats, $lngs ) = self::get_location_data();

		// Check if latitude is an array.
		if ( ! is_array( $lats ) ) {
			return;
		}

		$meta_value = array(
			'key' => 'geo_lat_lng',
		);

		$frm_action = FrmAppHelper::get_param( 'frm_action', '', 'post', 'sanitize_text_field' );
		if ( 'create' === $frm_action ) {
			$key = FrmAppHelper::get_param( 'item_meta[key_pointer]', '', 'post', 'sanitize_text_field' );
		} else {
			$key = FrmAppHelper::get_param( 'item_meta[key_pointer]', $entry_id, 'post', 'sanitize_text_field' );
			$key = str_replace( 'i', '', $key );  // remove i from index.
		}

		// add all latitudes and longitudes to meta_value.
		foreach ( $lats as $field_id => $lat ) {
			$lng = $lngs[ $field_id ];

			if ( is_array( $lat ) && ! isset( $lat[ $key ] ) ) {
				continue;
			}

			$meta_value[ 'latitude_' . $field_id ]  = is_array( $lat ) ? $lat[ (int) $key ] : $lat;
			$meta_value[ 'longitude_' . $field_id ] = is_array( $lng ) ? $lng[ (int) $key ] : $lng;
		}

		// Update entry.
		global $wpdb;
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}frm_item_metas SET meta_value = %s WHERE meta_value LIKE %s AND item_id = %d",
				FrmAppHelper::maybe_json_encode( $meta_value ),
				'%geo_lat_lng%',
				$entry_id
			)
		);

		if ( empty( $result ) ) {
			// New entry or row.
			FrmEntryMeta::add_entry_meta( $entry_id, 0, '', $meta_value );
		}

		// clear cache.
		wp_cache_delete( $entry_id, 'frm_entry' );
		FrmEntryMeta::clear_cache();
	}

	/**
	 * Gets value of coordinates on entry meta.
	 *
	 * @param int $entry_id Entry ID.
	 * @return array|false Return `false` if the custom value does not exist.
	 */
	public static function get_geo_entry_meta( $entry_id ) {
		// get all field id 0 metas.
		$metas = FrmDb::get_col(
			'frm_item_metas',
			array(
				'item_id'  => intval( $entry_id ),
				'field_id' => 0,
			),
			'meta_value'
		);

		// search for geo metas.
		$meta = array_filter(
			$metas,
			/**
			 * @param string $meta Meta value.
			 */
			function ( $meta ) {
				return strpos( $meta, 'geo_lat_lng' ) !== false;
			}
		);

		if ( ! count( $meta ) ) {
			return false;
		}

		$meta = array_values( $meta );
		FrmAppHelper::unserialize_or_decode( $meta[0] );
		return $meta[0];
	}

	/**
	 * Gets first location from meta.
	 *
	 * @param array|bool $metas Entry Meta.
	 * @return array Return array of latitude and longitude of the first location found.
	 */
	public static function get_first_location_from_meta( $metas ) {
		$latitude  = false;
		$longitude = false;

		if ( is_array( $metas ) ) {
			foreach ( $metas as $meta_key => $meta_value ) {
				if ( ! $latitude && false !== strpos( $meta_key, 'latitude' ) ) {
					$latitude = $meta_value;
				}
				if ( ! $longitude && false !== strpos( $meta_key, 'longitude' ) ) {
					$longitude = $meta_value;
				}
			}
		}

		return array(
			'latitude'  => $latitude,
			'longitude' => $longitude,
		);
	}

	/**
	 * Shows latitude and longitude on sidebar.
	 *
	 * @since 1.0
	 *
	 * @param object $entry Entry.
	 * @return void
	 */
	public static function show_lat_lng( $entry ) {
		$metas    = self::get_geo_entry_meta( $entry->id );
		$location = self::get_first_location_from_meta( $metas );

		// Echo latitude and longitude on sidebar.
		if ( $location['latitude'] && $location['longitude'] ) :
			?>
		<div class="misc-pub-section">
			<?php FrmAppHelper::icon_by_class( 'frm_icon_font frm_location_icon', array( 'aria-hidden' => 'true' ) ); ?>
			<?php esc_html_e( 'Latitude', 'formidable-geo' ); ?>: <b><?php echo esc_html( $location['latitude'] ); ?></b><br>
			<?php FrmAppHelper::icon_by_class( '', array( 'aria-hidden' => 'true' ) ); ?>
			<?php esc_html_e( 'Longitude', 'formidable-geo' ); ?>: <b><?php echo esc_html( $location['longitude'] ); ?></b>
		</div>
			<?php
		endif;
	}

	/**
	 * Shows map on entries with locations.
	 *
	 * @since 1.0
	 *
	 * @param object|string|int $entry Entry.
	 * @param array|false       $location
	 * @return void
	 */
	public static function show_entry_map( $entry, $location = false ) {
		$entry_id = absint( is_object( $entry ) ? $entry->id : $entry );

		if ( false === $location ) {
			$metas    = self::get_geo_entry_meta( $entry_id );
			$location = self::get_first_location_from_meta( $metas );
		}

		if ( ! $location['latitude'] || ! $location['longitude'] ) {
			return;
		}

		$id = 'frmgeo-entry-map' . uniqid();

		// Load Google Geolocation API.
		self::enqueue_google_scripts();

		/**
		 * @param int   $zoom
		 * @param array $args
		 */
		$zoom = apply_filters(
			'frm_geo_map_zoom',
			9,
			array(
				'entry_id' => $entry_id,
				'view_id'  => false,
			)
		);
		?>

	<div class="postbox">
		<div class="inside">
			<div id="<?php echo esc_attr( $id ); ?>" style="height: 300px;"></div>
		</div>
	</div>

	<script>
		( function() {
			function FrmGeoShowEntryMap() {
				if ( 'function' !== typeof google.maps.LatLngBounds ) {
					/*
					The map script loads asynchronously.
					If it makes it here it is because it has not loaded yet.
					So exit early and try again in 100ms.
					*/
					setTimeout( FrmGeoShowEntryMap, 100 );
					return;
				}
				var mapElem, location, map, marker;
				mapElem = document.getElementById( '<?php echo esc_js( $id ); ?>' );
				if ( ! mapElem ) {
					return;
				}
				location = new google.maps.LatLng(<?php echo esc_js( $location['latitude'] ); ?>, <?php echo esc_js( $location['longitude'] ); ?>);
				map = new google.maps.Map(
					mapElem,
					{
						zoom: <?php echo esc_js( $zoom ); ?>,
						center: location,
						mapId: 'DEMO_MAP_ID'
					}
				);
				marker = new google.maps.marker.AdvancedMarkerElement(
					{
						position: location,
						map: map
					}
				);
			}
			if ( document.readyState === 'complete' ) {
				setTimeout( FrmGeoShowEntryMap, 0 );
			} else {
				window.addEventListener( 'load', FrmGeoShowEntryMap );
			}
		}() );
	</script>

		<?php
	}

	/**
	 * Enqueues google scripts.
	 *
	 * @since 1.0
	 *
	 * @param array  $deps Dependencies array. Optional. Default empty.
	 * @param string $callback Google script callback. Optional. Default empty.
	 * @return void
	 */
	public static function enqueue_google_scripts( $deps = array(), $callback = '' ) {
		// Get Google API Key from settings.
		$frm_geo_settings = new FrmGeoSettings();

		wp_enqueue_script(
			'google-geolocation-api',
			add_query_arg(
				array(
					'key'       => $frm_geo_settings->api_key,
					'libraries' => 'marker,places',
					'callback'  => $callback,
					'loading'   => 'async',
				),
				'https://maps.googleapis.com/maps/api/js'
			),
			$deps,
			FrmGeoAppHelper::$plug_version,
			true
		);
	}

	/**
	 * Avoid loading gmaps api multiple times. Clear the other scripts from loading into the page.
	 *
	 * @since 1.2
	 *
	 * @param string $tag
	 * @param string $handle
	 * @param string $src
	 *
	 * @return string
	 */
	public static function skip_other_google_map_scripts_api( $tag, $handle, $src ) {
		global $frm_vars;
		if ( empty( $frm_vars['forms_loaded'] ) || ! wp_script_is( 'google-geolocation-api' ) ) {
			return $tag;
		}
		if ( ! preg_match( '/maps\.google/i', $src ) || 'google-geolocation-api' === $handle ) {
			return $tag;
		}
		if ( preg_match( '/maps\.google.+?callback=([A-Za-z0-9_]*)/i', $src, $matches ) && isset( $matches[1] ) ) {
			// Execute the gmaps callback function passed via api link.
			return '<script> if ( "undefined" !== typeof ' . esc_js( $matches[1] ) . ' ) { ' . esc_js( $matches[1] . '(); }' ) . '</script>';
		}
		return '';
	}

	/**
	 * @param string   $replace_with
	 * @param string   $tag
	 * @param array    $atts
	 * @param stdClass $field
	 * @return string
	 */
	public static function on_replace_shortcodes( $replace_with, $tag, $atts, $field ) {
		if ( ! in_array( $field->type, array( 'text', 'address' ), true ) ) {
			return $replace_with;
		}

		if ( empty( $atts['show'] ) || ! in_array( $atts['show'], array( 'map', 'lat', 'long' ), true ) ) {
			return $replace_with;
		}

		if ( empty( $atts['entry_id'] ) ) {
			return $replace_with;
		}

		$meta = self::get_geo_entry_meta( $atts['entry_id'] );
		if ( ! $meta ) {
			return $replace_with;
		}

		switch ( $atts['show'] ) {
			case 'lat':
				$replace_with = ! empty( $meta[ 'latitude_' . $field->id ] ) ? $meta[ 'latitude_' . $field->id ] : '';
				break;
			case 'long':
				$replace_with = ! empty( $meta[ 'longitude_' . $field->id ] ) ? $meta[ 'longitude_' . $field->id ] : '';
				break;
			case 'map':
				ob_start();
				self::show_entry_map(
					$atts['entry_id'],
					array(
						'latitude'  => ! empty( $meta[ 'latitude_' . $field->id ] ) ? $meta[ 'latitude_' . $field->id ] : '',
						'longitude' => ! empty( $meta[ 'longitude_' . $field->id ] ) ? $meta[ 'longitude_' . $field->id ] : '',
					)
				);
				$replace_with = ob_get_clean();
				break;
		}

		return $replace_with;
	}

	/**
	 * A required address field will fail validation if the state abbreviation is empty.
	 * But like line2, let's leave it as optional since there is no state abbreviation sent
	 * when address autocomplete is not enabled.
	 *
	 * @since 1.1.1
	 * @deprecated 1.3.3 Since this version, "optional" => true is passed. Since Pro v6.16.1, the "optional" attribute is checked during validation.
	 *
	 * @param array  $errors
	 * @param object $field
	 * @return array
	 */
	public static function do_not_require_state_abbreviations( $errors, $field ) {
		_deprecated_function( __METHOD__, '1.3.3' );
		return $errors;
	}
}
