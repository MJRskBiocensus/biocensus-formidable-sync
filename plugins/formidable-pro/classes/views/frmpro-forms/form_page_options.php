<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

FrmProFormsHelper::array_to_hidden_inputs(
	array(
		'rootline'             => $values['rootline'],
		'pagination_position'  => $values['pagination_position'],
		'rootline_titles'      => $values['rootline_titles'],
		'rootline_titles_on'   => $values['rootline_titles_on'],
		'rootline_numbers_off' => $values['rootline_numbers_off'],
		'rootline_lines_off'   => $values['rootline_lines_off'],
	),
	'options'
);
