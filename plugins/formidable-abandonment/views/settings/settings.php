<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Formidable abandonment settings.
 *
 * @var array $values Settings data.
 *
 * @package formidable-abandonment
 */
?>
<p class="howto">
	<?php esc_html_e( 'Capture partial entries and optionally reclaim them.', 'formidable-abandonment' ); ?>
</p>
<div class="frm_grid_container">
	<p class="frm-abandoned-enable">
	<?php
		FrmHtmlHelper::toggle(
			'frm_abandonment_enable',
			'options[enable_abandonment]',
			array(
				'checked'       => ! empty( $values['enable_abandonment'] ),
				'on_label'      => __( 'Turn on form abandonment', 'formidable-abandonment' ),
				'value'         => 1,
				'show_labels'   => true,
				'echo'          => true,
				'input_html'    => array( 'data-toggleclass' => 'abandoned-extra-options' ),
			)
		);
		?>
	</p>
	<div class="abandoned-extra-options frm_grid_container<?php echo empty( $values['enable_abandonment'] ) ? esc_attr( ' frm_hidden' ) : ''; ?>">
		<p>
		<?php
			FrmHtmlHelper::toggle(
				'frm_abandon_email_required',
				'options[abandon_email_required]',
				array(
					'checked'       => ! empty( $values['abandon_email_required'] ),
					'on_label'      => __( 'Require an email address or phone number before saving', 'formidable-abandonment' ),
					'value'         => 1,
					'show_labels'   => true,
					'echo'          => true,
				)
			);
			?>
		</p>
		<p>
			<a href="javascript:void(0)" class="button frm-button-secondary" id="abandoned-email-action" >
				<?php esc_html_e( 'Create a form recovery email', 'formidable-abandonment' ); ?>
			</a>
		</p>
	</div>
</div>
