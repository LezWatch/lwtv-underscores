<?php
/**
 * The template part for displaying a message that posts cannot be found.
 *
 * @package LWTV Underscores
 */
?>

<article class="post no-results not-found">
	<header class="entry-header">
		<h1 class="entry-title"><?php esc_attr_e( 'Nothing Found', 'lwtv-underscores' ); ?></h1>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>

		<p>
			<?php
			// translators: $1 is a link to wp-admin
			printf( esc_attr__( 'Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'lwtv-underscores' ), esc_url( admin_url( 'post-new.php' ) ) );
			?>
		</p>

		<?php elseif ( is_search() ) : ?>

			<p><?php esc_attr_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'lwtv-underscores' ); ?></p>
			<?php get_search_form(); ?>

			<?php
			get_template_part(
				'template-parts/partials/ai/discovery-no-results',
				null,
				array(
					'failed_query' => get_search_query(),
					'mood_chips'   => apply_filters( 'lwtv_discovery_no_results_mood_chips', array() ),
				)
			);
			?>

		<?php else : ?>

			<p><?php esc_attr_e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'lwtv-underscores' ); ?></p>
			<?php get_search_form(); ?>

		<?php endif; ?>
	</div><!-- .entry-content -->
</article><!-- .no-results .not-found -->
