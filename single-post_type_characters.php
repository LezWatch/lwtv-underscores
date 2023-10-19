<?php
/**
 * The Template for displaying all single character pages.
 *
 * @package YIKES Starter
 */

// Build the icon.
$icon = '<div class="show-header-svg">';
if ( has_term( 'dead', 'lez_cliches' ) ) {
	$icon .= ' <span role="img" aria-label="RIP - Dead Character" data-bs-target="tooltip" title="RIP - Dead Character" class="cliche-dead">' . lwtv_symbolicons( 'rest-in-peace.svg', 'fa-ban' ) . '</span>';
}
$icon .= '</div>';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><?php the_title( '<h1 class="entry-title">', '</h1>' ); ?></div>
					<div class="col-2 icon plain"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content character-page">
								<div class="card">
									<?php
									while ( have_posts() ) :
										the_post();
										get_template_part( 'template-parts/content', get_post_type() );
										// Force Jetpack to display sharing links where we want them.
										lwtv_yikes_jetpack_post_meta();
										// Echo last updated.
										lwtv_last_updated_date( get_the_ID() );
									endwhile;
									// end of the loop.
									?>
								</div>
							</div><!-- .entry-content -->
						</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-8 -->
			<div class="col-sm-4 site-sidebar site-loop showschar-section">
				<?php get_sidebar(); ?>
			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
