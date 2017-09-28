<?php
/**
 * The Template for displaying all single Show pages.
 *
 * @package YIKES Starter
 */

// Build the show icon
$icon = '<div class="show-header-svg">';
// If this is a show, we may want to show a star or a heart.
if ( get_post_type() == 'post_type_shows' ) {	
	// If there's a star, we'll show it:
	if ( get_post_meta( get_the_ID(), 'lezshows_stars', true) ) {
		$color = esc_attr( get_post_meta( get_the_ID(), 'lezshows_stars' , true ) );
		$star  = lwtv_yikes_symbolicons( 'star.svg', 'fa-star' );
		$icon .= ' <span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" data-toggle="tooltip" title="' . ucfirst( $color ) . ' Star Show" class="show-star ' . $color . '">' . $star . '</span>';
	}
	
	// If we love this show, we'll show it:
	if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true) ) {
		$heart = lwtv_yikes_symbolicons( 'heart.svg', 'fa-heart' );
		$icon .= ' <span role="img" aria-label="We Love This Show!" data-toggle="tooltip" title="We Love This Show!" class="show-we-love">' . $heart . '</span>';
	}
}
$icon .= '</div>';
 
get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="showschar-header">
				<?php the_title( '<h1 class="entry-title">', $icon . '</h1>' ); ?>
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
										while ( have_posts() ) : the_post(); 
											get_template_part( 'template-parts/content', get_post_type() );
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

<?php get_footer();