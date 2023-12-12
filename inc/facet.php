<?php
/**
 * FacetWP Compatibility File
 *
 * This file contains the custom tweaks made to Facet in order
 * to have it match Yikes' design.
 *
 * @link https://facetwp.com/
 *
 * @package LezWatch.TV
 */

/**
 * Adding ajaxy content to archives.
 *
 * FWP.facets.sortby_XXX
 *
 * Since Facet is so fast, the old PHP way of calculating sorted and titles
 * doesn't work anymore. Welcome to Javascript Hell.
 */
add_action( 'wp_head', 'lwtv_yikes_facetwp_add_labels', 100 );
function lwtv_yikes_facetwp_add_labels() {

	// If this isn't an archive, return early.
	if ( ! is_archive() ) {
		return;
	}
	?>
	<script>
	(function($) {
		$(document).on('facetwp-loaded', function() {

			var title = new Array();
			var fwp_sorted = '';
			var sortby = 'name_a_z';

			// Titles
			if ( FWP.facets.show_loved == 'yes' ) { title.push( 'We Love' ); }
			if ( FWP.facets.show_loved == 'no' ) { title.push( 'We Don\'t Love' ); }
			if ( FWP.facets.show_worthit == 'yes' ) { title.push( 'That Are Worth Watching' ); }
			if ( FWP.facets.show_worthit == 'no' ) { title.push( 'That Are Not Worth Watching' ); }
			if ( FWP.facets.show_stars != '' && typeof FWP.facets.show_stars != 'undefined' ) {
				if ( FWP.facets.show_stars.constructor == Array ) {
					var stars = FWP.facets.show_stars.join( ' & ' );
				} else {
					var stars = FWP.facets.show_stars;
				}
				title.push( 'With ' + stars + ' Stars ' );
			}

			if ( title[0] != null ) {
				$('.facetwp-title').html( 'TV Shows ' + title.join( ', ' ) + ' ' );
			}

			if ( typeof FWP.facets.sortby_shows !== 'undefined' && FWP.facets.sortby_shows[0] != null ) {
				sortby = FWP.facets.sortby_shows[0];
			} else if ( typeof FWP.facets.sortby_actors !== 'undefined' && FWP.facets.sortby_actors[0] != null ) {
				sortby = FWP.facets.sortby_actors[0];
			} else if ( typeof FWP.facets.sortby_chars !== 'undefined' && FWP.facets.sortby_chars[0] != null ) {
				sortby = FWP.facets.sortby_chars[0];
			}

			// Sorting Shows
			switch( sortby ) {
				case 'death_asc':
					var fwp_sorted = 'date of death (oldest to newest)';
					break;
				case 'death_desc':
					var fwp_sorted = 'date of death (most recent to oldest)';
					break;
				case 'birth_asc':
					var fwp_sorted = 'date of birth (oldest to newest)';
					break;
				case 'birth_desc':
					var fwp_sorted = 'date of birth (most recent to oldest)';
					break;
				case 'most_queers':
					var fwp_sorted = 'number of characters (descending)';
					break;
				case 'least_queers':
					var fwp_sorted = 'number of characters (ascending)';
					break;
				case 'most_dead':
					var fwp_sorted = 'number of dead characters (descending)';
					break;
				case 'least_dead':
					var fwp_sorted = 'number of dead characters (ascending)';
					break;
				case 'date_desc':
					var fwp_sorted = 'date added (newest to oldest)';
					break;
				case 'date_asc':
					var fwp_sorted = 'date added (oldest to newest)';
					break;
				case 'high_score':
					var fwp_sorted = 'score (high to low)';
					break;
				case 'low_score':
					fwp_sorted = 'score (low to high)';
					break;
				case 'newest':
					fwp_sorted = 'newest added to oldest';
					break;
				case 'oldest':
					fwp_sorted = 'oldest added to newest';
					break;
				case 'name_z_a':
					fwp_sorted = 'name (Z-A)';
					break;
				case 'name_a_z':
				default:
					fwp_sorted = 'name (A-Z)'
					break;
			}
			$('.facetwp-sorted').html( 'Sorted by ' + fwp_sorted + '.' );

		});
	})(jQuery);
	</script>
	<?php
}


/**
 * Facet filter for Actors.
 *
 * FWP.facets.sortby_actors
 */
function lwtv_yikes_facetwp_add_labels_actors() {

	if ( ! is_archive() ) {
		return;
	}
	?>
	<script>
	(function($) {
		$(document).on('facetwp-loaded', function() {

			var fwp_sorted = '';

			// Sorting Actors
			switch( FWP.facets.sortby_actors[0] ) {
				case 'death_asc':
					var fwp_sorted = 'date of death (oldest to newest)';
					break;
				case 'death_desc':
					var fwp_sorted = 'date of death (most recent to oldest)';
					break;
				case 'birth_asc':
					var fwp_sorted = 'date of birth (oldest to newest)';
					break;
				case 'birth_desc':
					var fwp_sorted = 'date of birth (most recent to oldest)';
					break;
				case 'most_queers':
					var fwp_sorted = 'number of characters (descending)';
					break;
				case 'least_queers':
					var fwp_sorted = 'number of characters (ascending)';
					break;
				case 'most_dead':
					var fwp_sorted = 'number of dead characters (descending)';
					break;
				case 'least_dead':
					var fwp_sorted = 'number of dead characters (ascending)';
					break;
				case 'date_desc':
					var fwp_sorted = 'date added (newest to oldest)';
					break;
				case 'date_asc':
					var fwp_sorted = 'date added (oldest to newest)';
					break;
				case 'high_score':
					var fwp_sorted = 'score (high to low)';
					break;
				case 'low_score':
					fwp_sorted = 'score (low to high)';
					break;
				case 'newest':
					fwp_sorted = 'newest added to oldest';
					break;
				case 'oldest':
					fwp_sorted = 'oldest added to newest';
					break;
				case 'name_z_a':
					fwp_sorted = 'name (Z-A)';
					break;
				case 'name_a_z':
				default:
					fwp_sorted = 'name (A-Z)'
					break;
			}
			$('.facetwp-sorted').html( 'Sorted by ' + fwp_sorted + '.' );

		});
	})(jQuery);
	</script>
	<?php
}

