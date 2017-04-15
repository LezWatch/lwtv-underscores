<?php
/*
 * Template Name: Newest Posts
 * Description: List the newest characters or shows, based on page name.
 * @package LezWatch_TV
 */

$type = ( isset( $wp_query->query['newtype'] ) )? $wp_query->query['newtype'] : false ;

$validnew = array( 'shows', 'characters' );

if ( !in_array( $type, $validnew ) ){	
	wp_redirect( get_site_url(), '301' );
	exit;
}

$archive_title  = 'Newest '.ucfirst( $type );
$count_posts    = wp_count_posts( 'post_type_'.$type )->publish;
$posttype       = 'post_type_'.$type;
$iconpath       = LP_SYMBOLICONS_PATH . '/svg/flag.svg';

add_filter( 'body_class', 'leznew_body_class' );
function leznew_body_class( $classes ) {
	global $type;
	$classes[] = 'newest-'.$type;
	return $classes;
}

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<header class="page-header">
			<div class="archive-description">
				<h1 class="archive-title">
					<?php echo $archive_title.' ('. $count_posts .')'; ?>
					<span role="img" aria-label="calendar" title="calendar" class="taxonomy-svg calendar"><?php echo file_get_contents( $iconpath ); ?></span>	
				</h1>
				<p><?php echo ucfirst($type); ?> in order of being added, newest first.</p>
			</div>
		</header>

			<?php
			$query = new WP_Query ( array(
					'post_type'              => $posttype,
					'posts_per_page'         => get_option( 'posts_per_page' ),
					'orderby'                => 'date',
					'order'                  => 'DESC',
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
					get_template_part( 'template-parts/archive/'.$posttype );
				}
			
				lwtv_underscore_numeric_posts_nav( $query );
			}
		
			?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();