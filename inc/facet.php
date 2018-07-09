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

/*
 * Numeric Post Navigation
 *
 * Used by FacetWP on custom pages (roles)
 */
function lwtv_yikes_facet_numeric_posts_nav( $queery = 'wp_query', $count = null ) {

	if ( is_singular( 'post' ) ) {
		return;
	}

	if ( 'wp_query' === $queery ) {
		global $wp_query;
		$queery = $wp_query;
	}

	$posts_per_page = get_option( 'posts_per_page' );

	if ( null === $count ) {
		$post_type       = ( '' === $queery->post_type ) ? 'post' : $queery->post_type;
		$published_posts = wp_count_posts( $post_type )->publish;
		$page_number_max = ceil( $published_posts / $posts_per_page );
	} else {
		$published_posts = ceil( $count / $posts_per_page );
		$page_number_max = ceil( $count / $posts_per_page );
	}

	/** Stop execution if there's only 1 page */
	if ( $page_number_max <= 1 ) {
		return;
	}

	/** Listical calculations for how many pages */
	$paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;
	$max   = intval( $published_posts );

	/** Add current page to the array */
	if ( $paged >= 1 ) {
		$links[] = $paged;
	}

	/** If it's the first page, and we have at least 3 pages, we add +3 */
	if ( 1 === $paged && $max >= 3 ) {
		$links[] = $paged + 3;
	}

	/** If it's the last page, and we have at least 3 pages, we add -2 */
	if ( $paged === $max && $max >= 3 ) {
		$links[] = $paged - 2;
	}

	/** Add the pages around the current page to the array */
	if ( $paged >= 2 ) {
		$links[] = $paged - 1;
	}
	if ( ( $paged + 1 ) <= $max ) {
		$links[] = $paged + 1;
	}
	if ( ( $paged + 2 ) <= $max ) {
		$links[] = $paged + 2;
	}

	echo '<nav aria-label="Post Pages navigation" role="navigation"><ul class="pagination justify-content-center">';

	/** If we have previous posts, add previous navigation */
	if ( get_previous_posts_link() ) {
		// Add FIRST
		printf( '<li class="page-item first mr-auto"><a href="%s" class="page-link">%s</a></li>', esc_url( get_pagenum_link( 1 ) ), lwtv_yikes_symbolicons( 'caret-left-circle.svg', 'fa-chevron-circle-left' ) . ' First' );
		// Add PREVIOUS
		printf( '<li class="page-item previous">%s</li>', get_previous_posts_link( lwtv_yikes_symbolicons( 'caret-left.svg', 'fa-chevron-left' ) . ' Previous' ) );
	}

	/** Link to current page, plus pages based on listical */
	sort( $links );
	foreach ( (array) $links as $link ) {
		$class = ( $paged === $link ) ? ' active' : '';
		printf( '<li class="page-item%s"><a href="%s" class="page-link">%s</a></li>', esc_attr( $class ), esc_url( get_pagenum_link( $link ) ), (int) str_pad( $link, 2, '0', STR_PAD_LEFT ) );
	}

	/** Link to last page, plus next if necessary */
	if ( ! in_array( $max, $links, true ) ) {
		if ( ! in_array( $max - 1, $links, true ) ) {
			printf( '<li class="page-item next">%s</li>', get_next_posts_link( 'Next ' . lwtv_yikes_symbolicons( 'caret-right.svg', 'fa-chevron-right' ) ) );
		}

		$class = ( $paged === $max ) ? ' active' : '';
		printf( '<li class="page-item last ml-auto%s"><a href="%s" class="page-link">%s</a></li>', esc_attr( $class ), esc_url( get_pagenum_link( $max ) ), 'Last ' . lwtv_yikes_symbolicons( 'caret-right-circle.svg', 'fa-chevron-circle-right' ) );
	}

	/** Next Post Link */
	if ( get_next_posts_link() ) {
		printf( '<li class="page-item next">%s</li>', get_next_posts_link( 'Next ' . lwtv_yikes_symbolicons( 'caret-right.svg', 'fa-chevron-right' ) ) );
	}
	echo '</ul></nav>';

}

/**
 * Facet WP Pagination to match Yikes
 *
 * Used on character and show pages.
 */
function lwtv_yikes_facetwp_pager_html( $output, $params ) {
	$output      = '';
	$page        = $params['page'];
	$total_pages = $params['total_pages'];

	/** If there's only one page, return nothing */
	if ( $total_pages <= 1 ) {
		return $output;
	}

	/** Add current page to the array */
	if ( $page >= 1 ) {
		$links[] = $page;
	}

	/** If it's the first page, and we have at least 3 pages, we add +3 */
	if ( 1 === $page && $total_pages >= 3 ) {
		$links[] = $page + 3;
	}

	/** If it's the last page, and we have at least 3 pages, we add -2 */
	if ( $page === $total_pages && $total_pages >= 3 ) {
		$links[] = $page - 2;
	}

	/** Add the pages around the current page to the array */
	if ( $page >= 2 ) {
		$links[] = $page - 1;
	}
	if ( ( $page + 1 ) <= $total_pages ) {
		$links[] = $page + 1;
	}
	if ( ( $page + 2 ) <= $total_pages ) {
		$links[] = $page + 2;
	}

	$navigation = '<nav aria-label="Post Pages navigation" role="navigation"><ul class="pagination justify-content-center">';

	/** Link to first pages if we have previous pages */
	if ( $page > 1 ) {

		// Add link to first page
		$navigation .= '<li class="page-item first mr-auto page-link"><a class="facetwp-page" data-page="1">' . lwtv_yikes_symbolicons( 'caret-left-circle.svg', 'fa-chevron-circle-left' ) . ' First</a></li>';

		// Add link to previous page
		$navigation .= '<li class="page-item previous page-link"><a class="facetwp-page" data-page="' . ( $page - 1 ) . '">' . lwtv_yikes_symbolicons( 'caret-left.svg', 'fa-chevron-left' ) . ' Previous</a></li>';
	}

	/** Link to current page, plus pages in either direction if necessary */
	sort( $links );
	foreach ( (array) $links as $link ) {
		$class       = ( $page === $link ) ? ' active' : '';
		$navigation .= '<li class="page-item page-link' . $class . '"><a data-page="' . $link . '" class="facetwp-page' . $class . '">' . str_pad( (int) $link, 2, '0', STR_PAD_LEFT ) . '</a></li>';
	}

	/** Link to last page and next page if necessary */
	if ( ! in_array( $total_pages, $links, true ) ) {
		if ( ! in_array( $total_pages - 1, $links, true ) ) {
			$navigation .= '<li class="page-item page-link"><a class="facetwp-page" data-page="' . ( $page + 1 ) . '">Next ' . lwtv_yikes_symbolicons( 'caret-right.svg', 'fa-chevron-right' ) . '</a></li>';
		}

		$class       = ( $page === $total_pages ) ? ' active' : '';
		$navigation .= '<li class="page-item last ml-auto page-link"><a data-page="' . $total_pages . '" class="facetwp-page">Last ' . lwtv_yikes_symbolicons( 'caret-right-circle.svg', 'fa-chevron-circle-right' ) . '</a></li>';
	}

	$navigation .= '</ul></nav>';
	$output      = $navigation;

	return $output;
}

add_filter( 'facetwp_pager_html', 'lwtv_yikes_facetwp_pager_html', 10, 2 );


/**
 * Adding ajaxy content to archives.
 *
 * Since Facet is so fast, the old PHP way of calculating sorted and titles
 * doesn't work anymore. Welcome to Javascript Hell.
 */
function lwtv_yikes_facetwp_add_labels() {

	if ( ! is_archive() ) {
		return;
	}

	?>
	<script>
	(function($) {
		$(document).on('facetwp-loaded', function() {

			var title = new Array();

			// Titles
			if ( FWP.facets.show_loved == 'on' ) { title.push( 'We Love' ); }
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
				$('.facetwp-title').html( 'Shows ' + title.join( ', ' ) );
			}

			// Sorting
			switch( FWP.extras.sort ) {
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
				case 'title_desc':
					var fwp_sorted = 'name (Z-A)';
					break;
				case 'high_score':
					var fwp_sorted = 'score (high to low)';
					break;
				case 'low_score':
					var fwp_sorted = 'score (low to high)';
					break;
				case 'title_asc':
				default:
					var fwp_sorted = 'name (A-Z)';
			}
			$('.facetwp-sorted').html( 'Sorted by ' + fwp_sorted + '.' );

		});
	})(jQuery);
	</script>
	<?php
}
add_action( 'wp_head', 'lwtv_yikes_facetwp_add_labels', 100 );
