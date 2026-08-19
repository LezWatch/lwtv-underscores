// Search Box toggle
jQuery(function ($) {
	$('a[href="#search"]').on('click', function (event) {
		event.preventDefault();
		$('#search').addClass('open');
		$('#search > form > input[type="search"]').focus();
	});

	$('#search, #search button.close').on('click keyup', function (event) {
		if (
			event.target === this ||
			event.target.className === 'close' ||
			event.keyCode === 27
		) {
			$(this).removeClass('open');
		}
	});
});

// Focus on search textarea when opening via toggle
jQuery(function () {
	if (document.getElementById('collapseSearch')) {
		const searchCollapsible = document.getElementById('collapseSearch');
		searchCollapsible.addEventListener('shown.bs.collapse', () => {
			document.getElementById('header-search').focus();
		});
	}
});
