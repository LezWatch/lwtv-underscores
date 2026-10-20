/**
 * Bootstrap 6 opt-in components.
 *
 * Loaded as a script module (see functions.php). 'bootstrap' resolves through
 * the WordPress import map to inc/bootstrap/js/bootstrap.bundle.min.js, so this
 * shares the single Bootstrap instance instead of bundling another copy.
 *
 * Dialog, Drawer, Menu, Collapse and Tab wire themselves up from data-bs-*
 * attributes. Tooltips do not, so they are initialized here.
 */
import { Tooltip } from 'bootstrap';

const TOOLTIP_SELECTOR = '[data-bs-toggle="tooltip"]';

/**
 * Attach Bootstrap tooltips to every TOOLTIP_SELECTOR element.
 *
 * Markup comes from two places: server-rendered PHP (shortcodes, templates)
 * that exists when this module runs, and the Screener block's React
 * components, which may render after this module runs.
 */
function initTooltips() {
	// Lazy, delegated: build each tooltip on first hover/focus, so markup
	// rendered after this module runs (the Screener block) is covered too.
	const onFirstInteraction = ( event ) => {
		const el = event.target.closest?.( TOOLTIP_SELECTOR );
		if ( ! el || Tooltip.getInstance( el ) ) {
			return;
		}
		// The new instance binds its own hover/focus listeners, but this
		// event has already happened, so show it once by hand.
		Tooltip.getOrCreateInstance( el ).show();
	};

	document.addEventListener( 'mouseover', onFirstInteraction );
	document.addEventListener( 'focusin', onFirstInteraction );
}

initTooltips();
