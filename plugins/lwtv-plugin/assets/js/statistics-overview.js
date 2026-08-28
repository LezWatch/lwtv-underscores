/**
 * Statistics animations: count-up numbers and grow-in bars.
 * Reads targets from data attributes; respects prefers-reduced-motion.
 * Exposes window.lwtvStatsCountUp(root) so modals can replay on open.
 */
(function () {
	'use strict';

	const DURATION = 1100;

	function easeOutCubic(t) {
		return 1 - Math.pow(1 - t, 3);
	}

	function finalText(el) {
		const target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
		return (
			target.toLocaleString() +
			(el.getAttribute('data-count-suffix') || '')
		);
	}

	function animate(root) {
		root = root || document;
		const reduce = window.matchMedia(
			'(prefers-reduced-motion: reduce)'
		).matches;
		const numbers = Array.prototype.slice.call(
			root.querySelectorAll('[data-count-to]')
		);
		const bars = Array.prototype.slice.call(
			root.querySelectorAll('[data-grow-to]')
		);

		if (reduce) {
			bars.forEach(function (el) {
				el.style.width =
					parseFloat(el.getAttribute('data-grow-to')) + '%';
			});
			numbers.forEach(function (el) {
				el.textContent = finalText(el);
			});
			return;
		}

		numbers.forEach(function (el) {
			el.textContent =
				(0).toLocaleString() +
				(el.getAttribute('data-count-suffix') || '');
		});

		let start = null;
		function step(ts) {
			if (null === start) {
				start = ts;
			}
			const p = Math.min((ts - start) / DURATION, 1);
			const e = easeOutCubic(p);

			numbers.forEach(function (el) {
				const target =
					parseInt(el.getAttribute('data-count-to'), 10) || 0;
				el.textContent =
					Math.round(e * target).toLocaleString() +
					(el.getAttribute('data-count-suffix') || '');
			});
			bars.forEach(function (el) {
				const target = parseFloat(el.getAttribute('data-grow-to')) || 0;
				el.style.width = e * target + '%';
			});

			if (p < 1) {
				window.requestAnimationFrame(step);
			}
		}
		window.requestAnimationFrame(step);
	}

	window.lwtvStatsCountUp = animate;

	// Year-bar charts: hovering a bar shows that year's value in the corner
	// readout (top-right), so 3-digit numbers stay readable instead of being
	// cramped onto a thin bar. Leaving the strip restores the default readout.
	function wireYearbars(root) {
		root = root || document;
		const cards = Array.prototype.slice.call(
			root.querySelectorAll('.lwtv-yearbars-card')
		);

		cards.forEach(function (card) {
			const strip = card.querySelector('.lwtv-yearbars');
			const numEl = card.querySelector('.lwtv-yearbars-avg-num');
			const subEl = card.querySelector('.lwtv-yearbars-avg-sub');
			if (!strip || !numEl || !subEl) {
				return;
			}

			// Cache the default from data-count-to (textContent may be mid-animation).
			const defNum = numEl.getAttribute('data-count-to')
				? finalText(numEl)
				: numEl.textContent;
			const defSub = subEl.textContent;
			const fmt = strip.getAttribute('data-hover-sub') || '%s';

			Array.prototype.slice
				.call(strip.querySelectorAll('.lwtv-yearbar'))
				.forEach(function (bar) {
					bar.addEventListener('mouseenter', function () {
						numEl.textContent = (
							parseInt(bar.getAttribute('data-count'), 10) || 0
						).toLocaleString();
						subEl.textContent = fmt.replace(
							'%s',
							bar.getAttribute('data-year') || ''
						);
					});
				});

			strip.addEventListener('mouseleave', function () {
				numEl.textContent = defNum;
				subEl.textContent = defSub;
			});
		});
	}

	// The By Name / By Country jump bar is sticky, and its height changes with
	// viewport width (the A–Z chips wrap across 1–3 rows and the eyebrow can take
	// its own line). Set each pane's --lwtv-sb-offset from the *measured* bar
	// height so a jump lands the group's key just below the bar at any width; the
	// rows' scroll-margin-top reads it. A fixed CSS value can't track the wrap.
	function setJumpOffsets(root) {
		root = root || document;
		Array.prototype.slice
			.call(root.querySelectorAll('.lwtv-ty-sb-jump'))
			.forEach(function (bar) {
				if (!bar.offsetHeight) {
					return; // Bar sits in a hidden tab-pane; measured when its tab is shown.
				}
				const top = parseFloat(window.getComputedStyle(bar).top) || 0;
				const offset = Math.round(top + bar.offsetHeight + 8);
				const pane = bar.closest('.tab-pane') || bar.parentNode;
				pane.style.setProperty('--lwtv-sb-offset', offset + 'px');
			});
	}

	// CSV download tracking (MonsterInsights / GA4).
	//
	// MonsterInsights' automatic download tracking only fires when a link's PATH
	// ends in a tracked extension (.csv, .pdf, ...). Our download links are
	// `?download=csv` on a normal page URL — the CSV is streamed server-side via
	// Content-Disposition — so the client-side tracker never sees an extension
	// and ignores the click. We fire the standard GA4 `file_download` event
	// ourselves so these land in the same report as auto-tracked downloads.

	// Send a GA4 file_download event through whichever tracker MonsterInsights
	// exposed. Fails silently when analytics isn't present (ad-blockers, logged-in
	// admins with tracking off, etc.) — tracking is never required for the UI.
	function sendDownloadEvent(params) {
		let tracker = null;
		if ('function' === typeof window.gtag) {
			tracker = window.gtag;
		} else if ('function' === typeof window.__gtagTracker) {
			tracker = window.__gtagTracker;
		}

		if (!tracker) {
			return;
		}
		tracker('event', 'file_download', params);
	}

	// TODO(you): Build a human-meaningful file name for the GA4 `file_name`
	// dimension from the download link's href. This is the string you'll scan
	// for in GA's File Downloads report, so name it the way YOU think about
	// these exports.
	//
	// The href is one of these shapes (query order not guaranteed):
	//   /statistics/characters/on-air/?download=csv
	//   /statistics/actors/?download=csv
	//   /statistics/death/years/?download=csv
	//   /statistics/nations/on-air/?download=csv&nation=united-kingdom
	//   /statistics/stations/on-air/?download=csv&station=hbo
	//
	// `new URL( href )` is available:
	//   u.pathname                     -> "/statistics/nations/on-air/"
	//   u.searchParams.get( 'nation' ) -> "united-kingdom" (or null)
	//   u.searchParams.get( 'station')-> "hbo" (or null)
	//
	// Return e.g. "lwtv-characters-on-air.csv" or
	// "lwtv-nations-on-air-united-kingdom.csv". Decisions that are yours:
	//   - how much of the path to keep (drop the leading "statistics"?)
	//   - whether to append the nation/station slug (recommended — otherwise
	//     every country's export reports as one identical row)
	//   - separator + the "lwtv-" prefix, to match your other download names
	function buildCsvFileName(href) {
		let url;
		try {
			url = new URL(href, window.location.origin);
		} catch {
			return 'lwtv-statistics.csv'; // Malformed href — report a stable fallback.
		}

		// Path segments minus the leading "statistics", e.g.
		// "/statistics/nations/on-air/" -> [ "nations", "on-air" ].
		const parts = url.pathname.split('/').filter(function (seg) {
			return seg && 'statistics' !== seg;
		});

		// Single-nation/station exports carry their slug in the query string, so
		// append it — otherwise every country/network reports as one identical row.
		const slug =
			url.searchParams.get('nation') || url.searchParams.get('station');
		if (slug) {
			parts.push(slug);
		}

		const name = parts.length ? parts.join('-') : 'statistics';
		return 'lwtv-' + name + '.csv';
	}

	function trackCsvDownloads(root) {
		root = root || document;
		Array.prototype.slice
			.call(root.querySelectorAll('.lwtv-download-csv-btn'))
			.forEach(function (link) {
				if (link.hasAttribute('data-dl-tracked')) {
					return; // Already wired (guards against double-binding).
				}
				link.setAttribute('data-dl-tracked', '1');
				link.addEventListener('click', function () {
					sendDownloadEvent({
						file_name: buildCsvFileName(link.href),
						file_extension: 'csv',
						link_url: link.href,
						link_text: (link.textContent || '').trim(),
					});
				});
			});
	}

	function init() {
		// Static pages (e.g. /statistics/): animate the whole document once.
		animate(document);
		wireYearbars(document);

		// Wire CSV download links (present in the DOM even inside hidden tab panes).
		trackCsvDownloads(document);

		// Keep the sticky-jump-bar scroll offset in sync with the bar's height.
		setJumpOffsets(document);
		window.addEventListener('resize', function () {
			setJumpOffsets(document);
		});
		// A pane's bar has no height until its tab is shown — remeasure then.
		document.addEventListener('shown.bs.tab', function () {
			setJumpOffsets(document);
		});

		// Bootstrap modals (e.g. actor Character Statistics): replay scoped to
		// the modal each time it opens.
		document.addEventListener('shown.bs.modal', function (ev) {
			if (
				ev.target &&
				ev.target.querySelector('[data-count-to],[data-grow-to]')
			) {
				animate(ev.target);
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
