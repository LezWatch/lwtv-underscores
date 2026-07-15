/**
 * Statistics Overview animations: count-up numbers and grow-in bars.
 * Reads targets from data attributes; respects prefers-reduced-motion.
 */
( function () {
	'use strict';

	var DURATION = 1100;

	function easeOutCubic( t ) {
		return 1 - Math.pow( 1 - t, 3 );
	}

	function run() {
		var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		var numbers = Array.prototype.slice.call( document.querySelectorAll( '[data-count-to]' ) );
		var bars    = Array.prototype.slice.call( document.querySelectorAll( '[data-grow-to]' ) );

		if ( reduce ) {
			bars.forEach( function ( el ) {
				el.style.width = parseFloat( el.getAttribute( 'data-grow-to' ) ) + '%';
			} );
			// Numbers already contain their final text server-side; leave as-is.
			return;
		}

		// Reset numbers to 0 before animating.
		numbers.forEach( function ( el ) {
			el.textContent = ( 0 ).toLocaleString();
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
				el.textContent = Math.round( e * target ).toLocaleString();
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

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
