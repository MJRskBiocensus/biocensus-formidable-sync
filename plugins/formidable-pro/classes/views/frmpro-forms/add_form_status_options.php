<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<p class="howto">
	<?php esc_html_e( 'Prevent the form from showing when submissions should not be accepted. Close it now, or schedule the form to open and/or close later.', 'formidable-pro' ); ?>
</p>

<p>
	<label for="frm_open_status" class="frm_left_label">
		<?php esc_html_e( 'Form Status', 'formidable-pro' ); ?>
	</label>
	<select name="options[open_status]" id="frm_open_status" data-toggleclass="hide_form_status">
		<option value="" <?php selected( $values['open_status'], '' ); ?>>
			<?php esc_html_e( 'Open', 'formidable-pro' ); ?>
		</option>
		<option value="closed" <?php selected( $values['open_status'], 'closed' ); ?>>
			<?php esc_html_e( 'Closed', 'formidable-pro' ); ?>
		</option>
		<option value="schedule" <?php selected( $values['open_status'], 'schedule' ); ?>>
			<?php esc_html_e( 'Schedule', 'formidable-pro' ); ?>
		</option>
		<option value="limit" <?php selected( $values['open_status'], 'limit' ); ?>>
			<?php esc_html_e( 'Limit Entries', 'formidable-pro' ); ?>
		</option>
		<option value="schedule-limit" <?php selected( $values['open_status'], 'schedule-limit' ); ?>>
			<?php esc_html_e( 'Schedule and Limit Entries', 'formidable-pro' ); ?>
		</option>
	</select>
</p>

<p class="hide_form_status hide_hide_form_status_closed hide_hide_form_status_limit<?php echo strpos( $values['open_status'], 'schedule' ) === false ? ' frm_hidden' : ''; ?>">
	<label for="frm_open_date" class="frm_left_label">
		<?php esc_html_e( 'Open Form On', 'formidable-pro' ); ?>
	</label>
	<input type="text" name="options[open_date]" id="frm_open_date" class="frm_date" value="<?php echo esc_attr( $values['open_date'] ); ?>" />
</p>
<p class="hide_form_status hide_hide_form_status_closed hide_hide_form_status_limit<?php echo strpos( $values['open_status'], 'schedule' ) === false ? ' frm_hidden' : ''; ?>">
	<label for="frm_close_date" class="frm_left_label">
		<?php esc_html_e( 'Close Form On', 'formidable-pro' ); ?>
	</label>
	<input type="text" name="options[close_date]" id="frm_close_date" class="frm_date" value="<?php echo esc_attr( $values['close_date'] ); ?>" />
</p>
<p class="hide_form_status hide_hide_form_status_closed hide_hide_form_status_schedule<?php echo strpos( $values['open_status'], 'limit' ) === false ? ' frm_hidden' : ''; ?>">
	<label class="frm_left_label">
		<?php esc_html_e( 'Entry Limit', 'formidable-pro' ); ?>
		<?php FrmProAppHelper::tooltip_icon( __( 'Close the form after a specific number of entries have been received.', 'formidable-pro' ), array( 'data-placement' => 'right' ) ); ?>
	</label>

	<input type="text" name="options[max_entries]" id="frm_max_entries" size="4" value="<?php echo esc_attr( $values['max_entries'] ); ?>" />
</p>
<p class="hide_form_status<?php echo empty( $values['open_status'] ) ? ' frm_hidden' : ''; ?>">
	<label for="frm_closed_msg" class="frm_left_label">
		<?php esc_html_e( 'Form Closed Message', 'formidable-pro' ); ?>
		<?php FrmProAppHelper::tooltip_icon( __( 'This message is shown when a form is closed for new entries.', 'formidable-pro' ), array( 'data-placement' => 'right' ) ); ?>
	</label>
	<textarea name="options[closed_msg]" id="frm_closed_msg" rows="3"><?php echo FrmAppHelper::esc_textarea( $values['closed_msg'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></textarea>
</p>

<script>
	jQuery( function() {
		jQuery('.frm_date').datepicker({changeMonth:true,changeYear:true,dateFormat:'yy-mm-dd',
			beforeShow: function() {
				document.getElementById( 'ui-datepicker-div' )?.classList.add( 'frm-datepicker' );
			},
			onSelect: function(val){
			var d = new Date();

			var h = d.getHours();
			h = (h < 10) ? ('0' + h) : h ;

			var m = d.getMinutes();
			m = (m < 10) ? ('0' + m) : m ;

			val = val + ' ' + h + ':' + m;

			jQuery(this).val(val);
		}});
	});
</script>
