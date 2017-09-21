<?php
/**
 * The template used for single page content
 *
 * @package YIKES Starter
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h1 class="entry-title"><?php the_title(); ?></h1>

		<div class="entry-meta">
			<?php yikes_starter_posted_on(); ?>
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php the_content(); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . esc_attr__( 'Pages:', 'yikes_starter' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->

	<footer class="entry-meta">
		<?php
			/* translators: used between list items, there is a space after the comma */
			$category_list = get_the_category_list( __( ', ', 'yikes_starter' ) );

			/* translators: used between list items, there is a space after the comma */
			$tag_list = get_the_tag_list( '', __( ', ', 'yikes_starter' ) );

			if ( ! yikes_starter_categorized_blog() ) {
				// This blog only has 1 category so we just need to worry about tags in the meta text
				if ( '' != $tag_list ) {
					$meta_text = __( '<span class="footer-entry-meta-item"><i class="fa fa-tags"></i> %2$s.</span> <span class="footer-entry-meta-item"><i class="fa fa-bookmark-o"></i> <a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a>.</span>', 'yikes_starter' );
				} else {
					$meta_text = __( '<span class="footer-entry-meta-item"><i class="fa fa-bookmark-o"></i> <a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a>.</span>', 'yikes_starter' );
				}
			} else {
				// But this blog has loads of categories so we should probably display them here
				if ( '' != $tag_list ) {
					$meta_text = __( '<span class="footer-entry-meta-item"><i class="fa fa-folder-open"></i> %1$s</span> <span class="footer-entry-meta-item"><i class="fa fa-tags"></i> %2$s.</span> <span class="footer-entry-meta-item"><i class="fa fa-bookmark-o"></i> <a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a>.</span>', 'yikes_starter' );
				} else {
					$meta_text = __( '<span class="footer-entry-meta-item"><i class="fa fa-folder-open"></i> %1$s.</span> <span class="footer-entry-meta-item"><i class="fa fa-bookmark-o"></i> <a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a>.</span>', 'yikes_starter' );
				}
			} // End if().

			printf(
				$meta_text,
				$category_list,
				$tag_list,
				get_permalink(),
				the_title_attribute( 'echo=0' )
			);
		?>
	</footer><!-- .entry-meta -->
</article><!-- #post-## -->