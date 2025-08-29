<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to access this file directly.' );
}

/**
 * Contains functions related to processing data from XML.
 *
 * @since 1.3.1
 */
class FrmGeoXMLController {

	/**
	 * Updates the global $_POST with location data that is used in FrmGeoAppController::save_lat_lng.
	 *
	 * @since 1.3.1
	 *
	 * @param array  $imported Data about already imported items.
	 * @param object $entries  Has entries from the xml being imported.
	 *
	 * @return array
	 */
	public static function post_address_coordinates( $imported, $entries ) {
		$entries = $entries->item;
		foreach ( $entries as $entry ) {
			self::post_address_coordinates_for_item( $entry );
		}

		return $imported;
	}

	/**
	 * Posts location coordinates in a single entry.
	 *
	 * @since 1.3.1
	 *
	 * @param object $entry
	 *
	 * @return void
	 */
	private static function post_address_coordinates_for_item( $entry ) {
		foreach ( $entry->item_meta as $meta ) {
			if ( (int) $meta->field_id !== 0 ) {
				// We only want to continue if field_id is 0, which is used for saving coordinates.
				continue;
			}

			$coordinate_data = FrmAppHelper::maybe_json_decode( (string) $meta->meta_value );
			if ( ! is_array( $coordinate_data ) ) {
				continue;
			}
			$coordinate_keys = array_keys( $coordinate_data );

			$latitude_keys = self::get_coordinate_keys( 'latitude', $coordinate_keys );
			self::update_posted_coordinates_with_values( $latitude_keys, $coordinate_data );

			$longitude_keys = self::get_coordinate_keys( 'longitude', $coordinate_keys );
			self::update_posted_coordinates_with_values( $longitude_keys, $coordinate_data );
		}
	}

	/**
	 * @since 1.3.1
	 *
	 * @param string $single_coordinate_key
	 *
	 * @return int
	 */
	private static function get_geo_field_id( $single_coordinate_key ) {
		global $frm_duplicate_ids;
		$geo_field_id = explode( '_', $single_coordinate_key )[1];
		if ( isset( $frm_duplicate_ids[ $geo_field_id ] ) ) {
			return $frm_duplicate_ids[ $geo_field_id ];
		}

		return (int) $geo_field_id;
	}

	/**
	 * @since 1.3.1
	 *
	 * @param array $single_coordinate_keys
	 * @param array $coordinate_data
	 *
	 * @return void
	 */
	private static function update_posted_coordinates_with_values( $single_coordinate_keys, $coordinate_data ) {
		foreach ( $single_coordinate_keys as $key => $single_coordinate_key ) {
			if ( strpos( $single_coordinate_key, '_' ) === false ) {
				continue;
			}
			$geo_field_id = self::get_geo_field_id( $single_coordinate_key );
			self::update_posted_coordinates_with_value( $single_coordinate_keys, $key, $geo_field_id, $coordinate_data[ $single_coordinate_key ] );
		}
	}

	/**
	 * @since 1.3.1
	 *
	 * @param array $single_coordinate_keys Ex. array( 'latitude_{field_id_1}' => 9.0326568, 'latitude_{field_id_2}' => 9.0427565 ).
	 * @param int   $key                    Index of the current coordinate in $single_coordinate_keys.
	 * @param int   $geo_field_id
	 * @param float $single_coordinate_value
	 *
	 * @return void
	 */
	private static function update_posted_coordinates_with_value( $single_coordinate_keys, $key, $geo_field_id, $single_coordinate_value ) {
		$single_coordinate_key = $single_coordinate_keys[ $key ];
		$geo_coordinate        = strpos( $single_coordinate_key, 'lat' ) === 0 ? 'geo_lat' : 'geo_lng';

		if ( count( $single_coordinate_keys ) === 1 || $key === 0 ) {
			$_POST[ $geo_coordinate ][] = array(
				$geo_field_id => $single_coordinate_value,
			);
		} else { // Importing multiple address fields per entry.
			$last_index = count( $_POST[ $geo_coordinate ] ) - 1; // phpcs:ignore WordPress.Security
			$_POST[ $geo_coordinate ][ $last_index ][ $geo_field_id ] = $single_coordinate_value;
		}
	}

	/**
	 * Returns the array key that references either a latitude or longitude.
	 *
	 * @since 1.3.1
	 *
	 * @param string $type           Either 'latitude' or 'longitude'.
	 * @param array  $coordinate_keys
	 *
	 * @return array
	 */
	private static function get_coordinate_keys( $type, $coordinate_keys ) {
		return array_values(
			array_filter(
				$coordinate_keys,
				function ( $key ) use ( $type ) {
					return strpos( $key, $type ) !== false;
				}
			)
		);
	}
}
