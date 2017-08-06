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

	$taxicon = $term->name;
	if ( defined( 'LP_SYMBOLICONS_PATH' ) )  {

		$iconpath = LP_SYMBOLICONS_PATH;
		$termicon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
		$svg      = $termicon ? $termicon.'.svg' : 'square.svg';

		if ( get_query_var( 'taxonomy' ) == 'lez_country' ) {
			$svg = 'globe_stand.svg';
			$description = $description ?: "Shows that originate in " . $term->name . ".";
		}

		if ( get_query_var( 'taxonomy' ) == 'lez_stations' ) {
			$svg = 'tv_retro.svg';
			$description = $description ?: "Shows that air on " . $term->name . ".";
		}

		$get_svg = wp_remote_get( $iconpath . $svg );
		$icon    = $get_svg['body'];

		$taxicon = '<span role="img" aria-label="'.$term->name.'" title="'.$term->name.'" class="taxonomy-svg '.$term->slug.'">' . $icon .'</span>';
				
	}

	$symbolicon = $taxicon;
}

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main"><div class="facetwp-template">
		<?php
		if ( have_posts() ) : ?>

			<header class="page-header">
				<?php
					the_archive_title( '<h1 class="page-title">' . $symbolicon, '</h1>' );
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
