<?php
/**
 * The template for displaying archive pages for Shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

$icon        = lwtv_yikes_symbolicons( 'window.svg', 'fa-video-camera' );
$count_posts = facetwp_display( 'counts' );
$selections  = facetwp_display( 'selections' );
$title       = '<span role="img" aria-label="post_type_shows" title="Shows" class="taxonomy-svg shows">' . $icon . '</span>';
$sort        = lwtv_yikes_facetwp_sortby( ( isset( $_GET['fwp_sort'] ) )? $_GET['fwp_sort'] : '' );

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<?php
					the_archive_title( '<h1 class="page-title">' . $title, ' (' . $count_posts . '<span class="facetwp-count"></span>)</h1>' );
					$descriptions = get_option( 'wpseo_titles' );
					$description  = $descriptions['metadesc-ptarchive-post_type_shows'];
					echo '<div class="archive-description">' . $description . '<br />Sorted by ' . $sort . '.</div>';
					echo $selections;
				?>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>


<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main"><div class="facetwp-template">
						<?php
						if ( have_posts() ) : ?>

							<?php
							/* Start the Loop */
							while ( have_posts() ) : the_post();
								get_template_part( 'template-parts/excerpt', 'post_type_shows' );
				
							endwhile;
				
							echo facetwp_display( 'pager' );
				
						else :
							get_template_part( 'template-parts/content', 'none' );
				
						endif; ?>

					</div></div><!-- #content -->
				</div><!-- #primary -->
	
			</div><!-- .col-sm-8 -->
	
			<div class="col-sm-4">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-4 -->

		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();