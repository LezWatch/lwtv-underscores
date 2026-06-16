(function ($) {
	function initSlider(container) {
		var input  = container.querySelector('input[type="range"]');
		var output = container.querySelector('.lwtv-slider-value');
		if (!input || !output) return;
		input.addEventListener('input', function () {
			output.value = this.value;
		});
	}

	if (typeof acf !== 'undefined' && typeof acf.add_action === 'function') {
		acf.add_action('ready append', function ($el) {
			$el.find('.lwtv-number-slider').each(function () {
				initSlider(this);
			});
		});
	}
}(jQuery));
