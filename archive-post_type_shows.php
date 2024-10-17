<?php
/**
 * The template for displaying archive pages for Shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$icon        = lwtv_plugin()->get_symbolicon( 'tv-hd.svg', 'fa-tv' );
$count_posts = ( function_exists( 'facetwp_display' ) ) ? facetwp_display( 'counts' ) : '';
$show_title  = '<span role="img" aria-label="post_type_shows" title="Shows" class="taxonomy-svg shows">' . $icon . '</span>';
$seo_titles  = get_option( 'wpseo_titles' );
$seo_desc    = $seo_titles['metadesc-ptarchive-post_type_shows'];

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<div class="row">
					<div class="col-10">
						<?php the_archive_title( '<h1 class="facetwp-page-title entry-title"><span class="facetwp-title">', '</span> (' . $count_posts . '<span class="facetwp-count"></span>)</h1>' ); ?>
					</div>
					<div class="col-2 icon plain">
						<?php echo wp_kses_post( $show_title ); ?>
					</div>
				</div>
				<div class="row">
					<div class="col">
						<div class="archive-description">
							<?php
							echo '<p>' . wp_kses_post( $seo_desc ) . ' <span class="facetwp-description"></span></p>';
							if ( function_exists( 'facetwp_display' ) ) {
								echo wp_kses_post( facetwp_display( 'selections' ) );
							}
							?>
							<p><span class="facetwp-sorted"></span></p>
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
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content">
								<div class="row site-loop show-archive-loop">
									<div class="facetwp-template">
										<?php
										if ( have_posts() ) {
											/* Start the Loop */
											while ( have_posts() ) {
												the_post();
												get_template_part( 'template-parts/excerpt/shows' );
											}
										} else {
											get_template_part( 'template-parts/content/none' );
										}
										?>
									</div>
								</div><!-- .site-loop -->
								<?php
								echo do_shortcode( '[facetwp facet="pager_list"]' );
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

<?php

get_footer();
