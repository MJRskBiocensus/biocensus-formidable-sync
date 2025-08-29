<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmViewsTimelineEditorHelper {

	const DEFAULT_STYLES = array(
		'timelineMarkerColor'     => '#12B76A',
		'timelineLineColor'       => 'rgba(152, 162, 179, 1)',
		'timelineCardBackground'  => '#ffffff',
		'timelineTextColor'       => 'rgba(29, 41, 57, 1)',
		'timelinePopupBackground' => '#ffffff',
		'timelinePopupTextColor'  => 'rgba(29, 41, 57, 1)',
	);

	const DEFAULT_THEME_COLORS = array(
		'light'         => array(
			'lineColor'      => 'rgba(152, 162, 179, 1)',
			'markerColor'    => '#12B76A',
			'cardBackground' => '#ffffff',
			'textColor'      => 'rgba(29, 41, 57, 1)',
		),
		'dark'          => array(
			'lineColor'      => 'rgba(152, 162, 179, 1)',
			'markerColor'    => '#12B76A',
			'cardBackground' => '#101B2B',
			'textColor'      => '#ffffff',
		),
		'no-background' => array(
			'lineColor'      => 'rgba(152, 162, 179, 1)',
			'markerColor'    => '#12B76A',
			'cardBackground' => 'transparent',
			'textColor'      => 'rgba(29, 41, 57, 1)',
		),
		'life-events'   => array(
			'lineColor'      => 'rgba(152, 162, 179, 1)',
			'markerColor'    => '#12B76A',
			'cardBackground' => '#ffffff',
			'textColor'      => 'rgba(29, 41, 57, 1)',
		),
	);

	/**
	 * The js options keys and their corresponding option keys in the settings section of the timeline options
	 *
	 * @since 5.8
	 * @var array
	 */
	private static $js_options_settings_kyes = array(
		'timelineThumbnail'        => 'thumbnail',
		'timelineTitle'            => 'title',
		'timelineShowDetailsPopup' => 'show_details_popup',
		'timelineNaturalTimeline'  => 'natural_timeline',
		'timelineAddDivider'       => 'add_divider',
		'timelineDividerType'      => 'divider_type',
		'timelineDescription'      => 'description',
		'timelineHideDescription'  => 'hide_description',
		'timelineCardContentOrder' => 'card_content_order',
		'timelineDate'             => 'date',
		'timelineDateFormat'       => 'date_format',
		'timelineDateCustomFormat' => 'date_custom_format',
	);

	/**
	 * The js options keys and their corresponding option keys in the style section of the timeline options
	 *
	 * @since 5.8
	 * @var array
	 */
	private static $js_options_style_keys = array(
		'timelineLayout'               => 'layout',
		'timelineTheme'                => 'theme',
		'timelineEventMarker'          => 'marker_type',
		'timelineEventMarkerImage'     => 'marker_icon',
		'timelineEventMarkerImageSize' => 'marker_icon_size',
		'timelineLineColor'            => 'line_color',
		'timelineMarkerColor'          => 'marker_color',
		'timelineCardBackground'       => 'card_background',
		'timelineTextColor'            => 'text_color',
		'timelinePopupBackground'      => 'popup_background',
		'timelinePopupTextColor'       => 'popup_text_color',
		'timelineDatePosition'         => 'date_position',
	);

	/**
	 * Get timeline settings from POST data
	 *
	 * @since 5.8
	 * @return array
	 */
	public static function get_settings_from_post_request() {
		return array(
			'settings' => self::get_settings_section_from_post_request(),
			'style'    => self::get_style_section_from_post_request(),
		);
	}

	/**
	 * Get the settings section of timeline options
	 *
	 * @since 5.8
	 * @return array
	 */
	private static function get_settings_section_from_post_request() {
		$settings = array();
		foreach ( self::$js_options_settings_kyes as $js_key => $option_key ) {
			$settings[ $option_key ] = FrmAppHelper::get_param( $js_key, '', 'post', 'sanitize_text_field' );
		}

		return $settings;
	}

	/**
	 * Get the style section of timeline options
	 *
	 * @since 5.8
	 * @return array
	 */
	private static function get_style_section_from_post_request() {
		$style = array();

		foreach ( self::$js_options_style_keys as $js_key => $option_key ) {
			$style[ $option_key ] = FrmAppHelper::get_param( $js_key, '', 'post', 'sanitize_text_field' );
		}

		return $style;
	}

	/**
	 * Get the view editor option value by key and type
	 *
	 * @since 5.8
	 * @param array  $view_editor_options The view editor options.
	 * @param string $key The key to get the value of.
	 * @param string $type The type of option to get.
	 * @return string
	 */
	private static function get_option_value( $view_editor_options, $key, $type = 'settings' ) {
		if ( empty( $view_editor_options ) ) {
			return '';
		}

		if ( 'settings' === $type ) {
			return $view_editor_options['settings'][ $key ] ?? '';
		}

		return $view_editor_options['style'][ $key ] ?? '';
	}

	/**
	 * Get the js options values
	 *
	 * @since 5.8
	 * @param array $view_editor_options The view editor options.
	 * @return array
	 */
	public static function get_js_options_values( $view_editor_options ) {
		$js_options = array();

		foreach ( self::$js_options_settings_kyes as $js_key => $option_key ) {
			$js_options[ $js_key ] = self::get_option_value( $view_editor_options, $option_key, 'settings' );
		}

		foreach ( self::$js_options_style_keys as $js_key => $option_key ) {
			$js_options[ $js_key ] = self::get_option_value( $view_editor_options, $option_key, 'style' );
		}

		return $js_options;
	}

	/**
	 * Get default timeline styles
	 *
	 * @since 5.8
	 * @see FrmViewsTimelineEditorHelper::DEFAULT_STYLES
	 * @return array
	 */
	public static function get_default_styles() {
		/**
		 * Filter for the default styles for the timeline.
		 *
		 * @since 5.8
		 * @param array $styles The default styles.
		 * @return array
		 */
		return apply_filters( 'frm_views_timeline_default_styles', self::DEFAULT_STYLES );
	}

	/**
	 * Get timeline date formats
	 *
	 * @since 5.8
	 * @return array
	 */
	public static function get_date_formats() {
		return array_map(
			function ( $format ) {
				return array(
					'value' => $format,
					'label' => $format . ' ' . gmdate( $format, time() ),
				);
			},
			array_keys( FrmProAppHelper::display_to_datepicker_format() )
		);
	}

	/**
	 * Get the themes colors
	 *
	 * @since 5.8
	 * @see FrmViewsTimelineEditorHelper::DEFAULT_THEME_COLORS
	 * @return array
	 */
	public static function get_themes_colors() {

		/**
		 * Filter for the default theme colors for the timeline.
		 *
		 * @since 5.8
		 * @param array $theme_colors The default theme colors.
		 * @return array
		 */
		return apply_filters( 'frm_views_timeline_theme_colors', self::DEFAULT_THEME_COLORS );
	}
}
