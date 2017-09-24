<?php
/**
 * The template for displaying archive pages for Characters
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */

$symbolicon = '';
if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
	$get_svg = wp_remote_get( LP_SYMBOLICONS_PATH . 'users.svg' );
	$icon    = $get_svg['body'];

	$symbolicon = '<span role="img" aria-label="post_type_characters" title="Characters" class="taxonomy-svg characters">' . $icon .'</span>';
}

$count_posts = facetwp_display( 'counts' );
$selections  = facetwp_display( 'selections' );

// I wish this wasn't hard coded, but it's very weird and I was very tired.
$fwp_sort    = ( isset( $_GET['fwp_sort'] ) )? $_GET['fwp_sort'] : '';
switch ( $fwp_sort ) {
	case 'most_queers':
		$sort = 'Number of Characters (Descending)';
		break;
	case 'least_queers':
		$sort = 'Number of Characters (Ascending)';
		break;
	case 'most_dead':
		$sort = 'Number of Dead Characters (Descending)';
		break;
	case 'least_dead':
		$sort = 'Number of Dead Characters (Ascending)';
		break;
	case 'date_desc':
		$sort = 'Date (Newest)';
		break;
	case 'date_asc':
		$sort = 'Date (Oldest)';
		break;
	case 'title_desc':
		$sort = 'Name (Z-A)';
		break;
	case 'title_asc':
	default:
		$sort = 'Name (A-Z)';
}

get_header(); ?>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<?php if ( have_posts() ) : ?>

							<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
								<header class="entry-header">
									<?php
										the_archive_title( '<h1 class="entry-title"><i class="fa fa-users" aria-hidden="true"></i> ', '</h1>' );
									?>
									<?php
										echo '<h2>Sorted By ' . $sort . ' (' . $count_posts . ')</h2>' ;					
										$descriptions = get_option( 'wpseo_titles' );
										$description  = $descriptions['metadesc-ptarchive-post_type_characters'];
										echo '<div class="taxonomy-description">' . $description . '</div>';
										echo $selections;
									?>
								</header><!-- .entry-header -->
	
								<div class="entry-content">
				        			<div class="row site-loop main-posts-loop equal-height">

										<?php while ( have_posts() ) : the_post(); ?>

											<div class="col-sm-4">
												<?php get_template_part( 'template-parts/content', 'characters' ); ?>
											</div>

										<?php endwhile; ?>

									</div>

									<?php wp_bootstrap_pagination(); ?>

									<?php else : ?>

										<?php get_template_part( 'content', 'none' ); ?>

									<?php endif; ?>

								</div>
							</article><!-- #post-## -->

					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-8 -->

			<div class="col-sm-3 site-sidebar site-loop">

				<?php dynamic_sidebar( 'sidebar-3' ); ?>

			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->


<?php get_footer(); ?>