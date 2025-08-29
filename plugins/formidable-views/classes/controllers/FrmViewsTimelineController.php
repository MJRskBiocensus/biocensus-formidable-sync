<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmViewsTimelineController {

	/**
	 * Card content order
	 *
	 * @since 5.8
	 * @var array
	 */
	public static $card_content_order = array( 'thumbnail', 'title', 'date', 'description', 'default_content' );

	/**
	 * Template styles
	 *
	 * @since 5.8
	 * @var array
	 */
	public static $template_styles = array();

	/**
	 * Settings
	 *
	 * @since 5.8
	 * @var array
	 */
	public static $settings = array();

	/**
	 * Entries data
	 *
	 * @since 5.8
	 * @var array
	 */
	private static $entries_data = array();

	/**
	 * Entries data by divider type
	 *
	 * @since 5.8
	 * @var array
	 */
	private static $entries_data_by_divider_type = array();

	/**
	 * Default template styles
	 *
	 * @since 5.8
	 * @var array
	 */
	public static $default_template_styles = array(
		'layout'           => 'vertical',
		'marker_type'      => 'numbers', // dots, numbers, images
		'marker_color'     => '#12B76A',
		'marker_icon_size' => 'medium', // small, medium, large
		'theme'            => 'light',
		'line_color'       => 'rgba(152, 162, 179, 1)',
		'card_background'  => '#ffffff',
		'text_color'       => 'rgba(29, 41, 57, 1)',
		'popup_background' => '#ffffff',
		'popup_text_color' => 'rgba(29, 41, 57, 1)',
		'date_position'    => 'outside', // outside, inside
	);

	/**
	 * Default settings
	 *
	 * @since 5.8
	 * @var array
	 */
	public static $default_settings = array(
		'card_content_order' => 'thumbnail,title,date,description,default_content',
		'hide_description'   => '',
		'show_details_popup' => 0,
		'description'        => '',
		'title'              => '',
		'thumbnail'          => '',
		'date'               => '',
		'date_format'        => 'year',
		'date_custom_format' => 'd/m/Y',
		'add_divider'        => 0,
		'divider_type'       => 'year',
	);

	/**
	 * The template renderer
	 *
	 * @since 5.8
	 * @var FrmViewsTimelineRendererHelper
	 */
	private static $renderer;

	/**
	 * Get the view template
	 *
	 * @since 5.8
	 * @param array  $args The arguments.
	 * @param object $view The view.
	 *
	 * @return string The view.
	 */
	public static function get_view( $args, $view ) {

		$entries_data = self::get_entries_data( $view, $args['entry_ids'] );

		self::init_template_settings_and_styles( $view );
		self::init_card_content_order();
		self::prepare_entries_data( $entries_data, $view );

		self::add_inline_styles();
		self::load_timeline_frontend_scripts();

		self::$renderer = new FrmViewsTimelineRendererHelper(
			self::$settings,
			self::$template_styles,
			array(
				'current_page'                 => FrmViewsDisplaysHelper::get_current_page_num( $view->ID ),
				'page_size'                    => $view->frm_page_size,
				'entries_data'                 => self::$entries_data,
				'entries_data_by_divider_type' => self::$entries_data_by_divider_type,
				'view'                         => $view,
			)
		);

		$content = FrmAppHelper::clip(
			function () {
				self::$renderer->get_template();
			}
		);

		// Clean up the content
		$content = str_replace( array( "\r\n", "\n", "\r" ), ' ', $content );
		$content = preg_replace( '/<p>\s*<\/p>/', '', $content );
		$content = preg_replace( '/>\s+</', '><', $content );

		return $content;
	}

	/**
	 * Initialize the template settings and styles
	 *
	 * @since 5.8
	 * @param object $view The view.
	 */
	private static function init_template_settings_and_styles( $view ) {
		if ( empty( $view->frm_timeline_options ) ) {
			$view->frm_timeline_options = array(
				'style'    => array(),
				'settings' => array(),
			);
		}

		// Remove empty properties from arrays
		$template_styles = array_filter(
			$view->frm_timeline_options['style'],
			function ( $value ) {
				return ! empty( $value );
			}
		);

		$settings = array_filter(
			$view->frm_timeline_options['settings'],
			function ( $value ) {
				return ! empty( $value );
			}
		);

		self::$template_styles = array_merge( self::$default_template_styles, $template_styles );
		self::$settings        = array_merge( self::$default_settings, $settings );
	}

	/**
	 * Check if the timeline has dividers
	 *
	 * @since 5.8
	 * @return bool True if the timeline has dividers, false otherwise.
	 */
	public static function has_dividers() {
		return 1 === (int) self::$settings['add_divider'];
	}

	/**
	 * Get the divider type
	 *
	 * @since 5.8
	 * @return string The divider type.
	 */
	public static function get_divider_type() {
		return self::$settings['divider_type'];
	}

	/**
	 * Initialize the card content order
	 *
	 * @since 5.8
	 */
	private static function init_card_content_order() {
		if ( empty( self::$settings['card_content_order'] ) ) {
			return;
		}
		self::$card_content_order = explode( ',', self::$settings['card_content_order'] );
	}

	/**
	 * Get the marker type
	 *
	 * @since 5.8
	 * @return string The marker type.
	 */
	public static function get_marker_type() {
		return self::$template_styles['marker_type'];
	}

	/**
	 * Get the entry field id
	 *
	 * @since 5.8
	 * @param object $view The view.
	 * @param string $key The key.
	 * @param string $type The type.
	 */
	private static function get_entry_field_id( $view, $key, $type = 'settings' ) {
		if ( empty( $view->frm_timeline_options ) ) {
			return '';
		}
		if ( ! empty( $view->frm_timeline_options[ $type ][ $key ] ) ) {
			return $view->frm_timeline_options[ $type ][ $key ];
		}
		return '';
	}

	/**
	 * Get the entry field value
	 *
	 * @since 5.8
	 * @param object $entry The entry.
	 * @param string $key The key.
	 * @param object $view The view.
	 * @param string $type The type.
	 * @return string The entry field value.
	 */
	private static function get_entry_field_value( $entry, $key, $view, $type = 'settings' ) {
		$field_id  = self::get_entry_field_id( $view, $key, $type );
		$field_row = FrmField::getOne( (int) $field_id );
		if ( empty( $field_row ) ) {
			return '';
		}
		$field = new FrmFieldValue( $field_row, $entry );
		$field->prepare_displayed_value();
		return $field->get_displayed_value();
	}

	/**
	 * Get the entries data
	 *
	 * @since 5.8
	 * @param object $view The view.
	 * @param array  $entry_ids The entry ids.
	 *
	 * @return array The entries data.
	 */
	private static function get_entries_data( $view, $entry_ids ) {
		$entries_data    = array();
		$content_helper  = new FrmViewsContentHelper( $view->post_content );
		$default_content = $content_helper->get_content();
		$shortcodes      = FrmProDisplaysHelper::get_shortcodes( $default_content, $view->frm_form_id );

		while ( $next_set = array_splice( $entry_ids, 0, 30 ) ) {
			$entries = FrmEntry::getAll( array( 'id' => $next_set ), ' ORDER BY FIELD(it.id,' . implode( ',', $next_set ) . ')', '', true, false );
			foreach ( $entries as $entry ) {
				$entry->default_content = FrmProContent::replace_shortcodes( $default_content, $entry, $shortcodes, $view );
				$entries_data[] = $entry;
			}
		}
		return $entries_data;
	}

	/**
	 * Prepare the entries data
	 *
	 * @since 5.8
	 * @param array  $entries The entries.
	 * @param object $view The view.
	 */
	private static function prepare_entries_data( $entries, $view ) {
		foreach ( $entries as $entry ) {
			$detail_link = FrmProContent::get_pretty_url(
				array(
					'param' => 'entry',
					'param_value' => $entry->id,
				)
			);

			$date = self::get_entry_field_value( $entry, 'date', $view, 'settings' );
			$data = array(
				'id'              => $entry->id,
				'date'            => self::get_date( $view, $date ),
				'full_date'       => $date,
				'year'            => self::get_year_from_date( $date ),
				'month'           => self::get_month_from_date( $date ),
				'title'           => self::get_entry_field_value( $entry, 'title', $view, 'settings' ),
				'description'     => self::get_entry_field_value( $entry, 'description', $view, 'settings' ),
				'marker_icon'     => self::get_entry_field_value( $entry, 'marker_icon', $view, 'style' ),
				'thumbnail'       => self::get_entry_field_value( $entry, 'thumbnail', $view, 'settings' ),
				'detail_content'  => self::get_entry_detail_content( $entry, $view ),
				'detail_link'     => $detail_link,
				'default_content' => $entry->default_content,
			);

			self::$entries_data[] = $data;
		}

		self::prepare_entries_by_divider_type();

		return self::$entries_data;
	}

	/**
	 * Prepare the entries data by date
	 *
	 * @since 5.8
	 * @param array $entries_data The entries data.
	 * @return array The entries data.
	 */
	private static function prepare_entries_by_date( $entries_data ) {
		$grouped_data = array();

		// Group based on divider type
		if ( self::get_divider_type() === 'year' ) {
			// Group only by year
			foreach ( $entries_data as $entry ) {
				if ( ! isset( $grouped_data[ $entry['year'] ] ) ) {
					$grouped_data[ $entry['year'] ] = array(
						'date'    => $entry['full_date'],
						'entries' => array(),
					);
				}
				$grouped_data[ $entry['year'] ]['entries'][] = $entry;
			}
			return $grouped_data;
		}

		// Group by year and month
		foreach ( $entries_data as $entry ) {
			$year_month = $entry['year'] . '-' . $entry['month'];
			if ( ! isset( $grouped_data[ $year_month ] ) ) {
				$grouped_data[ $year_month ] = array(
					'date'    => $entry['full_date'],
					'entries' => array(),
				);
			}
			$grouped_data[ $year_month ]['entries'][] = $entry;
		}

		return $grouped_data;
	}

	/**
	 * Prepare the entries data by divider type
	 *
	 * @since 5.8
	 */
	private static function prepare_entries_by_divider_type() {
		if ( ! self::has_dividers() ) {
			return;
		}

		self::$entries_data_by_divider_type = self::prepare_entries_by_date( self::$entries_data );
	}

	/**
	 * Get the entry detail content
	 *
	 * @since 5.8
	 * @param object $entry The entry.
	 * @param object $view The view.
	 * @return string The entry detail content.
	 */
	private static function get_entry_detail_content( $entry, $view ) {
		$detail_content = $view->frm_dyncontent;
		$shortcodes     = FrmProDisplaysHelper::get_shortcodes( $detail_content, $view->frm_form_id );
		$detail_content = apply_filters( 'frm_display_entry_content', $detail_content, $entry, $shortcodes, $view, 'one', 'odd', array() );

		FrmProFieldsHelper::replace_non_standard_formidable_shortcodes( array(), $detail_content );

		return $detail_content;
	}

	/**
	 * Loads the frontend scripts required for the timeline functionality.
	 *
	 * This function enqueues the 'frm-timeline' JavaScript file, which is responsible for providing the timeline functionality on the frontend.
	 * The script is loaded using the FrmViewsAppHelper::plugin_url() method to get the plugin's URL, and the FrmViewsAppHelper::plugin_version() method to get the plugin's version.
	 *
	 * @since 5.8
	 */
	private static function load_timeline_frontend_scripts() {
		wp_enqueue_script( 'frm-timeline', FrmViewsAppHelper::plugin_url() . '/js/timeline.js', array( 'formidable', 'wp-hooks' ), FrmViewsAppHelper::plugin_version(), true );
		self::add_inline_scripts();
	}

	/**
	 * Add the inline scripts
	 *
	 * @since 5.8
	 */
	private static function add_inline_scripts() {
		wp_localize_script(
			'frm-timeline',
			'frmViewsTimeline',
			array(
				'entries'  => self::$entries_data,
				'style'    => self::$template_styles,
				'settings' => self::$settings,
			)
		);
	}

	/**
	 * Add the inline styles
	 *
	 * @since 5.8
	 */
	private static function add_inline_styles() {
		$inline_styles = array(
			'.frm-timeline-view--wrapper {',
			'--frm-views-timeline--line-color: ' . esc_attr( self::$template_styles['line_color'] ) . ';',
			'--frm-views-timeline--marker-color: ' . esc_attr( self::$template_styles['marker_color'] ) . ';',
			'--frm-views-timeline--card-background: ' . esc_attr( self::$template_styles['card_background'] ) . ';',
			'--frm-views-timeline--text-color: ' . esc_attr( self::$template_styles['text_color'] ) . ';',
			'--frm-views-timeline--popup-background: ' . esc_attr( self::$template_styles['popup_background'] ) . ';',
			'--frm-views-timeline--popup-text-color: ' . esc_attr( self::$template_styles['popup_text_color'] ) . ';',
			'}',
		);

		$css = implode( ' ', $inline_styles );
		wp_add_inline_style( 'formidable', $css );
	}

	/**
	 * Extract year from any date format
	 *
	 * @since 5.8
	 * @param object $view The view.
	 * @param string $date The date string.
	 *
	 * @return string The year
	 */
	private static function get_date( $view, $date ) {
		if ( empty( $date ) ) {
			return '';
		}

		if ( 'custom' === self::$settings['date_format'] && 'life-events' !== self::$template_styles['theme'] ) {
			return gmdate( self::$settings['date_custom_format'], strtotime( $date ) );
		}

		return self::get_year_from_date( $date );
	}

	/**
	 * Get the year from date
	 *
	 * @since 5.8
	 * @param string $date The date.
	 * @return string The year.
	 */
	private static function get_year_from_date( $date ) {
		$timestamp = strtotime( $date );
		if ( false !== $timestamp ) {
			return gmdate( 'Y', $timestamp );
		}
		return '';
	}

	/**
	 * Get the month from date
	 *
	 * @since 5.8
	 * @param string $date The date.
	 * @return string The month.
	 */
	private static function get_month_from_date( $date ) {
		$timestamp = strtotime( $date );
		if ( false !== $timestamp ) {
			return gmdate( 'm', $timestamp );
		}
		return '';
	}
}
