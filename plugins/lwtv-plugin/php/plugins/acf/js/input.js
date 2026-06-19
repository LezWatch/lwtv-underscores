(function ($) {
	function initSlider(container) {
		var input  = container.querySelector('input[type="range"]');
		var output = container.querySelector('.lwtv-slider-value');
		if (!input || !output) return;
		// Prevent double-binding if already initialised.
		if (input.dataset.sliderInit) return;
		input.dataset.sliderInit = '1';
		input.addEventListener('input', function () {
			output.textContent = this.value;
		});
	}

	function initAll(root) {
		var els = (root || document).querySelectorAll('.lwtv-number-slider');
		for (var i = 0; i < els.length; i++) {
			initSlider(els[i]);
		}
	}

	// Run on DOM ready (covers the normal page-load case).
	$(function () { initAll(); });

	// Also hook ACF's append so sliders inside repeaters/flex-content work.
	if (typeof acf !== 'undefined' && typeof acf.add_action === 'function') {
		acf.add_action('append', function ($el) {
			$el.find('.lwtv-number-slider').each(function () { initSlider(this); });
		});
	}
}(jQuery));
