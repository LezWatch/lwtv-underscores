<?php
/**
 * The Template for displaying all single actor pages.
 *
 * @package YIKES Starter
 */

// Build the icon
$icon  = '<div class="show-header-svg">';
if ( lwtv_yikes_is_queer( $post->ID ) ) {
	$icon .= ' <span role="img" aria-label="Queer IRL Actor" data-toggle="tooltip" title="Queer IRL Actor" class="cliche-queer-irl">' . lwtv_yikes_symbolicons( 'rainbow.svg', 'fa-cloud' ) . '</span>';
}
$icon .= '</div>';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><?php the_title( '<h1 class="entry-title">', '</h1>' ); ?></div>
					<div class="col-2 icon plain"><?php echo $icon; ?></div>
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
							<div class="entry-content actor-page">
								<div class="card">
									<?php
										while ( have_posts() ) : the_post(); 
											get_template_part( 'template-parts/content', get_post_type() );
											// Force Jetpack to display sharing links where we want them.
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

<?php get_footer();