jQuery(function($) {
	const calendarTabList = document.querySelectorAll('a[data-bs-toggle="tab"]')

	calendarTabList.forEach(calendarTabEl => {
		calendarTabEl.addEventListener( 'show.bs.tab', (e) => {
			var nextTab  = e.target.getAttribute('id').replace( '-tab', '' );
			const params = new URLSearchParams( window.location.search )

			// Update the URL query string with the new tab view.
			params.set( 'tvview', nextTab )

			// Update the URL visibly without changing the browser history.
			window.history.replaceState(
				{},
				'',
				window.location.pathname + '?' + params.toString()
			);
		})
	})
});
