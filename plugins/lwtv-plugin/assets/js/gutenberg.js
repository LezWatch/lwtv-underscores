/**
 * Force the post editor's fullscreen mode and welcome guide off.
 *
 * Reads and writes the preferences store directly rather than going through
 * the core/edit-post store, so it doesn't depend on edit-post having
 * registered first. That order isn't fixed now that WordPress 7.2 loads admin
 * scripts individually instead of concatenated. A preference that was never
 * set reads as undefined, and core/edit-post fills in its defaults (both on)
 * later, so we set an explicit false until one is stored. We only write when
 * the stored value isn't already false, because every write is persisted to
 * user meta over REST.
 *
 * @param {Object} wp The WordPress global.
 */
( function ( wp ) {
	wp.domReady( function () {
		const preferences = wp.data.select( 'core/preferences' );
		const setPreference = wp.data.dispatch( 'core/preferences' ).set;

		[ 'fullscreenMode', 'welcomeGuide' ].forEach( function ( feature ) {
			if ( false !== preferences.get( 'core/edit-post', feature ) ) {
				setPreference( 'core/edit-post', feature, false );
			}
		} );
	} );
} )( window.wp );
