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

$iconpath = '☃';
if ( defined( 'LP_SYMBOLICONS_PATH' ) )  {
	$get_svg  = wp_remote_get( LP_SYMBOLICONS_PATH . 'person.svg' );
	$iconpath = $get_svg['body'];
}

get_header(); ?>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">

				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<header class="archive-header card">
							<div class="archive-description">
								<h1 class="archive-title">
									<?php echo ucfirst( $thisrole ).' Characters ('. $count_posts .')'; ?>
									<span role="img" aria-label="calendar" title="calendar" class="taxonomy-svg calendar"><?php echo $iconpath; ?></span>
								</h1>
								<p>Characters who are considered to be cast as <?php echo $thisrole; ?>s. Some characters have multiple roles, of course ...</p>
							</div>
						</header>
				
						<?php
						$query = new WP_Query ( array(
							'post_type'              => $type,
							'posts_per_page'         => 24,
							'orderby'                => 'title',
							'order'                  => 'ASC',
							'no_found_rows'          => true,
							'update_post_term_cache' => false,
							'update_post_meta_cache' => false,
							'post_status'            => array( 'publish' ),
							'paged'                  => $paged,
							'meta_query'	         => array(
								array(
									'key'            => 'lezchars_show_group',
									'value'          => $thisrole,
									'compare'        => 'LIKE',
								),
							),
						) );
						wp_reset_query();

						if ( $query->have_posts() ) : 
						
							/* Start the Loop */
							while ( $query->have_posts() ) : $query->the_post();
								get_template_part( 'template-parts/archive-loop', get_post_type() );
							endwhile;
				
							lwtv_underscore_numeric_posts_nav( $query, $count_posts );
				
						else :
							get_template_part( 'template-parts/content', 'none' );
				
						endif;
					
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
				
			</div><!-- .col-sm-8 -->

			<div class="col-sm-4">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer(); ?>
