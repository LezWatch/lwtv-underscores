<?php
/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

// Set everything to empty
$title_prefix = $title_suffix = $icon = '';

// Set the defaults
$term     = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
$taxonomy = get_query_var( 'taxonomy' );
$termicon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
$svg      = $termicon ? $termicon . '.svg' : 'square.svg';

$count_posts = $term->count;

if ( 'post_type_characters' == get_post_type( get_the_ID() ) || 'post_type_shows' == get_post_type( get_the_ID() ) ) {
	$count_posts = facetwp_display( 'counts' );
	
	switch ( get_post_type( get_the_ID() ) ) {
		case 'post_type_characters':
			$title_suffix = ' Characters';
			break;
		case 'post_type_shows':
			$title_prefix = 'TV Shows ';

			// TV Shows are harder to have titles
			switch ( get_query_var( 'taxonomy' ) ) {
				case 'lez_tropes':
					$title_prefix .= 'With The ';
					$title_suffix .= ' Trope';
					break;
				case 'lez_country':
					$svg = 'globe_stand.svg';
					$title_prefix .= 'That Originate In ';
					break;
				case 'lez_stations':
					$svg = 'tv_retro.svg';
					$title_prefix .= 'That Air On ';
					break;
				case 'lez_formats':
					$title_prefix .= 'That Air As ';
					break;
				case 'lez_genres':
					$title_prefix = '';
					$title_suffix = ' TV Shows';
					break;
			}
			break;
	}
}

$selections = facetwp_display( 'selections' );
$sort       = lwtv_yikes_facetwp_sortby( ( isset( $_GET['fwp_sort'] ) )? $_GET['fwp_sort'] : '' );
$icon       = lwtv_yikes_symbolicons( $svg, 'fa-square' );
$title      = '<span role="img" aria-label="' . $term->name . '" title="' . $term->name . '" class="taxonomy-svg ' . $term->slug . '"> ' . $icon . '</span>';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<?php
					the_archive_title( '<h1 class="facetwp-page-title page-title">' . $title_prefix . $title, $title_suffix . ' (' . $count_posts . '<span class="facetwp-count"></span>)</h1>' );
					the_archive_description( '<div class="archive-description">', ' Sorted by ' . $sort . '.</div>' );
					echo $selections;
				?>
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
			        			<div class="row site-loop show-archive-loop equal-height">
									<?php
									if ( have_posts() ) : ?>
										<?php
										/* Start the Loop */
										while ( have_posts() ) : the_post();
											switch ( get_post_type( get_the_ID() ) ) {
												case 'post_type_characters':
													?><div class="col-sm-4"><?php
													get_template_part( 'template-parts/excerpt', 'post_type_characters' );
													?></div><?php
													break;
												case 'post_type_shows':
													get_template_part( 'template-parts/excerpt', 'post_type_shows' );
													break;
												default:
													get_template_part( 'template-parts/content', 'posts' );
											}				
									endwhile; ?>
								</div><!-- .site-loop -->

								<?php
									echo facetwp_display( 'pager' );
			
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