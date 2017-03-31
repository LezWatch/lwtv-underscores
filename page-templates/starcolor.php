<?php
/**
* Template Name: Stars
* Description: Show shows with stars based on page slug.
*/

$color = ( isset($wp_query->query['starcolor'] ) )? $wp_query->query['starcolor'] : 'all' ;
$validcolors = array('gold', 'silver');

if ( in_array( $color, $validcolors ) ){
	$title = ucfirst($color);
} elseif ( $color == 'all' ) {
	$title = 'All Gold and Silver';
	$color = $validcolors;
} else {
	wp_redirect( get_site_url() , '301' );
	exit;
}

$type           = 'post_type_shows';
$query_args     = LWTV_Loops::post_meta_query( 'post_type_shows', 'lezshows_stars', $color, 'IN' );
$count_posts    = $query_args->post_count;
$iconpath       = LWTV_SYMBOLICONS_PATH.'/svg/star.svg';

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<header class="page-header">
			<div class="archive-description">
				<span role="img" aria-label="calendar" title="calendar" class="taxonomy-svg calendar"><?php echo file_get_contents( $iconpath ); ?></span>
				<h1 class="archive-title"><?php echo ucfirst( $title ).' Star Shows ('. $count_posts .')'; ?></h1>
				<p>Shows awarded the covetous <?php echo ucfirst( $title ); ?> Star</p>
			</div>
		</header>

			<?php
			$query = new WP_Query ( array(
					'post_type'              => $type,
					'posts_per_page'         => get_option( 'posts_per_page' ),
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
					'post_status'            => array( 'publish' ),
					'paged'                  => $paged,
				)
			);
			wp_reset_query();
	
			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					get_template_part( 'template-parts/archive/'.$type );
				}
			
				lwtv_underscore_numeric_posts_nav( $query, $count_posts );
			}
		
			?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();