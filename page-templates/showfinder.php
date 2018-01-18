<?php
/*
 * Template Name: Show Finder
 * Description: Show all shows with a special interface to help people find the ones they want.
 */
 
global $pager;

$amarchive  = true;

$queery = new WP_Query ( array(
	'post_type'              => 'post_type_shows',
	'update_post_term_cache' => false,
	'update_post_meta_cache' => false,
	'posts_per_page'         => 24,
	'order'                  => 'ASC',
	'orderby'                => 'title',
	'post_status'            => array( 'publish' ),
	'paged'                  => $paged,
) );

$count_posts = facetwp_display( 'counts' );
$icon        = lwtv_yikes_symbolicons( 'tv-hd.svg', 'fa-tv' );
$title       = '<span role="img" aria-label="post_type_shows" title="Shows" class="taxonomy-svg shows">' . $icon . '</span>';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<h1 class="facetwp-page-title entry-title">
					<?php echo 'TV Shows ('. $count_posts .'<span class="facetwp-count"></span>)'; ?>
					<?php echo $title; ?>
				</h1>

				<div class="archive-description">
					<?php 
						echo '<p>Looking for the greatest shows with the characters you want? The show finder can help! <span class="facetwp-description"></span></p>';
						echo '<p><span class="facetwp-sorted"></span></p>';
						echo facetwp_display( 'selections' );
					?>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main archive" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content facetwp-template">
								<div class="row site-loop show-archive-loop equal-height">
									<?php
									if ( $queery->have_posts() ):
										while ( $queery->have_posts() ): $queery->the_post();
											get_template_part( 'template-parts/excerpt', 'post_type_shows' );
										endwhile; 
									?>
								</div><!-- .site-loop -->

								<?php
									lwtv_yikes_facet_numeric_posts_nav( $queery );
									wp_reset_postdata();
							
									else :
										get_template_part( 'template-parts/content', 'none' );
							
								endif; ?>
							</div><!-- .entry-content -->
						</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-9 -->
	
			<div class="col-sm-3 site-sidebar showchars-sidebar site-loop">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-3 -->

		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();