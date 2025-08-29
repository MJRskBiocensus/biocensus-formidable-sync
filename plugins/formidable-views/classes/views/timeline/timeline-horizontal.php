<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * @package Formidable Visual Views
 * @var FrmViewsTimelineRendererHelper $this The renderer object that includes this template.
 */

?>
<div 
	class="<?php echo esc_attr( $this->get_wrapper_classname() ); ?> frm-timeline-view--horizontal"
	data-event-marker-type="<?php echo esc_attr( FrmViewsTimelineController::get_marker_type() ); ?>"
	data-divider="<?php echo esc_attr( FrmViewsTimelineController::has_dividers() ); ?>">
	<div class="frm-timeline-view-container">
		<div class="frm-timeline-view--tracker">
			<div class="frm-timeline-view--tracker-line"></div>
		</div>
		<div class="frm-timeline-view--content">
			<?php $this->get_entry_template_list(); ?>
		</div>
	</div>
	<div class="frm-timeline-view--detail-popup"></div>
</div>
