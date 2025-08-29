( function() {
	'use strict';

	if ( 'undefined' === typeof frmViewsDom || 'undefined' === typeof wp ) {
		return;
	}

	const { __ } = wp.i18n;
	const { collapsible, div, tag, select, option } = frmViewsDom;

	let dataProcessor;

	wp.hooks.addFilter(
		'frm_views_map_settings',
		'formidable-geo',
		function( _, data ) {
			dataProcessor = data.dataProcessor;
			return collapsible(
				__( 'Map Settings', 'formidable-views' ),
				div({
					class: 'frm_grid_container',
					children: [
						div({
							class: 'frm_form_field',
							children: [
								tag(
									'label',
									{
										text: __( 'Address Fields', 'formidable-views' )
									}
								),
								dataProcessor.placeholder( 'mapAddressFields', getMapAddressFieldsDropdown, 'none' )
							]
						})
					]
				}),
				{ open: true }
			);
		}
	);

	wp.hooks.addFilter(
		'frm_views_editor_box_preview_payload',
		'formidable-geo',
		function( payload, dataProcessor ) {
			payload.mapAddressFields = dataProcessor.data.mapAddressFields;
			return payload;
		}
	);

	document.addEventListener(
		'frmGeoAddedMapMarker',
		/**
		 * Pop up the info window for the first marker automatically after the preview.
		 *
		 * @param {Event} event
		 */
		event => {
			const { map, marker, markerContent, i } = event.frmData;
			if ( 0 !== i || '' === markerContent ) {
				return;
			}
			const infowindow = new google.maps.InfoWindow({ content: markerContent });
			infowindow.open( map, marker );
		}
	);

	/**
	 * @param {Array} mapAddressFields
	 * @returns {HTMLElement}
	 */
	function getMapAddressFieldsDropdown( mapAddressFields ) {
		const options = Object.entries( frmViewsEditorInfo.addressFieldOptions ).map(
			([ value, label ]) => {
				return option( value, label, false );
			}
		);
		options.unshift( option( '', '', false ) );
		const dropdown = select(
			options,
			{
				id: 'frm_map_address_fields',
				onchange: handleMapAddressFieldChange
			}
		);
		dropdown.setAttribute( 'multiple', 'multiple' );

		jQuery( dropdown ).val( mapAddressFields );

		setTimeout(
			function() {
				dropdown.style.display = 'none';
				frmDom.bootstrap.multiselect.init.bind( dropdown )();
				frmViewsDom.unselectBlankOptionOnMultiselectChange( dropdown );
			},
			0
		);

		return dropdown;
	}

	function handleMapAddressFieldChange() {
		// Unselect the empty placeholder option.
		jQuery( this ).val(
			jQuery( this ).val().filter(
				function( value ) {
					return '' !== value;
				}
			)
		);
		dataProcessor.data.mapAddressFields = jQuery( this ).val();

		triggerCustomEvent( 'frmViewsEditorUnsavedChange' );
		triggerCustomEvent( 'frmViewsEditorRefresh' );
	}

	function triggerCustomEvent( eventName ) {
		const event = new CustomEvent( eventName );
		document.dispatchEvent( event );
	}
}() );
