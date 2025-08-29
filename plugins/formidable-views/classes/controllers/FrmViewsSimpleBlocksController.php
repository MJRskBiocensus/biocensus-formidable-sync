<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmViewsSimpleBlocksController {

	/**
	 * Register Gutenberg block assets
	 *
	 * @since 5.6
	 *
	 * @return void
	 */
	public static function block_editor_assets() {

		$version = FrmViewsAppHelper::plugin_version();

		wp_register_script(
			'formidable-view-selector',
			FrmViewsAppHelper::plugin_url() . '/js/frm_blocks.js',
			array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor' ),
			$version,
			true
		);

		$script_vars = array(
			'views'        => self::get_views_options(),
			'show_counts'  => FrmViewsDisplaysHelper::get_show_counts(),
			'view_options' => FrmViewsDisplaysHelper::get_frm_options_for_views( 'limit' ),
			'name'         => FrmAppHelper::get_menu_name() . ' ' . __( 'Views', 'formidable-views' ),
		);

		wp_localize_script( 'formidable-view-selector', 'formidable_view_selector', $script_vars );
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'formidable-view-selector', 'formidable-views', FrmViewsAppHelper::plugin_path() . '/languages' );
		}

		FrmViewsCalendarHelper::load_calendar_frontend_scripts();
	}

	/**
	 * Returns an array of Views options with name as the label and the id as the value, sorted by label
	 *
	 * @return array
	 */
	public static function get_views_options() {
		$views_options = FrmViewsDisplaysHelper::get_views_options();

		$output = array();
		foreach ( $views_options as $post_id => $post_title ) {
			$output[] = array(
				'label' => $post_title,
				'value' => $post_id,
			);
		}

		return $output;
	}

	private static function get_forms( $ids ) {
		$forms = FrmForm::getAll(
			array(
				'is_template' => 0,
				'status'      => 'published',
				'id'          => $ids,
			),
			'name'
		);
		return self::set_form_options( $forms );
	}

	/**
	 * Returns an array for a form with name as label and id as value
	 *
	 * @param array $forms
	 * @return array
	 */
	private static function set_form_options( $forms ) {
		$list   = array();
		$parent = array();
		foreach ( $forms as $form ) {
			if ( ! empty( $form->parent_form_id ) ) {
				$parent[] = $form->parent_form_id;
			} else {
				$list[ $form->id ] = array(
					'label' => $form->name,
					'value' => $form->id,
				);
			}
		}

		if ( $parent ) {
			$parent = array_diff( $parent, array_keys( $list ) );
			if ( $parent ) {
				$parents = self::get_forms( $parent );
				$list   += $parents;
			}
		}

		$list = array_values( $list );
		return $list;
	}

	/**
	 * Registers simple View block
	 */
	public static function register_simple_view_block() {
		if ( ! is_callable( 'register_block_type' ) ) {
			return;
		}

		$block_type = 'formidable/simple-view';
		$registry   = WP_Block_Type_Registry::get_instance();
		$registered = $registry->get_registered( $block_type );

		if ( ! $registered ) {
			register_block_type(
				$block_type,
				array(
					'attributes'      => array(
						'viewId'          => array(
							'type' => 'string',
						),
						'filter'          => array(
							'type'    => 'string',
							'default' => 'limited',
						),
						'useDefaultLimit' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'className'       => array(
							'type' => 'string',
						),
						'calendarViews'   => array(
							'type' => 'object',
						),
						'align'           => array(
							'type'    => 'string',
							'default' => 'wide',
						),
					),
					'editor_style'    => 'formidable',
					'editor_script'   => 'formidable-view-selector',
					'render_callback' => 'FrmViewsSimpleBlocksController::simple_view_render',
				)
			);
		}
	}

	/**
	 * Renders a View given the specified attributes.
	 *
	 * @param array $attributes
	 * @return string
	 */
	public static function simple_view_render( $attributes ) {
		if ( ! isset( $attributes['viewId'] ) ) {
			return '';
		}

		$params = array_filter( $attributes );

		// Since array filter removes the "None" align setting, we need to add it back in.
		// Otherwise the default "full" is used instead.
		if ( ! isset( $params['align'] ) && isset( $attributes['align'] ) ) {
			$params['align'] = $attributes['align'];
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$in_editor = strrpos( $_SERVER['REQUEST_URI'], 'context=edit' );

		if ( $in_editor && ! empty( $params['useDefaultLimit'] ) ) {
			$params['limit'] = 20;
		}
		unset( $params['useDefaultLimit'] );

		$params['id'] = $params['viewId'];
		unset( $params['viewId'] );

		return FrmViewsDisplaysController::get_shortcode( $params );
	}
}
