window.onload = function () {
	const isFullscreenMode = wp.data
		.select('core/edit-post')
		.isFeatureActive('fullscreenMode');
	const isWelcomeGuide = wp.data
		.select('core/edit-post')
		.isFeatureActive('welcomeGuide');

	if (isFullscreenMode) {
		wp.data.dispatch('core/edit-post').toggleFeature('fullscreenMode');
	}

	if (isWelcomeGuide) {
		wp.data.dispatch('core/edit-post').toggleFeature('welcomeGuide');
	}
};
