<?php
/**
 * The template for displaying the shows stats page
 *
 * @package LezWatchTV
 */

$valid_views = array( 'piecharts', 'barcharts', 'percentages' );
$view        = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'piecharts' : $_GET['view'];

?>
<h2>
	<a href="/shows/">Total Shows</a> (<?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?>)
</h2>

<p>The average show score is <strong><?php LWTV_Stats::generate( 'shows', 'scores', 'average' ); ?></strong>. The lowest score is <strong><?php LWTV_Stats::generate( 'shows', 'scores', 'low' ); ?></strong> and the highest is <strong><?php LWTV_Stats::generate( 'shows', 'scores', 'high' ); ?></strong>.</p>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Go to:</h4>
		<?php
		foreach ( $valid_views as $the_view ) {
			echo '<a class="breadcrumb-item" href="' . esc_url( add_query_arg( 'view', $the_view, '/statistics/shows/' ) ) . '">' . ucfirst( $the_view ) . '</a> ';
		}
		?>
	</nav>
</section>

<h2><a name="charts"><?php echo ucfirst( $view ) ?></a></h2>

<?php
switch ( $view ) {
	case 'piecharts':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<h3>Worth It?</h3>
					<?php LWTV_Stats::generate( 'shows', 'thumbs', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Stars</h3>
					<?php LWTV_Stats::generate( 'shows', 'stars', 'piechart' ); ?>
				</div>
			</div>
			<div class="row">	
				<div class="col-sm-6">
					<h3>Show Format</h3>
					<?php LWTV_Stats::generate( 'shows', 'formats', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Trigger Warnings</h3>
					<?php LWTV_Stats::generate( 'shows', 'triggers', 'piechart' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Shows We Love</h3>
					<?php LWTV_Stats::generate( 'shows', 'weloveit', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Currently Airing</h3>
					<?php LWTV_Stats::generate( 'shows', 'current', 'piechart' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'barcharts':
		?>
		<div class="container barchart-container">
			<div class="row">
				<div class="col">
					<h3>Shows per Countries</h3>
					<?php LWTV_Stats::generate( 'shows', 'country', 'barchart' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'percentages':
		?>
		<div class="container percentage-container">
			<div class="row">
				<div class="col-sm-6">
					<h3>Worth It Scores</h3>
					<?php LWTV_Stats::generate( 'shows', 'thumbs', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Stars Rankings</h3>
					<?php LWTV_Stats::generate( 'shows', 'stars', 'percentage' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Show Formats</h3>
					<?php LWTV_Stats::generate( 'shows', 'formats', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Trigger Warnings</h3>
					<?php LWTV_Stats::generate( 'shows', 'triggers', 'percentage' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Shows We Love</h3>
					<?php LWTV_Stats::generate( 'shows', 'weloveit', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Currently Airing</h3>
					<?php LWTV_Stats::generate( 'shows', 'current', 'percentage' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Tropes</h3>
					<?php LWTV_Stats::generate( 'shows', 'tropes', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Genres</h3>
					<?php LWTV_Stats::generate( 'shows', 'genres', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
}