<?php
/**
 * LWTV Author Box
 *
 * @package LezWatch.TV
 */
function lwtv_author_box( $content ) {
	global $post;

	// Bail Early
	if ( is_singular( 'post' ) && isset( $post->post_author ) ) {

		$author = $post->post_author;

		// Get author's display name. If display name is not available then use nickname.
		$display_name = ( get_the_author_meta( 'display_name', $author ) ) ? get_the_author_meta( 'display_name', $author ) : get_the_author_meta( 'nickname', $author );

		// Get author's biographical information or description.
		$author_description = ( get_the_author_meta( 'user_description', $author ) ) ? get_the_author_meta( 'user_description', $author ) : '';

		// Get author's social deets.
		$author_social = lwtv_author_social( $author );

		// Get link to the author archive page.
		$author_posts = get_author_posts_url( get_the_author_meta( 'ID', $author ) );

		// Get number of posts written.
		$raw_count        = count_user_posts( $author, 'post' );
		$author_articles  = ( $raw_count > 1 ) ? 'Read all ' . $raw_count . ' articles' : 'This is the first article';
		$author_articles .= ' by ' . $display_name . '.';

		// Get author Fav Shows.
		$favourites = lwtv_author_favourite_shows( $author );

		// Author avatar, and name.
		$author_details  = '<div class="col-sm-3">' . get_avatar( get_the_author_meta( 'user_email' ), 190 ) . '</div>';
		$author_details .= '<div class="col-sm-9"><h4 class="author_name">About ' . $display_name . '</h4>';

		// Add socials if they exist.
		$author_details .= ( ! empty( $author_social ) ) ? $author_social : '';

		// Add author bio.
		$author_details .= '<div class="author-bio">' . nl2br( $author_description ) . '</div>';

		// Add link to author page with article counts.
		$author_details .= '<div class="author-archives">' . lwtv_symbolicons( 'newspaper.svg', 'fa-newspaper' ) . '&nbsp;<a href="' . $author_posts . '">' . $author_articles . '</a></div>';

		// Add favourite shows if they exist.
		$author_details .= ( isset( $favourites ) && ! empty( $favourites ) ) ? $favourites : '';

		// End Div.
		$author_details .= '</div>';

		// Pass all this info to post content
		$content .= '<section class="author-bio-box"><div class="row">' . $author_details . '</div></section>';
	}

	return $content;
}

// Add our function to the post content filter
add_action( 'the_content', 'lwtv_author_box' );

// Allow HTML in author bio section
remove_filter( 'pre_user_description', 'wp_filter_kses' );
