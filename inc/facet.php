<?php
/**
 * FacetWP Compatibility File
 *
 * This file contains the custom tweaks made to Facet in order
 * to have it match Yikes' design.
 *
 * @link https://facetwp.com/
 *
 * @package LezWatchTV
 */

/*
 * Numeric Post Navigation
 *
 * Used by FacetWP on custom pages (roles)
 */
function lwtv_yikes_facet_numeric_posts_nav( $queery = 'wp_query', $count = null ) {

	if( is_singular( 'post' ) )
		return;

	if ( $queery == 'wp_query' ) {
		global $wp_query;
		$queery = $wp_query;
	}

	$posts_per_page = get_option('posts_per_page');

	if ( $count == null ) {
		$post_type = ( $queery->post_type == '' )? 'post' : $queery->post_type;
		$published_posts = wp_count_posts( $post_type )->publish;
		$page_number_max = ceil( $published_posts / $posts_per_page );
	} else {
		$published_posts = ceil( $count / $posts_per_page );
		$page_number_max = ceil( $count / $posts_per_page );
	}

	/** Stop execution if there's only 1 page */
	if( $page_number_max <= 1 )
		return;

	/** Listical calculations for how many pages */
	$paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;
	$max   = intval( $published_posts );

	/**	Add current page to the array */
	if ( $paged >= 1 ) $links[] = $paged;
	
	/** If it's the first page, and we have at least 3 pages, we add +3 */
	if ( $paged == 1 && $max >= 3 ) $links[] = $paged + 3;

	/** If it's the last page, and we have at least 3 pages, we add -2 */
	if ( $paged == $max && $max >= 3 ) $links[] = $paged - 2;

	/**	Add the pages around the current page to the array */
	if ( $paged >= 2 )            $links[] = $paged - 1;
	if ( ( $paged + 1 ) <= $max ) $links[] = $paged + 1;
	if ( ( $paged + 2 ) <= $max ) $links[] = $paged + 2;

	echo '<nav aria-label="Post Pages navigation" role="navigation"><ul class="pagination justify-content-center">';

	/** If we have previous posts, add previous navigation */
	if ( get_previous_posts_link() ) {
		// Add FIRST
		printf( '<li class="page-item first mr-auto"><a href="%s" class="page-link">%s</a></li>', esc_url( get_pagenum_link( 1 ) ), '<i class="fa fa-chevron-circle-left" aria-hidden="true"></i>&nbsp; First' );
		// Add PREVIOUS
		printf( '<li class="page-item previous">%s</li>', get_previous_posts_link( '<i class="fa fa-chevron-left" aria-hidden="true"></i>&nbsp; Previous' ) );
	}

	/**	Link to current page, plus pages based on listical */
	sort( $links );
	foreach ( (array) $links as $link ) {
		$class = $paged == $link ? ' active' : '';
		printf( '<li class="page-item%s"><a href="%s" class="page-link">%s</a></li>', $class, esc_url( get_pagenum_link( $link ) ), str_pad( (int)$link, 2, '0', STR_PAD_LEFT ) );
	}

	/**	Link to last page, plus next if necessary */
	if ( ! in_array( $max, $links ) ) {
		if ( ! in_array( $max - 1, $links ) )
			printf( '<li class="page-item next">%s</li>', get_next_posts_link( 'Next &nbsp;<i class="fa fa-chevron-right" aria-hidden="true"></i>' ) );

		$class = $paged == $max ? ' active' : '';
		printf( '<li class="page-item last ml-auto%s"><a href="%s" class="page-link">%s</a></li>', $class, esc_url( get_pagenum_link( $max ) ), 'Last &nbsp;<i class="fa fa-chevron-circle-right" aria-hidden="true"></i>' );
	}

	/**	Next Post Link */
	if ( get_next_posts_link() )
		printf( '<li class="page-item next">%s</li>', get_next_posts_link( 'Next &nbsp;<i class="fa fa-chevron-right" aria-hidden="true"></i>' ) );

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
	if( $total_pages <= 1 ) return $output;

	/**	Add current page to the array */
	if ( $page >= 1 ) $links[] = $page;

	/** If it's the first page, and we have at least 3 pages, we add +3 */
	if ( $page == 1 && $total_pages >= 3 ) $links[] = $page + 3;

	/** If it's the last page, and we have at least 3 pages, we add -2 */
	if ( $page == $total_pages && $total_pages >= 3 ) $links[] = $page - 2;

	/**	Add the pages around the current page to the array */
	if ( $page >= 2 )                    $links[] = $page - 1;
	if ( ( $page + 1 ) <= $total_pages ) $links[] = $page + 1;
	if ( ( $page + 2 ) <= $total_pages ) $links[] = $page + 2;

	$navigation = '<nav aria-label="Post Pages navigation" role="navigation"><ul class="pagination justify-content-center">';

	/** Link to first pages if we have previous pages */
    if ( $page > 1 ) {

		// Add link to first page
	    $navigation .= '<li class="page-item first mr-auto page-link"><a class="facetwp-page" data-page="1"><i class="fa fa-chevron-circle-left" aria-hidden="true"></i>&nbsp; First</a></li>';

		// Add link to previous page
	    $navigation .= '<li class="page-item previous page-link"><a class="facetwp-page" data-page="' . ($page - 1) . '"><i class="fa fa-chevron-left" aria-hidden="true"></i>&nbsp; Previous</a></li>';
	}
	
	/**	Link to current page, plus pages in either direction if necessary */
	sort( $links );
	foreach ( (array) $links as $link ) {
		$class = ( $page == $link )? ' active' : '';
		$navigation .= '<li class="page-item page-link'. $class .'"><a data-page="' . $link . '" class="facetwp-page'. $class .'">' . str_pad( (int)$link, 2, '0', STR_PAD_LEFT ) . '</a></li>';
	}

	/**	Link to last page and next page if necessary */
	if ( ! in_array( $total_pages, $links ) ) {
		if ( ! in_array( $total_pages - 1, $links ) )
			$navigation .= '<li class="page-item page-link"><a class="facetwp-page" data-page="' . ($page + 1) . '">Next &nbsp;<i class="fa fa-chevron-right" aria-hidden="true"></i></s></li>';

		$class = ( $page == $total_pages )? ' active' : '';
		$navigation .=  '<li class="page-item last ml-auto page-link"><a data-page="' . $total_pages . '" class="facetwp-page">Last &nbsp;<i class="fa fa-chevron-circle-right" aria-hidden="true"></i></a></li>';
	}
    
    $navigation .= '</ul></nav>';
    
    $output = $navigation;
    
    return $output;
}

add_filter( 'facetwp_pager_html', 'lwtv_yikes_facetwp_pager_html', 10, 2 );


/**
 * Determine FacetWP Sort By
 *
 * Outputs sort value.
 */
function lwtv_yikes_facetwp_sortby( $fwp_sort ) {

	switch ( $fwp_sort ) {
		case 'most_queers':
			$sort = 'number of characters (descending)';
			break;
		case 'least_queers':
			$sort = 'number of characters (ascending)';
			break;
		case 'most_dead':
			$sort = 'number of dead characters (descending)';
			break;
		case 'least_dead':
			$sort = 'number of dead characters (ascending)';
			break;
		case 'date_desc':
			$sort = 'date added (newest to oldest)';
			break;
		case 'date_asc':
			$sort = 'date added (oldest to newest)';
			break;
		case 'title_desc':
			$sort = 'name (Z-A)';
			break;
		case 'title_asc':
		default:
			$sort = 'name (A-Z)';
	}

	return $sort;

}