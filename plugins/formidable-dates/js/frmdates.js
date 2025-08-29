/** global frmDatepicker, frmProForm */
jQuery( function( $ ) {

	var frmdates = {
		normalizeSettings: function( fieldSettings ) {
			return $.extend(
				{},
				{ triggerID: fieldSettings.triggerID, repeating: -1 !== fieldSettings.triggerID.indexOf( '^' ), locale: fieldSettings.locale },
				{ datepickerOptions: fieldSettings.options },
				fieldSettings.formidable_dates
			);
		},

		getTargets: function( fieldConfig ) {
			var targets = [];

			$( fieldConfig.triggerID ).each(
				function() {
					if ( fieldConfig.repeating && fieldConfig.inline ) {
						targets.push( $( this ).siblings( '.frm_date_inline' ) );
					} else {
						targets.push( $( this ) );
					}
				}
			);

			return targets;
		},

		setupFields: function() {
			var hasSettings,
				dateSettings = window.__frmDatepicker;

			$.each(
				dateSettings,
				function() {
					if ( 'undefined' !== typeof this.formidable_dates && this.formidable_dates ) {

						// Trigger changes if any field in the form has extended settings.
						hasSettings = true;
					}
				}
			);

			if ( ! hasSettings ) {
				return;
			}

			$.each( dateSettings, function( index ) {
				var fieldConfig = frmdates.normalizeSettings( this ),
					hasConfig = 'undefined' !== typeof this.formidable_dates && this.formidable_dates;

				if ( 0 === $( fieldConfig.triggerID ).length ) {
					return;
				}

				if ( ! hasConfig ) {

					// Trigger changes in case other fields depend on it.
					if ( ! frmDatepickerInstance.isFlatpickrOn() ) {
						// TODO: Remove this once we're sure Flatpickr is always available.
						window.__frmDatepicker[ index ].options.onSelect = $.proxy( frmdates.callbacks.onSelect, fieldConfig );
					} else {
						window.__frmDatepicker[ index ].options.onChange = frmDatepickerInstance.onChange;
					}
					return;
				}

				if ( frmDatepickerInstance.isFlatpickrOn() ) {
					fieldConfig.datepickerOptions.onOpen  = frmProForm.frmDatepicker.callbacks.onOpen;
					fieldConfig.datepickerOptions.onClose = frmProForm.frmDatepicker.callbacks.onClose;
					fieldConfig.datepickerOptions.onChange = frmDatepickerInstance.onChange;
				} else {
					// TODO: Remove this once we're sure flatpickr is always available.
					if ( ! fieldConfig.inline && 'undefined' !== typeof frmProForm && 'function' === typeof frmProForm.addFormidableClassToDatepicker && 'function' === typeof frmProForm.removeFormidableClassFromDatepicker ) {
						fieldConfig.datepickerOptions.beforeShow = frmProForm.addFormidableClassToDatepicker;
						fieldConfig.datepickerOptions.onClose    = frmProForm.removeFormidableClassFromDatepicker;
					}
					fieldConfig.datepickerOptions.beforeShowDay = $.proxy( frmdates.callbacks.beforeShowDay, fieldConfig );
					fieldConfig.datepickerOptions.onSelect      = $.proxy( frmdates.callbacks.onSelect, fieldConfig );
				}

				fieldConfig.datepickerOptions.minDate = ! fieldConfig.repeating ? frmdates.getMinOrMaxDate( 'minimum_date', fieldConfig ) : null;
				fieldConfig.datepickerOptions.maxDate = ! fieldConfig.repeating ? frmdates.getMinOrMaxDate( 'maximum_date', fieldConfig ) : null;

				// Hijack global settings so our functions are called.
				window.__frmDatepicker[ index ].options = fieldConfig.datepickerOptions;

				$.each( frmdates.getTargets( fieldConfig ), function() {
					var altField, dateFormat,
						localConfig = fieldConfig.datepickerOptions;
						frmDatePicker = new frmDatepickerInstance( this, fieldConfig );

					if ( fieldConfig.inline ) {
						this.addClass( 'frm-datepicker' );

						altField = document.getElementById( this.attr( 'id' ) + '_alt' );
						if ( null !== altField && '' !== altField.value ) {
							dateFormat = frmDatePicker.getDateFormat();
							if ( null !== dateFormat ) {
								localConfig.defaultDate = frmDatePicker.getDate();
							} else {
								localConfig.defaultDate = altField.value;
							}
						}

						//Calculating default date based on offset
						frmdates.defaultDateOffset( fieldConfig, localConfig );

					}

					if ( fieldConfig.repeating ) {

						// Min. or max. date might need to be computed based on the repeating container.
						localConfig = $.extend(
							localConfig,
							{
								minDate: frmdates.getMinOrMaxDate( 'minimum_date', fieldConfig, this ),
								maxDate: frmdates.getMinOrMaxDate( 'maximum_date', fieldConfig, this )
							}
						);
					}

					localConfig = frmdates.adjustYearRange( localConfig );

					// Handle localization.
					// TODO: Remove this once we're sure Flatpickr is always available.
					if ( ! frmDatepickerInstance.isFlatpickrOn() ) {
						localConfig = $.extend(
							{}, $.datepicker.regional[ fieldConfig.locale ], localConfig
						);
					}

					if ( this.data( 'frmdates_configured' ) || this.hasClass( 'hasDatepicker' ) ) {
						frmDatePicker.updateConfig( localConfig );
					} else {
						fieldConfig.datepickerOptions.locale = fieldConfig.locale;
						frmDatePicker.initInstance( fieldConfig, localConfig );
					}

					if ( ! localConfig.defaultDate && fieldConfig.inline ) {
						frmDatePicker.setDate( null );
						this.find( '.ui-state-active' ).removeClass( 'ui-state-active ui-state-hover' ).parent().removeClass( 'ui-datepicker-current-day' );
					}

					this.data( 'frmdates_configured', true );

					if ( fieldConfig.repeating && fieldConfig.inline ) {
						altField = this.closest( '.frm_repeat_sec, .frm_repeat_inline, .frm_repeat_grid' ).find( 'input[id^="' + this.attr( 'id' ) + '"]' );
						if ( altField.length > 0 ) {
							frmDatepickerInstance.setAltField( this[0], altField[0]);
						}
					}
				});
			});
		},

		getMinOrMaxDate: function( limit, field, $instance ) {
			var $container, $sourceField, condition, val,
				result = null;

			condition = field[ limit + '_cond' ];
			if ( ! condition ) {
				return null;
			}

			val = field[ limit + '_val' ];

			// Specific date.
			if ( 'date' === condition ) {
				return frmDatepickerInstance.parseDate( val, 'yy-mm-dd' );
			}

			// Relative dates.
			if ( 'today' === condition ) {
				result = new Date();
			} else if ( 'field_' === condition.substr( 0, 6 ) ) {

				// First search for the condition field inside the same repeating container.
				if ( field.repeating && $instance ) {
					$container   = $instance.closest( '.frm_repeat_sec, .frm_repeat_inline, .frm_repeat_grid' );
					$sourceField = $container.find( '[id^="' + condition + '"].frm_date_inline' );
					$sourceField = ( 0 === $sourceField.length ) ? $container.find( 'input[id^="' + condition + '"]' ) : $sourceField;
				}

				$sourceField = ( ! $sourceField || 0 === $sourceField.length ) ? $( '#' + condition ) : $sourceField;

				if ( $sourceField && 1 === $sourceField.length ) {

					// The field might be on a different page and it's hidden now.
					if ( $sourceField.is( 'input[type="hidden"]' ) ) {

						// All date fields use the same dateFormat value, so we can re-use the one from `field`.
						result = frmDatepickerInstance.parseDate( $sourceField.val(), null, field.datepickerOptions );
					} else {
						result = frmDatepickerInstance.getDate( $sourceField[0]);
						if ( ! result && $sourceField.val() ) {
							// if source field datepicker is not initialized, the case when source doesn't have custom settings
							result = new Date( $sourceField.val() );
						}
					}
				}

				if ( ! result ) {
					return null;
				}
			}

			result = this.applyDateOffset( result, val );
			return result;
		},

		adjustYearRange: function( localConfig ) {
			var parts = localConfig.yearRange.split( ':' ),
				start = parts[0],
				end = parts[1];

			if ( null !== localConfig.minDate ) {
				start = localConfig.minDate.getFullYear();
			}

			if ( null !== localConfig.maxDate ) {
				end = localConfig.maxDate.getFullYear();
			}

			return $.extend(
				localConfig,
				{
					yearRange: start + ':' + end
				}
			);
		},

		applyDateOffset: function( date, offset, settings ) {
			var matches, oldDate,
				pattern = /([+\-]?[0-9]+)\s*(d|day|days|w|week|weeks|m|month|months|y|year|years)?/g;

			if ( ! offset ) {
				return date;
			}

			date.setHours( 0 );
			date.setMinutes( 0 );
			date.setSeconds( 0 );
			date.setMilliseconds( 0 );

			oldDate = new Date( date.getTime() );
			offset  = offset.replaceAll( /\s/g, '' ).replace( '--', '' ).replace( '+-', '-' ).replace( '-+', '-' ).toLowerCase();
			matches = pattern.exec( offset );

			while ( matches ) {
				switch ( matches[2]) {
					case 'd':
					case 'day':
					case 'days':
						date.setDate( date.getDate() + parseInt( matches[1], 10 ) );
						break;
					case 'w':
					case 'week':
					case 'weeks':
						date.setDate( date.getDate() + 7 * parseInt( matches[1], 10 ) );
						break;
					case 'm':
					case 'month':
					case 'months':
						date.setMonth( date.getMonth() + parseInt( matches[1], 10 ) );
						break;
					case 'y':
					case 'year':
					case 'years':
						date.setFullYear( date.getFullYear() + parseInt( matches[1], 10 ) );
						break;
				}

				matches = pattern.exec( offset );
			}

			if ( settings && settings.skipBlockedDatesFromCalc ) {
				return frmdates.maybeSkipBlockedDates( oldDate, date, settings );
			}

			return date;
		},

		maybeSkipBlockedDates: function( oldDate, newDate, settings ) {
			var daysDiff, isMinus, i;

			daysDiff = ( newDate.getTime() - oldDate.getTime() ) / 86400000;
			isMinus  = daysDiff < 0;

			// Increase or decrease date with a loop, skip blocked dates in each loop.
			for ( i = 0; i < Math.abs( daysDiff ); i++ ) {
				oldDate.setDate( isMinus ? ( oldDate.getDate() - 1 ) : ( oldDate.getDate() + 1 ) );
				oldDate = frmdates.getNextAvailableDate( oldDate, settings, isMinus );
			}

			return oldDate;
		},

		/**
		 * Gets date object from date string.
		 *
		 * @param {String} dateStr Date string.
		 * @return {Object|false}
		 */
		getDateFromStr: function( dateStr ) {
			var date = new Date( dateStr );
			if ( date instanceof Date && ! isNaN( date ) ) {
				return date;
			}
			return false;
		},

		/**
		 * Gets date settings from field ID.
		 *
		 * @param {Integer} fieldId Field ID.
		 * @return {Object|false}
		 */
		getDateSettingsFromFieldId: function( fieldId ) {
			var field, i,
				dateSettings = window.__frmDatepicker;

			for ( i = 0; i < dateSettings.length; i++ ) {
				if ( parseInt( fieldId ) === parseInt( dateSettings[i].fieldId ) ) {
					field = dateSettings[i];
					break;
				}
			}

			if ( ! field ) {
				return false;
			}

			return this.normalizeSettings( field );
		},

		/**
		 * Parses date calculation string to get start date and diff string.
		 *
		 * @param {String} str      Date calculation string.
		 * @param {Object} calc     Calculation data.
		 * @param {Object} settings Normalized field settings.
		 * @return {Object|false}   Return an object with `start` and `diff` if success.
		 */
		parseCalcStr: function( str, calc, settings ) {
			var data = {
					start: '',
					diff: ''
				},
				parsedStr = str.split( '+' );

			if ( ! parsedStr[0]) { // Start date is empty.
				return;
			}

			if ( ! isNaN( parsedStr[0]) ) { // Is number of days since 1/1/1970.
				data.start = new Date( parsedStr[0] * 24 * 60 * 60 * 1000 );
			} else {
				try {
					data.start = frmDatepickerInstance.parseDate( parsedStr[0], null, settings.datepickerOptions );
				} catch ( e ) {
					return false;
				}
			}

			if ( 2 === parsedStr.length ) {
				data.diff = parsedStr[1];
			} else if ( 3 === parsedStr.length ) { // [date]++3 days.
				data.diff = parsedStr[2];
			}

			return data;
		},

		/**
		 * Checks if the given date is blocked.
		 *
		 * @param {Object} date     Date object.
		 * @param {Object} settings Normalized field settings.
		 * @return {Boolean}
		 */
		isBlockedDate: function( date, settings ) {
			var dateStr;

			// Check against blackout dates.
			if ( settings.datesDisabled && settings.datesDisabled.length ) {
				dateStr = frmDatepickerInstance.formatDate( date, 'yy-mm-dd' );
				if ( -1 !== settings.datesDisabled.indexOf( dateStr ) ) {
					return true;
				}
			}

			// Check against days of the week.
			if ( settings.daysEnabled && settings.daysEnabled.length ) {
				if ( -1 === settings.daysEnabled.indexOf( date.getDay() ) ) {
					return true;
				}
			}

			return false;
		},

		/**
		 * Gets the next available date.
		 *
		 * @param {Object}  date     Date object.
		 * @param {Object}  settings Normalized field settings.
		 * @param {Boolean} isMinus  Is minus date.
		 * @return {Object}
		 */
		getNextAvailableDate: function( date, settings, isMinus ) {
			while ( this.isBlockedDate( date, settings ) ) {
				date.setDate( isMinus ? ( date.getDate() - 1 ) : ( date.getDate() + 1 ) );
			}

			return date;
		},

		setInlineDatepickerAfterCalc: function() {
			document.addEventListener( 'frmCalcUpdatedTotal', function( event ) {
				var hiddenInput;

				if ( ! event.frmData || ! event.frmData.totalField || ! event.frmData.totalField.length ) {
					return;
				}

				if ( ! event.frmData.totalField.hasClass( 'frm_date_inline' ) ) {
					return;
				}

				hiddenInput = event.frmData.totalField.prev();
				if ( 0 === hiddenInput[0].name.indexOf( 'item_meta[' ) ) {
					hiddenInput.val( event.frmData.total );
				}
				frmDatepickerInstance.setDate( event.frmData.totalField[0], event.frmData.total );
			});
		},

		resetInlineDatepickerAfterStartOver: function() {
			/**
			 * Gets default date from the input.
			 *
			 * @param {HTMLElement} input Input element.
			 * @returns {Date|null}
			 */
			function getDefaultVal( input ) {
				var val = input.getAttribute( 'data-frmval' );
				if ( ! val ) {
					return null;
				}

				val = new Date( val );
				if ( isNaN( val ) ) {
					return null;
				}

				return removeTimezoneFromDate( val );
			}

			function removeTimezoneFromDate( date ) {
				var offset = date.getTimezoneOffset() * 60000; // getTimezoneOffset() return minutes.
				date.setTime( date.getTime() + offset );
				return date;
			}

			document.addEventListener( 'frm_after_start_over', function( event ) {
				var datepickerEls, i, defaultVal, currentCell;

				datepickerEls = document.querySelectorAll( '#frm_form_' + event.frmData.formId + '_container .frm_date_inline' );
				if ( ! datepickerEls ) {
					return;
				}

				for ( i = 0; i < datepickerEls.length; i++ ) {
					defaultVal = getDefaultVal( datepickerEls[ i ].previousElementSibling );
					frmDatepickerInstance.setDate( datepickerEls[ i ], defaultVal );
					if ( ! defaultVal ) {
						// Reset styling of the current date cell if no default date.
						currentCell = datepickerEls[ i ].querySelector( '.ui-datepicker-today' );
						if ( currentCell ) {
							currentCell.classList.remove( 'ui-datepicker-current-day' );
							currentCell.querySelector( 'a' ).classList.remove( 'ui-state-active' );
						}
					}
				}
			});
		},

		init: function() {
			if ( 'undefined' === typeof window.__frmDatepicker || ! window.__frmDatepicker ) {
				return;
			}

			frmdates.setupFields();

			$( document ).on( 'frmPageChanged frmFormComplete frmAfterAddRow frmAfterRemoveRow', frmdates.setupFields );
			$( document ).on( 'frmdates_date_changed', frmdates.callbacks.dateChanged );

			this.setInlineDatepickerAfterCalc();
			this.resetInlineDatepickerAfterStartOver();
		},

		defaultDateOffset: function( fieldConfig, localConfig ) {
			var isAllowed,
				defaultDate = fieldConfig.datepickerOptions.defaultDate,
				minDate = fieldConfig.datepickerOptions.minDate;

			if ( null === defaultDate || '' === defaultDate ) {
				return;
			}

			defaultDate = new Date( defaultDate );
			if ( minDate && defaultDate < minDate ) {
				defaultDate = minDate;
			}

			do {
				isAllowed = fieldConfig.datepickerOptions.beforeShowDay( defaultDate );
				isAllowed = isAllowed[0];

				if ( false === isAllowed ) {
					defaultDate = frmdates.defaultDate( defaultDate );
				}
			}
			while ( false === isAllowed );

			localConfig.defaultDate = new Date( defaultDate.toISOString().slice( 0, -1 ) );
		},

		defaultDate: function( _date ) {
			_date.setDate( _date.getDate() + 1 );
			return _date;
		},

		callbacks: {
			beforeShowDay: function( date ) {
				var day, year, month, day_, dateISO, d, y, m,
					isAllowed = false;

				if ( ! date ) {
					return [ true, '' ];
				}

				day     = date.getDay();
				year    = date.getFullYear();
				month   = ( '0' + ( date.getMonth() + 1 ) ).slice( -2 );
				day_    = ( '0' + date.getDate() ).slice( -2 );
				dateISO = year + '-' + month + '-' + day_;

				y = year;
				d = date.getDate();
				m = date.getMonth() + 1;

				if ( -1 !== $.inArray( dateISO, this.datesEnabled ) ) {
					isAllowed = true;
				} else if ( -1 !== $.inArray( dateISO, this.datesDisabled ) ) {
					isAllowed = false;
				} else if ( -1 !== $.inArray( day, this.daysEnabled ) ) {
					isAllowed = true;
				}

				return [ isAllowed && eval( this.selectableResponse ), '' ];
			},

			// TODO: Remove this once we're sure Flatpickr is always available.
			onSelect: function( dateText, instance ) {
				var field, fieldId, mockEventObject;
				field = instance.input.get( 0 );
				fieldId = frmdates.getFieldIdFromField( field );
				mockEventObject = {
					currentTarget: field,
					type: 'change',
					target: field
				};

				$( document ).trigger( 'frmdates_date_changed', [ this, dateText, instance ]);
				$( document ).trigger( 'frmFieldChanged', [ field, fieldId, mockEventObject ]);
				instance.input.trigger( 'change' );
			},

			dateChanged: function() {
				frmdates.setupFields(); // TODO: For now, we refresh everything, but we should be more clever here.
			}
		},

		getFieldIdFromField: function( field ) {
			var $parentFormField, strippedFieldIdString, fieldIdParts;

			$parentFormField = jQuery( field ).closest( '.frm_form_field' );
			strippedFieldIdString = $parentFormField.attr( 'id' ).replace( 'frm_field_', '' ).replace( '_container', '' );
			fieldIdParts = strippedFieldIdString.split( '-' );

			return fieldIdParts[0];
		}
	};

	frmdates.init();

	window.frmProGetCalcTotaldate = function( thisFullCalc ) {
		var parsedData, resultDate,
			settings = frmdates.getDateSettingsFromFieldId( this.field_id );
		if ( ! settings ) {
			return '';
		}

		parsedData = frmdates.parseCalcStr( thisFullCalc, this, settings );
		if ( ! parsedData ) {
			return '';
		}

		resultDate = frmdates.applyDateOffset( parsedData.start, parsedData.diff, settings );

		return frmDatepickerInstance.formatDate( resultDate, null, settings.datepickerOptions );
	};

	window.frmCalcDateDifferenceDays = function( a, b, fieldId, compareId ) {
		var fieldSettings, compareSettings, swap, swapped, numberOfDays, currentDate, currentDateIsBlockedForSetting, currentDateIsBlocked;

		fieldSettings   = frmdates.getDateSettingsFromFieldId( parseInt( fieldId ) );
		compareSettings = frmdates.getDateSettingsFromFieldId( parseInt( compareId ) );

		if ( ! fieldSettings && ! compareSettings ) {
			return Math.floor( b - a ) / 86400000;
		}

		// Make sure a is always the lowest value.
		// This is because we loop from a to b.
		// If this gets swapped, the final result is returned as a negative value.
		swapped = false;
		if ( a > b ) {
			swapped = true;
			swap    = b;
			b       = a;
			a       = swap;
		}

		currentDateIsBlockedForSetting = function( settings ) {
			return settings && settings.skipBlockedDatesFromCalc && frmdates.isBlockedDate( currentDate, settings );
		};

		currentDateIsBlocked = function() {
			return currentDateIsBlockedForSetting( fieldSettings ) || currentDateIsBlockedForSetting( compareSettings );
		};

		// Count all of the dates that are not blocked.
		currentDate  = a;
		numberOfDays = 0;
		while ( currentDate < b ) {
			if ( ! currentDateIsBlocked() ) {
				++numberOfDays;
			}
			currentDate.setDate( currentDate.getDate() + 1 );
		}

		if ( swapped ) {
			numberOfDays = -numberOfDays;
		}

		return numberOfDays;
	};
});

/**
 * Datepicker instance.
 *
 * @param {HTMLElement} dateInput Date input element.
 * @param {Object} config Datepicker config.
 * @return {Object}
 */
function frmDatepickerInstance( dateInput = null, config = {}) {

	const _this = this;

	this.isFlatpickrOn = frmDatepickerInstance.isFlatpickrOn();

	if ( this.isFlatpickrOn ) {
		this.instance = null !== dateInput && 'undefined' !== typeof dateInput[0] && 'undefined' !== typeof dateInput[0]._flatpickr ? dateInput[0]._flatpickr : new frmProForm.frmDatepicker( dateInput[0], config );
	} else {
		// TODO: Remove this once we're sure flatpickr is always available.
		this.instance = null !== dateInput ? dateInput : jQuery;
	}

	/**
	 * Initializes the datepicker instance.
	 *
	 * @param {Object} localConfig Datepicker config.
	 */
	this.initInstance = function( fieldConfig, localConfig ) {
		if ( _this.isFlatpickrOn ) {
			const dateInputEl = null !== dateInput && 'undefined' !== typeof dateInput[0] ? dateInput[0] : dateInput;
			if ( null !== dateInputEl ) {
				if ( dateInputEl._flatpickr ) {
					_this.instance.destroy();
					dateInputEl._flatpickr.destroy();
					delete dateInputEl._flatpickr;
				}
				_this.instance = new frmProForm.frmDatepicker( dateInputEl, fieldConfig );
			}
			return;
		}

		// TODO: Remove this once we're sure flatpickr is always available.
		_this.instance.datepicker( localConfig );
	};

	/**
	 * Sets the date.
	 *
	 * @param {Date} date Date object.
	 */
	this.setDate = function( date ) {
		if ( _this.isFlatpickrOn ) {
			_this.instance.setDate( date );
		} else {
			// TODO: Remove this once we're sure flatpickr is always available.
			_this.instance.datepicker( 'setDate', date );
		}
	};

	/**
	 * Gets the date format.
	 *
	 * @return {String}
	 */
	this.getDateFormat = function() {
		if ( _this.isFlatpickrOn ) {
			return _this.instance.config.dateFormat;
		}
		// TODO: Remove this once we're sure flatpickr is always available.
		return _this.instance.datepicker( 'option', 'dateFormat' );
	};

	/**
	 * Gets the date.
	 *
	 * @return {Date}
	 */
	this.getDate = function() {
		if ( _this.isFlatpickrOn ) {
			if ( true === _this.instance.config.altInput ) {
				return _this.instance.config.altInputElement.value;
			}
			return _this.instance.getDate();
		}
		// TODO: Remove this once we're sure flatpickr is always available.
		return _this.instance.datepicker( 'getDate' );
	};

	/**
	 * Updates the datepicker config.
	 *
	 * @param {Object} config Datepicker config.
	 */
	this.updateConfig = function( config ) {
		if ( _this.isFlatpickrOn ) {
			_this.instance.config = config;
		} else {
			// TODO: Remove this once we're sure flatpickr is always available.
			_this.instance.datepicker( 'option', config );
		}
	};

	return {
		instance: this.instance,
		initInstance: this.initInstance,
		setDate: this.setDate,
		getDate: this.getDate,
		getDateFormat: this.getDateFormat,
		updateConfig: this.updateConfig
	};
}

/**
 * Parses a date string.
 *
 * @param {String} dateStr Date string.
 * @param {String} format Date format.
 * @param {Object} options Options.
 * @return {Date}
 */
frmDatepickerInstance.parseDate = function( dateStr, format, options = {}) {
	if ( frmDatepickerInstance.isFlatpickrOn() ) {
		const flatPickrDateFormat = format || options.fpDateFormat;
		return flatpickr.parseDate( dateStr, flatPickrDateFormat );
	}

	// TODO: Remove this once we're sure flatpickr is always available.
	const dateFormat = format || options.dateFormat;
	return jQuery.datepicker.parseDate( dateFormat, dateStr );
};

/**
 * Checks if Flatpickr is available.
 *
 * @return {Boolean}
 */
frmDatepickerInstance.isFlatpickrOn = function() {
	return window.frm_js && frm_js.datepickerLibrary === 'flatpickr';
};

/**
 * Sets the date.
 *
 * @param {HTMLElement} input Input element.
 * @param {Date} dateValue Date value.
 */
frmDatepickerInstance.setDate = function( input, dateValue ) {
	if ( frmDatepickerInstance.isFlatpickrOn() ) {
		input._flatpickr.setDate( dateValue );
	} else {
		// TODO: Remove this once we're sure flatpickr is always available.
		jQuery( input ).datepicker( 'setDate', dateValue );
	}
};

/**
 * Gets the date.
 *
 * @param {HTMLElement} input Input element.
 * @return {Date}
 */
frmDatepickerInstance.getDate = function( input ) {
	if ( frmDatepickerInstance.isFlatpickrOn() ) {
		return input._flatpickr.getDate();
	}
	// TODO: Remove this once we're sure flatpickr is always available.
	return jQuery( input ).datepicker( 'getDate' );
};

/**
 * Sets the alt field.
 *
 * @param {HTMLElement} input Input element.
 * @param {HTMLElement} altField Alt field element.
 */
frmDatepickerInstance.setAltField = function( input, altField ) {
	if ( frmDatepickerInstance.isFlatpickrOn() ) {
		input._flatpickr.config.altInput = true;
		input._flatpickr.altInput = altField[0];
	} else {
		// TODO: Remove this once we're sure flatpickr is always available.
		jQuery( input ).datepicker( 'option', 'altField', jQuery( altField ) );
	}
};

/**
 * Formats a date.
 *
 * @param {Date} date Date object.
 * @param {String} format Date format.
 * @param {Object} options Options.
 * @return {String}
 */
frmDatepickerInstance.formatDate = function( date, format, options = {}) {
	if ( frmDatepickerInstance.isFlatpickrOn() ) {
		const flatPickrDateFormat = format || options.fpDateFormat;
		return flatpickr.formatDate( date, flatPickrDateFormat );
	}

	// TODO: Remove this once we're sure flatpickr is always available.
	const dateFormat = format || options.dateFormat;
	return jQuery.datepicker.formatDate( dateFormat, date );
};

/**
 * Handles the date change event in Flatpickr.
 *
 * @param {Array} selectedDates Selected dates.
 * @param {String} dateText Date text.
 * @param {Object} instance Instance.
 */
frmDatepickerInstance.onChange = function( selectedDates, dateText, instance ) {
	var field, fieldId, mockEventObject;
	field = instance.element;
	fieldId = frmdates.getFieldIdFromField( field );
	mockEventObject = {
		currentTarget: field,
		type: 'change',
		target: field
	};

	$( document ).trigger( 'frmdates_date_changed', [ this, dateText, instance ]);
	$( document ).trigger( 'frmFieldChanged', [ field, fieldId, mockEventObject ]);
	jQuery( field ).trigger( 'change' );
};
