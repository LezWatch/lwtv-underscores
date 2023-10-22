<?php
/**
 * The template for displaying Archive pages.
 *
 * @package YIKES Starter
 */

// Defaults
$current_archive   = get_queried_object();
$archive_icon      = lwtv_symbolicons( 'newspaper.svg', 'fa-newspaper' );
$archive_details   = '';
$archive_subheader = '<span class="post-count">' . sprintf( _n( '%s article', '%s articles', $current_archive->count ), number_format_i18n( $current_archive->count ) ) . '</span>';

// Custom header info
if ( is_author() ) {
	// Authors:
	$author            = get_the_author_meta( 'ID' );
	$archive_icon      = get_avatar( get_the_author_meta( 'user_email' ), 96, '', 'Avatar for author ' . get_the_author_meta( 'display_name' ) );
	$archive_subheader = lwtv_author_social( $author );
	$archive_details   = lwtv_author_favourite_shows( $author );
} elseif ( is_tag() && class_exists( 'LWTV_Related_Posts' ) ) {
	$tag_id          = get_queried_object()->term_id;
	$archive_icon    = lwtv_symbolicons( 'tag.svg', 'fa-tag' );
	$archive_details = ( new LWTV_Related_Posts() )->related_archive_header( $tag_id );
}

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<div class="row">
					<div class="col-10">
						<?php the_archive_title( '<h1 class="entry-title">', '</h1>' ); ?>
						<?php
						if ( isset( $archive_subheader ) ) {
							echo '<div class="archive-description">';
							echo $archive_subheader; // phpcs:ignore WordPress.Security.EscapeOutput
							echo '</div>';
						}
						?>
					</div>
					<div class="col-2 icon plain">
						<div class="archive-header-icon">
							<?php echo $archive_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col">
						<div class="archive-header-details">
							<div class="archive-description">
								<?php the_archive_description(); ?>
								<?php echo $archive_details; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</div>
						</div>
					</div>
				</div>
			</section><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix">
						<?php
						if ( have_posts() ) :
							?>
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content">
								<div class="row site-loop main-posts-loop">
									<?php
									while ( have_posts() ) :
										the_post();
										get_template_part( 'template-parts/content', 'posts' );
									endwhile;
									else :
										get_template_part( 'template-parts/content', 'none' );
									endif;
									?>
								</div>
								<?php wp_bootstrap_pagination(); ?>
							</div>
						</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-9 -->

			<div class="col-sm-3 site-sidebar site-loop">
				<?php get_sidebar(); ?>
			</div><!-- .col-sm-3 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
