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

	// Year-bar charts: hovering a bar shows that year's value in the corner
	// readout (top-right), so 3-digit numbers stay readable instead of being
	// cramped onto a thin bar. Leaving the strip restores the default readout.
	function wireYearbars( root ) {
		root = root || document;
		var cards = Array.prototype.slice.call( root.querySelectorAll( '.lwtv-yearbars-card' ) );

		cards.forEach( function ( card ) {
			var strip = card.querySelector( '.lwtv-yearbars' );
			var numEl = card.querySelector( '.lwtv-yearbars-avg-num' );
			var subEl = card.querySelector( '.lwtv-yearbars-avg-sub' );
			if ( ! strip || ! numEl || ! subEl ) {
				return;
			}

			// Cache the default from data-count-to (textContent may be mid-animation).
			var defNum = numEl.getAttribute( 'data-count-to' ) ? finalText( numEl ) : numEl.textContent;
			var defSub = subEl.textContent;
			var fmt    = strip.getAttribute( 'data-hover-sub' ) || '%s';

			Array.prototype.slice.call( strip.querySelectorAll( '.lwtv-yearbar' ) ).forEach( function ( bar ) {
				bar.addEventListener( 'mouseenter', function () {
					numEl.textContent = ( parseInt( bar.getAttribute( 'data-count' ), 10 ) || 0 ).toLocaleString();
					subEl.textContent = fmt.replace( '%s', bar.getAttribute( 'data-year' ) || '' );
				} );
			} );

			strip.addEventListener( 'mouseleave', function () {
				numEl.textContent = defNum;
				subEl.textContent = defSub;
			} );
		} );
	}

	// The By Name / By Country jump bar is sticky, and its height changes with
	// viewport width (the A–Z chips wrap across 1–3 rows and the eyebrow can take
	// its own line). Set each pane's --lwtv-sb-offset from the *measured* bar
	// height so a jump lands the group's key just below the bar at any width; the
	// rows' scroll-margin-top reads it. A fixed CSS value can't track the wrap.
	function setJumpOffsets( root ) {
		root = root || document;
		Array.prototype.slice.call( root.querySelectorAll( '.lwtv-ty-sb-jump' ) ).forEach( function ( bar ) {
			if ( ! bar.offsetHeight ) {
				return; // Bar sits in a hidden tab-pane; measured when its tab is shown.
			}
			var top    = parseFloat( window.getComputedStyle( bar ).top ) || 0;
			var offset = Math.round( top + bar.offsetHeight + 8 );
			var pane   = bar.closest( '.tab-pane' ) || bar.parentNode;
			pane.style.setProperty( '--lwtv-sb-offset', offset + 'px' );
		} );
	}

	function init() {
		// Static pages (e.g. /statistics/): animate the whole document once.
		animate( document );
		wireYearbars( document );

		// Keep the sticky-jump-bar scroll offset in sync with the bar's height.
		setJumpOffsets( document );
		window.addEventListener( 'resize', function () {
			setJumpOffsets( document );
		} );
		// A pane's bar has no height until its tab is shown — remeasure then.
		document.addEventListener( 'shown.bs.tab', function () {
			setJumpOffsets( document );
		} );

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
