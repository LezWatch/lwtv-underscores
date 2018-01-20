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
$validstat = array( 'death', 'characters', 'shows', 'lists', 'main', 'trends', 'nations' );

// Based on the type of stats, set our display:
switch ( $statstype ) {
	case 'death':
		$title = 'Statistics on Queer Female Deaths';
		$image = lwtv_yikes_symbolicons( 'grim-reaper.svg', 'fa-ban' );
		$intro = 'For a pure list of all dead, we have <a href="https://lezwatchtv.com/trope/dead-queers/">shows where characters died</a> as well as <a href="https://lezwatchtv.com/cliche/dead/">characters who have died</a> (aka the <a href="https://lezwatchtv.com/cliche/dead/">Dead Lesbians</a> list).';
		break;
	case 'characters':
		$title = 'Statistics on Queer Female Characters';
		$image = lwtv_yikes_symbolicons( 'chart-bar.svg', 'fa-chart-bar' );
		$intro = 'Statistics specific to characters (sexuality, gender IDs, role types, etc).';
		break;
	case 'shows':
		$title = 'Statistics on Shows with Queer Females';
		$image = lwtv_yikes_symbolicons( 'chart-pie.svg', 'fa-chart-pie' );
		$intro = 'Statistics specific to shows.';
		break;
	case 'trends':
		$title = 'Statistics in the form of Trendlines';
		$image = lwtv_yikes_symbolicons( 'graph-line.svg', 'fa-chart-line' );
		$intro = 'Trendlines and predictions.';
		break;
	case 'trends':
		$title = 'Statistics on Nations with Shows with Queer Females';
		$image = lwtv_yikes_symbolicons( 'globe.svg', 'fa-globe' );
		$intro = 'Data specific to queer representation on shows by nation.';
		break;
	case 'main':
	default: 
		$title = 'Statistics of Queer Females on TV';
		$image = lwtv_yikes_symbolicons( 'graph-bar.svg', 'fa-chart-area' );
		$intro = '';
		break;
}

$image = '<span role="img" aria-label="statistics" title="Statistics" class="taxonomy-svg statistics">' . $image . '</span>';

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
					<h1 class="entry-title"><?php echo $title . $image; ?></h1>
					<p><?php echo $intro; ?></p>
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
							get_template_part( 'template-parts/statistics', $statstype );
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-12 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();