<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * @package Formidable Visual Views
 * @var FrmViewsTimelineRendererHelper $this The renderer object that includes this template.
 * @var bool                           $has_entry_link
 * @var bool                           $clickable
 */
?>
<div class="frm-timeline-view--item" data-even="true" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
	<div class="frm-timeline-view--content-col frm-timeline-view--animate-second">
		<div class="frm-timeline-view--content-box<?php echo ! empty( $clickable ) ? ' frm-clickable' : ''; ?>">
			<?php if ( ! empty( $has_entry_link ) ) : ?>
				<a class="frm-timeline-view--entry-link" href="<?php echo esc_url( $entry['detail_link'] ); ?>"></a>
			<?php endif; ?>
			<?php
			foreach ( FrmViewsTimelineController::$card_content_order as $content_type ) :
				switch ( $content_type ) :
					case 'thumbnail':
						if ( ! empty( $entry['thumbnail'] ) && 'life-events' !== FrmViewsTimelineController::$template_styles['theme'] ) :
							?>
							<img src="<?php echo esc_url( $entry['thumbnail'] ); ?>" alt="<?php echo esc_attr( $entry['title'] ); ?>" />
							<?php
						endif;
						break;
					case 'title':
						?>
						<h4><?php echo esc_html( $entry['title'] ); ?></h4>
						<?php
						break;
					case 'date':
						if ( 'inside' === FrmViewsTimelineController::$template_styles['date_position'] && 'life-events' !== FrmViewsTimelineController::$template_styles['theme'] ) :
							?>
							<h5><?php echo esc_html( $entry['date'] ); ?></h5>
							<?php
						endif;
						break;
					case 'description':
						if ( empty( $entry['description'] ) || 1 === (int) FrmViewsTimelineController::$settings['hide_description'] ) {
							break;
						}
						?>
						<div class="frm-timeline-view--content-item-description">
							<?php echo wp_kses_post( $entry['description'] ); ?>
						</div>
						<?php
						break;
					case 'default_content':
						if ( empty( $entry['default_content'] ) ) {
							break;
						}
						?>
						<div class="frm-timeline-view--content-item-default-content">
							<?php echo wp_kses_post( $entry['default_content'] ); ?>
						</div>
						<?php
						break;
				endswitch;
			endforeach;
			?>
		</div>
	</div>
	<div class="frm-timeline-view--content-col">
		<span class="<?php echo esc_attr( $this->get_marker_classname() ); ?>">
			<?php if ( 'dots' === FrmViewsTimelineController::get_marker_type() ) : ?>
				<span class="frm-timeline-view--marker-dot"><span class="frm-timeline-view--marker-dot-icon"></span></span>
			<?php else : ?>
				<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="20" cy="20" r="19" fill="white" stroke="<?php echo esc_attr( FrmViewsTimelineController::$template_styles['line_color'] ); ?>" stroke-width="2"/>
				</svg>
				<svg class="frm-timeline-view--marker-svg-active" width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="20" cy="20" r="19" fill="white" stroke="<?php echo esc_attr( FrmViewsTimelineController::$template_styles['marker_color'] ); ?>" stroke-width="2"/>
				</svg>
				<span class="frm-timeline-view--marker-icon">
					<?php if ( 'numbers' === FrmViewsTimelineController::get_marker_type() ) : ?>
						<b><?php echo (int) str_pad( $index + 1, 2, '0', STR_PAD_LEFT ); ?></b>
					<?php else : ?>
						<?php if ( ! empty( $entry['marker_icon'] ) ) : ?>
							<img src="<?php echo esc_url( $entry['marker_icon'] ); ?>" alt="<?php echo esc_attr( $entry['title'] ); ?>" />
						<?php endif; ?>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</span>
	</div>
	<div class="frm-timeline-view--content-col frm-timeline-view--animate-first">
		<?php if ( 'life-events' === FrmViewsTimelineController::$template_styles['theme'] ) : ?>
			<div class="frm-timeline-view--image-box">
				<span class="frm-timeline-view--image-box-pin" style="background: url( <?php echo esc_url( FrmViewsAppHelper::plugin_url() . '/images/timeline/life-events-pin.png' ); ?> ) no-repeat">&nbsp;</span>
				<?php if ( ! empty( $entry['thumbnail'] ) ) : ?>
					<img src="<?php echo esc_url( $entry['thumbnail'] ); ?>" alt="<?php echo esc_attr( $entry['title'] ); ?>" />
				<?php endif; ?>
				<h3><?php echo esc_html( $entry['date'] ); ?></h3>
			</div>
		<?php else : ?>
			<?php if ( 'outside' === FrmViewsTimelineController::$template_styles['date_position'] ) : ?>
				<h3><?php echo esc_html( $entry['date'] ); ?></h3>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>