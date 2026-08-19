/**
 * Airdate calendar agenda.
 *
 * Two jobs:
 *
 * 1. Resolve each episode's aired state against the visitor's own clock. The
 *    server renders a state derived from the day alone (past days aired, future
 *    days upcoming) because the processed calendar is cached for a day and page
 *    output may be cached too - so it cannot know whether something airing
 *    later today has aired yet. We refine every dot here.
 *
 * 2. Scroll the agenda to today. This is deliberately NOT done on load: yanking
 *    the page down before the visitor has seen the heading is disorienting, and
 *    it fights the browser's own scroll restoration. It runs when the visitor
 *    asks for it, or when they arrive on the #ep-agenda-today fragment.
 */
(function () {
	'use strict';

	const AIRED = 'is-aired';
	const TODAY = 'is-today';
	const UPCOMING = 'is-upcoming';

	/**
	 * Is the given date on the same calendar day as the reference date, in the
	 * visitor's local timezone?
	 *
	 * @param {Date} date      The date to test.
	 * @param {Date} reference The date to compare against, usually now.
	 * @return {boolean} True when both fall on the same local calendar day.
	 */
	function sameDay(date, reference) {
		return (
			date.getFullYear() === reference.getFullYear() &&
			date.getMonth() === reference.getMonth() &&
			date.getDate() === reference.getDate()
		);
	}

	/**
	 * Resolve every dot's aired state against the visitor's clock.
	 *
	 * @param {Element} root The agenda container.
	 */
	function paintDots(root) {
		const now = new Date();
		const dots = root.querySelectorAll('.ep-agenda-dot[data-airtime]');

		Array.prototype.forEach.call(dots, function (dot) {
			const airtime = new Date(dot.getAttribute('data-airtime'));

			if (isNaN(airtime.getTime())) {
				return;
			}

			let state;
			if (airtime <= now) {
				// Already aired - grey, regardless of which day it sits under.
				state = AIRED;
			} else if (sameDay(airtime, now)) {
				// Airing later today - solid accent.
				state = TODAY;
			} else {
				// A later date - hollow ring.
				state = UPCOMING;
			}

			dot.classList.remove(AIRED, TODAY, UPCOMING);
			dot.classList.add(state);
		});
	}

	function scrollToToday(root) {
		const marker = root.querySelector('#ep-agenda-today');

		if (!marker) {
			return;
		}

		marker.scrollIntoView({ block: 'start', behavior: 'smooth' });
	}

	function init() {
		const root = document.querySelector('[data-lwtv-agenda]');

		if (!root) {
			return;
		}

		paintDots(root);

		const jump = root.querySelector('[data-lwtv-agenda-jump]');
		if (jump) {
			jump.addEventListener('click', function (event) {
				event.preventDefault();
				scrollToToday(root);
			});
		}

		// Arriving on the fragment directly should still land on today, but let
		// the browser handle its own anchor jump first.
		if ('#ep-agenda-today' === window.location.hash) {
			window.requestAnimationFrame(function () {
				scrollToToday(root);
			});
		}

		// Repaint when the tab comes back into focus, so a page left open
		// overnight or across an airtime does not show stale dots.
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				paintDots(root);
			}
		});

		// And on a slow timer, for a tab that simply stays open and visible.
		window.setInterval(function () {
			paintDots(root);
		}, 60000);
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
