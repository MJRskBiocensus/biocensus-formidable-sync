<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Success message for logged out draft.
 *
 * @var string $token_link Token link.
 *
 * @package formidable-abandonment
 */
?>
<div class="frm-abandonment-copy-box">
	<input id="frm-abandonment-link" type="hidden" value="<?php echo esc_attr( $token_link ); ?>" />
	<div class="frm_submit">
		<button id="frm-abandonment-copy-link">
			<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m14 11 1-1a3.5 3.5 0 1 0-5-5L9 6M6 9l-1 1a3.5 3.5 0 0 0 5 5l1-1M11.9 8.1 8 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
			<?php echo esc_html( $label ); ?>
		</button>
	</div>
</div>
