<?php
/**
 * The template used for single page content
 *
 * @package YIKES Starter
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<div class="d-flex justify-content-center">
			<?php
			if ( has_post_thumbnail() ) {
				the_post_thumbnail( 'large' );
			}
			?>
		</div>

		<div class="entry-meta">
			<?php yikes_starter_posted_on(); ?>
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content clearfix">
		<?php
		the_content();
		?>
	</div><!-- .entry-content -->

		<?php
		wp_link_pages(
			array(
				'before' => '<div class="page-links">' . esc_attr__( 'Pages:', 'lwtv-underscores' ),
				'after'  => '</div>',
			)
		);
		?>

	<footer class="entry-meta">
		<?php
		/* translators: used between list items, there is a space after the comma */
		$category_list = get_the_category_list( __( ', ', 'lwtv-underscores' ) );

		/* translators: used between list items, there is a space after the comma */
		$tag_list = get_the_tag_list( '', __( ', ', 'lwtv-underscores' ) );

		if ( ! yikes_starter_categorized_blog() ) {
			// This blog only has 1 category so we just need to worry about tags in the meta text
			if ( '' !== $tag_list ) {
				$meta_text = __( '<span class="footer-entry-meta-item">' . lwtv_symbolicons( 'tag.svg', 'fa-tags' ) . '&nbsp;%2$s</span> <span class="footer-entry-meta-item">' . lwtv_symbolicons( 'bookmark.svg', 'fa-bookmark' ) . '&nbsp;<a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a></span>', 'lwtv-underscores' );
			} else {
				$meta_text = __( '<span class="footer-entry-meta-item">' . lwtv_symbolicons( 'bookmark.svg', 'fa-bookmark' ) . '&nbsp;<a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a></span>', 'lwtv-underscores' );
			}
		} elseif ( '' !== $tag_list ) {
			// But this blog has loads of categories so we should probably display them here
			$meta_text = __( '<span class="footer-entry-meta-item">' . lwtv_symbolicons( 'folder-open.svg', 'fa-folder-open' ) . '&nbsp;%1$s</span> <span class="footer-entry-meta-item">' . lwtv_symbolicons( 'tag.svg', 'fa-tags' ) . '&nbsp;%2$s</span> <span class="footer-entry-meta-item">' . lwtv_symbolicons( 'bookmark.svg', 'fa-bookmark' ) . '&nbsp;<a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a></span>', 'lwtv-underscores' );
		} else {
			$meta_text = __( '<span class="footer-entry-meta-item">' . lwtv_symbolicons( 'folder-open.svg', 'fa-folder-open' ) . '&nbsp;%1$s</span> <span class="footer-entry-meta-item">' . lwtv_symbolicons( 'bookmark.svg', 'fa-bookmark' ) . '&nbsp;<a href="%3$s" title="Permalink to %4$s" rel="bookmark">Post link</a></span>', 'lwtv-underscores' );
		} // End if.

		printf(
			$meta_text,     // phpcs:ignore WordPress.Security.EscapeOutput
			$category_list, // phpcs:ignore WordPress.Security.EscapeOutput
			$tag_list,      // phpcs:ignore WordPress.Security.EscapeOutput
			esc_url( get_permalink() ),
			the_title_attribute( 'echo=0' )
		);

		?>
	</footer><!-- .entry-meta -->
</article><!-- #post-## -->
