<?php
/**
 * LWTV Author Box
 *
 * @package LezWatch.TV
 */
function lwtv_author_box( $content ) {
	global $post;

	if ( is_singular( 'post' ) && isset( $post->post_author ) ) {

		$author = $post->post_author;

		$display_name = ( get_the_author_meta( 'display_name', $author ) ) ? get_the_author_meta( 'display_name', $author ) : get_the_author_meta( 'nickname', $author );

		$author_description = ( get_the_author_meta( 'user_description', $author ) ) ? get_the_author_meta( 'user_description', $author ) : '';

		$author_social = lwtv_plugin()->get_author_social( $author );

		$author_posts = get_author_posts_url( get_the_author_meta( 'ID', $author ) );

		$raw_count        = count_user_posts( $author, 'post' );
		$author_articles  = ( $raw_count > 1 ) ? 'Read all ' . $raw_count . ' articles' : 'This is the first article';
		$author_articles .= ' by ' . $display_name . '.';

		$favourites = lwtv_plugin()->get_author_favorite_shows( $author );

		$author_details  = '<div class="col-sm-3">' . get_avatar( get_the_author_meta( 'user_email' ), 190 ) . '</div>';
		$author_details .= '<div class="col-sm-9"><h4 class="author_name">About ' . $display_name . '</h4>';

		$author_details .= ( ! empty( $author_social ) ) ? $author_social : '';

		$author_details .= '<div class="author-bio">' . nl2br( $author_description ) . '</div>';

		$author_details .= '<div class="author-archives">' . lwtv_plugin()->get_symbolicon( svg: 'newspaper.svg', icon: 'svg-newspaper', max_size: '20' ) . '&nbsp;<a href="' . $author_posts . '">' . $author_articles . '</a></div>';

		$author_details .= ( isset( $favourites ) && ! empty( $favourites ) ) ? $favourites : '';

		$author_details .= '</div>';

		$content .= '<section class="author-bio-box"><div class="row">' . $author_details . '</div></section>';
	}

	return $content;
}

// Add our function to the post content filter
add_action( 'the_content', 'lwtv_author_box' );

// Allow HTML in author bio section
remove_filter( 'pre_user_description', 'wp_filter_kses' );
