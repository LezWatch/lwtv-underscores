jQuery(function($) {
	const searchCollapsible = document.getElementById( 'collapseSearch' );
	searchCollapsible.addEventListener( 'shown.bs.collapse', () => {
		document.getElementById( 'header-search' ).focus();
	})
});
