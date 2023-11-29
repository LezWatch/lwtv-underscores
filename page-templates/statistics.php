<?php
/**
 * Template Name: Statistics
 * Description: Used as a page template to show page contents, followed by a loop
 * to show the stats of lesbians and what not.
 *
 * This uses var query data to determine what to show. All of the code is in the
 * /lwtv-plugin/statistics.php file so that it can be easily ported to any new theme.
 *
 * @package LezWatch.TV
 */

$validstat = array( 'death', 'characters', 'shows', 'main', 'actors', 'nations', 'stations', 'formats' );
$statstype = ( isset( $wp_query->query['statistics'] ) && in_array( $wp_query->query['statistics'], $validstat, true ) ) ? esc_attr( $wp_query->query['statistics'] ) : 'main';

// Defaults:
$theme_defaults = array(
	'image' => lwtv_symbolicons( 'graph-bar.svg', 'fa-chart-area' ),
	'intro' => '',
);

if ( class_exists( 'LWTV_Theme_Stats_Symbolicon' ) ) {
	$theme_defaults = ( new LWTV_Theme_Stats_Symbolicon() )->make( $statstype );
}

$image = '<span role="img" aria-label="statistics" title="Statistics" class="taxonomy-svg statistics">' . $theme_defaults['image'] . '</span>';

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
							<p><?php echo wp_kses_post( $theme_defaults['intro'] ); ?></p>
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
						<div class="statistics">
							<?php
							if ( 'main' === $statstype ) {
								the_content();
							}

							if ( method_exists( 'LWTV_Statistics_Gutenberg_SSR', 'statistics' ) ) {
								$attributes = array(
									'page' => $statstype,
								);

								// phpcs:ignore WordPress.Security.EscapeOutput
								echo ( new LWTV_Statistics_Gutenberg_SSR() )->statistics( $attributes );
							} else {
								echo '<p>After this maintenance, statistics will be right back!</p>';
							}
							?>
						</div>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-12 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
