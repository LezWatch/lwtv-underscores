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
		<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>

		<?php 
			if ( 'post' === get_post_type() ) { ?>
				<div class="entry-meta">
					<?php lwtv_underscore_posted_on(); ?>
				</div><!-- .entry-meta -->
		<?php } ?>
	</header><!-- .entry-header -->

	<div class="entry-summary">

	<?php
		if ( 'post_type_shows'  === get_post_type() ) {
			get_template_part( 'template-parts/excerpt/post_type_shows' );
		} elseif ( 'post_type_characters'  === get_post_type() ) {
			$character = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post->ID ),
				'url'       => get_the_permalink( $post->ID ),
				'content'   => get_the_content( $post->ID ),
				'shows'     => get_post_meta( $post->ID, 'lezchars_show_group', true ),
				'show_from' => 0,
			);
			include(locate_template('template-parts/excerpt/post_type_characters.php'));
		} else {
			the_excerpt();
		}
	?>
	</div><!-- .entry-summary -->

	<footer class="entry-footer">
		<?php lwtv_underscore_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->
