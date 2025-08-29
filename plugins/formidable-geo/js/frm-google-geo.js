/* global frmGeoSettings, window, console, google */

'use strict';

/**
 * Geolocation Google Places API.
 *
 * @since 1.0
 */
var FrmGeolocationGooglePlacesAPI = window.FrmGeolocationGooglePlacesAPI || ( function( document, window ) {

	/**
	 * List of fields with autocomplete feature.
	 *
	 * @type {Array}
	 */
	var fieldsPlaces = [],

		/**
		 * List of the states from asset/json/states.json file.
		 *
		 * @type {object}
		 */
		states = {},

		/**
		 * Geodecoder from Geolocation API which help to detect current place by latitude and longitude.
		 *
		 * @type {object}
		 */
		geocoder;

	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.0
		 */
		init: function() {
			app.setup();

			// Using jQuery as event is being triggered with jQuery.
			jQuery( document ).on( 'frmPageChanged frm_after_start_over', app.setup );
			document.addEventListener( 'frmAfterAddRepeaterRow', app.setup );
		},

		/**
		 * Setup the engine.
		 *
		 * @since 1.0
		 * @since 1.3.4 Added event parameter.
		 * @param {Object} event
		 */
		setup: function( event ) {
			if ( 'function' !== typeof google.maps.Geocoder ) {
				/**
				 * Delay calling app.setup until google.maps.Geocoder is defined to fix #114.
				 */
				setTimeout( () => {
					app.setup( event );
				}, 100 );
				return;
			}

			app.getFields();
			if ( ! fieldsPlaces.length ) {
				return;
			}

			app.initGeocoder();
			fieldsPlaces.forEach( function( currentFieldPlace ) {
				app.initMap( currentFieldPlace );
				app.initAutocomplete( currentFieldPlace );
			});
			app.detectGeolocation( event );
		},

		/**
		 * Show debug message.
		 *
		 * @since 1.0
		 *
		 * @param {string|object} message Debug message.
		 */
		showDebugMessage: function( message ) {
			if ( ! window.location.hash || '#frmdebug' !== window.location.hash ) {
				return;
			}

			console.log( message );
		},

		/**
		 * Closest function.
		 *
		 * @param {Element} el Element.
		 * @param {string} selector Parent selector.
		 *
		 * @returns {Element|undefined} Parent.
		 */
		closest: function( el, selector ) {
			var matchesSelector = el.matches || el.webkitMatchesSelector || el.mozMatchesSelector || el.msMatchesSelector;

			while ( el ) {
				if ( matchesSelector.call( el, selector ) ) {
					break;
				}
				el = el.parentElement;
			}
			return el;
		},

		/**
		 * Get all fields for geolocation.
		 *
		 * @since 1.0
		 */
		getFields: function() {
			var fields = Array.prototype.slice.call( document.querySelectorAll( '.frm_form_field input[type="text"][data-geoautocomplete="1"]' ) );
			var country = '';

			fields.forEach( function( el ) {

				var wrapper = el.closest( '.frm_form_field' ),
					isAddress = el.hasAttribute( 'data-geoisaddress' ),
					additionalFields = {},
					mapField;

				if ( isAddress ) {
					if ( el.name.indexOf( 'line1' ) === -1 ) {
						// only enable autocomplete on line1.
						return;
					}

					wrapper = wrapper.parentElement.closest( '.frm_form_field' );
				}

				mapField = el.hasAttribute( 'data-geoshowmap' ) ? wrapper.getElementsByClassName( 'frm-geolocation-map' )[0] : null;

				// set address details inputs.
				if ( isAddress ) {
					country = wrapper.querySelector( 'select[id*="_country"]' );
					additionalFields = {
						line2: {
							el: wrapper.querySelector( 'input[id*="_line2"]' ),
							type: 'long_name'
						},
						city: {
							el: wrapper.querySelector( 'input[id*="_city"]' ),
							type: 'long_name'
						},
						political: {
							el: wrapper.querySelector( '[id*="_state"]' ),
							type: 'long_name'
						},
						administrative_area_level_1: { // eslint-disable-line camelcase
							el: wrapper.querySelector( '[id*="_state"]' ),
							type: 'long_name'
						},
						state_abbreviation: {
							el: wrapper.querySelector( '[id*="_state_abbreviation"]' ),
							type: 'hidden'
						},
						postal_code_prefix: { // eslint-disable-line camelcase
							el: wrapper.querySelector( 'input[id*="_zip"]' ),
							type: 'long_name'
						},
						postal_code: { // eslint-disable-line camelcase
							el: wrapper.querySelector( 'input[id*="_zip"]' ),
							type: 'long_name'
						},
						country: {
							el: country,
							type: 'long_name'
						}
					};
				}
				fieldsPlaces.push({
					'searchField': el,
					'mapField': mapField,
					'type': isAddress ? 'address' : 'text',
					'additionalFields': additionalFields,
					'wrapper': wrapper
				});
			});
		},

		/**
		 * Retrieves city data from Google Places API result.
		 *
		 * @since 1.1
		 *
		 * @param {Array} addressComponents Array of address components from Google Places API.
		 * @returns {Object|null} An object containing data about the city or null if no city data is found.
		 */
		getCityComponent: function( addressComponents ) {
			var locality, administrativeAreaLevel3, sublocality, postalTown;

			locality = this.getComponentType( addressComponents, 'locality' );
			if ( locality ) {
				return locality;
			}

			administrativeAreaLevel3 = this.getComponentType( addressComponents, 'administrative_area_level_3' );
			if ( administrativeAreaLevel3 ) {
				return administrativeAreaLevel3;
			}

			sublocality = this.getComponentType( addressComponents, 'sublocality' );
			if ( sublocality ) {
				return sublocality;
			}

			postalTown = this.getComponentType( addressComponents, 'postal_town' );
			return postalTown;
		},

		getComponentType: function( components, type ) {
			var length, index;

			length = components.length;
			for ( index = 0; index < length; ++index ) {
				if ( -1 !== components[ index ].types.indexOf( type ) ) {
					return components[ index ];
				}
			}

			return false;
		},

		/**
		 * Init Google Map.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 */
		initMap: function( currentFieldPlace ) {
			var defaultLocation, zoom, lat, lng;

			if ( ! currentFieldPlace.mapField ) {
				return;
			}
			defaultLocation = frmGeoSettings.default_location;
			zoom = parseInt( frmGeoSettings.zoom );

			// Check if lat and lng already defined.
			lat = currentFieldPlace.wrapper.getElementsByClassName( 'frm-geo-lat' )[0].value;
			lng = currentFieldPlace.wrapper.getElementsByClassName( 'frm-geo-lng' )[0].value;
			if ( lat && lng ) {
				defaultLocation = new google.maps.LatLng( lat, lng );
			}

			currentFieldPlace.map = new google.maps.Map(
				currentFieldPlace.mapField,
				{
					zoom: zoom,
					center: defaultLocation,
					mapId: 'DEMO_MAP_ID'
				});

			currentFieldPlace.marker = new google.maps.marker.AdvancedMarkerElement(
				{
					position: defaultLocation,
					gmpDraggable: true,
					map: currentFieldPlace.map
				});

			currentFieldPlace.marker.addListener( 'dragend', app.markerDragend );
		},

		/**
		 * Init Google Geocoder.
		 *
		 * @since 1.0
		 */
		initGeocoder: function() {
			geocoder = new google.maps.Geocoder();
		},

		/**
		 * Action after marker was dragend.
		 *
		 * @since 1.0
		 *
		 * @param {object} marker Google Marker.
		 */
		markerDragend: function( marker ) {
			var currentFieldPlace = app.findFieldPlaceByMarker( this );

			if ( ! currentFieldPlace ) {
				return;
			}

			app.detectByCoordinates( currentFieldPlace, marker.latLng, false );
			currentFieldPlace.map.setCenter( marker.latLng );
		},

		/**
		 * Detect Place by latitude and longitude.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 * @param {object} latLng            Latitude and longitude.
		 * @param {bool} useResultsLatLng    Whether or not to use the passed lat/long or to use the results value.
		 * @returns {void}
		 */
		detectByCoordinates: function( currentFieldPlace, latLng, useResultsLatLng ) {
			if ( ! geocoder ) {
				return;
			}

			geocoder.geocode({ location: latLng }, function( results, status ) {
				if ( status !== 'OK' ) {
					app.showDebugMessage( 'Geocode was wrong' );
					app.showDebugMessage( results );
					return;
				}
				if ( ! results[ 0 ]) {
					return;
				}

				if ( ! useResultsLatLng ) {
					// Instead of using the lat/long from the result, use the exact value.
					results[0].geometry.location.lat = function() {
						return 'function' === typeof latLng.lat ? latLng.lat() : latLng.lat;
					};
					results[0].geometry.location.lng = function() {
						return 'function' === typeof latLng.lng ? latLng.lng() : latLng.lng;
					};
				}

				app.updateFields( currentFieldPlace, results[ 0 ]);

				if ( useResultsLatLng ) {
					app.updateMap( currentFieldPlace, results[ 0 ].geometry.location );
				}
			});
		},

		/**
		 * Update map.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 * @param {object} latLng Latitude and longitude.
		 */
		updateMap: function( currentFieldPlace, latLng ) {
			if ( ! currentFieldPlace.map ) {
				return;
			}

			currentFieldPlace.marker.position = latLng;
			currentFieldPlace.map.setCenter( latLng );
		},

		/**
		 * Find current group field with places API by Google marker.
		 *
		 * @since 1.0
		 *
		 * @param {object} marker Google marker.
		 *
		 * @returns {object|null} currentFieldPlace Current group field with places API.
		 */
		findFieldPlaceByMarker: function( marker ) {
			var currentFieldPlace = null;

			fieldsPlaces.forEach( function( el ) {

				if ( el.marker !== marker ) {
					return;
				}
				currentFieldPlace = el;
			});

			return currentFieldPlace;
		},

		/**
		 * Find current group field with places API by Google Autocomplete.
		 *
		 * @since 1.0
		 *
		 * @param {object} autocomplete Google Autocomplete.
		 *
		 * @returns {object|null} currentFieldPlace Current group field with places API.
		 */
		findFieldPlaceByAutocomplete: function( autocomplete ) {
			var currentFieldPlace = null;

			fieldsPlaces.forEach( function( el ) {

				if ( el.autocomplete !== autocomplete ) {
					return;
				}
				currentFieldPlace = el;
			});

			return currentFieldPlace;
		},

		/**
		 * Find current group field with places API by country field element.
		 *
		 * @since 1.0
		 *
		 * @param {object} countryEl Country field element.
		 *
		 * @returns {object|null} currentFieldPlace Current group field with places API.
		 */
		findFieldPlaceByCountry: function( countryEl ) {
			var currentFieldPlace = null;

			fieldsPlaces.forEach( function( el ) {

				if ( ! el.additionalFields || ! el.additionalFields.country || el.additionalFields.country.el !== countryEl ) {
					return;
				}

				currentFieldPlace = el;
			});

			return currentFieldPlace;
		},

		/**
		 * Find current group field with places API by state field element.
		 *
		 * @since 1.0
		 *
		 * @param {object} politicalEl State field element.
		 *
		 * @returns {object|null} currentFieldPlace Current group field with places API.
		 */
		findFieldPlaceByPolitical: function( politicalEl ) {

			var currentFieldPlace = null;

			fieldsPlaces.forEach( function( el ) {

				if ( ! el.additionalFields || ! el.additionalFields.political || el.additionalFields.political.el !== politicalEl ) {
					return;
				}

				currentFieldPlace = el;
			});

			return currentFieldPlace;
		},

		/**
		 * Init Google Autocomplete.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 */
		initAutocomplete: function( currentFieldPlace ) {

			currentFieldPlace.autocomplete = new google.maps.places.Autocomplete(
				currentFieldPlace.searchField,
				frmGeoSettings.autoCompleteOptions
			);

			currentFieldPlace.autocomplete.addListener( 'place_changed', app.updateFieldPlace );

			// Disable enter on input to submit the form.
			currentFieldPlace.searchField.addEventListener( 'keydown', function( e ) {
				if ( 13 === e.keyCode ) {
					e.preventDefault();
				}
			});

			if ( 'address' === currentFieldPlace.type ) {
				app.initAutocompleteAddress( currentFieldPlace );
			}
		},

		/**
		 * Sets component restrictions for a field place's autocomplete functionality.
		 *
		 * @since 1.3.2
		 *
		 * @param {object} currentFieldPlace
		 * @param {Boolean} isUSOnly
		 * @returns {void}
		 */
		setComponentRestrictions: function( currentFieldPlace, isUSOnly = false ) {
			if ( isUSOnly ) {
				currentFieldPlace.autocomplete.setComponentRestrictions({
					country: [ 'us' ]
				});
				return;
			}

			if ( ! currentFieldPlace.additionalFields.country.el || ! currentFieldPlace.additionalFields.country.el.dataset.defaultCountry ) {
				return;
			}
			currentFieldPlace.autocomplete.setComponentRestrictions({
				country: [ currentFieldPlace.additionalFields.country.el.dataset.defaultCountry ]
			});
		},

		/**
		 * Init address field autocomplete features.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 */
		initAutocompleteAddress: function( currentFieldPlace ) {
			var code, xhr;

			code = '';

			app.disableBrowserAutocomplete( currentFieldPlace.searchField );

			if ( currentFieldPlace.additionalFields.country.el ) {
				app.setComponentRestrictions( currentFieldPlace );
				currentFieldPlace.additionalFields.country.el.addEventListener( 'change', app.updateCountry );
				return;
			}

			// Only for US Address field.
			app.setComponentRestrictions( currentFieldPlace, true );

			if ( currentFieldPlace.additionalFields.political.el && frmGeoSettings.states ) {
				xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {

					if ( xhr.readyState === 4 && xhr.status === 200 ) {
						states = JSON.parse( xhr.responseText );
						for ( code in states ) {
							if ( ! Object.prototype.hasOwnProperty.call( states, code ) ) {
								continue;
							}

							delete states[ code ].name;
						}
					}
				};
				xhr.open( 'GET', frmGeoSettings.states );
				xhr.send();

				currentFieldPlace.additionalFields.political.el.addEventListener( 'change', app.updateArea );
			}

			if ( currentFieldPlace.additionalFields.political.el.addEventListener ) {
				currentFieldPlace.additionalFields.political.el.addEventListener( 'change', app.updateStateAbbreviation );
			}
		},

		updateStateAbbreviation: function() {
			var currentFieldPlace = app.findFieldPlaceByPolitical( this );
			var state = this.value.toString().toUpperCase();

			if ( ! currentFieldPlace || ! currentFieldPlace.autocomplete || ! currentFieldPlace.additionalFields.state_abbreviation.el ) {
				return;
			}

			geocoder.geocode({ address: state }, function( results, status ) {
				var i, place, addressType;
				if ( status === 'OK' ) {
					place = results[0];
					for ( i = 0; i < place.address_components.length; i++ ) {
						addressType = place.address_components[ i ].types[ 0 ];
						if ( 'administrative_area_level_1' !== addressType ) {
							continue;
						}

						currentFieldPlace.additionalFields.state_abbreviation.el.value = place.address_components[ i ].short_name;
					}
				} else {
					app.showDebugMessage( 'Geocode was not successful for the following reason: ' + status );
				}
			});
		},

		/**
		 * Disable Chrome browser autocomplete.
		 *
		 * @since 1.0
		 *
		 * @param {Element} searchField Search field.
		 */
		disableBrowserAutocomplete: function( searchField ) {

			var observerHack;

			if ( navigator.userAgent.indexOf( 'Chrome' ) === -1 ) {
				return;
			}

			observerHack = new MutationObserver( function() {
				observerHack.disconnect();
				searchField.setAttribute( 'autocomplete', 'chrome-off' );
			});

			observerHack.observe( searchField, {
				attributes: true,
				attributeFilter: [ 'autocomplete' ]
			});
		},

		/**
		 * Update field place when Google Autocomplete field fill.
		 *
		 * @since 1.0
		 */
		updateFieldPlace: function() {

			var currentFieldPlace = app.findFieldPlaceByAutocomplete( this );
			var place;

			if ( ! currentFieldPlace || ! currentFieldPlace.autocomplete ) {
				return;
			}

			place = currentFieldPlace.autocomplete.getPlace();

			if ( ! place.geometry || ! place.geometry.location ) {
				return;
			}

			app.updateMap( currentFieldPlace, place.geometry.location );
			app.updateFields( currentFieldPlace, place );
		},

		/**
		 * Update fields at specified place.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 * @param {object} place Current place.
		 */
		updateFields: function( currentFieldPlace, place ) {
			var lat, lng, latElem, lngElem, cityComponent, line2Component;

			if ( ! Object.prototype.hasOwnProperty.call( place, 'formatted_address' ) ) {
				return;
			}

			if ( 'text' === currentFieldPlace.type ) {
				app.updateTextField( currentFieldPlace, place );
			} else if ( 'address' === currentFieldPlace.type ) {
				app.clearAddressFields( currentFieldPlace );
				app.updateAddressField( currentFieldPlace, place );
			}

			// Update latitude and longitude hidden inputs.
			lat     = place.geometry.location.lat();
			lng     = place.geometry.location.lng();
			latElem = currentFieldPlace.wrapper.getElementsByClassName( 'frm-geo-lat' )[0];
			if ( latElem ) {
				latElem.value = lat;
			}
			lngElem = currentFieldPlace.wrapper.getElementsByClassName( 'frm-geo-lng' )[0];
			if ( lngElem ) {
				lngElem.value = lng;
			}

			// Extract city name from either 'locality' or 'administrative_area_level_3'
			cityComponent = app.getCityComponent( place.address_components );

			// If cityComponent is found, retrieve the corresponding field (either 'locality' or 'administrative_area_level_3')
			if ( cityComponent && currentFieldPlace.additionalFields.city ) {
				currentFieldPlace.additionalFields.city.el.value = cityComponent.long_name;
			}

			line2Component = app.getComponentType( place.address_components, 'subpremise' );
			if ( line2Component && -1 !== line2Component.long_name.indexOf( ' ' ) && currentFieldPlace.additionalFields.line2.el ) {
				currentFieldPlace.additionalFields.line2.el.value = line2Component.long_name;
			}

			app.triggerEvent( currentFieldPlace.searchField, 'change' );

			app.showDebugMessage( 'Fields was updated' );
			app.showDebugMessage( currentFieldPlace );
			app.showDebugMessage( place );
		},

		/**
		 * Update text field at specified place.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 * @param {object} place Current place.
		 */
		updateTextField: function( currentFieldPlace, place ) {
			var fieldValue = place.formatted_address;

			if ( place.name && -1 === fieldValue.indexOf( place.name ) ) {
				fieldValue = place.name + ', ' + fieldValue;
			}

			currentFieldPlace.searchField.value = fieldValue;
		},

		/**
		 * Trigger JS event.
		 *
		 * @since 1.0
		 *
		 * @param {Element} el Element.
		 * @param {string} eventName Event name.
		 */
		triggerEvent: function( el, eventName ) {

			var e = document.createEvent( 'HTMLEvents' );

			e.initEvent( eventName, true, true );
			el.dispatchEvent( e );
		},

		/**
		 * Update address fields at specified place.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 * @param {object} place Current place.
		 */
		updateAddressField: function( currentFieldPlace, place ) {

			var street = '',
				streetNumber = '',
				subpremise = '',
				i = 0,
				addressType = '';

			for ( i = 0; i < place.address_components.length; i++ ) {
				addressType = place.address_components[ i ].types[ 0 ];

				if ( 'subpremise' === addressType && -1 === place.address_components[ i ].long_name.indexOf( ' ' ) ) {
					subpremise = place.address_components[ i ].long_name;
				}

				if ( 'administrative_area_level_1' === addressType && currentFieldPlace.additionalFields.state_abbreviation.el ) {
					currentFieldPlace.additionalFields.state_abbreviation.el.value = place.address_components[ i ].short_name;
				}

				if ( -1 !== [ 'route', 'town_square', 'premise' ].indexOf( addressType ) ) {
					street = place.address_components[ i ].short_name;
					continue;
				}

				if ( 'street_number' === addressType ) {
					streetNumber = place.address_components[ i ].short_name;
					continue;
				}

				if ( currentFieldPlace.additionalFields[ addressType ] && currentFieldPlace.additionalFields[ addressType ].el ) {
					if ( 'country' === addressType ) {
						currentFieldPlace.additionalFields.country.el.addEventListener( 'change', app.updateCountry );

						currentFieldPlace.additionalFields[ addressType ].el.value = place.address_components[ i ][ currentFieldPlace.additionalFields[ addressType ].type ];

						// Map Czechia to Czech Republic.
						if ( '' === currentFieldPlace.additionalFields[ addressType ].el.value && 'Czechia' === place.address_components[ i ][ currentFieldPlace.additionalFields[ addressType ].type ]) {
							currentFieldPlace.additionalFields[ addressType ].el.value = 'Czech Republic';
						}

						if ( '' === currentFieldPlace.additionalFields[ addressType ].el.value ) {
							// Google map may not be in English.
							// If it is not, try to match country by country code instead.
							const countryCode     = place.address_components[ i ].short_name;
							const countryDropdown = currentFieldPlace.additionalFields[ addressType ].el;
							const countryOption   = countryDropdown.querySelector( 'option[data-code="' + countryCode + '"]' );
							if ( countryOption ) {
								countryDropdown.value = countryOption.getAttribute( 'value' );
							}
						}

						app.triggerEvent( currentFieldPlace.additionalFields.country.el, 'change' );
					} else {
						currentFieldPlace.additionalFields[ addressType ].el.value = place.address_components[ i ][ currentFieldPlace.additionalFields[ addressType ].type ];
					}
				}
			}

			if ( '' !== subpremise ) {
				if ( '' !== streetNumber ) {
					streetNumber = subpremise + '/' + streetNumber;
				} else {
					streetNumber = subpremise;
				}
			}

			if ( '' === streetNumber && '' === street && place.name ) {
				streetNumber = place.name;
			}

			currentFieldPlace.searchField.value = app.formatAddressField( place, streetNumber, street );
		},

		/**
		 * Clear all address fields.
		 *
		 * @since 1.0
		 *
		 * @param {object} currentFieldPlace Current group field with places API.
		 */
		clearAddressFields: function( currentFieldPlace ) {
			Object.keys( currentFieldPlace.additionalFields ).map( function( field ) {
				if ( currentFieldPlace.additionalFields[field].el ) {
					currentFieldPlace.additionalFields[field].el.value = '';
				}
			});
		},

		/**
		 * Get formatted address.
		 *
		 * @since 1.0
		 *
		 * @param {object} place Current place.
		 * @param {string} streetNumber Street number.
		 * @param {string} street Street name.
		 *
		 * @returns {string} Formatted address.
		 */
		formatAddressField: function( place, streetNumber, street ) {
			var address = 0 === place.formatted_address.indexOf( streetNumber ) ?
				streetNumber + ' ' + street : // US format.
				street + ', ' + streetNumber; // EU format.

			// Remove spaces and commas at the start or end of the string.
			return address.trim().replace( /,$|^,/g, '' );
		},

		/**
		 * Update country for address field. Conditional strict. Start work after CUSTOMER change a country field.
		 *
		 * @since 1.0
		 */
		updateCountry: function() {

			var currentFieldPlace = app.findFieldPlaceByCountry( this ),
				countryName = this.value.toString().toLocaleLowerCase(),
				countryCode;

			geocoder.geocode({ address: countryName }, function( results, status ) {

				if ( status === 'OK' ) {
					countryCode = results[0].address_components[0].short_name.toLocaleLowerCase();

					if ( ! currentFieldPlace || ! currentFieldPlace.autocomplete ) {
						return;
					}

					currentFieldPlace.autocomplete.setComponentRestrictions({
						country: [ countryCode ]
					});

					app.showDebugMessage( 'Autocomplete field restrict to country: ' + countryCode );
				} else {
					app.showDebugMessage( 'Geocode was not successful for the following reason: ' + status );
				}
			});
		},

		/**
		 * Update state for address field. Conditional not strict. Start work after CUSTOMER change a state field.
		 *
		 * @since 1.0
		 */
		updateArea: function() {

			var currentFieldPlace = app.findFieldPlaceByPolitical( this ),
				stateCode = this.value.toString().toUpperCase();

			if ( ! currentFieldPlace || ! currentFieldPlace.autocomplete ) {
				return;
			}

			if ( ! states[ stateCode ]) {
				return;
			}

			currentFieldPlace.autocomplete.setBounds( new google.maps.LatLngBounds( states[ stateCode ]) );

			app.showDebugMessage( 'Autocomplete field restrict to state: ' + stateCode );
		},

		isEditingEntry: function( currentFieldPlace ) {
			const entryIdEl = currentFieldPlace.searchField.closest( 'form' ).querySelector( 'input[name="id"]' );
			return entryIdEl && '' !== entryIdEl.value;
		},

		shouldSetLocationForFieldPlace: function( event, currentFieldPlace ) {
			return ! event ||
				'frmAfterAddRepeaterRow' !== event.type ||
				! event.hasOwnProperty( 'frmData' ) ||
				! event.frmData.hasOwnProperty( 'repeater' ) ||
				event.frmData.repeater.contains( currentFieldPlace.searchField );
		},

		/**
		 * Detect customer geolocation.
		 * @since 1.3.4 Added event parameter.
		 * @since 1.0
		 *
		 * @param {Object} event
		 */
		detectGeolocation: function( event ) {
			if ( ! fieldsPlaces ) {
				return;
			}

			if ( ! navigator.geolocation || ! frmGeoSettings.current_location ) {
				if ( app.isEditingEntry( fieldsPlaces[0]) ) {
					return;
				}
				for ( let i = 0; i < fieldsPlaces.length; i++ ) {
					const fieldId = fieldsPlaces[i].searchField.dataset.geofieldid;
					const lng = document.getElementById( `geo-lng-${fieldId}` );
					const lat = document.getElementById( `geo-lat-${fieldId}` );
					if ( ! lng || ! lat ) {
						continue;
					}
					if ( '' === lng.value && '' === lat.value ) { // Location is not set to a some place.
						app.useDefaultLocation();
						break;
					}
				}
				return;
			}

			navigator.geolocation.getCurrentPosition(
				function( position ) {
					var geolocation = {
						lat: position.coords.latitude,
						lng: position.coords.longitude
					};
					fieldsPlaces.forEach(
						function( currentFieldPlace ) {
							if ( ! app.shouldSetLocationForFieldPlace( event, currentFieldPlace ) ) {
								return;
							}
							app.updateMap( currentFieldPlace, geolocation );
							app.detectByCoordinates( currentFieldPlace, geolocation, true );
						}
					);
				},
				function() {
					app.useDefaultLocation();
				}
			);
		},

		/**
		 * @since 1.1
		 */
		useDefaultLocation: function() {
			var geolocation = frmGeoSettings.default_location;
			fieldsPlaces.forEach( function( currentFieldPlace ) {
				var fieldContainer, idInput;

				fieldContainer = currentFieldPlace.searchField.closest( '.frm_fields_container' );
				if ( fieldContainer ) {
					idInput = fieldContainer.querySelector( '[name="id"]' );
					if ( idInput && '' !== idInput.value ) {
						// Do not set the marker to default if we're editing an entry.
						return;
					}
				}

				app.updateMap( currentFieldPlace, geolocation );
			});
		}
	};

	// Provide access to public functions/properties.
	return app;

}( document, window ) );

/**
 * Use function callback for running throw Google JS API.
 *
 * @since 1.0
 */
function FrmGeolocationInitGooglePlacesAPI() {
	FrmGeolocationGooglePlacesAPI.init();
}
