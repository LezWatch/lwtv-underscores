<?php
/**
 * LWTV Author Box
 *
 * @package LezWatch.TV
 */
function lwtv_author_box( $content ) {
	global $post;

	// Bail Early
	if ( is_singular( 'post' ) && isset( $post->post_author ) && ! is_amp_endpoint() ) {

		$author = $post->post_author;

		// Get author's display name
		// If display name is not available then use nickname as display name
		$display_name = ( get_the_author_meta( 'display_name', $author ) ) ? get_the_author_meta( 'display_name', $author ) : get_the_author_meta( 'nickname', $author );

		// Get author's biographical information or description
		$user_description = ( get_the_author_meta( 'user_description', $author ) ) ? get_the_author_meta( 'user_description', $author ) : '';

		// Get author's website URL
		$user_twitter = get_the_author_meta( 'twitter', $author );

		// Get link to the author archive page
		$user_posts = get_author_posts_url( get_the_author_meta( 'ID', $author ) );

		// Get number of posts written
		$user_post_num = ( count_user_posts( $author, 'post' ) > 1 ) ? 'Read all ' . count_user_posts( $author, 'post' ) . ' articles' : 'This is the first article';
		$user_articles = $user_post_num . ' by ' . $display_name . '.';

		// Get author Fav Shows
		$all_fav_shows = get_the_author_meta( 'lez_user_favourite_shows', $author );
		if ( '' !== $all_fav_shows ) {
			$show_title = array();
			foreach ( $all_fav_shows as $each_show ) {
				if ( get_post_status( $each_show ) !== 'publish' ) {
					array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show ) . '</span></em>' );
				} else {
					array_push( $show_title, '<em><a href="' . get_permalink( $each_show ) . '">' . get_the_title( $each_show ) . '</a></em>' );
				}
			}
			$favourites = ( empty( $show_title ) ) ? '' : implode( ', ', $show_title );
			$fav_title  = _n( 'Show', 'Shows', count( $show_title ) );
		}

		// Author avatar, name and bio
		$author_details  = '<div class="col-sm-3">' . get_avatar( get_the_author_meta( 'user_email' ), 190 ) . '</div>';
		$author_details .= '<div class="col-sm-9"><h4 class="author_name">About ' . $display_name . '</h4><div class="author-bio">' . nl2br( $user_description ) . '</div>';

		$author_details .= '<div class="author-archives">' . lwtv_yikes_symbolicons( 'newspaper.svg', 'fa-newspaper' ) . '&nbsp;<a href="' . $user_posts . '">' . $user_articles . '</a></div>';

		// Add Twitter if it's there
		$author_details .= ( ! empty( $user_twitter ) ) ? '<div class="author-twitter">' . lwtv_yikes_symbolicons( 'twitter.svg', 'fa-twitter' ) . '&nbsp;<a href="https://twitter.com/' . $user_twitter . '" target="_blank" rel="nofollow">@' . $user_twitter . '</a> </div>' : '';

		// Add favourite shows if they're there
		$author_details .= ( isset( $favourites ) && ! empty( $favourites ) ) ? '<div class="author-favourites">' . lwtv_yikes_symbolicons( 'tv-hd.svg', 'fa-tv' ) . '&nbsp;Favorite ' . $fav_title . ': ' . $favourites . '</div>' : '';

		$author_details .= '</div>';

		// Pass all this info to post content
		$content = $content . '<section class="author-bio-box"><div class="row">' . $author_details . '</div></section>';
	}

	return $content;
}

// Add our function to the post content filter
add_action( 'the_content', 'lwtv_author_box' );

// Allow HTML in author bio section
remove_filter( 'pre_user_description', 'wp_filter_kses' );
