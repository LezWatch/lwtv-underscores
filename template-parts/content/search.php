<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php 
			the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' );
		?>

		<?php 
			if ( 'post' === get_post_type() ) { ?>
				<div class="entry-meta">
					<?php lwtv_underscore_posted_on(); ?>
				</div><!-- .entry-meta -->
		<?php } ?>
	</header><!-- .entry-header -->

	<div class="entry-summary">
		<?php 
			echo '<a href="' . get_permalink( ) . '">' . get_the_post_thumbnail( get_the_id(), 'thumbnail', array( 'alt' => get_the_title(), 'title' => get_the_title() ) ) . '</a>';
			the_excerpt();
		?></div>

	<footer class="entry-footer">
		<?php lwtv_underscore_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->
