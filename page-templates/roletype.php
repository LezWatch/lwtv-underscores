<?php
/*
 * Template Name: Characters by Roles
 * Description: Show them all by groups, based on page name.
 */
 
global $pager;

$thisrole   = ( isset($wp_query->query['roletype'] ) )? $wp_query->query['roletype'] : '' ;
$validroles = array('regular', 'recurring', 'guest');
$amarchive  = true;

if ( !in_array( $thisrole, $validroles ) ){
	wp_redirect( get_site_url().'/character/' , '301' );
	exit;
}

$queery = new WP_Query ( array(
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
$icon        = lwtv_yikes_symbolicons( 'user.svg', 'fa-user' );
$title       = '<span role="img" aria-label="post_type_characters" title="Characters" class="taxonomy-svg characters">' . $icon . '</span>';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<h1 class="facetwp-page-title entry-title">
					<?php echo ucfirst( $thisrole ).' Characters ('. $count_posts .'<span class="facetwp-count"></span>)'; ?>
					<?php echo $title; ?>
				</h1>

				<div class="archive-description">
					<?php 
						echo '<p>Characters who are considered to be cast as ' . $thisrole . 's. Some characters have multiple roles, of course. <span class="facetwp-description"></span></p>';
						echo '<p><span class="facetwp-sorted"></span></p>';
						echo facetwp_display( 'selections' );
					?>
				</div>
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
								<div class="row site-loop character-archive-loop equal-height">
									<?php
										if ( $queery->have_posts() ):
										while ( $queery->have_posts() ): $queery->the_post();
											?><div class="col-sm-4"><?php
												get_template_part( 'template-parts/excerpt', 'post_type_characters' );
											?></div><?php
										endwhile; 
									?>
								</div>

								<?php

								lwtv_yikes_facet_numeric_posts_nav( $queery );
								
								wp_reset_postdata(); 
						
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