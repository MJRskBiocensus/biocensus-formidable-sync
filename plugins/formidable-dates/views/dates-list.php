<ul class="frmdates_date_list" data-field-id="<?php echo absint( $field_id ); ?>" data-date-type="<?php echo esc_attr( $date_type ); ?>">
	<?php
	foreach ( $items as $i => $date ) :
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo FrmDatesTemplatesHelper::settings_render_dates_list_item(
			array(
				'date'        => $date, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'date_type'   => $date_type, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'field_id'    => $field_id, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'css_classes' => ( $i > 4 ) ? 'frm_hidden' : '',
			)
		);
	endforeach;
	?>

	<li class="frmdates_show_all_placeholder <?php echo count( $items ) < 5 ? 'frm_hidden' : ''; ?>">
		<?php // translators: %s - the number of dates initially hidden. ?>
		<a href="#"><?php printf( esc_html( _n( '... and %s more', '... and %s more', count( $items ) - 5, 'frmdates' ) ), '<span class="count">' . ( count( $items ) - 5 ) . '</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</li>
</ul>
