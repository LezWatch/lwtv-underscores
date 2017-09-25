<?php
/*
 * Template Name: Characters by Roles
 * Description: Show them all by groups, based on page name.
 */
 
global $pager;

$thisrole = ( isset($wp_query->query['roletype'] ) )? $wp_query->query['roletype'] : '' ;
$validroles = array('regular', 'recurring', 'guest');

if ( !in_array( $thisrole, $validroles ) ){
	wp_redirect( get_site_url().'/character/' , '301' );
	exit;
}

					$query = new WP_Query ( array(
						'post_type'              => 'post_type_characters',
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
						'posts_per_page'         => 24,
						'order'                  => 'ASC',
						'orderby'                => 'title',
						'post_status'            => array( 'publish' ),
						'paged'                  => $paged,
						'meta_query'             => array(
							array(
								'key'     => 'lezchars_show_group',
								'value'   => $thisrole,
								'compare' => 'LIKE',
							),
						),
					) );

$count_posts = facetwp_display( 'counts' );

//$count_posts = $query->post_count;

$icon        = lwtv_yikes_symbolicons( 'person.svg', 'fa-person' );
$selections  = facetwp_display( 'selections' );
$title       = '<span role="img" aria-label="post_type_characters" title="Characters" class="taxonomy-svg characters">' . $icon . '</span>';
$sort        = lwtv_yikes_facetwp_sortby( ( isset( $_GET['fwp_sort'] ) )? $_GET['fwp_sort'] : '' );

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<h1 class="facetwp-page-title page-title">
					<?php echo ucfirst( $thisrole ).' Characters ('. $count_posts .'<span class="facetwp-count"></span>)'; ?>
					<?php echo $title; ?>
				</h1>
				
				<div class="taxonomyg-description"><p>Characters who are considered to be cast as <?php echo $thisrole; ?>s. Some characters have multiple roles, of course ...<br />Sorted by <?php echo $sort; ?>.</p></div>
					<?php echo $selections; ?>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main"><div class="facetwp-template">

					<?php
					if ( $query->have_posts() ):
					?>
						<div class="container">
							<div class="row"><?php
								while ( $query->have_posts() ): $query->the_post();
									?><div class="col-sm-4"><?php
										get_template_part( 'template-parts/excerpt', 'post_type_characters' );
									?></div><?php
								endwhile; ?>
								</div>
							</div><?php

							lwtv_yikes_facet_numeric_posts_nav( $query );
							
							wp_reset_postdata(); 
			
					else :
						get_template_part( 'template-parts/content', 'none' );
				
					endif; ?>

					</div></div><!-- #content -->
				</div><!-- #primary -->
	
			</div><!-- .col-sm-9 -->
	
			<div class="col-sm-3">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-3 -->

		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();