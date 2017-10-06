<?php
/**
 * LWTV Author Box
 * 
 * @package LezWatchTV
 */
function lwtv_author_box( $content ) {
	global $post;
	
	// Bail Early
	if ( is_singular( 'post' ) && isset( $post->post_author ) ) {

		$author = $post->post_author;
		 
		// Get author's display name 
		// If display name is not available then use nickname as display name
		$display_name = ( get_the_author_meta( 'display_name', $author ) )? get_the_author_meta( 'display_name', $author ) : get_the_author_meta( 'nickname', $author ) ;
		 
		// Get author's biographical information or description
		$user_description = ( get_the_author_meta( 'user_description', $author ) )? get_the_author_meta( 'user_description', $author ) : '';
		 
		// Get author's website URL 
		$user_website = get_the_author_meta('url', $author);
		 
		// Get link to the author archive page
		$user_posts = get_author_posts_url( get_the_author_meta( 'ID' , $author));
		
		// Author avatar, name and bio		 
		$author_details  = '<div class="col-sm-3">' . get_avatar( get_the_author_meta('user_email') , 190 ) . '</div>';
		$author_details .= '<div class="col-sm-9"><h4 class="author_name">About ' . $display_name . '</h4><div class="author-bio">' . nl2br( $user_description ) . '</div>';
		$author_details .= '<div class="author-archives">' . lwtv_yikes_symbolicons( 'newspaper.svg', 'fa-newspaper-o' ) . '&nbsp;<a href="'. $user_posts .'">View all articles by ' . $display_name . '</a></div>'; 
		
		// Add URL if it's there
		$author_details .= ( ! empty( $user_website ) )? '<div class="author-website">' . lwtv_yikes_symbolicons( 'earth.svg', 'fa-globe' ) . '&nbsp;<a href="' . $user_website . '" target="_blank" rel="nofollow">Website</a></div></div>' : '</div>';
		 	 
		// Pass all this info to post content  
		$content = $content . '<section class="author-bio-box"><div class="row">' . $author_details . '</div></section>';
	
	}
	
	return $content;
}
 
// Add our function to the post content filter 
add_action( 'the_content', 'lwtv_author_box' );
 
// Allow HTML in author bio section 
remove_filter('pre_user_description', 'wp_filter_kses');