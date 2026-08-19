jQuery(function () {
	const overlayList = document.querySelectorAll(
		'div[data-modal-type="overlay"]'
	);

	const params = new URLSearchParams(window.location.search);
	const path = window.location.pathname;
	const currentView = params.get('overlay');

	// If the URL query string contains an overlay parameter, show the modal.
	if (currentView) {
		const showModal = new bootstrap.Modal(
			document.getElementById(currentView),
			{}
		);
		showModal.toggle();
	}

	overlayList.forEach((overlayListEl) => {
		// If the URL query string contains an overlay parameter, show the modal.
		overlayListEl.addEventListener('show.bs.modal', (e) => {
			const nextTab = e.target.getAttribute('id').replace('-modal', '');

			// Update the URL query string with the new tab view.
			params.set('overlay', nextTab);

			// Update the URL visibly without changing the browser history.
			window.history.replaceState({}, '', path + '?' + params.toString());
		});

		// When the modal is hidden, remove the query string from the URL.
		overlayListEl.addEventListener('hide.bs.modal', () => {
			// Update the URL visibly without changing the browser history.
			window.history.replaceState({}, '', path);
		});
	});
});
