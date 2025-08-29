<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmUsrTrkSession {
	/**
	 * Keep the page history below 100
	 *
	 * @var int $page_max
	 */
	private static $page_max = 100;

	/**
	 * @var array $referer_info
	 */
	private static $referer_info = array();

	/**
	 * @return void
	 */
	public static function compile_referer_session() {
		if ( self::session_not_started() ) {
			return;
		}
		self::add_referer_to_session();
		self::add_current_page_to_session();
		self::remove_visited_above_max();
	}

	/**
	 * Only start session if we're not importing, running a cron, using REST and the CLI.
	 *
	 * @return bool
	 */
	private static function should_start_session() {
		$constants = array( 'WP_IMPORTING', 'DOING_CRON', 'REST_REQUEST', 'WP_CLI' );

		foreach ( $constants as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return void
	 */
	private static function maybe_start_session() {
		if ( ! isset( $_SESSION ) ) {
			session_start();
		}
	}

	/**
	 * @return void
	 */
	public static function try_starting_session() {
		if ( headers_sent() || ! self::should_start_session() ) {
			return;
		}

		self::maybe_start_session();
	}

	/**
	 * @return bool
	 */
	private static function session_not_started() {
		return session_status() === PHP_SESSION_NONE;
	}

	/**
	 * @return void
	 */
	private static function add_referer_to_session() {
		if ( self::is_excluded_from_session( 'frm_http_referer' ) ) {
			$_SESSION['frm_http_referer'] = array();
		}

		$referer = self::get_referer();
		if ( ! $referer || in_array( $referer, $_SESSION['frm_http_referer'], true ) ) {
			return;
		}

		$_SESSION['frm_http_referer'][] = $referer;

		self::add_to_session( compact( 'referer' ) );
	}

	/**
	 * @return void
	 */
	private static function add_current_page_to_session() {
		if ( self::is_excluded_from_session( 'frm_http_pages' ) ) {
			$_SESSION['frm_http_pages'] = array();
		}

		$url = self::get_url();
		if ( self::is_skippable_page( $url ) ) {
			return;
		}

		$session = $_SESSION['frm_http_pages'];
		self::add_duration_to_last_page( $session );

		$end_page = end( $session );
		if ( isset( $end_page['url'] ) && $url === $end_page['url'] ) {
			// Don't save the same page twice in a row.
			return;
		}

		$request = self::parse_wp_request();

		$page_details = array(
			'url'          => $url,
			'relative_url' => $request ? $request : FrmAppHelper::get_server_value( 'QUERY_STRING' ),
		);
		self::add_title_to_page( $page_details );

		self::add_to_session( $page_details, $session );
	}

	/**
	 * @param array $action The page details to add to the session.
	 * @param array $session The session info we should add to. This may have already been modified.
	 * @return void
	 */
	private static function add_to_session( $action, $session = array() ) {
		$date = self::get_timestamp();
		if ( empty( $session ) && isset( $_SESSION['frm_http_pages'] ) ) {
			$session = $_SESSION['frm_http_pages'];
		}

		if ( isset( $session[ $date ] ) ) {
			// Don't overwrite the referrer if already saved.
			$date = gmdate( 'Y-m-d H:i:s', strtotime( $date ) + 1 );
		}
		$session[ $date ] = $action;
		$_SESSION['frm_http_pages'] = $session;
	}

	/**
	 * @return string
	 */
	private static function get_timestamp() {
		return (string) current_time( 'mysql', 1 );
	}

	/**
	 * @return string
	 */
	private static function parse_wp_request() {
		global $wp;
		$wp->parse_request();

		return $wp->request;
	}

	/**
	 * Return the full URL path to the current request.
	 *
	 * @return string
	 */
	private static function get_url() {
		$url = ( is_ssl() ? 'https://' : 'http://' ) . FrmAppHelper::get_server_value( 'HTTP_HOST' ) . FrmAppHelper::get_server_value( 'REQUEST_URI' );
		return untrailingslashit( $url );
	}

	/**
	 * Examines a URL and return true if it should be skipped.
	 * For example, there can be API requests while loading a page.
	 *
	 * @param string $url The url to check.
	 *
	 * @return bool
	 */
	private static function is_skippable_page( $url ) {
		if ( FrmAppHelper::doing_ajax() || wp_is_json_request() || FrmAppHelper::get_param( 'wc-ajax' ) ) {
			return true;
		}

		$url = strrchr( $url, '.' );
		if ( ! $url ) {
			return false;
		}
		$ext = substr( $url, 1 );
		return in_array( $ext, array( 'css', 'js', 'map', 'ico' ), true );
	}

	/**
	 *
	 *
	 * @param array $page_details
	 * @return void
	 */
	private static function add_title_to_page( &$page_details ) {
		add_filter( 'document_title_parts', 'FrmUsrTrkAppController::unset_document_title_parts' );
		$title = wp_get_document_title();
		$page_details['title'] = $title ? $title : __( 'Untitled', 'formidable-usr-trk' );
		remove_filter( 'document_title_parts', 'FrmUsrTrkAppController::unset_document_title_parts' );
	}

	/**
	 * If the referer is external, log it.
	 *
	 * @return string
	 */
	private static function get_referer() {
		$referer = FrmAppHelper::get_server_value( 'HTTP_REFERER' );
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = __( 'Type-in or bookmark', 'formidable-usr-trk' );
		}

		// Check if the referrer is external before saving.
		if ( $referer && false === strpos( $referer, FrmAppHelper::site_url() ) ) {
			return $referer;
		}

		return '';
	}

	/**
	 * @return void
	 */
	private static function remove_visited_above_max() {
		$total_pages_visited = self::count_total_pages_visited();

		if ( $total_pages_visited > self::$page_max ) {
			$number_to_remove           = $total_pages_visited - self::$page_max;
			$_SESSION['frm_http_pages'] = array_slice( $_SESSION['frm_http_pages'], $number_to_remove );
		}
	}

	/**
	 * @return int
	 */
	private static function count_total_pages_visited() {
		if ( empty( $_SESSION['frm_http_pages'] ) ) {
			return 0;
		}

		return count( $_SESSION['frm_http_pages'] );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	private static function is_excluded_from_session( $key ) {
		return ( ! self::is_included_in_session( $key ) || ! is_array( $_SESSION[ $key ] ) );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	private static function is_included_in_session( $key ) {
		return isset( $_SESSION ) && isset( $_SESSION[ $key ] ) && $_SESSION[ $key ];
	}

	/**
	 * Adds the time difference between the last page visited and the current page a user is opening to the last page details.
	 *
	 * @param array $session pages visited so far in the current session.
	 * @return void
	 */
	private static function add_duration_to_last_page( &$session ) {
		if ( ! $session ) {
			return;
		}

		end( $session );
		$last_date = key( $session );
		reset( $session );

		if ( is_numeric( $last_date ) || empty( $last_date ) ) {
			return;
		}

		$diff = time() - strtotime( $last_date );

		$last_action = $session[ $last_date ];
		if ( isset( $last_action['entry'] ) && ( $diff >= DAY_IN_SECONDS ) ) {
			$session               = array();
			$session[ $last_date ] = $last_action;
		}

		$session[ $last_date ]['duration'] = $diff;
	}

	/**
	 * Inserts tracking data into entry description once it is saved.
	 * This function also adds the last row that has summary of the user journey.
	 *
	 * @param int $entry_id
	 * @return void
	 */
	public static function insert_tracking_into_entry( $entry_id ) {
		if ( self::session_not_started() ) {
			return;
		}

		if ( empty( $_SESSION['frm_http_pages'] ) ) {
			return;
		}

		self::add_referers_keywords_to_data();

		$entry             = FrmEntry::getOne( $entry_id );
		$entry_description = $entry->description;
		FrmAppHelper::unserialize_or_decode( $entry_description );
		if ( ! is_array( $entry_description ) ) {
			return;
		}

		$session = $_SESSION['frm_http_pages'];

		self::add_duration_to_last_page( $session );
		$form_name = FrmFormsHelper::edit_form_link_label( $entry->form_id );

		$date             = self::get_timestamp();
		$session[ $date ] = array(
			'url'   => admin_url( 'admin.php?page=formidable-entries&frm_action=show&id=' . $entry_id ),
			'entry' => $entry_id,
			'title' => $form_name,
		);

		$entry_description['user_journey'] = $session;
		$_SESSION['frm_http_pages']        = $session;

		if ( isset( self::$referer_info['keywords'] ) ) {
			$entry_description['user_journey']['keywords'] = self::$referer_info['keywords'];
		}

		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'frm_items', array( 'description' => json_encode( $entry_description ) ), array( 'id' => $entry_id ) );
		FrmEntry::clear_cache();
	}

	/**
	 * @return void
	 */
	private static function add_referers_keywords_to_data() {
		$referers = self::is_included_in_session( 'frm_http_referer' ) ? $_SESSION['frm_http_referer'] : array( FrmAppHelper::get_server_value( 'HTTP_REFERER' ) );

		foreach ( $referers as $referer ) {
			$keywords_used = FrmUsrTrkAppHelper::get_referer_query( $referer );
			if ( false !== $keywords_used ) {
				self::$referer_info['keywords'][] = $keywords_used;
			}
		}
	}
}
