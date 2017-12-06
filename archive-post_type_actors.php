<?php
/**
 * The template for displaying archive pages for Actors
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

// Determine icon (Font-Awesome fallback)
$icon         = lwtv_yikes_symbolicons( 'team.svg', 'fa-users' );
$count_posts  = facetwp_display( 'counts' );
$title        = '<span role="img" aria-label="post_type_actors" title="Actors" class="taxonomy-svg actors">' . $icon . '</span>';
$descriptions = get_option( 'wpseo_titles' );
$description  = $descriptions['metadesc-ptarchive-post_type_actors' ];

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<?php
					the_archive_title( '<h1 class="facetwp-page-title entry-title">', ' (' . $count_posts . '<span class="facetwp-count"></span>)' . $title . '</h1>' );
				?>
				<div class="archive-description">
					<?php 
						echo '<p>' . $description . ' <span class="facetwp-description"></span></p>';
						echo '<p><span class="facetwp-sorted"></span></p>';
						echo facetwp_display( 'selections' );
					?>
				</div>
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
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content facetwp-template">
								<div class="row site-loop actor-archive-loop equal-height">
								<?php
								if ( have_posts() ) : ?>
									<?php
									/* Start the Loop */
									while ( have_posts() ) : the_post();
										?><div class="col-sm-4"><?php
										get_template_part( 'template-parts/excerpt', 'post_type_actors' );
										?></div><?php
									endwhile; ?>
										</div><!-- .row .site-loop -->
									<?php
									echo facetwp_display( 'pager' );
								else :
									get_template_part( 'template-parts/content', 'none' );
								endif; 
								?>
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