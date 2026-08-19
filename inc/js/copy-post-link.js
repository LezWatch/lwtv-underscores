/**
 * copy-post-link.js
 *
 * Progressive enhancement for the post meta permalink.
 *
 * The template renders a real <a href> so the control works with no JS at all
 * (and keeps right-click "Copy link address" / middle-click behaviour). When
 * the Clipboard API is available we swap the anchor for a <button>, because a
 * control that copies rather than navigates should not announce as a link.
 */

(function () {
	const SUCCESS_TIMEOUT = 2500;

	function announce(statusEl, message) {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = message;
		window.setTimeout(function () {
			if (statusEl.textContent === message) {
				statusEl.textContent = '';
			}
		}, SUCCESS_TIMEOUT);
	}

	function enhance(anchor) {
		const url = anchor.getAttribute('href');
		if (!url) {
			return;
		}

		const wrapper = anchor.parentNode;
		const statusEl = wrapper
			? wrapper.querySelector('.entry-meta-permalink__status')
			: null;

		const button = document.createElement('button');
		button.type = 'button';
		button.className = anchor.className;
		button.innerHTML = anchor.innerHTML;

		const label = button.querySelector('.entry-meta-permalink__label');
		if (label && label.dataset.copyLabel) {
			label.textContent = label.dataset.copyLabel;
		}

		if (anchor.hasAttribute('title')) {
			button.setAttribute('title', anchor.getAttribute('title'));
		}

		button.addEventListener('click', function () {
			navigator.clipboard.writeText(url).then(
				function () {
					announce(
						statusEl,
						statusEl && statusEl.dataset.copiedText
							? statusEl.dataset.copiedText
							: 'Link copied'
					);
				},
				function () {
					announce(
						statusEl,
						statusEl && statusEl.dataset.failedText
							? statusEl.dataset.failedText
							: 'Copy failed'
					);
				}
			);
		});

		anchor.replaceWith(button);
	}

	function init() {
		if (!navigator.clipboard || !navigator.clipboard.writeText) {
			return;
		}

		const anchors = document.querySelectorAll('a[data-lwtv-copy-link]');
		Array.prototype.forEach.call(anchors, enhance);
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
