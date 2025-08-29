<?php
/**
 * @package FormidableUserFlow
 * @since 2.0
 *
 * @var array  $data The user journey info saved with the entry.
 * @var string $date_format The WP date format setting.
 * @var string $time_format The WP time format setting.
 * @var string $sep The separator to use between values.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<div class="postbox">
	<h3 class="hndle"><?php esc_html_e( 'User Flow', 'formidable-usr-trk' ); ?></h3>
	<table class="frm-alt-table frm-user-flow">
		<?php
		$total_steps = 0;
		$total_time  = 0;
		$date        = '';
		foreach ( $data['user_journey'] as $key => $info ) {
			$duration    = 1;
			if ( 'keywords' === $key ) {
				$info = array(
					'keywords' => true,
					'summary'  => implode( ', ', $info ),
				);
				$key = 0;
			} elseif ( ! is_numeric( $key ) ) {
				$total_steps ++;
				$page_date = FrmAppHelper::get_localized_date( $date_format, $key );
				if ( $date !== $page_date ) {
					$date = $page_date;
					?>
			<tr class="frm-usr-trk-date-row">
				<td colspan="3">
					<?php echo esc_html( $date ); ?>
					<a href="#" class="frm-usr-trk-toggle-date" aria-expanded="true" data-open="<?php esc_attr_e( 'Open', 'formidable-usr-trk' ); ?>" data-close="<?php esc_attr_e( 'Close', 'formidable-usr-trk' ); ?>">
						<?php
						if ( FrmAppHelper::pro_is_installed() ) {
							FrmProAppHelper::get_svg_icon( 'frm_arrowdown6_icon', 'frm-usr-trk-toggle-icon frmsvg', array( 'echo' => true ) );
						}
						?>
						<span><?php esc_html_e( 'Close', 'formidable-usr-trk' ); ?></span>
					</a>
				</td>
			</tr>
					<?php
				}
			}

			// Used for reverse compatability set up in convert_journey_string_to_array().
			if ( ! empty( $info['summary'] ) ) {
				?>
				<tr class="frm-child-row">
				<td>
					<?php
					if ( $key ) {
						echo esc_html( FrmAppHelper::get_localized_date( $time_format, $key ) );
					}
					?>
				</td>
				<td>
					<?php
					if ( ! empty( $info['keywords'] ) ) {
						esc_html_e( 'Keywords', 'formidable-usr-trk' );
						echo esc_html( $sep );
					} elseif ( ! empty( $info['referer'] ) ) {
						esc_html_e( 'Referrer', 'formidable-usr-trk' );
						echo esc_html( $sep );
					}
					echo esc_html( $info['summary'] );
					?>
				</td>
				<td></td>
				<?php
				continue;
			}

			if ( ! is_array( $info ) || ( ! isset( $info['url'] ) && empty( $info['referer'] ) ) ) {
				continue;
			}

			?>
			<tr class="frm-child-row">
				<td>
					<?php echo esc_html( FrmAppHelper::get_localized_date( $time_format, $key ) ); ?>
				</td>
				<td>
					<?php
					$url = '';
					if ( ! empty( $info['referer'] ) ) {
						$total_steps --;
						esc_html_e( 'Referrer', 'formidable-usr-trk' );
						echo esc_html( $sep );
						echo wp_kses_post( make_clickable( esc_html( $info['referer'] ) ) );
					} else {
						if ( isset( $info['relative_url'] ) ) {
							$url = '/' . $info['relative_url'];
						}
						if ( ! empty( $info['title'] ) ) {
							if ( isset( $info['entry'] ) ) {
								// For backward compatibility, remove "form submitted" text in the original language when the entry was submitted from title.
								$info['title'] = preg_replace( '/ form submitted$/', '', $info['title'] );
								/* translators: %s: The form name */
								echo esc_html( sprintf( __( '%s form submitted', 'formidable-usr-trk' ), $info['title'] ) . $sep );
							} else {
								echo esc_html( $info['title'] . $sep );
							}
						}
						?>
						<a target="_blank" href="<?php echo esc_attr( $info['url'] ); ?>" >
						<?php
						echo esc_html( $url ) . ' ';
						FrmAppHelper::icon_by_class( 'frmfont frm_external_link_icon' );
						?>
						</a>
						<?php
					}
					?>
				</td>
				<td>
					<?php
					if ( isset( $info['duration'] ) ) {
						$duration    = (int) $info['duration'];
						$total_time += $duration;
					}

					if ( isset( $info['entry'] ) && $duration > 1 ) {
						// This was a form submitted so show how long it took.
						echo '(' . esc_html( FrmUsrTrkAppHelper::get_readable_duration( $total_time ) ) . ')';
					} elseif ( isset( $info['duration'] ) && empty( $info['referer'] ) ) {
						echo esc_html( FrmUsrTrkAppHelper::get_readable_duration( $duration ) );
					}
					?>
				</td>
			</tr>
			<?php
		}
		?>
		<tr class="frm-child-row">
			<td></td>
			<td colspan="2">
				<?php
				echo esc_html(
					FrmUsrTrkAppHelper::get_journey_summary( $total_steps, $total_time )
				);
				?>
			</td>
		</tr>
	</table>
</div>
