<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * FrmViewsAppController class
 */
class FrmViewsAppController {

	/**
	 * Load addon i18n.
	 *
	 * @return void
	 */
	public static function load_lang() {
		load_plugin_textdomain( 'formidable-views', false, FrmViewsAppHelper::plugin_folder() . '/languages/' );
	}

	/**
	 * Admin init hook.
	 *
	 * @return void
	 */
	public static function admin_init() {
		self::include_updater();
		FrmViewsMigrate::get_instance();
		self::maybe_remove_beta_inbox_message();
		self::maybe_force_formidable_block_on_gutenberg_page();
		self::maybe_load_expired_script();
		FrmViewsCalendarHelper::init_admin_filters();
	}

	/**
	 * @since 5.4.2
	 *
	 * @return bool
	 */
	public static function is_expired_outside_grace_period() {
		return is_callable( 'FrmProAddonsController::is_expired_outside_grace_period' ) && FrmProAddonsController::is_expired_outside_grace_period();
	}

	/**
	 * Register and enqueue scripts used to show expired modal.
	 *
	 * @since 5.4.2
	 *
	 * @return void
	 */
	private static function maybe_load_expired_script() {
		$post_type = FrmAppHelper::simple_get( 'post_type', 'sanitize_title' );

		if ( self::is_expired_outside_grace_period() && 'frm_display' === $post_type ) {
			wp_register_script( 'formidable_pro_expired', FrmProAppHelper::plugin_url() . '/js/admin/expired.js', array( 'formidable_dom' ), FrmProDb::$plug_version, true );
			wp_enqueue_script( 'formidable_pro_expired' );
		}
	}

	/**
	 * Remove beta message.
	 *
	 * @return void
	 */
	private static function maybe_remove_beta_inbox_message() {
		if ( class_exists( 'FrmInbox' ) ) {
			$inbox = new FrmInbox();
			$inbox->remove( 'formidable_views_beta' );
		}
	}

	/**
	 * Addon updater.
	 *
	 * @return void
	 */
	private static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			include_once FrmViewsAppHelper::plugin_path() . '/classes/models/FrmViewsUpdate.php';
			FrmViewsUpdate::load_hooks();
		}
	}

	/**
	 * Prepare admin bar.
	 *
	 * @return void
	 */
	public static function admin_bar_configure() {
		if ( is_admin() || ! current_user_can( 'frm_edit_forms' ) ) {
			return;
		}

		$actions = array();
		self::add_views_to_admin_bar( $actions );

		if ( ! $actions ) {
			return;
		}

		self::maybe_add_parent_admin_bar();

		global $wp_admin_bar;

		foreach ( $actions as $id => $action ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'frm-forms',
					'title'  => $action['name'],
					'href'   => $action['url'],
					'id'     => 'edit_' . $id,
				)
			);
		}
	}

	/**
	 * Add admin bar.
	 *
	 * @return void
	 */
	private static function maybe_add_parent_admin_bar() {
		global $wp_admin_bar;
		$has_node = $wp_admin_bar->get_node( 'frm-forms' );
		if ( ! $has_node ) {
			FrmFormsController::add_menu_to_admin_bar();
		}
	}

	/**
	 * Menu to WordPress admin bar.
	 *
	 * @param array<mixed> $actions actions.
	 *
	 * @return void
	 */
	private static function add_views_to_admin_bar( &$actions ) {
		global $frm_vars;

		if ( empty( $frm_vars['views_loaded'] ) ) {
			return;
		}

		foreach ( $frm_vars['views_loaded'] as $id => $name ) {
			$actions[ 'view_' . $id ] = array(
				// translators: %s name.
				'name' => sprintf( __( '%s View', 'formidable-views' ), $name ),
				'url'  => admin_url( 'post.php?post=' . intval( $id ) . '&action=edit' ),
			);
		}
		asort( $actions );
	}

	/**
	 * Form nav.
	 *
	 * @param array<mixed>  $nav nav.
	 * @param array<string> $atts attributes.
	 *
	 * @return array<mixed>
	 */
	public static function form_nav( $nav, $atts ) {
		$form_id = absint( $atts['form_id'] );
		$nav[]   = array(
			'link'       => admin_url( 'edit.php?post_type=frm_display&form=' . $form_id . '&show_nav=1' ),
			'label'      => __( 'Views', 'formidable-views' ),
			'current'    => array(),
			'page'       => 'frm_display',
			'permission' => 'frm_edit_displays',
		);
		return $nav;
	}

	/**
	 * Load genesis.
	 *
	 * @return void
	 */
	public static function load_genesis() {
		// trigger Genesis hooks for integration.
		FrmViewsAppHelper::load_genesis();
	}

	/**
	 * Views css.
	 *
	 * @param array $args The style args.
	 * @return void
	 */
	public static function include_views_css( $args ) {
		self::include_grid_views_css();
		self::include_table_views_css();
		self::include_calendar_views_css( $args );
		self::include_timeline_views_css();
	}

	/**
	 * Grid views css.
	 *
	 * @return void
	 */
	public static function include_grid_views_css() {
		readfile( FrmViewsAppHelper::plugin_path() . '/css/grid-views.css' );
	}

	/**
	 * Table views css.
	 *
	 * @return void
	 */
	private static function include_table_views_css() {
		readfile( FrmViewsAppHelper::plugin_path() . '/css/table-views.css' );
	}

	/**
	 * Calendar views css.
	 *
	 * @param array $args The style args.
	 * @return void
	 */
	private static function include_calendar_views_css( $args ) {
		// Load CSS for legacy calendar views.
		$defaults = $args['defaults'];
		include FrmProAppHelper::plugin_path() . '/css/views-calendar-old-style.css.php';

		// Load CSS for modern calendar views.
		readfile( FrmViewsAppHelper::plugin_path() . '/css/calendar-views.css' );
	}

	private static function include_timeline_views_css() {
		readfile( FrmViewsAppHelper::plugin_path() . '/css/timeline-views.css' );
	}

	/**
	 * Shortcode content.
	 *
	 * @since 5.2
	 *
	 * @param string $content content.
	 * @param int    $view_id view_id.
	 *
	 * @return string
	 */
	public static function get_page_shortcode_content( $content, $view_id ) {
		$shortcode          = '[display-frm-data id="' . $view_id . '" filter="limited"]';
		$html_comment_start = '<!-- wp:formidable/simple-view {"viewId":"' . $view_id . '","useDefaultLimit":true} -->';
		$html_comment_end   = '<!-- /wp:formidable/simple-view -->';
		return $html_comment_start . '<div>' . $shortcode . '</div>' . $html_comment_end;
	}

	/**
	 * Automatically insert a Formidable block when loading Gutenberg when $_GET['frmView'] is set.
	 *
	 * @since 5.2
	 *
	 * @return void
	 */
	private static function maybe_force_formidable_block_on_gutenberg_page() {
		global $pagenow;
		if ( 'post.php' !== $pagenow ) {
			return;
		}

		$view_id = FrmAppHelper::simple_get( 'frmView', 'absint' );
		if ( ! $view_id || ! is_callable( 'FrmAppController::add_js_to_inject_gutenberg_block' ) ) {
			return;
		}

		FrmAppController::add_js_to_inject_gutenberg_block( 'formidable/simple-view', 'viewId', $view_id );
	}

	/**
	 * Update stylesheet.
	 *
	 * @since 5.3.1
	 *
	 * @return void
	 */
	public static function update_stylesheet() {
		$frm_style = new FrmStyle();
		$frm_style->update( 'default' );
	}

	/**
	 * Show a list of all alphabet letters used to filter views.
	 *
	 * @since 5.6.2
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public static function letter_filter( $atts ) {
		$defaults = array(
			'param' => 'lname',
		);

		$atts = shortcode_atts( $defaults, $atts );
		$link = '<li><a href="?' . esc_attr( $atts['param'] ) . '=%1$s">%2$s</a></li>';
		if ( ! strpos( get_the_permalink(), '?' ) === false ) {
			$link = str_replace( ' href="?', ' href="&', $link );
		}
		$list  = '<ul class="frm_plain_list frm_inline_list frm_full_row">';
		$list .= '<li><a href="?">' . esc_html__( 'All', 'formidable-views' ) . '</a></li>';

		$letters = range( 'A', 'Z' );
		foreach ( $letters as $letter ) {
			$list .= sprintf( $link, strtolower( $letter ), $letter );
		}
		$list .= '</ul>';
		return $list;
	}

	/**
	 * Show a tooltip icon with the message passed.
	 *
	 * @since 5.6.4
	 *
	 * @param string $message The message to be displayed in the tooltip.
	 * @param array  $atts    The attributes to be added to the tooltip.
	 *
	 * @return void
	 */
	public static function show_svg_tooltip( $message, $atts = array() ) {
		if ( ! is_callable( 'FrmAppHelper::tooltip_icon' ) ) {
			return;
		}
		FrmAppHelper::tooltip_icon( $message, $atts );
	}

	/**
	 * Adds usage tracking data.
	 *
	 * @since 5.7.1
	 *
	 * @param array $snapshot Usage snapshot data.
	 * @return array
	 */
	public static function add_usage_tracking_data( $snapshot ) {
		global $wpdb;
		$view_meta_rows = $wpdb->get_results(
			"SELECT m.meta_key AS meta_key, m.meta_value AS meta_value, p.ID AS view_id
			FROM {$wpdb->prefix}posts AS p INNER JOIN {$wpdb->prefix}postmeta as m
			ON p.ID = m.post_id
			WHERE p.post_type = 'frm_display'"
		);

		$snapshot['views'] = array();
		foreach ( $view_meta_rows as $row ) {
			if ( 'frm_dyncontent' === $row->meta_key || 0 !== strpos( $row->meta_key, 'frm_' ) ) {
				// Skip sending view content.
				continue;
			}

			if ( 'frm_options' === $row->meta_key ) {
				FrmAppHelper::unserialize_or_decode( $row->meta_value );
			}

			if ( ! isset( $snapshot['views'][ $row->view_id ] ) ) {
				$snapshot['views'][ $row->view_id ] = array();
			}
			$snapshot['views'][ $row->view_id ][ $row->meta_key ] = $row->meta_value;
		}

		foreach ( $snapshot['views'] as $view_id => &$view_metas ) {
			$view_metas['ID']   = $view_id;
			$view_metas['type'] = FrmViewsDisplaysHelper::get_view_type( $view_metas );
		}

		return $snapshot;
	}

	/**
	 * Register script.
	 *
	 * @deprecated 5.3.1
	 *
	 * @return void
	 */
	public static function register_scripts() {
		_deprecated_function( __FUNCTION__, '5.3.1', 'This method FrmViewsAppController::register_scripts() is deprecated and will be removed in a few upcoming versions.' );
	}
}
