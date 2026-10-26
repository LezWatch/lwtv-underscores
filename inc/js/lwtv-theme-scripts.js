// Smooths scrolling for TOC
jQuery( document ).ready( function ( $ ) {
	$( 'a.smoothscroll.breadcrumb-item[href*="#"]:not([href="#"])' ).click(
		function () {
			if (
				location.pathname.replace( /^\//, '' ) ===
					this.pathname.replace( /^\//, '' ) &&
				location.hostname === this.hostname
			) {
				let target = $( this.hash );
				target = target.length
					? target
					: $( '[name=' + this.hash.slice( 1 ) + ']' );
				if ( target.length ) {
					$( 'html, body' ).animate(
						{
							scrollTop: target.offset().top - 55,
						},
						1000
					);
					return false;
				}
			}
		}
	);
} );

// Tooltips are initialized by the bootstrap-init.js script module.
