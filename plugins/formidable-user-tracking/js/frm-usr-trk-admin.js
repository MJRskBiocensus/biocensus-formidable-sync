( function() {
	'use strict';

	const hideOrShowDateHistory = ( event ) => {
		if ( event.type === 'keydown' && ( event.key !== ' ' && event.key !== 'Enter' ) ) {
			return;
		}
		event.preventDefault();

		const target     = event.currentTarget;
		const dateIsOpen = target.dataset.open === target.innerText;
		let nextRow      = target.closest( 'tr' )?.nextElementSibling;

		while ( nextRow ) {
			if ( nextRow.classList?.contains( 'frm-usr-trk-date-row' ) ) {
				break;
			}
			nextRow.classList.toggle( 'frm_hidden', ! dateIsOpen );
			nextRow = nextRow.nextElementSibling;
		}
		target.querySelector( 'span' ).innerText = dateIsOpen ? target.dataset.close : target.dataset.open;
		target.classList.toggle( 'open', ! dateIsOpen );
		target.setAttribute( 'aria-expanded', dateIsOpen );
	};

	document.querySelectorAll( '.frm-usr-trk-toggle-date' ).forEach( ( toggleBtn ) => {
		toggleBtn.addEventListener( 'click', hideOrShowDateHistory );
		toggleBtn.addEventListener( 'keydown', hideOrShowDateHistory );
	});
}() );
