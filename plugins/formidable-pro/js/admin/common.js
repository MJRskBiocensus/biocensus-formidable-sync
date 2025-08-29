( function() {
	function addEventListeners() {
		document.addEventListener( 'change', handleChangeEvent );
	}

    function handleChangeEvent( event ) {
        const target = event.target;
		if ( target.name.includes( '[hide_field_cond' ) ) {
			maybeUpdateConditionalLogicValueSelect( target );
		}
    }

	/**
	 * Updates conditional logic row's value element type based on the comparison selected.
	 *
	 * @since 6.17
	 *
	 * @param {HTMLElement} conditionComparisonElement
	 * @returns {Void}
	 */
	function maybeUpdateConditionalLogicValueSelect( conditionComparisonElement ) {
		const comparison  = conditionComparisonElement.value;
		const parentIDs   = conditionComparisonElement.closest( '.frm_logic_row' )?.id.replace( 'frm_logic_', '' ).split( '_' ); //Conditional logic container id Example: frm_logic_3741_0
		if (  ! parentIDs || parentIDs.length !== 2 ) {
			return;
		}
		const fieldID = parentIDs[0];
		const rowID   = parentIDs[1];

		const valueSelectWrapper = document.getElementById( `frm_show_selected_values_${fieldID}_${rowID}` );
		const valueSelect        = valueSelectWrapper?.querySelector( 'select, input' );

		if ( ! valueSelect ) {
			return;
		}
		const comparisonIsExactMatch = [ '==', '!=' ].includes( comparison );
		if ( valueSelect.nodeName === 'SELECT' && ! comparisonIsExactMatch ) {
			changeValueSelectToTextInput( valueSelect, valueSelectWrapper, fieldID, rowID );
			return;
		}
		if ( valueSelect.nodeName === 'INPUT' && comparisonIsExactMatch ) {
			changeValueSelectToDropdown( valueSelect, valueSelectWrapper, fieldID, rowID );
		}
	}

	/**
	 * @since 6.17
	 *
	 * @param {HTMLElement} valueSelect        The HTML control used to define the conditional logic value match.
	 * @param {HTMLElement} valueSelectWrapper A div wrapping the value selector element.
	 * @param {Number} fieldID                 The field id whose conditional logic is updated.
	 * @param {Number} rowID                   The conditional logic row id updated.
	 *
	 * @return {Void}
	 */
	function changeValueSelectToTextInput( valueSelect, valueSelectWrapper, fieldID, rowID ) {
        const input = frmDom.tag( 'input', {
            id: `frm_field_logic_opt_${fieldID}_${rowID}`,
        });

		input.name  = valueSelect.name;
		input.type  = 'text';
		input.value = valueSelect.value;
		valueSelectWrapper.append( input );
		valueSelect.remove();
	}

    /**
     * Handles switching value selector to dropdown in form action conditional logic.
     *
     * @since 6.17
     *
     * @param {HTMLElement} valueSelect
     * @returns {Void}
     */
    function changeActionValueSelectToDropdown( valueSelect ) {
        const actionLogicFieldDropdown = valueSelect.closest( '.frm_logic_row' )?.querySelector( '[name*=hide_field]' );
        if ( ! actionLogicFieldDropdown ) {
            return;
        }
        actionLogicFieldDropdown.dispatchEvent( new Event( 'change' ) );
    }

	/**
	 * @since 6.17
	 *
	 * @param {HTMLElement} valueSelect        The HTML control used to define the conditional logic value match.
	 * @param {HTMLElement} valueSelectWrapper A div wrapping the value selector element.
	 * @param {Number} fieldID                 The field id whose conditional logic is updated.
	 * @param {Number} rowID                   The conditional logic row id updated.
	 *
	 * @return {Void}
	 */
	function changeValueSelectToDropdown( valueSelect, valueSelectWrapper, fieldID, rowID ) {
		if ( ! shouldChangeValueSelectToDropdown( valueSelectWrapper ) ) {
			return;
		}
        if ( document.querySelector( '.frm_form_action_settings' ) ) {
            changeActionValueSelectToDropdown( valueSelect );
            return;
        }

		if ( 'function' !== typeof frmAdminBuild.fillDropdownOpts ) {
			return;
		}
		const select = frmDom.tag( 'select' );
		select.name  = valueSelect.name;
		valueSelectWrapper.append( select );
		valueSelect.remove();
		const optionsSource = document.querySelector( `#frm_logic_${fieldID}_${rowID} .frm_logic_field_opts` );
		if ( ! optionsSource ) {
			return;
		}
		frmAdminBuild.fillDropdownOpts( select, {
			sourceID: optionsSource.value,
			placeholder: '',
			other: true
		});
	}

	/**
	 * @since 6.18
	 *
	 * @param {HTMLElement} valueSelectWrapper A div wrapping the value selector element.
	 * @returns {Boolean}
	 */
	function shouldChangeValueSelectToDropdown( valueSelectWrapper ) {
		const conditionFieldID = valueSelectWrapper.parentElement.querySelector( '.frm_logic_field_opts' ).value;
		const conditionFieldLi = document.getElementById( `frm_field_id_${conditionFieldID}` );
		return conditionFieldLi && [ 'checkbox', 'radio', 'select' ].includes( conditionFieldLi.dataset.ftype );
	}

    addEventListeners();
}() );
