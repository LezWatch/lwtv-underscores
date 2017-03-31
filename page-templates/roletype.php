<?php
/*
 * Template Name: Characters by Roles
 * Description: Show them all by groups, based on page name.
 */

$thisrole = ( isset($wp_query->query['roletype'] ) )? $wp_query->query['roletype'] : '' ;
$validroles = array('regular', 'recurring', 'guest');

if ( !in_array( $thisrole, $validroles ) ){
	wp_redirect( get_site_url().'/character/' , '301' );
	exit;
}

$type           = 'post_type_characters';
$query_args     = LWTV_Loops::post_meta_query( $type, 'lezchars_show_group', $thisrole, 'LIKE' );
$count_posts    = $query_args->post_count;
$iconpath       = LWTV_SYMBOLICONS_PATH.'/svg/person.svg';

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<header class="page-header">
			<div class="archive-description">
				<h1 class="archive-title">
					<?php echo ucfirst( $thisrole ).' Characters ('. $count_posts .')'; ?>
					<span role="img" aria-label="calendar" title="calendar" class="taxonomy-svg calendar"><?php echo file_get_contents( $iconpath ); ?></span>
				</h1>
				<p>Characters who are considered to be cast as <?php echo $thisrole; ?>s. Some characters have multiple roles, of course...</p>
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