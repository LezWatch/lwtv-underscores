<?php
/**
* Template Name: Statistics
* Description: Used as a page template to show page contents, followed by a loop
* to show the stats of lezbians and what not.
*
* This uses var query data to determine what to show. All of the code is in the 
* /lwtv-plugin/statistics.php file so that it can be easily ported to any new theme.
*/

$statstype = ( isset($wp_query->query['statistics'] ) )? $wp_query->query['statistics'] : 'main' ;
$validstat = array('death', 'characters', 'shows', 'lists', 'main', 'trends' );

// If there's no valid stat, we bail
if ( !in_array( $statstype, $validstat ) ){
	wp_redirect( get_site_url().'/stats/' , '301' );
	exit;
}

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="archive-description">
					<span role="img" aria-label="calendar" title="calendar" class="taxonomy-svg calendar"><?php echo file_get_contents( LWTV_Stats_Display::iconpath( $statstype ) ); ?></span>
					<h1 class="archive-title"><?php echo LWTV_Stats_Display::title( $statstype ); ?></h1>
					<p><?php echo LWTV_Stats_Display::intro( $statstype ); ?></p>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-12">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">				
						<?php 
							if ( $statstype == 'main' ) the_content();
							LWTV_Stats_Display::display( $statstype );
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-12 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer(); ?>
