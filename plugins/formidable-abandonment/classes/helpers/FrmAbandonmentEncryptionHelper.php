<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class responsible for encrypting and decrypting data.
 *
 * @since 1.0
 */
final class FrmAbandonmentEncryptionHelper {

	/**
	 * Setting name.
	 *
	 * @since 1.1
	 * @var string $settings_name
	 */
	private $settings_name = 'frm_abandonment_encryption';

	/**
	 * Salt Settings.
	 *
	 * @since 1.0
	 * @var array<string> $settings
	 */
	private $settings = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$settings = get_option( $this->settings_name );
		if ( is_array( $settings ) ) {
			$this->settings = $settings;
		}
	}

	/**
	 * Encrypts a value.
	 *
	 * If a user-based key is set, that key is used. Otherwise the default key is used.
	 *
	 * @since 1.0
	 *
	 * @param string $value Value to encrypt.
	 * @return string|WP_Error Encrypted value, or WP_Error on failure.
	 */
	public function encrypt( $value ) {
		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );

		if ( ! $ivlen ) {
			return new WP_Error( 'php_openssl', $this->fail_message() );
		}

		$iv = openssl_random_pseudo_bytes( $ivlen );
		if ( ! $iv ) {
			return new WP_Error( 'php_openssl', $this->fail_message() );
		}

		$raw_value = openssl_encrypt( $value . $this->get_key( 'encrypt_salt' ), $method, $this->get_key( 'encrypt_key' ), 0, $iv );
		if ( ! $raw_value ) {
			return new WP_Error( 'php_openssl', __( 'Oops, Something is wrong with openssl.', 'formidable-abandonment' ) );
		}

		return base64_encode( $iv . $raw_value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypts a value.
	 *
	 * If a user-based key is set, that key is used. Otherwise the default key is used.
	 *
	 * @since 1.0
	 *
	 * @param string $raw_value Value to decrypt.
	 * @return string|WP_Error Decrypted value, or WP_Error on failure.
	 */
	public function decrypt( $raw_value ) {
		$raw_value = base64_decode( $raw_value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! $raw_value ) {
			return new WP_Error( 'php_openssl', __( 'That access key has a problem.', 'formidable-abandonment' ) );
		}

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );

		if ( ! $ivlen ) {
			return new WP_Error( 'php_openssl', $this->fail_message() );
		}

		$iv = substr( $raw_value, 0, $ivlen );

		$raw_value = substr( $raw_value, $ivlen );

		$value = openssl_decrypt( $raw_value, $method, $this->get_key( 'encrypt_key' ), 0, $iv );
		$salt  = $this->get_key( 'encrypt_salt' );
		if ( ! $value || substr( $value, - strlen( $salt ) ) !== $salt ) {
			return new WP_Error( 'php_openssl', __( 'Oops, the salt key has changed or is unreadable.', 'formidable-abandonment' ) );
		}

		return substr( $value, 0, - strlen( $salt ) );
	}

	/**
	 * Check if the entry secret is correct for the entry.
	 *
	 * @since 1.1
	 *
	 * @param float|int|string $entry_id     The id of the entry to edit.
	 * @param array<string>    $allow_status Allow draft status with 'draft'. 'published' for published status.
	 *
	 * @return WP_Error|int|false
	 */
	public function check_entry_token( $entry_id = 0, $allow_status = array( 'draft' ) ) {
		// Check post request for multipage forms.
		$encrypted_token = FrmAppHelper::get_param( 'secret', '', 'post', 'sanitize_text_field' );
		if ( empty( $encrypted_token ) ) {
			// Check get request for single page forms.
			$encrypted_token = FrmAbandonmentAppHelper::get_url_token();
			if ( empty( $encrypted_token ) ) {
				return false;
			}
		}

		if ( ! $entry_id ) {
			$decrypted_value = $this->decrypt( $encrypted_token );
			if ( is_wp_error( $decrypted_value ) ) {
				return new WP_Error(
					'wrong_encrypted',
					__( 'That link has expired or the entry has already been submitted.', 'formidable-abandonment' )
				);
			}

			$entry_id = (int) FrmAbandonmentAppHelper::get_entry_id_from_token( $decrypted_value );

			// If entry id not accessible from this stage it means link is expired or submitted before etc.
			if ( ! $entry_id ) {
				return new WP_Error(
					'http_request_failed',
					__( 'Not authorized.', 'formidable-abandonment' )
				);
			}
		}

		$edit_token = FrmAbdnToken::get_by_entry( (int) $entry_id );
		if ( $edit_token !== $encrypted_token ) {
			return new WP_Error(
				'http_request_failed',
				__( 'Not authorized.', 'formidable-abandonment' )
			);
		}

		// Check entry status.
		$draft_status = FrmAbdnEntry::draft_status( (int) $entry_id );
		$skip_draft   = ! in_array( 'draft', $allow_status, true ) && FrmProEntry::is_draft_status( $draft_status );
		$skip_publish = ! in_array( 'published', $allow_status, true ) && $draft_status === FrmEntriesHelper::SUBMITTED_ENTRY_STATUS;
		if ( $skip_publish || $skip_draft ) {
			return new WP_Error(
				'already_submitted',
				__( 'Already submitted.', 'formidable-abandonment' )
			);
		}

		return (int) $entry_id;
	}

	/**
	 * Get the standard error message.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	private function fail_message() {
		return __( 'Oops, that link is invalid.', 'formidable-abandonment' );
	}

	/**
	 * Gets the default encryption salt to use.
	 *
	 * @since 1.0
	 *
	 * @param string $name Name of the salt to get. Options are 'encrypt_salt' and 'encrypt_key'.
	 * @return string Encryption salt.
	 */
	private function get_key( $name = 'encrypt_salt' ) {
		if ( empty( $this->settings[ $name ] ) ) {
			$this->settings[ $name ] = $this->generate_crypto_bytes();
			$this->save_settings();
		}

		return $this->settings[ $name ];
	}

	/**
	 * Update the settings.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function save_settings() {
		update_option( $this->settings_name, $this->settings, false );
	}

	/**
	 * Generate crypto key.
	 *
	 * @since 1.0
	 *
	 * @return string key for encryption.
	 */
	private function generate_crypto_bytes() {
		// Ready for easy migration from old php versions.
		if ( version_compare( phpversion(), '7.0', '>' ) ) {
			return bin2hex( random_bytes( 25 ) );
		}

		return wp_generate_password( 20 );
	}
}
