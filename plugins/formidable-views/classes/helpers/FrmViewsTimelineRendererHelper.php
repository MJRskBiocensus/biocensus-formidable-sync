<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmViewsTimelineRendererHelper {

	/**
	 * @var array
	 */
	private $settings;

	/**
	 * @var array
	 */
	private $template_styles;

	/**
	 * @var array
	 */
	private $args;

	/**
	 * Constructor
	 *
	 * @param array $settings The Timeline settings. Set in FrmViewsTimelineController.
	 * @param array $template_styles The Timeline template styles. Set in FrmViewsTimelineController.
	 * @param array $args The Timeline arguments.
	 */
	public function __construct( $settings, $template_styles, $args ) {
		$this->settings        = $settings;
		$this->template_styles = $template_styles;
		$this->args            = $args;
	}

	/**
	 * Get the template
	 *
	 * @since 5.8
	 */
	public function get_template() {
		if ( 1 < (int) $this->args['current_page'] ) {
			$this->get_entry_template_list();
			return;
		}

		if ( in_array( $this->template_styles['layout'], array( 'vertical', 'vertical-left', 'vertical-right' ), true ) ) {
			include FrmViewsAppHelper::plugin_path() . '/classes/views/timeline/timeline.php';
			return;
		}

		include FrmViewsAppHelper::plugin_path() . '/classes/views/timeline/timeline-horizontal.php';
	}

	/**
	 * Get the entry template list
	 *
	 * @since 5.8
	 */
	public function get_entry_template_list() {
		$start_index   = ! empty( $this->args['page_size'] ) ? ( (int) $this->args['current_page'] - 1 ) * (int) $this->args['page_size'] : 0;
		$previous_year = null;

		if ( FrmViewsTimelineController::has_dividers() ) {
			$this->get_entries_template_list_by_divider_type( $start_index );
			return;
		}

		$this->get_entries_regular_template_list( $start_index );
	}

	/**
	 * Get the entries template list by divider type
	 *
	 * @since 5.8
	 * @param int $start_index The start index.
	 */
	private function get_entries_template_list_by_divider_type( $start_index ) {
		$index         = 0;
		$previous_year = null;

		foreach ( $this->args['entries_data_by_divider_type'] as $grouped_entries ) {
			echo wp_kses_post( $this->get_divider_template( $grouped_entries['date'] ) );
			foreach ( $grouped_entries['entries'] as $entry ) {
				$this->load_natural_timeline_empty_templates( $previous_year, $entry );
				$this->get_entry_template( $entry, $index + $start_index );
				$previous_year = $entry['year'];
				++$index;
			}
		}
	}

	/**
	 * Get the divider template
	 *
	 * @since 5.8
	 * @param string $date The date.
	 * @return string The divider template.
	 */
	private function get_divider_template( $date ) {
		$divider = 'year' === FrmViewsTimelineController::get_divider_type() ? gmdate( 'Y', strtotime( $date ) ) : gmdate( 'M Y', strtotime( $date ) );
		return '<div class="frm-timeline-view--divider"><div class="frm-timeline-view--divider-shape"><span>' . esc_html( $divider ) . '</span></div></div>';
	}

	/**
	 * Get the entries regular template list
	 *
	 * @since 5.8
	 * @param int $start_index The start index.
	 */
	private function get_entries_regular_template_list( $start_index ) {
		$previous_year = null;
		foreach ( $this->args['entries_data'] as $index => $entry ) {
			$this->load_natural_timeline_empty_templates( $previous_year, $entry );
			$this->get_entry_template( $entry, $index + $start_index );
			$previous_year = $entry['year'];
		}
	}

	/**
	 * Get the entry template
	 *
	 * @since 5.8
	 * @param array $entry The entry.
	 * @param int   $index The index.
	 */
	private function get_entry_template( $entry, $index ) {
		if ( ( 1 === $index % 2 && 'vertical-right' !== $this->template_styles['layout'] && 'horizontal-bottom' !== $this->template_styles['layout'] ) || 'vertical-left' === $this->template_styles['layout'] || 'horizontal-top' === $this->template_styles['layout'] ) {
			$this->get_entry_template_left_side( $entry, $index );
			return;
		}
		$this->get_entry_template_right_side( $entry, $index );
	}

	/**
	 * Get the entry template left side
	 *
	 * @since 5.8
	 * @param array $entry The entry.
	 * @param int   $index The index.
	 */
	private function get_entry_template_left_side( $entry, $index ) {
		$this->get_entry_template_side( $entry, $index, 'left' );
	}

	/**
	 * Get the entry template right side
	 *
	 * @since 5.8
	 * @param array $entry The entry.
	 * @param int   $index The index.
	 */
	private function get_entry_template_right_side( $entry, $index ) {
		$this->get_entry_template_side( $entry, $index, 'right' );
	}

	/**
	 * Get the entry template for a given side.
	 *
	 * @since 5.8
	 *
	 * @param array  $entry The entry.
	 * @param int    $index The index.
	 * @param string $side The side (left or right).
	 */
	private function get_entry_template_side( $entry, $index, $side ) {
		$has_entry_link = $this->show_entry_link() && ! empty( $entry['detail_link'] );
		$clickable      = $has_entry_link || 1 === (int) $this->settings['show_details_popup'];
		include FrmViewsAppHelper::plugin_path() . '/classes/views/timeline/entry-template-' . $side . '-side.php';
	}

	/**
	 * Get the entry empty template
	 *
	 * @since 5.8
	 * @return string The entry empty template.
	 */
	private function get_entry_empty_template() {
		return '<div class="frm-timeline-view--empty-entry"></div>';
	}

	/**
	 * Load the natural timeline empty templates
	 *
	 * @since 5.8
	 * @param null|int $previous_year The previous year.
	 * @param array    $entry The entry.
	 */
	private function load_natural_timeline_empty_templates( $previous_year, $entry ) {
		if ( empty( $this->settings['natural_timeline'] ) || null === $previous_year || $entry['year'] === $previous_year ) {
			return;
		}

		$years_diff = abs( $entry['year'] - $previous_year );
		if ( 0 === $years_diff ) {
			return;
		}

		for ( $i = 1; $i < $years_diff; $i++ ) {
			echo wp_kses_post( $this->get_entry_empty_template() );
		}
	}

	/**
	 * Get the wrapper class name
	 *
	 * @since 5.8
	 * @return string The wrapper class name.
	 */
	public function get_wrapper_classname() {
		return 'frm-timeline-view--wrapper frm-timeline-view--' . $this->template_styles['layout'] . ' frm-timeline-view--theme-' . $this->template_styles['theme'];
	}

	/**
	 * Get the marker class name
	 *
	 * @since 5.8
	 * @return string The marker class name.
	 */
	public function get_marker_classname() {
		return 'frm-timeline-view--marker frm-timeline-view--marker-' . $this->template_styles['marker_icon_size'];
	}

	/**
	 * Show the entry link
	 *
	 * @since 5.8
	 * @return bool True if the entry link should be shown, false otherwise.
	 */
	public function show_entry_link() {
		if ( 1 === (int) $this->settings['show_details_popup'] ) {
			return false;
		}
		return ! empty( $this->args['view']->frm_dyncontent );
	}
}
