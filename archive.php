<?php
/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */

$symbolicon = '';
if ( is_tax() ) {
	$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
	$iconpath = LP_SYMBOLICONS_PATH.'/svg/';
	$termicon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
	$icon = $termicon ? $termicon.'.svg' : 'square.svg';

	if ( get_query_var( 'taxonomy' ) == 'lez_stations' ) {
		$icon = 'tv_retro.svg';
		$description = $description ?: "Where to watch...";
	}

	if ( !file_exists( $iconpath . $icon ) ) $icon = 'square.svg';

	$symbolicon = '<span role="img" aria-label="'.$term->name.'" title="'.$term->name.'" class="taxonomy-svg '.$term->slug.'">'.file_get_contents( $iconpath . $icon ).'</span>';
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

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main"><div class="facetwp-template">
		<?php
		if ( have_posts() ) : ?>

			<header class="page-header">
				<?php
					the_archive_title( '<h1 class="page-title">' . $symbolicon, '<br />Sorted By ' . $sort . ' (' . $count_posts . ')</h1>' );
					the_archive_description( '<div class="archive-description">', '</div>' );

					echo $selections;
				?>
			</header><!-- .page-header -->


			<?php
			/* Start the Loop */
			while ( have_posts() ) : the_post();

				get_template_part( 'template-parts/archive/'.get_post_type() );

			endwhile;

			echo facetwp_display( 'pager' );

		else :
			get_template_part( 'template-parts/content/none' );

		endif; ?>

		</div></main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();
