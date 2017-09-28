<?php
/**
 * The Template for displaying all single posts.
 *
 * @package YIKES Starter
 */

// Default to a blank icon
$icon = '';

// If this is a show, we may want to show a star or a heart.
if ( get_post_type() == 'post_type_shows' ) {	
	// If there's a star, we'll show it:
	if ( get_post_meta( get_the_ID(), 'lezshows_stars', true) ) {
		$color = esc_attr( get_post_meta( get_the_ID(), 'lezshows_stars' , true ) );
		$star  = lwtv_yikes_symbolicons( 'star.svg', 'fa-star' );
		$icon .= ' <span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" title="' . ucfirst( $color ) . ' Star Show" class="show-star ' . $color . '">' . $star . '</span>';
	}
	
	// If we love this show, we'll show it:
	if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true) ) {
		$heart = lwtv_yikes_symbolicons( 'heart.svg', 'fa-heart' );
		$icon .= ' <span role="img" aria-label="We Love This Show!" title="We Love This Show!" class="show-we-love">' . $heart . '</span>';
	}
}
 
get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">			
				<?php the_title( '<h1 class="entry-title">', $icon . '</h1>' ); ?>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<?php
							while ( have_posts() ) : the_post(); 
								
								// Check for custom post types
								if ( 'post' === get_post_type() ) {
									get_template_part( 'template-parts/content', 'single' ); 
								} else {
									get_template_part( 'template-parts/content', get_post_type() );
								}

								// If comments are open or we have at least one comment, load up the comment template
								if ( comments_open() || '0' !== get_comments_number() ) {
									comments_template();
								}
								
								// Only show post nav on posts (to not break facet)
								if ( 'post' === get_post_type() ) yikes_starter_post_nav();

							endwhile; // end of the loop. 
						?>

					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-9 -->

			<div class="col-sm-3 site-sidebar site-loop">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-3 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();