<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Formidable abandonment entry detail.
 *
 * @var string $token_link Token link.
 * @var string $token Token.
 * @var object $entry Entry.
 *
 * @package formidable-abandonment
 */
?>
<div id="frm-abandon-box" class="frm_with_icons">
	<h3>
		<?php esc_html_e( 'Entry Edit Token', 'formidable-abandonment' ); ?>
	</h3>
	<div class="inside">
		<div class="misc-pub-section frm-abandonment-entry-detail-items">
			<a href="<?php echo esc_url( $token_link ); ?>" id="frm-abandonment-link-btn" target="_blank">
				<?php FrmAppHelper::icon_by_class( 'frmfont frm_external_link_icon', array( 'aria-hidden' => 'true' ) ); ?>
				<span class="frm-abandon-short-token"><?php echo esc_html( substr( $token, 0, 20 ) ); ?></span>...
			</a>
		</div>
		<?php if ( is_ssl() ) : ?>
		<div class="misc-pub-section frm-abandonment-entry-detail-items">
			<a href="#" id="frm-abandonment-copy-link-btn">
				<?php FrmAppHelper::icon_by_class( 'frm_icon_font frm_link_icon', array( 'aria-hidden' => 'true' ) ); ?>
				<?php esc_html_e( 'Copy Edit Link', 'formidable-abandonment' ); ?>
			</a>
		</div>
		<?php endif; ?>
		<div class="misc-pub-section frm-abandonment-entry-detail-items">
			<a href="#" id="frm-entry-detail-reset-token"
				data-entry-id="<?php echo esc_attr( $entry->id ); ?>"
				data-frmreset="1"
				data-frmverify-btn="frm-button-red"
				data-frmverify="<?php esc_attr_e( 'When a token is reset, the previous token will no longer work. Would you like to continue?', 'formidable-abandonment' ); ?>"
				>
				<?php FrmAppHelper::icon_by_class( 'frmfont frm_repeater_icon', array( 'aria-hidden' => 'true' ) ); ?>
				<?php esc_html_e( 'Reset Token', 'formidable-abandonment' ); ?>
			</a>
		</div>
	</div>
</div>
