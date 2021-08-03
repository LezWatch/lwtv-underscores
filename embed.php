<?php
/**
 * Contains the post embed base template
 *
 * When a post is embedded in an iframe, this file is used to create the output
 * if the active theme does not include an embed.php template.
 *
 * @package WordPress
 * @subpackage oEmbed
 * @since 4.4.0
 */

get_header( 'embed' );

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		switch ( get_post_type() ) {
			//case 'post_type_actors':
			//case 'post_type_characters':
			case 'post_type_shows':
				get_template_part( 'template-parts/embed-content', get_post_type() );
				break;
			default:
				get_template_part( 'embed', 'content' );
				break;
		}
	endwhile;
else :
	get_template_part( 'embed', '404' );
endif;

get_footer( 'embed' );
