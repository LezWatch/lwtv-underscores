jQuery(function($) {
	const searchCollapsible = document.getElementById( 'collapseSearch' );
	searchCollapsible.addEventListener( 'shown.bs.collapse', () => {
		console.log( 'Search box opened' );
		document.getElementById( 'header-search' ).focus();
	})
});
