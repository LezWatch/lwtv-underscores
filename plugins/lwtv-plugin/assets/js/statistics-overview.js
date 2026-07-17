/**
 * Statistics animations: count-up numbers and grow-in bars.
 * Reads targets from data attributes; respects prefers-reduced-motion.
 * Exposes window.lwtvStatsCountUp(root) so modals can replay on open.
 */
( function () {
	'use strict';

	var DURATION = 1100;

	function easeOutCubic( t ) {
		return 1 - Math.pow( 1 - t, 3 );
	}

	function finalText( el ) {
		var target = parseInt( el.getAttribute( 'data-count-to' ), 10 ) || 0;
		return target.toLocaleString() + ( el.getAttribute( 'data-count-suffix' ) || '' );
	}

	function animate( root ) {
		root = root || document;
		var reduce  = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var numbers = Array.prototype.slice.call( root.querySelectorAll( '[data-count-to]' ) );
		var bars    = Array.prototype.slice.call( root.querySelectorAll( '[data-grow-to]' ) );

		if ( reduce ) {
			bars.forEach( function ( el ) {
				el.style.width = parseFloat( el.getAttribute( 'data-grow-to' ) ) + '%';
			} );
			numbers.forEach( function ( el ) {
				el.textContent = finalText( el );
			} );
			return;
		}

		numbers.forEach( function ( el ) {
			el.textContent = ( 0 ).toLocaleString() + ( el.getAttribute( 'data-count-suffix' ) || '' );
		} );

		var start = null;
		function step( ts ) {
			if ( null === start ) {
				start = ts;
			}
			var p = Math.min( ( ts - start ) / DURATION, 1 );
			var e = easeOutCubic( p );

			numbers.forEach( function ( el ) {
				var target = parseInt( el.getAttribute( 'data-count-to' ), 10 ) || 0;
				el.textContent = Math.round( e * target ).toLocaleString() + ( el.getAttribute( 'data-count-suffix' ) || '' );
			} );
			bars.forEach( function ( el ) {
				var target = parseFloat( el.getAttribute( 'data-grow-to' ) ) || 0;
				el.style.width = ( e * target ) + '%';
			} );

			if ( p < 1 ) {
				window.requestAnimationFrame( step );
			}
		}
		window.requestAnimationFrame( step );
	}

	window.lwtvStatsCountUp = animate;

	function init() {
		// Static pages (e.g. /statistics/): animate the whole document once.
		animate( document );

		// Bootstrap modals (e.g. actor Character Statistics): replay scoped to
		// the modal each time it opens.
		document.addEventListener( 'shown.bs.modal', function ( ev ) {
			if ( ev.target && ev.target.querySelector( '[data-count-to],[data-grow-to]' ) ) {
				animate( ev.target );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
