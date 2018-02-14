<?php
/**
 * The template for displaying the character stats page
 *
 * @package LezWatchTV
 */

$valid_views = array( 'piecharts', 'barcharts', 'percentages' );
$view        = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'piecharts' : $_GET['view'];

?>

<h2>
	<a href="/characters/">Total Characters</a></strong> (<?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?>)
</h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Go to:</h4>
		<?php
		foreach ( $valid_views as $the_view ) {
			echo '<a class="breadcrumb-item" href="' . esc_url( add_query_arg( 'view', $the_view, '/statistics/characters/' ) ) . '">' . ucfirst( $the_view ) . '</a> ';
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
					<h3>Sexuality</h3>
					<?php LWTV_Stats::generate( 'characters', 'sexuality', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Gender Identity</h3>
					<?php LWTV_Stats::generate( 'characters', 'gender', 'piechart' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Queer IRL</h3>
					<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>By Role</h3>
					<?php LWTV_Stats::generate( 'characters', 'role', 'piechart' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'barcharts':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col">
					<h3>Actors per Character</h3>
					<?php LWTV_Stats::generate( 'actors', 'per-char', 'barchart' ); ?>
					<p>This chart displays the number of actors who play each character. So for example, "11 (1)" means there's one character who has 11 actors (and yes, there is one).</p>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<h3>Characters per Actor</h3>
					<?php LWTV_Stats::generate( 'actors', 'per-actor', 'barchart' ); ?>
					<p>This chart displays the number of characters each actor plays. So for example, "20 (1)" means there's one actor who played 20 characters (that would be the 'unknown' actor).</p>
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
					<h3>Sexual Identity</h3>
					<?php LWTV_Stats::generate( 'characters', 'sexuality', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Gender Identity</h3>
					<?php LWTV_Stats::generate( 'characters', 'gender', 'percentage' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<h3>Roles</h3>
					<?php LWTV_Stats::generate( 'characters', 'role', 'percentage' ); ?>
				</div>
				<div class="col-sm-6">
					<h3>Queer IRL</h3>
					<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
}