<?php
/**
 * Blocks controller
 *
 * @package FrmCharts
 */

/**
 * Class FrmChartsBlocksController
 */
class FrmChartsBlocksController {

	/**
	 * Registers block.
	 */
	public static function register() {
		register_block_type(
			FrmChartsAppHelper::plugin_path() . '/blocks/graph',
			array(
				'render_callback' => array( 'FrmChartsGraphController', 'render' ),
			)
		);
	}
}
