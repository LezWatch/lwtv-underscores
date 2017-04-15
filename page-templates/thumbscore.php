<?php
/**
* Template Name: Thumbs
* Description: Show shows with specific thumbs based on page slug.
*/

// Base Defines
$thumbscore = ( isset($wp_query->query['thumbscore'] ) )? $wp_query->query['thumbscore'] : '' ;
$validthumb = array('up', 'down', 'meh', 'yes', 'no');

// Redirect sections becuase I don't want to edit all the posts.
if ( !in_array( $thumbscore, $validthumb ) ){
	wp_redirect( get_site_url().'/show/' , '301' );
	exit;
} elseif( $thumbscore == 'yes' ){
	wp_redirect( get_site_url().'/thumbs/up/' , '301' );
	exit;
} elseif( $thumbscore == 'no' ){
	wp_redirect( get_site_url().'/thumbs/down/' , '301' );
	exit;
}

// Conditional defines.
if ( $thumbscore == 'up' ) {
	$thumb = 'yes';
	$icon = 'thumbs_up.svg';
} elseif ( $thumbscore == 'down' ) {
	$thumb = 'no';
	$icon = 'thumbs_down.svg';
} elseif ( $thumbscore == 'meh' ) {
	$icon = 'meh-o.svg';
	$thumb = 'meh';
}

$type           = 'post_type_shows';
$query_args     = LWTV_Loops::post_meta_query( 'post_type_shows', 'lezshows_worthit_rating', $thumb, 'IN' );
$count_posts    = $query_args->post_count;
$iconpath       = LP_SYMBOLICONS_PATH . '/svg/' . $icon;

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<header class="page-header">
			<div class="archive-description">
				<span role="img" aria-label="calendar" title="calendar" class="taxonomy-svg calendar"><?php echo file_get_contents( $iconpath ); ?></span>
				<h1 class="archive-title">Worth It? <?php echo ucfirst( $thumb ).' Star Shows ('. $count_posts .')'; ?></h1>
				<p>Should you watch this show? <?php echo ucfirst( $thumb ); ?> Star</p>
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