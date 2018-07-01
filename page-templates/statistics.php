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
$validstat = array( 'death', 'characters', 'shows', 'main', 'actors', 'nations', 'stations' );

// Based on the type of stats, set our display:
switch ( $statstype ) {
	case 'death':
		$image = lwtv_yikes_symbolicons( 'grim-reaper.svg', 'fa-ban' );
		$intro = 'For a pure list of all dead, we have <a href="/trope/dead-queers/">shows where characters died</a> as well as <a href="/cliche/dead/">characters who have died</a> (aka the <a href="/cliche/dead/">Dead Lesbians</a> list).';
		break;
	case 'characters':
		$image = lwtv_yikes_symbolicons( 'chart-bar.svg', 'fa-chart-bar' );
		$intro = 'Data specific to queer characters.';
		break;
	case 'actors':
		$image = lwtv_yikes_symbolicons( 'graph-line.svg', 'fa-chart-line' );
		$intro = 'Data specific to actors who play queer characters.';
		break;
	case 'shows':
		$image = lwtv_yikes_symbolicons( 'chart-pie.svg', 'fa-chart-pie' );
		$intro = 'Data specific to TV shows with queer characters.';
		break;
	case 'nations':
		$image = lwtv_yikes_symbolicons( 'globe.svg', 'fa-globe' );
		$intro = 'Data specific to queer representation on shows by nation.';
		break;
	case 'stations':
		$image = lwtv_yikes_symbolicons( 'satellite-signal.svg', 'fa-bullhorn' );
		$intro = 'Data specific to queer representation on shows by channel or station.';
		break;
	case 'main':
	default: 
		$image = lwtv_yikes_symbolicons( 'graph-bar.svg', 'fa-chart-area' );
		$intro = '';
		break;
}

$image = '<span role="img" aria-label="statistics" title="Statistics" class="taxonomy-svg statistics">' . $image . '</span>';

// If there's no valid stat, we bail
if ( !in_array( $statstype, $validstat ) ){
	wp_redirect( get_site_url().'/statistics/' , '301' );
	exit;
}

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><h1 class="entry-title">Statistics 
						<?php 
							$title = ( 'main' !== $statstype )? 'on ' . ucfirst( $statstype ) : '';
							echo $title;
						?>
					</h1></div>
					<div class="col-2 icon plain"><?php echo $image; ?></div>
				</div>
				<div class="row">
					<div class="archive-description"><p><?php echo $intro; ?></p></div>
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
							get_template_part( 'template-parts/statistics', $statstype );
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-12 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();