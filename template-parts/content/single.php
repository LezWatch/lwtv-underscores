<?php
/**
 * The template used for single page content
 *
 * @package LWTV Underscores
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
			<?php lwtv_theme_posted_on(); ?>
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
		// Categories are only worth showing if the blog actually uses more than one.
		$categories = lwtv_theme_categorized_blog() ? get_the_category() : array();
		$tags       = get_the_tags();
		$tags       = is_array( $tags ) ? $tags : array();

		if ( ! empty( $categories ) || ! empty( $tags ) ) :
			?>
			<div class="entry-meta-card">

				<?php if ( ! empty( $categories ) ) : ?>
					<div class="entry-meta-card__row entry-meta-card__row--categories">
						<span class="entry-meta-card__icon" aria-hidden="true">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo lwtv_plugin()->get_symbolicon( svg: 'folder-open.svg', icon: 'svg-folder-open', svg_class: 'symbolicon entry-meta-card__glyph', max_size: '18' );
							?>
						</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Posted in:', 'lwtv-underscores' ); ?></span>
						<span class="entry-meta-card__terms">
							<?php
							$category_links = array();
							foreach ( $categories as $category ) {
								$category_links[] = sprintf(
									'<a class="entry-meta-card__category" href="%1$s" rel="category tag">%2$s</a>',
									esc_url( get_category_link( $category->term_id ) ),
									esc_html( $category->name )
								);
							}
							/* translators: used between list items, there is a space after the comma */
							echo wp_kses_post( implode( __( ', ', 'lwtv-underscores' ), $category_links ) );
							?>
						</span>
					</div><!-- .entry-meta-card__row -->
				<?php endif; ?>

				<?php if ( ! empty( $categories ) && ! empty( $tags ) ) : ?>
					<hr class="entry-meta-card__divider">
				<?php endif; ?>

				<?php if ( ! empty( $tags ) ) : ?>
					<div class="entry-meta-card__row entry-meta-card__row--tags">
						<span class="entry-meta-card__icon" aria-hidden="true">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo lwtv_plugin()->get_symbolicon( svg: 'tag.svg', icon: 'svg-tags', svg_class: 'symbolicon entry-meta-card__glyph', max_size: '17' );
							?>
						</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Tagged:', 'lwtv-underscores' ); ?></span>
						<ul class="entry-meta-card__tags">
							<?php foreach ( $tags as $post_tag ) : ?>
								<li>
									<a class="entry-meta-card__tag" href="<?php echo esc_url( get_tag_link( $post_tag->term_id ) ); ?>" rel="tag"><?php echo esc_html( $post_tag->name ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div><!-- .entry-meta-card__row -->
				<?php endif; ?>

			</div><!-- .entry-meta-card -->
			<?php
		endif;

		$permalink_title = sprintf(
			/* translators: %s: post title */
			__( 'Permalink to %s', 'lwtv-underscores' ),
			the_title_attribute( 'echo=0' )
		);
		?>
		<div class="entry-meta-permalink">
			<a
				class="entry-meta-permalink__action"
				href="<?php echo esc_url( get_permalink() ); ?>"
				rel="bookmark"
				title="<?php echo esc_attr( $permalink_title ); ?>"
				data-lwtv-copy-link
			>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo lwtv_plugin()->get_symbolicon( svg: 'bookmark.svg', icon: 'fa-bookmark', svg_class: 'symbolicon entry-meta-permalink__glyph', max_size: '15' );
				?>
				<span class="entry-meta-permalink__label" data-copy-label="<?php esc_attr_e( 'Copy post link', 'lwtv-underscores' ); ?>"><?php esc_html_e( 'Post link', 'lwtv-underscores' ); ?></span>
			</a>
			<span
				class="entry-meta-permalink__status"
				role="status"
				aria-live="polite"
				data-copied-text="<?php esc_attr_e( 'Link copied', 'lwtv-underscores' ); ?>"
				data-failed-text="<?php esc_attr_e( 'Copy failed - use your browser to copy the link', 'lwtv-underscores' ); ?>"
			></span>
		</div><!-- .entry-meta-permalink -->
	</footer><!-- .entry-meta -->
</article><!-- #post-## -->
