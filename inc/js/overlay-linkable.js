// Script module (see functions.php): runs after the DOM is parsed, so no ready wrapper.
import { Dialog } from 'bootstrap';

const overlayList = document.querySelectorAll( '[data-modal-type="overlay"]' );

const params = new URLSearchParams( window.location.search );
const path = window.location.pathname;
const currentView = params.get( 'overlay' );

// If the URL query string contains an overlay parameter, show the dialog.
if ( currentView && document.getElementById( currentView ) ) {
	Dialog.getOrCreateInstance( document.getElementById( currentView ) ).show();
}

overlayList.forEach( ( overlayListEl ) => {
	// When a dialog opens, put its id in the URL so the view is linkable.
	overlayListEl.addEventListener( 'show.bs.dialog', ( e ) => {
		const nextTab = e.target.getAttribute( 'id' ).replace( '-modal', '' );

		// Update the URL query string with the new tab view.
		params.set( 'overlay', nextTab );

		// Update the URL visibly without changing the browser history.
		window.history.replaceState( {}, '', path + '?' + params.toString() );
	} );

	// When the dialog is hidden, remove the query string from the URL.
	overlayListEl.addEventListener( 'hide.bs.dialog', () => {
		// Update the URL visibly without changing the browser history.
		window.history.replaceState( {}, '', path );
	} );
} );
