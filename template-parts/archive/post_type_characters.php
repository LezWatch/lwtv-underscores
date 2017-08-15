<?php
/**
 * Template part for displaying characters
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */
 
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header"></header>
	<div class="entry-content"><?php get_template_part( 'template-parts/excerpt/'.get_post_type() ); ?></div>
</article>