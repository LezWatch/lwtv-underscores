<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package yikes starter
 */

if ( ! function_exists( 'yikes_starter_paging_nav' ) ) :
	/**
	 * Display navigation to next/previous set of posts when applicable.
	 */
	function yikes_starter_paging_nav() {
		// Don't print empty markup if there's only one page.
		if ( $GLOBALS['wp_query']->max_num_pages < 2 ) {
			return;
		}
		?>
		<nav aria-label="Post Pages navigation" role="navigation">
			<ul class="pagination justify-content-between">
				<?php if ( get_previous_posts_link() ) : ?>
				<li class="page-item previous"><?php previous_posts_link( __( '<i class="fa fa-chevron-circle-left" aria-hidden="true"></i>&nbsp; Previous', 'yikes_starter' ) ); ?></li>
				<?php endif; ?>

				<?php if ( get_next_posts_link() ) : ?>
				<li class="page-item next"><?php next_posts_link( __( 'Next &nbsp;<i class="fa fa-chevron-circle-right" aria-hidden="true"></i>', 'yikes_starter' ) ); ?></li>
				<?php endif; ?>
			</ul>
		</nav><!-- .navigation -->
		<?php
	}
endif;

if ( ! function_exists( 'yikes_starter_post_nav' ) ) :
	/**
	 * Display navigation to next/previous post when applicable.
	 */
	function yikes_starter_post_nav() {
		// Don't print empty markup if there's nowhere to navigate.
		$previous = ( is_attachment() ) ? get_post( get_post()->post_parent ) : get_adjacent_post( false, '', true );
		$next     = get_adjacent_post( false, '', false );

		if ( ! $next && ! $previous ) {
			return;
		}
		?>
		<nav aria-label="Post Navigation" role="navigation">
			<ul class="pagination justify-content-between">
				<?php
					previous_post_link( '<li class="page-item previous">%link</li>', _x( '<i class="fa fa-chevron-circle-left" aria-hidden="true"></i>&nbsp; Previous', 'Previous post link', 'yikes_starter' ) );
					next_post_link( '<li class="page-item next">%link</li>', _x( 'Next &nbsp <i class="fa fa-chevron-circle-right" aria-hidden="true"></i>', 'Next post link', 'yikes_starter' ) );
				?>
			</ul>
		</nav><!-- .navigation -->
		<?php
	}
endif;

if ( ! function_exists( 'yikes_starter_posted_on' ) ) :
	/**
	 * Prints HTML with meta information for the current post-date/time and author.
	 */
	function yikes_starter_posted_on() {
		$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time>';

		$time_string = sprintf( $time_string,
			esc_attr( get_the_date( 'c' ) ),
			esc_html( get_the_date() ),
			esc_attr( get_the_modified_date( 'c' ) ),
			esc_html( get_the_modified_date() )
		);

			$posted_on = sprintf(
				esc_html_x( 'Posted on %s', 'post date', 'yikes_starter' ),
				'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $time_string . '</a>'
			);

			$byline = sprintf(
				esc_html_x( 'by %s', 'post author', 'yikes_starter' ),
				'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'
			);

			echo '<span class="posted-on">' . $posted_on . '</span><span class="byline"> ' . $byline . '</span>';

	}
endif;

if ( ! function_exists( 'yikes_starter_entry_footer' ) ) :
/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function yikes_starter_entry_footer() {
	// Hide category and tag text for pages.
	if ( 'post' === get_post_type() ) {
		/* translators: used between list items, there is a space after the comma */
		$categories_list = get_the_category_list( esc_html__( ', ', 'yikes_starter' ) );
		if ( $categories_list && yikes_starter_categorized_blog() ) {
			printf( '<span class="cat-links">' . esc_html__( 'Posted in %1$s', 'yikes_starter' ) . '</span>', $categories_list ); // WPCS: XSS OK.
		}

		/* translators: used between list items, there is a space after the comma */
		$tags_list = get_the_tag_list( '', esc_html__( ', ', 'yikes_starter' ) );
		if ( $tags_list ) {
			printf( '<span class="tags-links">' . esc_html__( 'Tagged %1$s', 'yikes_starter' ) . '</span>', $tags_list ); // WPCS: XSS OK.
		}
	}

	if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
		echo '<span class="comments-link">';
		/* translators: %s: post title */
		comments_popup_link( sprintf( wp_kses( __( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'yikes_starter' ), array( 'span' => array( 'class' => array() ) ) ), get_the_title() ) );
		echo '</span>';
	}

	edit_post_link(
		sprintf(
			/* translators: %s: Name of current post */
			esc_html__( 'Edit %s', 'yikes_starter' ),
			the_title( '<span class="screen-reader-text">"', '"</span>', false )
		),
		'<span class="edit-link">',
		'</span>'
	);
}
endif;

/**
 * Returns true if a blog has more than 1 category.
 *
 * @return bool
 */
function yikes_starter_categorized_blog() {
	if ( false === ( $all_the_cool_cats = get_transient( 'yikes_starter_categories' ) ) ) {
		// Create an array of all the categories that are attached to posts.
		$all_the_cool_cats = get_categories( array(
			'fields'     => 'ids',
			'hide_empty' => 1,

			// We only need to know if there is more than one category.
			'number'     => 2,
		) );

		// Count the number of categories that are attached to the posts.
		$all_the_cool_cats = count( $all_the_cool_cats );

		set_transient( 'yikes_starter_categories', $all_the_cool_cats );
	}

	if ( $all_the_cool_cats > 1 ) {
		// This blog has more than 1 category so yikes_starter_categorized_blog should return true.
		return true;
	} else {
		// This blog has only 1 category so yikes_starter_categorized_blog should return false.
		return false;
	}
}

/**
 * Flush out the transients used in yikes_starter_categorized_blog.
 */
function yikes_starter_category_transient_flusher() {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// Like, beat it. Dig?
	delete_transient( 'yikes_starter_categories' );
}
add_action( 'edit_category', 'yikes_starter_category_transient_flusher' );
add_action( 'save_post',     'yikes_starter_category_transient_flusher' );

/**
 * Add Pagination where ever.
 */

function yikes_generate_pagination_buttons( $page_number, $max_num_pages, $view_all = false ) {

	if ( $max_num_pages === false || (int) $max_num_pages === 1 ) {
		return;
	}

	?>
	<nav aria-label="Post Pages navigation" role="navigation">
		<ul class="pagination justify-content-center">
			<li class="page-item next">
				<?php next_posts_link( '<span>Next &nbsp;</span><i class="fa fa-chevron-circle-right" aria-hidden="true"></i>', $max_num_pages ); ?>					
			</li>

			<!-- Page Number Buttons -->
			<?php yikes_generate_page_number_buttons( $page_number, $max_num_pages ); ?>

			<?php if ( $view_all !== false ) { echo '<li><a href="' . esc_attr( $view_all ) . '" class="page-link"><span>View All</span></a></li>'; } ?>

			<li class="page-item previous">
				<?php previous_posts_link( '<i class="fa fa-chevron-circle-left" aria-hidden="true"></i><span>&nbsp; Previous</span>' ); ?>
			</li>
		</ul>
	</nav>
	<?php
}

function yikes_generate_page_number_buttons( $page_number, $max_num_pages ) {
	$page_number   = (int) $page_number;
	$max_num_pages = (int) $max_num_pages;

	if( $page_number === 1 ) { 
		 echo yikes_pagination_get_page( 1, $max_num_pages, true );
		 echo yikes_pagination_get_page( 2, $max_num_pages, false );
		 echo yikes_pagination_get_page( 3, $max_num_pages, false );
		 echo yikes_pagination_get_page( 4, $max_num_pages, false );
		 echo yikes_pagination_get_page( 5, $max_num_pages, false );
		 echo yikes_pagination_get_page_space_right(); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, false, true ); 
	 } elseif( $page_number === 2 ) { 
		 echo yikes_pagination_get_page( 1, $max_num_pages, false );
		 echo yikes_pagination_get_page( 2, $max_num_pages, true );
		 echo yikes_pagination_get_page( 3, $max_num_pages, false );
		 echo yikes_pagination_get_page( 4, $max_num_pages, false );
		 echo yikes_pagination_get_page( 5, $max_num_pages, false );
		 echo yikes_pagination_get_page_space_right(); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, false, true ); 
	 } elseif( $page_number === 3 ) { 
		 echo yikes_pagination_get_page( 1, $max_num_pages, false );
		 echo yikes_pagination_get_page( 2, $max_num_pages, false );
		 echo yikes_pagination_get_page( 3, $max_num_pages, true );
		 echo yikes_pagination_get_page( 4, $max_num_pages, false );
		 echo yikes_pagination_get_page( 5, $max_num_pages, false );
		 echo yikes_pagination_get_page_space_right(); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, false, true ); 
	 } elseif( $page_number === 4 ) { 
		 echo yikes_pagination_get_page( 1, $max_num_pages, false );
		 echo yikes_pagination_get_page_space_left();
		 echo yikes_pagination_get_page( 2, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( 3, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( 4, $max_num_pages, true ); 
		 echo yikes_pagination_get_page( 5, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( 6, $max_num_pages, false ); 
		 echo yikes_pagination_get_page_space_right(); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, false, true ); 
	 } elseif( $page_number === $max_num_pages ) { 
		 echo yikes_pagination_get_first_page( 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 4, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 3, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 2, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, true, true, true ); 
	 } elseif( $page_number === ( $max_num_pages - 1 ) ) { 
		 echo yikes_pagination_get_page( 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page_space_left(); 
		 echo yikes_pagination_get_page( $page_number - 3, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 2, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number, $max_num_pages, true ); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, false, true ); 
	 } elseif( $page_number === ( $max_num_pages - 2 ) ) { 
		 echo yikes_pagination_get_page( 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page_space_left(); 
		 echo yikes_pagination_get_page( $page_number - 2, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number, $max_num_pages, true ); 
		 echo yikes_pagination_get_page( $page_number + 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, false, true ); 
	 } else { 
		 echo yikes_pagination_get_page( 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page_space_left(); 
		 echo yikes_pagination_get_page( $page_number - 2, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number - 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number, $max_num_pages, true ); 
		 echo yikes_pagination_get_page( $page_number + 1, $max_num_pages, false ); 
		 echo yikes_pagination_get_page( $page_number + 2, $max_num_pages, false ); 
		 echo yikes_pagination_get_page_space_right(); 
		 echo yikes_pagination_get_page( $max_num_pages, $max_num_pages, false, true ); 
	 }
}

function yikes_pagination_get_first_page( $page_number, $max_num_pages, $active_flag) {
	if ( $max_num_pages > 5 ) {
		$active = $active_flag === true ? 'class="active"' : false;
		return '<li class="page-item"><a ' . $active . ' href="' . get_pagenum_link( $page_number ) . '" class="page-link">' . $page_number . '</a></li>' . yikes_pagination_get_page_space_left();
	}
}

function yikes_pagination_get_page( $page_number, $max_num_pages, $active_flag, $last_page = false, $last_page_override = false ) {
	if ( $page_number <= $max_num_pages && $last_page === false || ( $last_page === true && $max_num_pages > 5 ) || $last_page_override === true ) {
		$active = $active_flag === true ? 'class="active"' : false;
		return '<li class="page-item"><a ' . $active . ' href="' . get_pagenum_link( $page_number ) . '" class="page-link">' . $page_number . '</a></li>';
	}
}

function yikes_pagination_get_page_space_left() {
	return '<li class="pagination-spacer"></li>';
}

function yikes_pagination_get_page_space_right() {
	return '<li class="pagination-spacer"></li>';
}