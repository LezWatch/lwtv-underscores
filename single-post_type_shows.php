<?php
/**
 * The Template for displaying all single Show pages.
 *
 * @package YIKES Starter
 */

// Build the icon.
$icon = '<div class="show-header-svg">';

// Show star if applicable.
$icon .= lwtv_yikes_show_star( get_the_ID() );

// Show love if applicable.
if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true ) ) {
	$heart = lwtv_symbolicons( 'hearts.svg', 'fa-heart' );
	$icon .= ' <span role="img" aria-label="We Love This Show!" data-toggle="tooltip" title="We Love This Show!" class="show-we-love">' . $heart . '</span>';
}

$icon .= '</div>';
// Icon is built.
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

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content show-page">
								<div class="card">
									<?php
									while ( have_posts() ) :
										the_post();
										get_template_part( 'template-parts/content', get_post_type() );
										// Force Jetpack share links to display ONCE.
										lwtv_yikes_jetpack_post_meta();
									endwhile; // end of the loop.
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
