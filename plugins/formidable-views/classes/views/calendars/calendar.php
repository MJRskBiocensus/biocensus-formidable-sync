<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<div class="frmcal-calendar">
	<div class="frmcal-row-headings">
		<?php for ( $i = $week_begins; $i < $week_begins + 7; $i++ ) { ?>
			<div>
				<div class="frmcal-day-name">
					<span class="frmcal-hide-on-desktop"><?php echo isset( $day_names[ $i ] ) ? esc_html( substr( $day_names[ $i ], 0, 3 ) ) . ' ' : ''; ?></span><span class="frmcal-hide-on-mobile"><?php echo isset( $day_names[ $i ] ) ? esc_html( $day_names[ $i ] ) . ' ' : ''; ?></span>
				</div>
			</div>
		<?php } ?>
	</div>

	<?php

	$last_entry_position = $week_begins;
	for ( $i = $week_begins; $i < $maxday + $startday; $i++ ) {
		$last_entry_position = $i;
		$pos                 = $i % 7;
		$end_tr              = false;
		$day                 = $i - $startday + 1;
		$date                = $year . '-' . $month . '-' . $day;

		if ( $day <= 0 ) {
			$day  = $prev_month_startday + $i;
			$date = $year . '-' . $prev_month . '-' . $day;
		}

		$date = gmdate( 'Y-m-d', strtotime( $date ) );

		if ( $pos == $week_begins ) {
			$week_start_date = $date;

			echo "<div>\n";
		}

		// Add classes for the day.
		$day_class  = 'frmcal-day';
		$day_class .= $i < $startday ? ' frm-inactive' : '';

		// Check for today.
		if ( isset( $today ) && $day == $today ) {
			$day_class .= ' frmcal-today';
		}

		if ( 0 == $pos || 6 == $pos ) {
			$day_class .= ' frmcal-week-end';
		}

		?>
		<div <?php echo ! empty( $day_class ) ? 'class="' . esc_attr( $day_class ) . '"' : ''; ?> data-week-start-date="<?php echo esc_attr( $week_start_date ); ?>" data-date="<?php echo esc_attr( $date ); ?>">
			<div class="frmcal_date">
				<span class="frmcal_num<?php echo $i < $startday ? ' frm-inactive' : ''; ?>"><?php echo esc_html( $day ); ?></span>
			</div>

			<div class="frmcal-content">
			<?php
			unset( $day_class );

			if ( ! empty( $daily_entries[ $i ] ) ) {
				$pass_atts = array(
					'event_date' => $date,
					'day_count'  => count( $daily_entries[ $i ] ),
					'view'       => $view,
				);

				do_action( 'frm_before_day_content', $pass_atts );

				$count = 0;
				foreach ( $daily_entries[ $i ] as $entry ) {
					++$count;
					$event_end_date = gmdate( 'Y-m-d', strtotime( $date . ' + ' . ( $entry->event_length - 1 ) . ' days' ) );
					$popup          = new FrmViewsCalendarPopupHelper(
						$view,
						$entry
					);

					if ( ! isset( $used_entries[ $entry->id ] ) ) {
						if ( $entry->is_multiday ) {
							?>
							<div class="frmcal-daily-event frmcal-hide-on-desktop"></div><?php // Mobile bullet event ?>
							<div
								class="frmcal-multi-day-event frmcal-hide frmcal-hide-on-mobile <?php echo ! empty( $entry->repeating_period ) ? 'frm-repeating-event' : ''; ?>"
								<?php echo ! empty( $entry->repeating_period ) ? 'data-repeating-start-dates="' . esc_attr( wp_json_encode( $entry->event_repeating_start_dates ) ) . '"' : ''; ?>
								data-entry-id="<?php echo (int) $entry->id; ?>"
								data-start-day="<?php echo (int) $pos - $week_begins; ?>"
								data-days-count="<?php echo (int) $entry->event_length; ?>"
								data-start-date="<?php echo esc_attr( $date ); ?>"
								data-end-date="<?php echo esc_attr( $event_end_date ); ?>"
								<?php echo ! empty( $entry->repeating_period ) ? 'data-repeating-period="' . esc_attr( $entry->repeating_period ) . '"' : ''; ?>
							>
							<?php
						}

						// switch [event_date] to [calendar_date] so it can be replaced on each individual date instead of each entry
						$new_content  = str_replace( array( '[event_date]', '[event_date ' ), array( '[calendar_date]', '[calendar_date ' ), $new_content );

						$this_content = apply_filters(
							'frm_display_entry_content',
							$new_content,
							$entry,
							$shortcodes,
							$view,
							'all',
							'',
							array(
								'event_date'                => $date,
								'event_end_date'            => $event_end_date,
								'calendar_repeating_period' => ! empty( $entry->repeating_period ) ? $entry->repeating_period : null,
							)
						);

						$used_entries[ $entry->id ] = $this_content;
						FrmProContent::replace_entry_position_shortcode( compact( 'entry', 'view' ), compact( 'count' ), $this_content );

						?>
						<div
							class="frmcal-daily-event"
							data-type="<?php echo $entry->is_multiday ? 'multiday' : 'regular'; ?>"
							data-start-date="<?php echo esc_attr( $date ); ?>"
							data-end-date="<?php echo esc_attr( $event_end_date ); ?>"
							<?php echo $popup->get_attr_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<div class="frmcal-hide-on-mobile frmcal-event-content">
								<?php echo FrmProContent::replace_calendar_date_shortcode( $this_content, $date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
						<?php

						if ( $entry->is_multiday ) {
							?>
							</div>
							<?php
						}
					} elseif ( ! $entry->is_multiday ) {
						// switch [event_date] to [calendar_date] so it can be replaced on each individual date instead of each entry
						$new_content               = str_replace( array( '[event_date]', '[event_date ' ), array( '[calendar_date]', '[calendar_date ' ), $new_content );
						$classname_hide_on_desktop = ! empty( $entry->repeating_period ) && ! $entry->is_multiday ? '' : 'frmcal-hide-on-desktop';

						$this_content = apply_filters(
							'frm_display_entry_content',
							$new_content,
							$entry,
							$shortcodes,
							$view,
							'all',
							'',
							array(
								'event_date'                => $date,
								'event_end_date'            => $event_end_date,
								'calendar_repeating_period' => ! empty( $entry->repeating_period ) ? $entry->repeating_period : null,
							)
						);

						$used_entries[ $entry->id ] = $this_content;
						FrmProContent::replace_entry_position_shortcode( compact( 'entry', 'view' ), compact( 'count' ), $this_content );

						?>
						<div
							class="frmcal-daily-event <?php echo esc_attr( $classname_hide_on_desktop ); ?>"
							<?php echo $popup->get_attr_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							data-type="regular"
							data-start-date="<?php echo esc_attr( $date ); ?>"
							data-end-date="<?php echo esc_attr( $event_end_date ); ?>">
							<div class="frmcal-hide-on-mobile frmcal-event-content">
								<?php echo FrmProContent::replace_calendar_date_shortcode( $this_content, $date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
						<?php
					}

					unset( $this_content );
				}

				do_action( 'frm_after_day_content', $pass_atts );
			}
			?>
			</div>
		</div>
		<?php

		if ( $pos == $week_ends ) {
			$end_tr = true;
			echo "</div>\n";
		}
	}

	++$pos;
	if ( 7 === $pos ) {
		$pos = 0;
	}

	if ( $pos !== $week_begins ) {
		$next_month_day = 1;

		if ( $pos > $week_begins ) {
			$week_begins = $week_begins + 7;
		}

		for ( $e = $pos; $e < $week_begins; $e++ ) {
			$day_class = 'frmcal-day';
			++$last_entry_position;
			if ( 6 == $e || 7 == $e ) {
				$day_class .= ' frmcal-week-end';
			}

			?>
			<div class="<?php echo esc_attr( $day_class ); ?>">
				<div class="frmcal_date"><div class="frmcal_num frm-inactive"><?php echo (int) $next_month_day; ?></div></div>
				<div class="frmcal-content">
				<?php
				if ( ! empty( $daily_entries[ $last_entry_position ] ) ) {

					$pass_atts = array(
						'event_date' => $date,
						'day_count'  => count( $daily_entries[ $last_entry_position ] ),
						'view'       => $view,
					);

					do_action( 'frm_before_day_content', $pass_atts );

					$count = 0;
					foreach ( $daily_entries[ $last_entry_position ] as $entry ) {
						++$count;
						$event_end_date = gmdate( 'Y-m-d', strtotime( $date . ' + ' . ( $entry->event_length - 1 ) . ' days' ) );
						$popup          = new FrmViewsCalendarPopupHelper(
							$view,
							$entry
						);

						if ( ! $entry->is_multiday ) {
							// switch [event_date] to [calendar_date] so it can be replaced on each individual date instead of each entry
							$new_content = str_replace( array( '[event_date]', '[event_date ' ), array( '[calendar_date]', '[calendar_date ' ), $new_content );

							$this_content = apply_filters(
								'frm_display_entry_content',
								$new_content,
								$entry,
								$shortcodes,
								$view,
								'all',
								'',
								array(
									'event_date'                => $date,
									'event_end_date'            => $event_end_date,
									'calendar_repeating_period' => ! empty( $entry->repeating_period ) ? $entry->repeating_period : null,
								)
							);

							$used_entries[ $entry->id ] = $this_content;
							FrmProContent::replace_entry_position_shortcode( compact( 'entry', 'view' ), compact( 'count' ), $this_content );

							?>
							<div
								class="frmcal-daily-event"
								<?php echo $popup->get_attr_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								data-type="regular"
								data-start-date="<?php echo esc_attr( $date ); ?>"
								data-end-date="<?php echo esc_attr( $event_end_date ); ?>">
								<div class="frmcal-hide-on-mobile frmcal-event-content">
									<?php echo FrmProContent::replace_calendar_date_shortcode( $this_content, $date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							</div>
							<?php
						}

						unset( $this_content );
					}

					do_action( 'frm_after_day_content', $pass_atts );
				}

				++$next_month_day;
				?>
				</div>
			</div>
			<?php
		}
	}

	if ( ! $end_tr ) {
		echo '</div>';
	}
	?>
</div>

</div><!-- .frmcal main wrapper closing tag -->
