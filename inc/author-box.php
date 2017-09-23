<?php

function lwtv_author_box( $content ) {
 
global $post;
 
// Detect if it is a single post with a post author
if ( is_single() && isset( $post->post_author ) ) {
 
// Get author's display name 
$display_name = get_the_author_meta( 'display_name', $post->post_author );

// If display name is not available then use nickname as display name
if ( empty( $display_name ) )
$display_name = get_the_author_meta( 'nickname', $post->post_author );
 
// Get author's biographical information or description
$user_description = get_the_author_meta( 'user_description', $post->post_author );
 
// Get author's website URL 
$user_website = get_the_author_meta('url', $post->post_author);
 
// Get link to the author archive page
$user_posts = get_author_posts_url( get_the_author_meta( 'ID' , $post->post_author));
 
if ( ! empty( $user_description ) )

// Author avatar, name and bio
 
$author_details .= '<div class="col-sm-3">' . get_avatar( get_the_author_meta('user_email') , 190 ) . '</div>';
 
$author_details .= '<div class="col-sm-9"><h4 class="author_name">About ' . $display_name . '</h4><div class="author-bio">' . nl2br( $user_description ). '</div>';
 
$author_details .= '<div class="author-archives"><i class="fa fa-newspaper-o" aria-hidden="true"></i>
 <a href="'. $user_posts .'">View all articles by ' . $display_name . '</a></div>';  
 
// Check if author has a website in their profile
if ( ! empty( $user_website ) ) {
 
// Display author website link
$author_details .= '<div class="author-website"><a href="' . $user_website .'" target="_blank" rel="nofollow">Website</a></div></div>';
 
} else { 
// if there is no author website then just close the column
$author_details .= '</div>';
}
 
// Pass all this info to post content  
$content = $content . '<section class="author-bio-box"><div class="row">' . $author_details . '</div></section>';
}
return $content;
}
 
// Add our function to the post content filter 
add_action( 'the_content', 'lwtv_author_box' );
 
// Allow HTML in author bio section 
remove_filter('pre_user_description', 'wp_filter_kses');