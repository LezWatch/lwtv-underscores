<?php
/**
 * The template for displaying page content.
 *
 * @package YIKES Starter
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h1 class="entry-title">
			<a href="<?php the_permalink(); ?>" rel="bookmark">
				<?php the_title(); ?>
			</a>
		</h1>

		<?php if ( 'post' === get_post_type() ) { ?>
			<div class="entry-meta">
				<?php yikes_starter_posted_on(); ?>
			</div><!-- .entry-meta -->
		<?php } ?>
	</header><!-- .entry-header -->

	<?php if ( is_search() ) { ?>
		<div class="entry-summary">
			<?php the_excerpt(); ?>
		</div><!-- .entry-summary -->
	<?php } else { ?>
		<div class="entry-content">
			<?php
				the_content( esc_attr__( 'Continue reading <span class="meta-nav">&rarr;</span>', 'lwtv-underscores' ) );
				wp_link_pages(
					array(
						'before' => '<div class="page-links">' . esc_attr__( 'Pages:', 'lwtv-underscores' ),
						'after'  => '</div>',
					)
				);
			?>
		</div><!-- .entry-content -->
	<?php } ?>

	<footer class="entry-meta">
		<?php
		if ( 'post' === get_post_type() ) {
			/* translators: used between list items, there is a space after the comma */
			$categories_list = get_the_category_list( esc_attr__( ', ', 'lwtv-underscores' ) );
			if ( $categories_list && yikes_starter_categorized_blog() ) {
				?>
				<span class="cat-links">
					<?php echo 'Posted in ' . wp_kses_post( $categories_list ); ?>
				</span>
				<?php
			}

			$tags_list = get_the_tag_list( '', esc_attr__( ', ', 'lwtv-underscores' ) );

			if ( $tags_list ) {
				?>
				<span class="tags-links">
					<?php echo 'Tagged ' . wp_kses_post( $tags_list ); ?>
				</span>
				<?php
			}
		}

		if ( ! post_password_required() && ( comments_open() || '0' !== get_comments_number() ) ) {
			?>
			<span class="comments-link">
				<?php comments_popup_link( esc_attr__( 'Leave a comment', 'lwtv-underscores' ), esc_attr__( '1 Comment', 'lwtv-underscores' ), esc_attr__( '% Comments', 'lwtv-underscores' ) ); ?>
			</span>
			<?php
		}

		edit_post_link( esc_attr__( 'Edit', 'lwtv-underscores' ), '<div class="edit-link">' . lwtv_plugin()->get_symbolicon( 'pencil.svg', 'fa-pencil-alt' ), '</div>' );
		?>
	</footer><!-- .entry-meta -->
</article><!-- #post-## -->
