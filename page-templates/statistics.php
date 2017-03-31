<?php
/**
* Template Name: Statistics
* Description: Used as a page template to show page contents, followed by a loop
* to show the stats of lezbians and what not.
*
* This uses var query data to determine what to show.
*/

$statstype = ( isset($wp_query->query['statistics'] ) )? $wp_query->query['statistics'] : 'main' ;
$validstat = array('death', 'characters', 'shows', 'lists', 'main', 'trends' );

if ( !in_array( $statstype, $validstat ) ){
	wp_redirect( get_site_url().'/stats/' , '301' );
	exit;
}

$iconpath = LWTV_SYMBOLICONS_PATH.'/svg/line_graph.svg';
$title = "Statistics";
$intro = '';

if ( $statstype == 'death' ) {
	$iconpath = LWTV_SYMBOLICONS_PATH.'/svg/rip_gravestone.svg';
	$title = "Statistics on Death";
	$intro = 'For a pure list of all dead, we have <a href="https://lezwatchtv.com/trope/dead-queers/">shows where characters died</a> as well as <a href="https://lezwatchtv.com/cliche/dead/">characters who have died</a> (aka the <a href="https://lezwatchtv.com/cliche/dead/">Dead Lesbians</a> list).';
}
if ( $statstype == 'characters' ) {
	$iconpath = LWTV_SYMBOLICONS_PATH.'/svg/users.svg';
	$title = "Statistics on Characters";
	$intro = 'Statistics specific to characters (sexuality, gender IDs, role types, etc).';
}
if ( $statstype == 'shows' ) {
	$iconpath = LWTV_SYMBOLICONS_PATH.'/svg/tv_retro.svg';
	$title = "Statistics on Shows";
	$intro = 'Statistics specific to shows.';
}
if ( $statstype == 'lists' ) {
	$iconpath = LWTV_SYMBOLICONS_PATH.'/svg/bar_graph_alt.svg';
	$intro = 'Raw statistics.';
}
if ( $statstype == 'trends' ) {
	$iconpath = LWTV_SYMBOLICONS_PATH.'/svg/line_graph.svg';
	$intro = 'Trendlines and predictions.';
}

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<header class="page-header">
			<div class="archive-description">
				<span role="img" aria-label="calendar" title="calendar" class="taxonomy-svg calendar"><?php echo file_get_contents( $iconpath ); ?></span>
				<h1 class="archive-title"><?php echo $title; ?></h1>
				<p><?php echo $intro; ?></p>
			</div>
		</header>

<?php

	if ( $statstype == 'main' ) {
		
		the_content();
		?>
		<hr>

		<div id="statistics">
			<ul>
				<li><strong><a href="/characters/">Total Characters</a></strong> (<?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?>)</li>
				<li><strong><a href="/shows/">Total Shows</a></strong> (<?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?>)</li>
			</ul>
		</div>

		<div id="statistics">
			<?php
				LWTV_Stats::generate( 'characters', 'cliches', 'barchart' );
				LWTV_Stats::generate( 'shows', 'tropes', 'barchart' );
			?>
		</div>

		<hr>

		<div id="statistics">
			<?php
				LWTV_Stats::generate( 'characters', 'cliches', 'list' );
				LWTV_Stats::generate( 'shows', 'tropes', 'list' );
			?>
		</div>
		<?php
	}

	if ( $statstype == 'characters' ) {
		?>
		<h2><a href="/characters/">Total Characters</a></strong> (<?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?>)</h2>

		<center><a href="#charts">Charts</a> // <a href="#percentages">Percentages</a></center>

		<div id="statistics">
			<h2><a name="charts">Charts</a></h2>

			<div id="container" class="one-half">
				<h3>Sexuality</h3>
				<?php LWTV_Stats::generate( 'characters', 'sexuality', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Gender Identity</h3>
				<?php LWTV_Stats::generate( 'characters', 'gender', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Queer IRL</h3>
				<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>By Role</h3>
				<?php LWTV_Stats::generate( 'characters', 'role', 'piechart' ); ?>
			</div>
		</div>

		<hr>

		<div id="statistics">
			<h2><a name="percentages">Percentages</a></h2>

				<h3>Sexual Identity</h3>
				<?php LWTV_Stats::generate( 'characters', 'sexuality', 'percentage' ); ?>

				<h3>Gender Identity</h3>
				<?php LWTV_Stats::generate( 'characters', 'gender', 'percentage' ); ?>

				<h3>Roles</h3>
				<?php LWTV_Stats::generate( 'characters', 'role', 'percentage' ); ?>

				<h3>Queer IRL</h3>
				<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'percentage' ); ?>
		</div>
		<?php
	}

	if ( $statstype == 'death' ) {
		?>
		<h2>Totals of Death</h2>

		<div id="statistics">
			<?php
				$deadchars = LWTV_Stats::generate( 'characters', 'dead', 'count' );
				$allchars  = LWTV_Stats::generate( 'characters', 'all', 'count' );
				$deadshows = LWTV_Stats::generate( 'shows', 'dead', 'count' );
				$allshows  = LWTV_Stats::generate( 'shows', 'all', 'count' );

				$deadchar_percent = round( ( $deadchars / $allchars ) * 100 , 2 ) ;
				$deadshow_percent = round( ( $deadshows / $allshows ) * 100 , 2 );
			?>

			<ul>
				<li><a href="/cliche/dead/">Dead Characters</a> — <?php echo $deadchar_percent; ?>% (<?php echo $deadchars; ?> characters)</li>
				<li><a href="/trope/dead-queers/">Shows with Dead</a> — <?php echo $deadshow_percent; ?>% (<?php echo $deadshows; ?> shows)</li>
		</div>

		<center><a href="#charts">Charts</a> // <a href="#percentages">Percentages</a></center>

		<div id="pies">
			<h2><a name="charts">Charts</a></h2>

			<div id="container" class="one-half">
				<h3>Shows With Dead</h3>
				<?php LWTV_Stats::generate( 'characters', 'dead-shows', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Character Sexuality</h3>
				<?php LWTV_Stats::generate( 'characters', 'dead-sex', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Character Gender Identity</h3>
				<?php LWTV_Stats::generate( 'characters', 'dead-gender', 'piechart' ); ?>
			</div>
		</div>

		<hr>

		<p>On average, <strong><?php LWTV_Stats::generate( 'characters', 'dead-years', 'average' ); ?></strong> characters die per year (including years where no queers died).</p>

		<div id="bars">
			<?php LWTV_Stats::generate( 'characters', 'dead-years', 'barchart' ); ?>
		</div>

		<div id="statistics">
			<h3>Deaths by Year</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-years', 'percentage' ); ?>
		</div>

		<hr>

		<div id="statistics">
			<h2><a name="percentages">Percentages</a></h2>

			<p>Percentages are of <em>all</em> queers and shows, not just the dead.</p>

			<h3>Shows</h3>
			<?php LWTV_Stats::generate( 'shows', 'dead-shows', 'percentage' ); ?>

			<h3>Sexual Orientation</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-sex', 'percentage' ); ?>

			<h3>Gender Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-gender', 'percentage' ); ?>

		</div>

		<?php
	}

	if ( $statstype == 'shows' ) {
		?>
		<h2><a href="/shows/">Total Shows</a></strong> (<?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?>)</h2>

		<center><a href="#charts">Charts</a> // <a href="#percentages">Percentages</a></center>

		<div id="pies">
			<h2><a name="charts">Charts</a></h2>

			<div id="container" class="one-half">
				<h3>Worth It?</h3>
				<?php LWTV_Stats::generate( 'shows', 'thumbs', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Stars</h3>
				<?php LWTV_Stats::generate( 'shows', 'stars', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Show Format</h3>
				<?php LWTV_Stats::generate( 'shows', 'formats', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Trigger Warnings</h3>
				<?php LWTV_Stats::generate( 'shows', 'trigger', 'piechart' ); ?>
			</div>

			<div id="container" class="one-half">
				<h3>Currently Airing</h3>
				<?php LWTV_Stats::generate( 'shows', 'current', 'piechart' ); ?>
			</div>
		</div>

		<hr>

		<div id="statistics">
			<h2><a name="percentages">Percentages</a></h2>

			<h3>Worth It Scores</h3>
			<?php LWTV_Stats::generate( 'shows', 'thumbs', 'percentage' ); ?>

			<h3>Stars Rankings</h3>
			<?php LWTV_Stats::generate( 'shows', 'stars', 'percentage' ); ?>

			<h3>Stars Rankings</h3>
			<?php LWTV_Stats::generate( 'shows', 'formats', 'percentage' ); ?>

			<h3>Trigger Warnings</h3>
			<?php LWTV_Stats::generate( 'shows', 'trigger', 'percentage' ); ?>

			<h3>Currently Airing</h3>
			<?php LWTV_Stats::generate( 'shows', 'current', 'percentage' ); ?>

		</div>

	<?php
	}
	if ( $statstype == 'trends' ) {
		?>
		<h2>Death Per Year</h2>

		<div id="statistics">
			<?php LWTV_Stats::generate( 'characters', 'dead-years', 'trendline' ); ?>
		</div>
		<?php
	}
?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();