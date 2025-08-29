<li class="frmdates_date_list_item <?php echo esc_attr( $css_classes ); ?>" data-date="<?php echo esc_attr( $date ); ?>">
	<a href="#" class="frmdates_remove_item">
		<svg viewBox="0 0 20 20" width="18" height="18" class="frmsvg">
		<path d="M5 9v2h10v-2h-10zM10 0c-5.52 0-10 4.48-10 10s4.48 10 10 10 10-4.48 10-10-4.48-10-10-10zM10 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"></path>
		</svg>
	<span class="frmdates_date_with_format"><?php echo esc_html( $formatted_date ); ?></span>

	<?php if ( ! empty( $input_name ) ) : ?>
	<input type="hidden" name="field_options[<?php echo esc_attr( $input_name ); ?>][]" value="<?php echo esc_attr( $date ); ?>" />
	<?php endif; ?>
</li>
