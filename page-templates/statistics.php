<?php
/**
 * Template Name: Statistics
 * Description: Used as a page template to show page contents, followed by a loop
 * to show the stats of lezbians and what not.
 *
 * This uses var query data to determine what to show. All of the code is in the
 * /lwtv-plugin/statistics.php file so that it can be easily ported to any new theme.
 *
 * @package LezWatch.TV
 */

$validstat = array( 'death', 'characters', 'shows', 'main', 'actors', 'nations', 'stations', 'formats' );
$statstype = ( isset( $wp_query->query['statistics'] ) && in_array( $wp_query->query['statistics'], $validstat, true ) ) ? esc_attr( $wp_query->query['statistics'] ) : 'main';


// Based on the type of stats, set our display.
switch ( $statstype ) {
	case 'death':
		$image = lwtv_symbolicons( 'grim-reaper.svg', 'fa-ban' );
		$intro = 'For a pure list of all dead, we have <a href="/trope/dead-queers/">shows where characters died</a> as well as <a href="/cliche/dead/">characters who have died</a> (aka the <a href="/cliche/dead/">Dead Lesbians</a> list).';
		break;
	case 'characters':
		$image = lwtv_symbolicons( 'chart-bar.svg', 'fa-chart-bar' );
		$intro = 'Data specific to queer characters.';
		break;
	case 'actors':
		$image = lwtv_symbolicons( 'graph-line.svg', 'fa-chart-line' );
		$intro = 'Data specific to actors who play queer characters.';
		break;
	case 'shows':
		$image = lwtv_symbolicons( 'chart-pie.svg', 'fa-chart-pie' );
		$intro = 'Data specific to TV shows with queer characters.';
		break;
	case 'nations':
		$image = lwtv_symbolicons( 'globe.svg', 'fa-globe' );
		$intro = 'Data specific to queer representation on shows by nation.';
		break;
	case 'stations':
		$image = lwtv_symbolicons( 'satellite-signal.svg', 'fa-bullhorn' );
		$intro = 'Data specific to queer representation on shows by channel or station.';
		break;
	case 'formats':
		$image = lwtv_symbolicons( 'graph-bar.svg', 'fa-chart-area' );
		$intro = 'Data specific to queer representation by show format (i.e. TV show, web series, etc.)';
		break;
	case 'main':
		$image = lwtv_symbolicons( 'graph-bar.svg', 'fa-chart-area' );
		$intro = '';
		break;
}

$image = '<span role="img" aria-label="statistics" title="Statistics" class="taxonomy-svg statistics">' . $image . '</span>';

// If there's no valid stat, we bail.
if ( ! in_array( $statstype, $validstat, true ) ) {
	wp_safe_redirect( get_site_url() . '/statistics/', '301' );
	exit;
}

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10">
						<h1 class="entry-title">
							Statistics
							<?php
								$stattitle = ( 'main' !== $statstype ) ? 'on ' . ucfirst( $statstype ) : '';
								echo wp_kses_post( $stattitle );
							?>
						</h1>
					</div>
					<div class="col-2 icon plain"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				</div>
				<div class="row">
					<div class="col">
						<div class="archive-description">
							<p><?php echo wp_kses_post( $intro ); ?></p>
						</div>
					</div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-12">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<?php
						if ( 'main' === $statstype ) {
							the_content();
						}

						if ( method_exists( 'LWTV_Stats_SSR', 'statistics' ) ) {
							$attributes = array(
								'page' => $statstype,
							);

							// phpcs:ignore WordPress.Security.EscapeOutput
							echo ( new LWTV_Stats_SSR() )->statistics( $attributes );
						} else {
							echo '<p>After this maintenance, statistics will be right back!</p>';
						}
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-12 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
