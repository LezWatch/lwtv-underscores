<?php
/**
 * The template for displaying the death stats page
 *
 * @package LezWatchTV
 */

$deadchars = LWTV_Stats::generate( 'characters', 'dead', 'count' );
$allchars  = LWTV_Stats::generate( 'characters', 'all', 'count' );
$deadshows = LWTV_Stats::generate( 'shows', 'dead', 'count' );
$allshows  = LWTV_Stats::generate( 'shows', 'all', 'count' );

$deadchar_percent = round( ( $deadchars / $allchars ) * 100 , 2 ) ;
$deadshow_percent = round( ( $deadshows / $allshows ) * 100 , 2 );

$valid_views = array( 'piecharts', 'barcharts', 'percentages' );
$view        = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'piecharts' : $_GET['view'];

?>
<h2>Totals of Death</h2>

<div class="container">
	<div class="row">
		<div class="col">
			<strong><a href="/cliche/dead/">Dead Characters</a></strong> — <?php echo $deadchar_percent; ?>% (<?php echo $deadchars; ?> characters)
		</div>
		<div class="col">
			<strong><a href="/trope/dead-queers/">Shows with Dead</a></strong> — <?php echo $deadshow_percent; ?>% (<?php echo $deadshows; ?> shows)
		</div>
	</div>
</div>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Go to:</h4>
		<?php
		foreach ( $valid_views as $the_view ) {
			echo '<a class="breadcrumb-item" href="' . esc_url( add_query_arg( 'view', $the_view, '/statistics/death/' ) ) . '">' . ucfirst( $the_view ) . '</a> ';
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
					<h3>Shows With Dead</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-shows', 'piechart' ); ?>	
				</div>
				<div class="col-sm-6">
					<h3>Character Sexuality</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-sex', 'piechart' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Character Gender Identity</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-gender', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Character Role</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-role', 'piechart' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'barcharts':
		?>
		<p>On average, <strong><?php LWTV_Stats::generate( 'characters', 'dead-years', 'average' ); ?></strong> characters die per year (including years where no queers died).</p>
		
		<div class="container chart-container">
			<div class="row">
				<div class="col">
					<?php LWTV_Stats::generate( 'characters', 'dead-years', 'barchart' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<h3>Deaths by Year</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-years', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'percentages':
		?>
		<p>Percentages are of <em>all</em> characters and shows, not just the dead.</p>
		
		<div class="container percentage-container">
			<div class="row">
				<div class="col-sm-6">
					<h3>Shows</h3>
					<?php LWTV_Stats::generate( 'shows', 'dead-shows', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Sexual Orientation</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-sex', 'percentage' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Gender Identity</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-gender', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Character Role</h3>
					<?php LWTV_Stats::generate( 'characters', 'dead-role', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
}