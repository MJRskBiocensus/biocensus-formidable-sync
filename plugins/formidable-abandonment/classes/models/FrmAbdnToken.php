<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrmAbdnToken
 *
 * @since 1.1
 *
 * @package formidable-abandonment
 */
class FrmAbdnToken {

	/**
	 * Perform reset token.
	 *
	 * @since 1.1
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return string|WP_Error
	 */
	public static function reset_token( $entry_id ) {
		self::unlink_token( $entry_id );
		return self::maybe_create_token( $entry_id );
	}

	/**
	 * Maybe create a edit token.
	 *
	 * @since 1.1
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return string|WP_Error
	 */
	public static function maybe_create_token( $entry_id ) {
		$token = self::generate_token( $entry_id );

		$edit_token = ( new FrmAbandonmentEncryptionHelper() )->encrypt( $token );
		if ( ! is_wp_error( $edit_token ) ) {
			FrmEntryMeta::add_entry_meta( $entry_id, 0, null, self::token_prefix() . $edit_token );
		}

		return $edit_token;
	}

	/**
	 * Generate a token for the entry.
	 *
	 * @since 1.1
	 *
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	private static function generate_token( $entry_id ) {
		return wp_generate_uuid4() . '-' . $entry_id;
	}

	/**
	 * A draft doesn't go through the ajax process to get a token,
	 * so we need to check and see if this entry needs a token created.
	 * This is only intended for logged out users saving drafts.
	 *
	 * @since 1.1
	 *
	 * @param array<string,mixed> $args {
	 *   Passed arguments from filter.
	 *   @type int    $entry_id Entry ID.
	 *   @type object $form     Form.
	 * }
	 *
	 * @return void
	 */
	public static function create_new_draft_token( $args ) {
		$secret = FrmAppHelper::get_param( 'secret', '', 'post', 'sanitize_text_field' );
		if ( $secret ) {
			return;
		}

		$args['entry_id'] = absint( $args['entry_id'] );

		$has_secret = self::get_by_entry( $args['entry_id'] );
		if ( $has_secret ) {
			// We don't want to create a new token if one already exists.
			return;
		}

		$token = self::maybe_create_token( $args['entry_id'] );

		if ( ! is_wp_error( $token ) ) {
			// Add the token to the form.
			$_POST['secret'] = $token; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Get Token of entry from metas, based on the entry id.
	 *
	 * @param int $entry_id The entry id to check.
	 *
	 * @return string
	 */
	public static function get_by_entry( $entry_id ) {
		if ( 0 === $entry_id ) {
			return '';
		}

		// Search for a token linked to entry metas.
		$edit_token = self::search_by_entry( $entry_id );
		if ( ! $edit_token ) {
			return '';
		}

		return ltrim( $edit_token, self::token_prefix() );
	}

	/**
	 * Search entry for an token between zero indexed metas.
	 *
	 * @since 1.0
	 * @since 1.1 Moved to FrmAbdnToken class.
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return string|false Token linked to an entry or false otherwise.
	 */
	private static function search_by_entry( $entry_id ) {
		$metas = FrmDb::get_results(
			'frm_item_metas',
			array(
				'field_id' => '0',
				'item_id'  => $entry_id,
			),
			'meta_value'
		);

		if ( ! $metas ) {
			return false;
		}

		// Find the token if there were more than a zero indexed meta for an entry.
		$token = false;
		foreach ( $metas as $value ) {
			if ( strpos( $value->meta_value, self::token_prefix() ) === 0 ) {
				$token = $value->meta_value;
				break;
			}
		}

		return $token;
	}

	/**
	 * Unlink Token from item meta.
	 *
	 * @since 1.0
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return void
	 */
	public static function unlink_token( $entry_id ) {
		global $wpdb;
		FrmEntryMeta::clear_cache();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}frm_item_metas WHERE item_id=%d AND meta_value LIKE %s OR meta_value LIKE %s",
				$entry_id,
				self::token_prefix() . '%',
				'uuid-%' // This can be removed in 2024-03. It's to remove old tokens we no longer create.
			)
		);
	}

	/**
	 * Get the token prefix for the entry meta.
	 *
	 * @since 1.1
	 *
	 * @return string
	 */
	private static function token_prefix() {
		return 'token-';
	}
}
