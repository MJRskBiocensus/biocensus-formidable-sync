<?php
/**
 * View for date calculation shortcodes modal
 *
 * @package frm-dates
 * @since 2.0
 *
 * @var array $field Field array.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

FrmAppHelper::show_search_box(
	array(
		'input_id'    => 'frm_dates_smart_tags',
		'placeholder' => __( 'Search Smart Tags', 'frmdates' ),
		'tosearch'    => 'search-smart-tags',
	)
);
?>
<div id="frm-dates-dynamic-values" data-fid="<?php echo esc_attr( $field['id'] ); ?>">
	<ul class="frm_code_list frm-full-hover frm-short-list">
		<?php
		$sc_tags = array(
			'date'               => __( 'Current Date', 'frmdates' ),
			'get param=whatever' => array(
				'label' => __( 'GET/POST', 'frmdates' ),
				'title' => __( 'A variable from the URL or value posted from previous page.', 'frmdates' ) . ' ' . __( 'Replace \'whatever\' with the parameter name. In url.com?product=form, the variable is \'product\'. You would use [get param=product] in your field.', 'frmdates' ),
			),
		);

		foreach ( $sc_tags as $sc_tag => $label ) {
			$sc_title = '';
			if ( is_array( $label ) ) {
				$sc_title = isset( $label['title'] ) ? $label['title'] : '';
				$label    = isset( $label['label'] ) ? $label['label'] : reset( $label );
			}

			?>
			<li class="search-smart-tags" data-code="<?php echo esc_attr( $sc_tag ); ?>">
				<a href="javascript:void(0)" data-code="<?php echo esc_attr( $sc_tag ); ?>" class="show_dyn_default_value frm_insert_code
						<?php
						if ( ! empty( $sc_title ) ) {
							echo ' frm_help" title="' . esc_attr( $sc_title );
						}
						?>
					">
					<?php echo esc_html( $label ); ?>
					<span>[<?php echo esc_html( $sc_tag ); ?>]</span>
				</a>
			</li>
			<?php
		}
		?>
	</ul>
	<p class="howto">
		<?php esc_html_e( 'Click smart value to dynamically populate this field. Smart values are not used when editing entries.', 'frmdates' ); ?>
	</p>
</div>
<?php
unset( $sc_tags, $sc_tag, $label, $sc_title );
