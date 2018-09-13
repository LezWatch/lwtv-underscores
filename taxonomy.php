<?php
/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Get the Term information and icons.
$the_term     = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
$iconname     = lwtv_yikes_tax_archive_title( 'icon', get_post_type( get_the_ID() ), get_query_var( 'taxonomy' ) );
$the_icon     = '<span role="img" aria-label="' . $the_term->name . '" title="' . $the_term->name . '" class="taxonomy-svg ' . $the_term->slug . '"> ' . $iconname . '</span>';
$title_prefix = lwtv_yikes_tax_archive_title( 'prefix', get_post_type( get_the_ID() ), get_query_var( 'taxonomy' ) );
$title_suffix = lwtv_yikes_tax_archive_title( 'suffix', get_post_type( get_the_ID() ), get_query_var( 'taxonomy' ) );

// Count Posts: If this is a show or a character taxonomy, we do extra.
$count_posts = $the_term->count;
if ( in_array( get_post_type( get_the_ID() ), array( 'post_type_shows', 'post_type_characters' ), true ) ) {
	$count_posts = facetwp_display( 'counts' );
}

// Post Type Detector.
$post_type_is = rtrim( str_replace( 'post_type_', '', lwtv_yikes_get_post_types_by_taxonomy( $the_term->taxonomy ) ), 's' );

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10">
						<?php the_archive_title( '<h1 class="facetwp-page-title entry-title">' . $title_prefix, $title_suffix . ' (' . $count_posts . '<span class="facetwp-count"></span>)</h1>' ); ?>
					</div>
					<div class="col-2 icon plain">
						<?php echo lwtv_sanitized( $the_icon ); // WPCS: XSS ok. ?>
					</div>
				</div>
				<div class="row">
					<div class="col">
						<div class="archive-description">
							<?php
							echo '<h3 class="facetwp-title"></h3>';
							$no_desc_terms = array( 'lez_actor_gender', 'lez_actor_sexuality', 'lez_gender', 'lez_sexuality', 'lez_romantic', 'lez_stations' );
							if ( in_array( get_query_var( 'taxonomy' ), $no_desc_terms, true ) ) {
								$meta = get_option( 'wpseo_titles' );
								$desc = $meta[ 'metadesc-tax-' . get_query_var( 'taxonomy' ) ];
								$desc = str_replace( '%%term_title%%', $the_term->name, $desc );
								echo '<p>' . wp_kses_post( $desc ) . '</p>';
								echo '<span class="facetwp-description"></span>';
							} else {
								the_archive_description( '<p>', '<span class="facetwp-description"></span></p>' );
							}

							echo '<p><span class="facetwp-sorted"></span></p>';
							echo facetwp_display( 'selections' );
							?>
						</div>
					</div>
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
								<div class="row site-loop <?php echo esc_attr( $post_type_is ); ?>-archive-loop">
									<?php
									if ( have_posts() ) :
										/* Start the Loop */
										while ( have_posts() ) :
											the_post();
											switch ( get_post_type( get_the_ID() ) ) {
												case 'post_type_characters':
													get_template_part( 'template-parts/excerpt', 'post_type_characters' );
													break;
												case 'post_type_shows':
													get_template_part( 'template-parts/excerpt', 'post_type_shows' );
													break;
												case 'post_type_actors':
													get_template_part( 'template-parts/excerpt', 'post_type_actors' );
													break;
												default:
													get_template_part( 'template-parts/content', 'posts' );
											}
										endwhile;
									else :
										get_template_part( 'template-parts/content', 'none' );
									endif;
									?>
								</div><!-- .site-loop -->
								<?php echo facetwp_display( 'pager' ); ?>
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
