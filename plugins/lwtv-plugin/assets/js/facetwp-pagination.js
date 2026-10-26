// Pagination Scrolling
//
// Source: https://facetwp.com/how-to-add-pagination-scrolling/
//
// FacetWP is ajax-based, meaning pagination happens without a full page reload.
// The trade-off is that the browser won’t automatically scroll to the top of
// the page upon pagination. This can be achieved with a bit of custom js.

( function ( $ ) {
	$( document ).on( 'facetwp-loaded', function () {
		if ( FWP.loaded ) {
			// Run only after the first page load
			$( 'html, body' ).animate(
				{
					scrollTop: $( '.facetwp-page-title' ), // Scroll to the top of the element with class "facetwp-page-title"
				},
				500
			);
		}
	} );
} )( jQuery );

// Refresh Warning
//
// Since FacetWP is Ajax based, having this there will make it more obvious
// a page is loading...

( function ( $ ) {
	// Translated label from wp_localize_script() in class-pagination.php.
	const label =
		( window.lwtvFacetwp && window.lwtvFacetwp.loading ) || 'Loading…';

	$( document ).on( 'facetwp-refresh', function () {
		// Bootstrap 6 spinner; the old Font Awesome icon rendered blank
		// because the theme doesn't load Font Awesome.
		const spinner = $(
			'<span class="spinner-border spinner-sm ms-2" role="status"></span>'
		).append( $( '<span class="visually-hidden"></span>' ).text( label ) );
		$( '.facetwp-count' ).empty().append( spinner );
	} );

	$( document ).on( 'facetwp-loaded', function () {
		$( '.facetwp-count' ).html( '' );
	} );
} )( jQuery );
