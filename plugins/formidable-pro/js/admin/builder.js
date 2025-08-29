( function() {

	/** globals wp */

	const hooks = wp.hooks;
	const hookNamespace = 'formidable-pro';

	const STEP_UNIT_SECOND = 'sec';

	const STEP_UNIT_MILLISECOND = 'millisec';

	function addEventListeners() {
		document.addEventListener( 'frm_logic_options_loaded', handleLogicOptionsLoaded );
		document.addEventListener( 'change', handleChangeEvent );
		document.addEventListener( 'frm_added_field', onFieldAdded );
		document.addEventListener( 'DOMContentLoaded', domReady );
	}

	hooks.addFilter( 'frm_conditional_logic_field_options', hookNamespace, updateFieldOptions );

	hooks.addAction( 'frm_after_delete_field', 'formidable-pro', function( fieldLi ) {
		const fieldID = getFieldIDFromHTMLID( fieldLi.id );
		document.querySelector( `a[data-code="${fieldID}"]` )?.closest( '.frm-customize-list' )?.remove();
	});

	hooks.addAction( 'frm_before_delete_field_option', hookNamespace, deleteConditionalLogicOptions );

	hooks.addAction( 'frmShowedFieldSettings', hookNamespace, onShowedFieldSettings );

	/**
	 * Performs necessary adjustments when field settings are shown.
	 *
	 * @param {HTMLElement} showBtn The button used to show the settings.
	 * @param {HTMLElement} fieldSettingsEl The settings container element.
	 */
	function onShowedFieldSettings( showBtn, fieldSettingsEl ) {
		adjustDefaultValueSelect( fieldSettingsEl );
		maybeUpdateFormatInputValue( fieldSettingsEl );
		maybeInitConditionalLogicDatepicker( fieldSettingsEl );
	}

	/**
	 * Adjusts default value dropdown icon position in the settings UI.
	 *
	 * @param {HTMLElement} fieldSettingsEl The settings container element.
	 */
	function adjustDefaultValueSelect( fieldSettingsEl ) {
		const defaultValueDropdownEl = fieldSettingsEl.querySelector( '.frm-default-value-select' );
		if ( ! defaultValueDropdownEl ) {
			return;
		}

		const wrapper = defaultValueDropdownEl.closest( '.frm-with-right-icon' );
		const defaultValueInput = wrapper.querySelector( 'input.default-value-field' );
		const moreSvgEl = wrapper.querySelector( '.frm_more_horiz_solid_icon' );

		// Adjust the position of the three-dot SVG icon to align with the default value input
		const offset = defaultValueInput.getBoundingClientRect().top - wrapper.getBoundingClientRect().top;
		moreSvgEl.style.top = `${ offset }px`;

		const isCustom = 'frm-toggle-custom' === defaultValueDropdownEl.value;

		// Toggle visibility based on dropdown value
		defaultValueInput.classList.toggle( 'frm_hidden', ! isCustom );
		moreSvgEl.classList.toggle( 'frm_hidden', ! isCustom );
	}

	/**
	 * Checks if the given format is a valid currency format.
	 *
	 * @param {string} formatValue The format value to check.
	 * @return {boolean}
	 */
	const isCurrencyFormat = formatValue => Boolean( formatValue ) && [ 'currency', 'number' ].includes( formatValue );

	/**
	 * Updates the format input value if necessary, based on the selected format in the dropdown.
	 *
	 * @param {HTMLElement} fieldSettingsEl The settings container element.
	 */
	function maybeUpdateFormatInputValue( fieldSettingsEl ) {
		const formatDropdownEl = fieldSettingsEl.querySelector( '.frm_format_dropdown' );

		if ( ! formatDropdownEl ) {
			return;
		}

		const formatValue = formatDropdownEl.value;
		const fieldId = fieldSettingsEl.dataset.fid;

		if ( isCurrencyFormat( formatValue ) ) {
			const formatNumberSection = document.getElementById( `frm-field-format-currency-${fieldId}` );
			formatNumberSection.classList.remove( 'frm_hidden' );

			if ( 'currency' === formatValue ) {
				const useGlobalCurrency = document.getElementById( `frm_use_global_currency_${fieldId}` );
				useGlobalCurrency.closest( '.frm_form_field' ).classList.remove( 'frm_hidden' );
				if ( useGlobalCurrency.checked ) {
					formatNumberSection.classList.add( 'frm_hidden' );
				}
			}

			// This logic ensures backward compatibility for older users who used currency checkboxes.
			// TODO: This workaround should be removed in the future once the legacy support is no longer required.
			const formatInput = document.getElementById( `frm_format_${fieldId}` );

			if ( ! isCurrencyFormat( formatInput.value ) ) {
				formatInput.setAttribute( 'value', formatValue );
			}
		}
	}

	function maybeInitConditionalLogicDatepicker( fieldSettingsEl ) {
		const settings = fieldSettingsEl.querySelectorAll( '[name^="field_options[hide_field_"]' );
		settings.forEach(
			setting => {
				if ( '' === setting.value ) {
					return;
				}

				const field = document.getElementById( 'field_' + setting.value + '_inner_container' );
				if ( ! field ) {
					return;
				}

				const optContainer = field.querySelector( '.frm_form_fields.frm_opt_container' );
				if ( ! optContainer ) {
					return;
				}

				const fieldId   = setting.name.replace( 'field_options[hide_field_', '' ).replace( ']', '' ).replace( '[]', '' );
				const fieldType = optContainer.dataset.ftype;

				if ( 'date' === fieldType ) {
					initConditionalLogicDatepicker(
						setting.closest( '.frm_logic_row' ).querySelector( '[name="field_options[hide_opt_' + fieldId + '][]"]' )
					);
				}
			}
		);
	}

	const onFieldAdded = event => {
		PageBreakField.onAddedField( event );
		maybeAddFieldToFieldShortcodes( event );
	};

	/**
	 * Handles logic options loaded event.
	 *
	 * @param {Event} event Event object.
	 * @return {void}
	 */
	const handleLogicOptionsLoaded = event => {
		if ( 'date' !== event.frmData.valueFieldType ) {
			// We only need to handle date fields for now.
			return;
		}

		const fieldID    = event.frmData.fieldID;
		const metaKey    = event.frmData.metaKey;
		const dateFields = document.querySelectorAll( `#frm_logic_${fieldID}_${metaKey} [name="field_options[hide_opt_${fieldID}][]"]` );
		dateFields.forEach( initConditionalLogicDatepicker );
	};

	const initConditionalLogicDatepicker = field => {
		jQuery( field ).datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'yy-mm-dd',
			beforeShow: function() {
				document.getElementById( 'ui-datepicker-div' )?.classList.add( 'frm-datepicker' );
			}
		});
	};

	/**
	 * Executes when the DOM is fully loaded.
	 */
	function domReady() {
		applyNumberFormattingInPreview();
	}

	/**
	 * Applies number formatting in the preview.
	 */
	function applyNumberFormattingInPreview() {
		const formatDropdowns = document.querySelectorAll( '.frm_format_dropdown' );

		formatDropdowns.forEach( formatDropdown => {
			if ( isCurrencyFormat( formatDropdown.value ) ) {
				formatFieldPreviewNumbers( formatDropdown.dataset.fieldId, formatDropdown.value );
			}
		});
	}

	const maybeAddFieldToFieldShortcodes = params => {
		if ( [ 'data', 'divider', 'end_divider', 'captcha', 'break', 'html', 'form', 'summary' ].includes( params.frmType ) ) {
			return;
		}
		const insertCodeID  = createFieldsShortcodeRowLink( 'id', params );
		const insertCodeKey = createFieldsShortcodeRowLink( 'key', params );
		const shortcodeLink = frmDom.tag( 'li', {
			className: 'frm-customize-list dropdown-item show_frm_not_email_to',
			children: [
				insertCodeID,
				insertCodeKey,
			],
		});
		document.querySelector( '#frm-insert-fields-box .frm_code_list' )?.insertAdjacentElement( 'beforeend', shortcodeLink );
	};

	/**
	 * Returns the field id from html id.
	 *
	 * @param {string} htmlID
	 * @returns {string}
	 */
	const getFieldIDFromHTMLID = htmlID => htmlID.replace( 'frm_field_id_', '' );

	/**
	 * Creates <li> elements for the new field to be inserted in the shortcodes popup.
	 *
	 * @param {string} type
	 * @param {Object} params
	 * @returns {HTMLElement}
	 */
	const createFieldsShortcodeRowLink = ( type, params ) => {
		const fieldID   = getFieldIDFromHTMLID( params.frmField.id );
		const fieldKey  = document.getElementById( `field_options_field_key_${fieldID}` )?.value;
		const isIDLink  = type === 'id';
		const shortcode = isIDLink ? fieldID : fieldKey;
		const link = frmDom.a({
			className: ( isIDLink ? 'frmids ' : 'frmkeys ' ) + 'frm_insert_code',
			children: [
				document.querySelector( `.frm_t${params.frmType} .frmsvg` ).cloneNode( true ),
				document.getElementById( `frm_name_${fieldID}` ).value,
				frmDom.span( `[${shortcode}]` ),
			]
		});
		const idsTabIsActiveInShortcodesModal = document.querySelector( '#frm-insert-fields-box .subsubsub .frmids' )?.classList.contains( 'current' );
		if ( isIDLink && ! idsTabIsActiveInShortcodesModal || ! isIDLink && idsTabIsActiveInShortcodesModal ) {
			link.classList.add( 'frm_hidden' );
		}

		link.setAttribute( 'data-code', isIDLink ? fieldID : fieldKey );
		return link;
	};

	function updateFieldOptions( fieldOptions, hookArgs ) {
		if ( 'scale' === hookArgs.type ) {
			fieldOptions = getScaleFieldOptions( hookArgs.fieldId );
		}
		return fieldOptions;
	}

	function getScaleFieldOptions( fieldId ) {
		let opts = [];
		const optVals = document.querySelectorAll( 'input[name^="item_meta[' + fieldId + ']"]' );

		optVals.forEach( opt => {
			opts.push( opt.value );
		});

		return opts;
	}

	function updateConditionalLogicsDependentOnThis( target ) {
		setTimeout( function() {
			let fieldId = target.closest( '.frm-single-settings' ).dataset.fid;

			if ( ! fieldId ) {
				return;
			}

			frmAdminBuild.adjustConditionalLogicOptionOrders( fieldId, 'scale' );
		}, 0 );
	}

	function updateShortcodeTriggerLabel( shortcodeTrigger, value ) {
		const textshortcodeTriggerLabel = shortcodeTrigger.querySelector( 'svg.frmsvg' )?.nextSibling;
		if ( ! textshortcodeTriggerLabel || textshortcodeTriggerLabel.nodeType !== Node.TEXT_NODE ) {
			return;
		}
		textshortcodeTriggerLabel.textContent = value;
	}

	function maybeUpdateFieldsShortcodeModal( labelInput ) {
		const fieldId = labelInput.id.replace( 'frm_name_', '' );
		const fieldShortcodeTrigger = document.querySelector( 'a[data-code="' + fieldId + '"]');
		if ( ! fieldShortcodeTrigger ) {
			return;
		}
		updateShortcodeTriggerLabel( fieldShortcodeTrigger, labelInput.value );
		updateShortcodeTriggerLabel( fieldShortcodeTrigger.nextElementSibling, labelInput.value );
	}

	function handleModalDismiss( input ) {
		const modalDismissers = document.querySelectorAll( '#frm_info_modal .dismiss, #frm_info_modal #frm-info-click, .ui-widget-overlay.ui-front' );
		function onModalClose() {
			input.classList.add( 'frm_invalid_field' );
			setTimeout( () => input.focus(), 0 );
			modalDismissers.forEach( el => {
				el.removeEventListener( 'click', onModalClose );
			});
		}

		modalDismissers.forEach( el => {
			el.addEventListener( 'click', onModalClose );
		});
	}

	function validateProductPriceValue( target ) {
		const price = target.value.trim();
		if ( price.includes( '[' ) && price.includes( ']' ) ) {
			// This is a shortcode and should be assumed a valid price.
			return;
		}
		if ( isNaN( price.replace( /,/, '' ) ) ) {
			const validationFailMessage = wp.i18n.__( 'Please enter a valid number.', 'formidable-pro' );
			frmAdminBuild.infoModal( validationFailMessage );
			handleModalDismiss( target );
			return;
		}
		if ( target.classList.contains( 'frm_invalid_field' ) ) {
			target.classList.remove( 'frm_invalid_field' );
		}
	}

	function validateSizeLimitValue( target ) {
		let validationFailMessage;
		if ( isNaN( target.value.trim() ) ) {
			validationFailMessage = wp.i18n.__( 'Please enter a valid number.', 'formidable-pro' );
		} else {
			const parent  = target.closest( '.frm_grid_container' );
			const minSize = parent.querySelector( '[id^=min_size_]' );
			const maxSize = parent.querySelector( '[id^=size_]' );
			if ( minSize.value && maxSize.value && Number( minSize.value ) > Number( maxSize.value ) ) {
				validationFailMessage = wp.i18n.__( 'Minimum size cannot be greater than maximum size.', 'formidable-pro' );
			} else {
				const otherInput = target.id.startsWith( 'min_size_' ) ? maxSize : minSize;
				if ( ! isNaN( otherInput.value.trim() ) ) {
					otherInput.classList.remove( 'frm_invalid_field' );
				}
			}
		}

		if ( validationFailMessage ) {
			frmAdminBuild.infoModal( validationFailMessage );
			handleModalDismiss( target );
			return;
		}
		if ( target.classList.contains( 'frm_invalid_field' ) ) {
			target.classList.remove( 'frm_invalid_field' );
		}
	}

	function handleChangeEvent( e ) {
		const target = e.target;

		if ( target.matches( '.frm_product_price' ) ) {
			validateProductPriceValue( target );
			return;
		}

		if ( target.id.startsWith( 'min_size' ) || target.id.startsWith( 'size' ) ) {
			validateSizeLimitValue( target );
		}

		if ( target.id.startsWith( 'frm_name_' ) ) {
			maybeUpdateFieldsShortcodeModal( target );
		}

		if ( isRootlineSettingInput( target ) ) {
			Rootline.updateRootline();
			return;
		}

		if ( target.matches( '.frm_page_transition_setting' ) ) {
			PageBreakField.onChangeTransition( e );
			return;
		}

		if ( target.matches( '.frm_scale_opt' ) ) {
			updateConditionalLogicsDependentOnThis( target );
		}

		if ( isAFormatSetting( target ) ) {
			handleFormatChange( target );
			return;
		}

		if ( target.classList.contains( 'frm-global-currency-checkbox' ) ) {
			onUseGlobalCurrencyChange( target );
			return;
		}

		if ( target.classList.contains( 'radio_maxnum' ) ) {
			setStarValues( target );
			return;
		}

		if ( target.classList.contains( 'frm-default-value-select' ) ) {
			handleDefaultValueSelectChange( target );
			return;
		}

		if ( target.classList.contains( 'frm_scale_opt' ) ) {
			setScaleValues( target );
		} else if ( 0 === target.id.indexOf( 'step_unit_' ) ) {
			onChangeStepUnit( e );
		}

		validateTimeFieldRangeValue( target );

		if ( target.id && ( target.id.startsWith( 'lookup_displayed_value_' ) || target.id.startsWith( 'lookup_saved_value_' ) ) ) {
			syncLookupOptionSettings( target );
		}
	}

	/**
	 * Change the saved value / displayed value settings on change events.
	 * We prevent the combination of using "Option Label" as the saved value
	 * in combination with using "Option Value" as the displayed value.
	 *
	 * @param {HTMLElement} changedElement The element that was changed.
	 * @return {void}
	 */
	function syncLookupOptionSettings( changedElement ) {
		const valueSettingChanged   = changedElement.id.startsWith( 'lookup_saved_value_' );
		const fieldId               = changedElement.id.replace( 'lookup_displayed_value_', '' ).replace( 'lookup_saved_value_', '' );
		const savedValueSetting     = document.getElementById( 'lookup_saved_value_' + fieldId );
		const displayedValueSetting = document.getElementById( 'lookup_displayed_value_' + fieldId );

		if ( valueSettingChanged ) {
			if ( changedElement.value === 'label' ) {
				// When the saved value setting changes to label, set the display value setting to label as well.
				displayedValueSetting.value = 'label';
			}
		} else if ( changedElement.value === 'value' ) {
			// When the display setting changes to value, set the saved value setting to value as well.
			savedValueSetting.value = 'value';
		}
	}

	/**
	 * Checks if the given target is a rootline setting input.
	 *
	 * @param {HTMLElement} target Target element.
	 * @return {Boolean}
	 */
	function isRootlineSettingInput( target ) {
		const rootlineSettingIds = [
			'frm-rootline-type',
			'frm-rootline-titles-on',
			'frm-rootline-numbers-off',
			'frm-rootline-lines-off'
		];

		return rootlineSettingIds.includes( target.id ) || target.matches( '.frm-rootline-title-setting input' );
	}

	/**
	 * Determines if the provided element is a format-related setting.
	 *
	 * @param {HTMLElement} element The HTML element to check.
	 * @returns {boolean}
	 */
	function isAFormatSetting( element ) {
		return element.classList.contains( 'frm_format_dropdown' )
			|| element.closest( '.frm_custom_format_options_wrapper' )
			|| element.closest( '.frm_custom_currency_options_wrapper' );
	}

	/**
	 * Handles changes to the format dropdown or related elements.
	 *
	 * @param {HTMLElement} element The HTML element that triggered the format change event.
	 */
	function handleFormatChange( element ) {
		const fieldSettingsEl = element.closest( '.frm-single-settings' );
		const formatDropdownEl = fieldSettingsEl.querySelector( '.frm_format_dropdown' );

		const fieldId = fieldSettingsEl.getAttribute( 'data-fid' );
		const formatValue = formatDropdownEl.value;
		const isSliderField = element.closest( '.frm-type-range' );

		if ( formatDropdownEl === element ) {
			updateFormatSettingsToDefault( fieldSettingsEl, fieldId, formatValue );
			resolveCurrencyFormatDependencies(formatDropdownEl, fieldId, formatValue);
		}

		if ( ! isCurrencyFormat( formatValue ) ) {
			const formatInputEl = document.getElementById( `frm_format_${fieldId}` );
			formatInputEl.setAttribute( 'value', '' );
		}

		const decimalType = [ 'number', 'custom' ].indexOf( formatValue ) !== -1 ? 'text' : 'select';
		if ( decimalType === 'text' ) {
			fieldSettingsEl.querySelector( 'input[name^="field_options[calc_dec_"]' )?.classList.remove( 'frm_hidden' );
			fieldSettingsEl.querySelector( 'select[name^="field_options[custom_decimals_"]' )?.classList.add( 'frm_hidden' );
		} else {
			fieldSettingsEl.querySelector( 'input[name^="field_options[calc_dec_"]' )?.classList.add( 'frm_hidden' );
			fieldSettingsEl.querySelector( 'select[name^="field_options[custom_decimals_"]' )?.classList.remove( 'frm_hidden' );
		}

		if ( 'none' === formatValue ) {
			// Clear the decimals setting when setting to none,
			// so the backward compatibility check doesn't force the number setting.
			const calcInput = fieldSettingsEl.querySelector( 'input[name^="field_options[calc_dec_"]' );
			if ( calcInput ) {
				calcInput.value = '';
			}
		}

		if ( isSliderField ) {
			formatSliderPreviewNumbers( fieldId, formatValue );
		} else {
			formatFieldPreviewNumbers( fieldId, formatValue );
		}
	}

	/**
	 * Resolves conflicts and applies dependencies for currency format settings.
	 *
	 * @param {HTMLElement} formatDropdownEl The format dropdown element.
	 * @param {string} fieldId The field ID.
	 * @param {string} formatValue The selected format value.
	 */
	function resolveCurrencyFormatDependencies( formatDropdownEl, fieldId, formatValue ) {
		formatDropdownEl.querySelector( `option[data-dependency="#frm-field-format-currency-${fieldId}"]` ).removeAttribute( 'data-dependency-skip' );

		if ( 'currency' !== formatValue ) {
			return;
		}

		const useGlobalCurrency = document.getElementById( `frm_use_global_currency_${fieldId}` );

		if ( ! useGlobalCurrency.checked ) {
			formatDropdownEl.querySelector( `option[data-dependency="#frm-field-format-currency-${fieldId}"]` ).setAttribute( 'data-dependency-skip', '' );
			document.getElementById( `frm-field-format-currency-${fieldId}` ).classList.remove( 'frm_hidden' );
		}
	}

	/**
	 * Toggles the currency options visibility based on the use global currency checkbox state.
	 *
	 * @param {HTMLInputElement} element The checkbox element triggering the event.
	 */
	function onUseGlobalCurrencyChange( element ) {
		const fieldSettingsEl = element.closest( '.frm-single-settings' );
		const formatDropdownEl = fieldSettingsEl.querySelector( '.frm_format_dropdown' );
		const fieldId = element.closest( '.frm-single-settings' ).getAttribute( 'data-fid' );

		if ( element.checked ) {
			updateFormatSettingsToDefault( fieldSettingsEl, fieldId, formatDropdownEl.value )
		} else {
			element.nextElementSibling.value = '0';
		}

		document.getElementById( `frm-field-format-currency-${fieldId}` ).classList.toggle( 'frm_hidden', element.checked );
	}

	/**
	 * Update the value of a format field setting.
	 *
	 * @param {HTMLElement} container The fields container.
	 * @param {string} fieldId The ID of the field to update.
	 * @param {HTMLElement} formatValue The Format dropdown value.
	 */
	function updateFormatSettingsToDefault( container, fieldId, formatValue ) {
		const formatSettingKeys = [
			'custom_decimals',
			'custom_decimal_separator',
			'custom_thousand_separator',
			'custom_symbol_left',
			'custom_symbol_right',
		];

		formatSettingKeys.forEach( settingKey => {
			const value = formatValue === 'number' && [ 'custom_symbol_left', 'custom_symbol_right' ].includes( settingKey )
				? ''
				: frmProBuilderVars.currency[ settingKey.replace( 'custom_', '' ) ];

			container.querySelector( '[name^="field_options[' + settingKey + '_"]' ).value = value;
		});

		const calcInput = container.querySelector( 'input[name^="field_options[calc_dec_"]' );
		if ( calcInput ) {
			calcInput.value = '2';
		}

		// Check the "Use Global Currency Settings" checkbox
		const useGlobalCurrency = document.getElementById( `frm_use_global_currency_${fieldId}` );
		useGlobalCurrency.checked = true;
		useGlobalCurrency.nextElementSibling.value = '1';
	}

	/**
	 * Updates the slider field's preview.
	 *
	 * @param {string} fieldId - The unique identifier of the field.
	 */
	function formatSliderPreviewNumbers( fieldId ) {
		const fieldPreview = document.getElementById( 'frm_field_id_' + fieldId );
		const rangeInput = fieldPreview.querySelector( 'input[type="range"]' );

		updateSliderFieldPreview({
			field: rangeInput,
			att: 'value',
			newValue: rangeInput.value
		});
	}

	/**
	 * Formats all numeric substrings in a field's preview.
	 *
	 * If no numeric substrings are found, the original default value is retained.
	 *
	 * @param {string} fieldId     The unique identifier of the field.
	 * @param {string} formatValue The desired format (e.g., currency, decimal places).
	 */
	function formatFieldPreviewNumbers( fieldId, formatValue ) {
		const fieldSettingsEl = document.getElementById( `frm-single-settings-${fieldId}` );
		const previewInputEl = document.querySelector( `[name="item_meta[${fieldId}]"]` );
		const defaultValueEl = document.getElementById( `frm_default_value_${fieldId}` );

		const defaultValue = defaultValueEl.value;

		// If there's no default value, clear the preview and exit
		if ( ! defaultValue ) {
			previewInputEl.value = '';
			return;
		}

		const formatConfig = getFormatConfig( fieldId, fieldSettingsEl );

		// Match only isolated numeric substrings, not mixed with other characters.
		const numericRegex = /(?<![\w-])\d+(?:[.,]\d+)*(?![\w-])/g;

		 // Format numbers except within shortcodes
		 const formattedValue = defaultValue.replace( numericRegex, ( match, offset ) => {
			const shortcodeContext = defaultValue.substring( 0, offset );
			const lastOpenBracket = shortcodeContext.lastIndexOf( '[' );
			// Skip formatting for shortcode content
			if ( lastOpenBracket !== -1 ) {
				const closingBracket = defaultValue.indexOf( ']', lastOpenBracket );
				if ( closingBracket !== -1 && offset < closingBracket ) {
					return match;
				}
			}

			return formatNumberValue(
				normalizeTotal( match, formatConfig, formatValue ),
				formatConfig,
				formatValue
			);
		});

		// Update the preview input with the fully formatted string
		previewInputEl.value = formattedValue;
	}

	function setScaleValues( target ) {
		const fieldID = target.id.replace( 'scale_maxnum_', '' ).replace( 'scale_minnum_', '' ).replace( 'frm_step_', '' );
		let min = document.getElementById( 'scale_minnum_' + fieldID ).value;
		let max = document.getElementById( 'scale_maxnum_' + fieldID ).value;

		updateScaleValues( parseInt( min, 10 ), parseInt( max, 10 ), fieldID );
	}

	function updateScaleValues( min, max, fieldID ) {
		const container = jQuery( '#field_' + fieldID + '_inner_container .frm_form_fields' );
		const appendFieldToContainer = ( optionValue ) => {
			container.append( '<div class="frm_scale"><label><input type="hidden" name="field_options[options_' + fieldID + '][' + optionValue + ']" value="' + optionValue + '"> <input type="radio" name="item_meta[' + fieldID + ']" value="' + optionValue + '"> ' + optionValue + ' </label></div>' );
		};

		container.html( '' );
		let step = parseInt( document.getElementById( 'frm_step_' + fieldID ).value, 10 );
		if ( step === 0 ) {
			step = 1;
		}

		const ascending = min <= max;

		step = Math.abs( step );

		for ( let i = min; ascending ? i <= max : i >= max; i = ascending ? i + step : i - step ) {
			appendFieldToContainer( i );
		}

		container.append( '<div class="clear"></div>' );
	}

	function toggle( element, on ) {
		jQuery( element ).stop();
		element.style.opacity = 1;

		if ( on ) {
			if ( element.classList.contains( 'frm_hidden' ) ) {
				element.style.opacity = 0;
				element.classList.remove( 'frm_hidden' );
				jQuery( element ).animate({ opacity: 1 });
			}
		} else if ( ! element.classList.contains( 'frm_hidden' ) ) {
			jQuery( element ).animate({ opacity: 0 }, function() {
				element.classList.add( 'frm_hidden' );
			});
		}
	}

	hooks.addAction( 'frm_update_slider_field_preview', hookNamespace, updateSliderFieldPreview, 10 );

	/**
	 * Updates the slider field's preview.
	 *
	 * @param {Object} args - Parameters for updating the slider preview.
	 * @param {HTMLElement} args.field - The slider input element.
	 * @param {string} args.att - The attribute to update.
	 * @param {string} args.newValue - The new value for the attribute.
	 */
	function updateSliderFieldPreview({ field, att, newValue }) {
		if ( 'value' === att ) {
			if ( '' === newValue ) {
				newValue = getSliderMidpoint( field );
			}
			field.value = newValue;
		} else {
			field.setAttribute( att, newValue );
		}

		if ( -1 === [ 'value', 'min', 'max' ].indexOf( att ) ) {
			return;
		}

		if ( ( 'max' === att || 'min' === att ) && '' === getSliderDefaultValueInput( field.id ) ) {
			field.value = getSliderMidpoint( field );
		}

		const fieldId = field.getAttribute( 'name' ).replace( 'item_meta[', '' ).replace( ']', '' );
		const fieldSettingsEl = document.getElementById( 'frm-single-settings-' + fieldId );
		const sliderValueElement = field.parentNode.querySelector( '.frm_range_value' );

		const formatConfig = getFormatConfig( fieldId, fieldSettingsEl );
		const formatValue = fieldSettingsEl.querySelector( '.frm_format_dropdown' ).value;
		let fieldValue = field.value;

		fieldValue = normalizeTotal( fieldValue, formatConfig, formatValue );
		sliderValueElement.textContent = formatNumberValue( fieldValue, formatConfig, formatValue );
	}

	/**
	 * Retrieves the default value input for a slider based on its preview input ID.
	 *
	 * @param {string} previewInputId The ID of the preview input.
	 * @returns {string}
	 */
	function getSliderDefaultValueInput( previewInputId ) {
		return document.querySelector( 'input[data-changeme="' + previewInputId + '"][data-changeatt="value"]' ).value;
	}

	/**
	 * Calculates the midpoint value of a slider input.
	 *
	 * @param {HTMLElement} sliderInput The slider input element.
	 * @returns {number}
	 */
	function getSliderMidpoint( sliderInput ) {
		const max = parseFloat( sliderInput.getAttribute( 'max' ) );
		const min = parseFloat( sliderInput.getAttribute( 'min' ) );

		return ( max - min ) / 2 + min;
	}

	/**
	 * Retrieves the format configuration.
	 *
	 * @param {string} fieldId The unique identifier of the field.
	 * @param {HTMLElement} fieldSettingsEl The container element for settings.
	 * @returns {Object}
	 */
	function getFormatConfig( fieldId, fieldSettingsEl ) {
		const formatConfig = { ...frmProBuilderVars.currency };

		const getFormatSettingValue = ( type, name ) => {
			let selector = `${type}[name="field_options[${name}_${fieldId}]"]`;

			if (type === 'select') {
				selector += ' option:checked';
			}

			return fieldSettingsEl.querySelector( selector )?.value ?? '';
		};

		formatConfig.decimals = parseInt( getFormatSettingValue( 'select', 'custom_decimals' ) );
		formatConfig.decimal_separator = getFormatSettingValue( 'input', 'custom_decimal_separator' );
		formatConfig.thousand_separator = getFormatSettingValue( 'input', 'custom_thousand_separator' );

		formatConfig.symbol_left = getFormatSettingValue( 'input', 'custom_symbol_left' );
		formatConfig.symbol_right = getFormatSettingValue( 'input', 'custom_symbol_right' );

		return formatConfig;
	}

	/**
	 * Normalizes the total based on format configuration.
	 *
	 * @param {string|number} total The numeric value to normalize.
	 * @param {Object} formatConfig The configuration for normalization.
	 * @returns {string}
	 */
	function normalizeTotal( total, formatConfig, formatValue = null ) {
		if ( ! isCurrencyFormat( formatValue ) ) {
			return total;
		}

		total = formatConfig.decimals > 0 ? round10( total, formatConfig.decimals ) : Math.ceil( total );
		return maybeAddTrailingZeroToPrice( total, formatConfig );
	}

	/**
	 * Rounds a number to a specified decimal place.
	 *
	 * @param {number} value The number to be rounded.
	 * @param {number} decimals The number of decimal places to round to.
	 * @returns {number}
	 */
	function round10( value, decimals ) {
		return Number( Math.round( value + 'e' + decimals ) + 'e-' + decimals );
	}

	/**
	 * Formats a numeric value based on the provided configuration.
	 *
	 * @param {number|string} total The numeric value to format.
	 * @param {Object} formatConfig The configuration for value formatting.
	 * @returns {string} The formatted value as a string.
	 */
	function formatNumberValue( total, formatConfig, formatValue = null ) {
		if ( ! isCurrencyFormat( formatValue ) ) {
			return total;
		}

		total = maybeAddTrailingZeroToPrice( total, formatConfig );
		total = maybeRemoveTrailingZerosFromPrice( total, formatConfig );
		total = addThousandsSeparator( total, formatConfig );

		const leftSymbol = formatConfig.symbol_left + formatConfig.symbol_padding;
		const rightSymbol = formatConfig.symbol_padding + formatConfig.symbol_right;

		return leftSymbol + total + rightSymbol;
	}

	/**
	 * Removes unnecessary trailing zeros from the price based on format configuration.
	 *
	 * @param {string} price The price to format.
	 * @param {Object} formatConfig The configuration for formatting.
	 * @returns {string}
	 */
	function maybeRemoveTrailingZerosFromPrice( total, formatConfig ) {
		var split = total.split( formatConfig.decimal_separator );
		if ( 2 !== split.length || split[1].length <= formatConfig.decimals ) {
			return total;
		}
		if ( 0 === formatConfig.decimals ) {
			return split[0];
		}

		return split[0] + formatConfig.decimal_separator + split[1].substr( 0, formatConfig.decimals );
	}

	/**
	 * Adds thousand separators to a number based on format configuration.
	 *
	 * @param {string} number - The number to format.
	 * @param {Object} formatConfig - The configuration for formatting.
	 * @returns {string} - The formatted number with thousand separators.
	 */
	function addThousandsSeparator( total, formatConfig ) {
		if ( formatConfig.thousand_separator ) {
			total = total.toString().replace( /\B(?=(\d{3})+(?!\d))/g, formatConfig.thousand_separator );
		}

		return total;
	}

	/**
	 * Adds trailing zeros to the price if necessary and replaces the decimal point.
	 *
	 * @param {string|number} price The price to format.
	 * @param {Object} formatConfig The configuration for formatting.
	 * @returns {string}
	 */
	function maybeAddTrailingZeroToPrice( price, formatConfig ) {
		if ( 'number' !== typeof price ) {
			return price;
		}

		price += ''; // first convert to string

		const pos = price.indexOf( '.' );
		if ( pos === -1 ) {
			price = price + '.00';
		} else if ( price.substring( pos + 1 ).length < 2 ) {
			price += '0';
		}

		return price.replace( '.', formatConfig.decimal_separator );
	}

	/**
	 * Wrap rich text logic into a function and initialize.
	 * A RTE field has uses TinyMCE for the preview, and for the default value input.
	 * The RTE needs to re-initialize at various points including:
	 * - when drag-and-dropped
	 * - when added with AJA
	 * - when a new field is inserted
	 * - when a group is broken into rows
	 * - when rows are merged into a group
	 *
	 * @returns {void}
	 */
	function initRichTextFields() {
		appendModalTriggersToRtePlaceholderSettings();

		document.addEventListener(
			'click',
			function( event ) {
				const classList = event.target.classList;
				if ( classList.contains( 'frm-break-field-group' ) || classList.contains( 'frm-row-layout-option' ) || classList.contains( 'frm-save-custom-field-group-layout' ) ) {
					initializeAllWysiwygsAfterSlightDelay();
				}
			}
		);

		document.addEventListener(
			'frm_added_field',
			/**
			 * Prepare an RTE field when a new field is added.
			 *
			 * @param {Event} event
			 * @returns {void}
			 */
			event => {
				if ( 'rte' !== event.frmType || ! event.frmField ) {
					return;
				}

				prepareDefaultValueInput( event.frmField.getAttribute( 'data-fid' ) )

				const wysiwyg = event.frmField.querySelector( '.wp-editor-area' );
				if ( wysiwyg ) {
					frmDom.wysiwyg.init( wysiwyg );
				}
			}
		);

		document.addEventListener(
			'frm_ajax_loaded_field',
			/**
			 * When new fields are loaded with AJAX, check if any are RTE fields and initialize.
			 *
			 * @param {Event} event
			 * @returns {void}
			 */
			event => {
				event.frmFields.forEach(
					/**
					 * Check if a single field is an RTE and possibly initialize.
					 *
					 * @param {Object} field {
					 *     @type {String} id Numeric field ID.
					 * }
					 * @returns {void}
					 */
					field => {
						if ( 'rte' !== field.type ) {
							return;
						}

						prepareDefaultValueInput( field.id );

						const wysiwyg = document.querySelector( '#frm_field_id_' + field.id + ' .wp-editor-area' );
						if ( wysiwyg ) {
							frmDom.wysiwyg.init( wysiwyg );
						}
					}
				);
			}
		);

		let draggable;
		// frm_sync_after_drag_and_drop does not pass along information about the draggable, so hook into dropdeactivate.
		jQuery( document ).on( 'dropdeactivate', function( _, ui ) {
			draggable = ui.draggable.get( 0 );
		});
		document.addEventListener(
			'frm_sync_after_drag_and_drop',
			() => {
				if ( draggable ) {
					// Use querySelectorAll as frm_sync_after_drag_and_drop is also called for field groups.
					draggable.querySelectorAll( '.wp-editor-area' ).forEach( frmDom.wysiwyg.init );
				}
			}
		);

		function prepareDefaultValueInput( fieldId ) {
			const defaultValueWrapper = document.getElementById( 'default-value-for-' + fieldId );
			addSmartValuesTriggerToDefaultValueWrapper( defaultValueWrapper );
			copyChangemeFromWrapperToInput( defaultValueWrapper );
		}

		function initializeAllWysiwygsAfterSlightDelay() {
			setTimeout(
				() => document.querySelectorAll( '#frm-show-fields .wp-editor-area' ).forEach( frmDom.wysiwyg.init ),
				1
			);
		}

		function appendModalTriggersToRtePlaceholderSettings() {
			const rtePlaceholderDefaults = document.querySelectorAll( '.frm-single-settings.frm-type-rte .frm-default-value-wrapper' );
			if ( ! rtePlaceholderDefaults.length ) {
				return;
			}

			rtePlaceholderDefaults.forEach(
				defaultValueWrapper => {
					addSmartValuesTriggerToDefaultValueWrapper( defaultValueWrapper );
					copyChangemeFromWrapperToInput( defaultValueWrapper );
				}
			);
		}

		function copyChangemeFromWrapperToInput( defaultValueWrapper ) {
			const fieldToChangeId = defaultValueWrapper.getAttribute( 'data-changeme' );

			document.getElementById( defaultValueWrapper.getAttribute( 'data-html-id' ) ).setAttribute( 'data-changeme', fieldToChangeId );
			defaultValueWrapper.removeAttribute( 'data-changeme' );

			const field = document.getElementById( fieldToChangeId );
			if ( field ) {
				jQuery( field ).on(
					'change',
					function() {
						if ( ! tinyMCE.editors[ field.id ] || tinyMCE.editors[ field.id ].isHidden() ) {
							return;
						}
						tinyMCE.editors[ field.id ].setContent( field.value );
					}
				);
			}
		}

		function addSmartValuesTriggerToDefaultValueWrapper( defaultValueWrapper ) {
			/*global frmDom */
			const { svg } = frmDom;

			const inputID = defaultValueWrapper.getAttribute( 'data-html-id' );

			const modalTrigger = svg({ href: '#frm_more_horiz_solid_icon', classList: [ 'frm_more_horiz_solid_icon', 'frm-show-inline-modal' ] });
			modalTrigger.setAttribute( 'data-open', 'frm-smart-values-box' );
			modalTrigger.setAttribute( 'title', defaultValueWrapper.getAttribute( 'data-modal-trigger-title' ) );

			document.getElementById( inputID ).parentElement.prepend( modalTrigger );

			// The icon should be wrapped in a 'p' tag, as the modal box is appended to the 'closest' p.
			const wrapper = document.createElement( 'p' );
			wrapper.prepend( document.getElementById( 'wp-' + inputID + '-wrap' ) );
			defaultValueWrapper.appendChild( wrapper );
		}
	}

	function validateTimeFieldRangeValue( target ) {
		if ( ! ( target.id.startsWith( 'start_time' ) || target.id.startsWith( 'end_time' ) ) ) {
			return;
		}

		const timeRangeInput = target;
		let isValid          = true;

		function getStepUnit() {
			const stepUnitEl = timeRangeInput.closest( '.frm-single-settings' ).querySelector( 'select[id^="step_unit_"]' );
			if ( ! stepUnitEl ) {
				return false;
			}
			return stepUnitEl.value;
		}

		if ( timeRangeInput.matches( '[id^=frm_step_]' ) ) {
			if ( timeRangeInput.value.match( /^\d{1,2}$/ ) ) {
				return;
			}
			frmAdminBuild.infoModal( 'Step value is invalid.' );
			isValid = false;

		} else if ( ! timeRangeInput.value.match( getTimeRangeRegex( getStepUnit() ) ) ) {
			let timeRangeString;
			if ( timeRangeInput.matches( '.frm-type-time [id^=start_time]' ) ) {
				timeRangeString = 'Start time';
			} else {
				timeRangeString = 'End time';
			}
			frmAdminBuild.infoModal( `${timeRangeString} is invalid.` );
			isValid = false;
		}

		if ( ! isValid ) {
			handleModalDismiss( timeRangeInput );
		} else if ( timeRangeInput.classList.contains( 'frm_invalid_field' ) ) {
			timeRangeInput.classList.remove( 'frm_invalid_field' );
		}
	}

	const PageBreakField = {
		transition: false, // Track the transition value when one of page break transition changes.

		/**
		 * Handles change transition event.
		 *
		 * @param {Event} event Event object.
		 */
		onChangeTransition: function( event ) {
			// Store the updated value to update new page break field.
			PageBreakField.transition = event.target.value;

			// Update other page break fields.
			document.querySelectorAll( '.frm_page_transition_setting' ).forEach( el => {
				if ( el.id === event.target.id ) {
					// Do not update current setting.
					return;
				}

				el.value = event.target.value;
			});
		},

		/**
		 * Handlers added field.
		 *
		 * @param {Event} event Event object.
		 */
		onAddedField: function( event ) {
			if ( false === PageBreakField.transition || 'break' !== event.frmType ) {
				return;
			}

			const transitionSetting = document.getElementById( 'frm_transition_' + event.frmField.dataset.fid );
			transitionSetting.value = PageBreakField.transition;
		}
	};

	const Rootline = {
		init: function() {
			hooks.addAction( 'frmShowedFieldSettings', hookNamespace, this.showedFieldSettings );

			document.addEventListener( 'frm_added_field', function( event ) {
				if ( 'break' === event.frmType && Rootline.isRootlineAvailable() ) {
					Rootline.updateRootline();
				}
			});

			hooks.addAction( 'frm_renumber_page_breaks', hookNamespace, function( pages ) {
				if ( ! Rootline.isRootlineAvailable() ) {
					return;
				}

				if ( pages.length > 1 ) {
					Rootline.updateRootline();
				} else {
					Rootline.toggleRootline( false );
				}
			});

			// Listen for rootline settings change to update the rootline.
			const settingSelectors = '#frm-rootline-type,#frm-rootline-titles-on,.frm-rootline-title-setting input,#frm-rootline-numbers-off,#frm-lines-numbers-off';
			frmDom.util.documentOn( 'change', settingSelectors, this.updateRootline );

			this.makeRootlineResponsive();
		},

		/**
		 * Checks if rootline is available in form builder.
		 *
		 * @return {Boolean}
		 */
		isRootlineAvailable: function() {
			return document.getElementById( 'frm-rootline-type' );
		},

		/**
		 * Does after showed field settings.
		 *
		 * @param {HTMLElement} showBtn Show settings button.
		 * @param {HTMLElement} settingsEl Settings element.
		 */
		showedFieldSettings: function( showBtn, settingsEl ) {
			if ( 'rootline' !== showBtn.dataset.fid ) {
				return;
			}

			Rootline.loadPageTitlesSetting( settingsEl );
		},

		/**
		 * Loads titles setting for Rootline.
		 *
		 * @param {HTMLElement} settingsEl Settings element.
		 */
		loadPageTitlesSetting: function( settingsEl ) {
			const pageBreaks    = document.querySelectorAll( '.frm_field_box.edit_field_type_break' );
			const titleSettings = settingsEl.querySelectorAll( '.frm-rootline-title-setting' );
			const pagesCount    = pageBreaks.length + 1; // Plus the first page break.

			for ( let i = 1; i < pagesCount; i++ ) {
				if ( 'undefined' !== typeof titleSettings[ i ] ) {
					// Show it.
					titleSettings[ i ].classList.remove( 'frm_hidden' );
				} else {
					// Append new title setting.
					Rootline.appendPageTitleSetting( titleSettings[0], pageBreaks[ i - 1 ] );
				}
			}

			// Hide title exceeded settings.
			if ( pagesCount < titleSettings.length ) {
				for ( let i = pagesCount; i < titleSettings.length; i++ ) {
					titleSettings[ i ].classList.add( 'frm_hidden' );
				}
			}
		},

		/**
		 * Appends page title input in the rootline titles setting.
		 *
		 * @param {HTMLElement} firstSetting First title setting element.
		 * @param {HTMLElement} pageBreak    Corresponding page break element.
		 */
		appendPageTitleSetting: function( firstSetting, pageBreak ) {
			const cloneSetting = firstSetting.cloneNode( true );
			const label        = cloneSetting.querySelector( 'label' );
			const input        = cloneSetting.querySelector( 'input' );
			const pageId       = pageBreak.dataset.fid;

			frmDom.setAttributes( label, {
				for: label.getAttribute( 'for' ).replace( '1', pageId )
			});

			frmDom.setAttributes( input, {
				id: input.id.replace( '1', pageId ),
				name: input.name.replace( '[0]', '[' + pageId + ']' ),
				'data-page': pageId,
				value: pageBreak.querySelector( '.frm_button_submit' ).innerText
			});

			firstSetting.parentNode.appendChild( cloneSetting );
		},

		/**
		 * Gets rootline settings.
		 *
		 * @return {{titlesOn, numbersOn: boolean, linesOn: boolean, position, titles: any[], type}}
		 */
		getSettings: function() {
			return {
				type: document.getElementById( 'frm-rootline-type' ).value,
				position: document.getElementById( 'frm-pagination-position' ).value,
				titlesOn: document.getElementById( 'frm-rootline-titles-on' ).checked,
				numbersOn: ! document.getElementById( 'frm-rootline-numbers-off' ).checked,
				linesOn: ! document.getElementById( 'frm-rootline-lines-off' ).checked,
				titles: Array.from( document.querySelectorAll( '.frm-rootline-title-setting input' ) ).map( input => input.value )
			};
		},

		/**
		 * Updates rootline UI.
		 */
		updateRootline: function() {
			const settings = Rootline.getSettings();
			if ( ! settings.type ) {
				Rootline.toggleRootline( false );
				return;
			}

			Rootline.toggleRootline( true );

			/**
			 * Allows using custom handler for live updating rootline in form builder.
			 *
			 * @since 6.9
			 *
			 * @param {Boolean} skip     Return `true` to skip remaining updates.
			 * @param {Object}  settings Rootline settings.
			 * @param {Object}  Rootline Rootline class.
			 */
			const skip = hooks.applyFilters( 'frm_pro_backend_update_rootline', false, settings, Rootline );
			if ( skip ) {
				return;
			}

			const rootlineWrapper = document.getElementById( 'frm-backend-rootline' );
			const rootlineList    = rootlineWrapper.querySelector( 'ul' );
			const pages           = document.querySelectorAll( '.frm-page-num' );

			Array.from( rootlineList.children ).forEach( el => el.remove() );

			for ( let i = 0; i < pages.length; i++ ) {
				// Add "more" rootline item.
				if ( pages.length - 1 === i ) {
					rootlineList.appendChild(
						Rootline.getRootlineItem(
							{
								title: '',
								number: '...',
								className: 'frm-rootline-item-more frm_hidden'
							},
							settings
						)
					);
				}

				rootlineList.appendChild(
					Rootline.getRootlineItem(
						{
							title: settings.titles[ i ] || wp.i18n.sprintf( wp.i18n.__( 'Page %d', 'formidable-pro' ), i + 1 ),
							number: i + 1
						},
						settings
					)
				);
			}

			rootlineWrapper.setAttribute( 'data-type', settings.type );
			rootlineWrapper.classList.toggle( 'frm-rootline-no-titles', ! settings.titlesOn );
			rootlineWrapper.classList.toggle( 'frm-rootline-no-numbers', ! settings.numbersOn );
			rootlineWrapper.classList.toggle( 'frm-rootline-no-lines', ! settings.linesOn );

			rootlineWrapper.querySelector( '.frm_pages_total' ).innerText = pages.length;

			Rootline.resizeRootline();
		},

		/**
		 * Gets one rootline item.
		 *
		 * @param {Object} data     Rootline item data.
		 * @param {Object} settings Rootline settings.
		 * @return {HTMLElement}
		 */
		getRootlineItem: function( data, settings ) {
			return frmDom.tag( 'li', {
				children: [
					'rootline' === settings.type ? frmDom.tag(
						'span',
						{
							className: 'frm-rootline-number',
							text: data.number
						}
					) : '',
					frmDom.tag(
						'span',
						{
							className: 'frm-rootline-title',
							text: data.title
						}
					)
				],
				className: data.className
			});
		},

		/**
		 * Toggles rootline.
		 *
		 * @param {Boolean} show Show rootline or not.
		 */
		toggleRootline: function( show ) {
			document.getElementById( 'frm-backend-rootline-wrapper' ).classList.toggle( 'frm_hidden', ! show );
		},

		/**
		 * Resizes rootline.
		 */
		resizeRootline: function() {
			if ( ! Rootline.isRootlineAvailable() ) {
				return;
			}

			const rootlineWrapper = document.getElementById( 'frm-backend-rootline' );
			if ( 'rootline' !== rootlineWrapper.dataset.type ) {
				// Don't need to resize progress bar.
				return;
			}

			const width = rootlineWrapper.offsetWidth;
			const itemWidth = rootlineWrapper.classList.contains( 'frm-rootline-no-titles' ) ? 50 : 150;
			const showItems = Math.floor( width / itemWidth );
			const items = rootlineWrapper.querySelectorAll( 'li' );

			items.forEach( ( item, index ) => {
				if ( ! index || items.length - 1 === index ) {
					// Always show the first and last item.
					return;
				}

				if ( items.length < showItems + 2 ) {
					item.classList.toggle( 'frm_hidden', item.classList.contains( 'frm-rootline-item-more' ) );
					return;
				}

				if ( index < showItems - 2 ) {
					item.classList.remove( 'frm_hidden' );
					return;
				}

				item.classList.toggle( 'frm_hidden', ! item.classList.contains( 'frm-rootline-item-more' ) );
			});
		},

		/**
		 * Makes rootline responsive.
		 */
		makeRootlineResponsive: function() {
			window.addEventListener( 'resize', this.resizeRootline );

			window.dispatchEvent( new Event( 'resize' ) );
		}
	};

	function getTimeRangeRegex( stepUnit ) {
		let regex = '^(?:\\d|[01]\\d|2[0-3]):[0-5]\\d';
		if ( 'sec' === stepUnit ) {
			regex += ':[0-5]\\d';
		} else if ( 'millisec' === stepUnit ) {
			regex += ':[0-5]\\d\\:\\d\\d\\d';
		}

		regex += '$';

		return new RegExp( regex );
	}

	function onChangeStepUnit( event ) {
		const stepUnit = event.target.value;
		const regex = getTimeRangeRegex( stepUnit );
		const wrapper = event.target.closest( '.frm-single-settings' );
		const inputs = wrapper.querySelectorAll( '.frm-number-range input[type="text"]' );
		const stepInput = wrapper.querySelector( 'input[id^="frm_step_"]' );
		const singleCheckbox = wrapper.querySelector( 'label[for^="single_time_"]' );

		const getFormattedRangeValue = value => {
			let [ h, m, s, ms ] = value.split( ':' );

			if ( ! h || isNaN( h ) ) {
				h = '00';
			}

			if ( ! m || isNaN( m ) ) {
				m = '00';
			}

			if ( STEP_UNIT_SECOND !== stepUnit && STEP_UNIT_MILLISECOND !== stepUnit ) {
				return h + ':' + m;
			}

			if ( ! s || isNaN( s ) ) {
				s = '00';
			}

			if ( STEP_UNIT_SECOND === stepUnit ) {
				return [ h, m, s ].join( ':' );
			}

			if ( ! ms || isNaN( ms ) ) {
				ms = '000';
			}

			return [ h, m, s, ms ].join( ':' );
		};

		const changeValueFormat = input => {
			if ( input.value.match( regex ) ) {
				return;
			}

			input.value = getFormattedRangeValue( input.value );
		};

		// Change format of time range inputs.
		inputs.forEach( changeValueFormat );

		// Change format of step input. If step setting is empty or is a number, don't change.
		if ( stepInput.value && isNaN( stepInput.value ) ) {
			stepInput.value = getFormattedRangeValue( stepInput.value );
		}

		// Show or hide single time dropdown checkbox.
		if ( STEP_UNIT_SECOND === stepUnit || STEP_UNIT_MILLISECOND === stepUnit ) {
			singleCheckbox.classList.add( 'frm_hidden' );
		} else {
			singleCheckbox.classList.remove( 'frm_hidden' );
		}
	}

	/**
	 * @param {HTMLElement} input
	 * @returns {void}
	 */
	function setStarValues( input ) {
		/*jshint validthis:true */
		const fieldID   = input.id.replace( 'radio_maxnum_', '' );
		const container = document.querySelector( '#field_' + fieldID + '_inner_container .frm-star-group' );

		if ( ! container ) {
			return;
		}

		const fieldKey      = document.getElementsByName( 'field_options[field_key_' + fieldID + ']' )[0].value;
		container.innerHTML = '';

		const min = 1;
		let max   = input.value;
		if ( min > max ) {
			max = min;
		}

		let i, hiddenInput, radioInput, label;
		const fragment = document.createDocumentFragment();
		for ( i = min; i <= max; i++ ) {
			hiddenInput = frmDom.tag( 'input' );
			hiddenInput.setAttribute( 'name', 'field_options[options_' + fieldID + '][' + i + ']' );
			hiddenInput.setAttribute( 'type', 'hidden' );

			radioInput = frmDom.tag( 'input' );
			radioInput.id = 'field_' + fieldKey + '-' + i;
			radioInput.setAttribute( 'type', 'radio' );
			radioInput.setAttribute( 'name', 'item_meta[' + fieldID + ']"' );

			label = frmDom.tag(
				'label',
				{
					className: 'star-rating',
					children: [
						frmDom.svg({ href: '#frm_star_icon' }),
						frmDom.svg({ href: '#frm_star_full_icon' })
					]
				}
			);
			label.setAttribute( 'for', radioInput.id );

			fragment.appendChild( hiddenInput );
			fragment.appendChild( radioInput );
			fragment.appendChild( label );
			fragment.appendChild( document.createTextNode( ' ' ) );
		}

		container.appendChild( fragment );
	}

	/**
	 * Deletes all conditional logic dropdown option elements that correspond to the deleted field option.
	 *
	 * @since 6.12
	 * @param {HTMLElement} option
	 * @return {void}
	 */
	function deleteConditionalLogicOptions( option ) {
		const deletedOption = option.closest( '.frm_single_option' ).querySelector( '.frm_option_key input[type="text"]' );
		if ( ! deletedOption ) {
			return;
		}
		const deletedOptionValue = deletedOption.value;
		const rows               = document.querySelectorAll( '.frm_logic_row' );

		rows.forEach( row => {
			const fieldId = row.id.split( '_' )[ 2 ]; // row.id Example: frm_logic_1234_0 where 1234 is the field id and 0 the conditional logic row.
			const relatedConditionalLogicOption = row.querySelector( 'select[name="field_options[hide_opt_' + fieldId + '][]"] option[value="' + deletedOptionValue + '"]' );
			if ( relatedConditionalLogicOption ) {
				relatedConditionalLogicOption.remove();
			}
		});
	}

	/**
	 * Handles default value select changes to toggle custom value field visibility.
	 *
	 * @param {HTMLElement} element The select element that triggered the change.
	 */
	function handleDefaultValueSelectChange( element ) {
		const wrapper = element.closest( '.frm-with-right-icon' );
		const defaultValueInput = wrapper.querySelector( 'input.default-value-field' );
		const moreSvgEl = wrapper.querySelector( '.frm_more_horiz_solid_icon' );

		const isCustom = 'frm-toggle-custom' === element.value;

		defaultValueInput.classList.toggle( 'frm_hidden', ! isCustom );
		moreSvgEl.classList.toggle( 'frm_hidden', ! isCustom );

		defaultValueInput.value = isCustom ? '' : element.selectedOptions[0].dataset.label;
	}

	addEventListeners();
	initRichTextFields();
	Rootline.init();

}() );
