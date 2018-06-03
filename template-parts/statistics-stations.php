<?php
/**
 * The template for displaying station statistics
 *
 * @package LezWatchTV
 */

// Stations
$valid_station = ( isset( $_GET['station'] ) )? term_exists( $_GET['station'], 'lez_stations' ) : '';
$station       = ( !isset( $_GET['station'] ) || !is_array( $valid_station ) )? 'all' : sanitize_title( $_GET['station'] );

// Views
$valid_views   = array( 'overview', 'gender', 'sexuality' );
$view          = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'overview' : sanitize_title( $_GET['view'] );

// Count
$all_stations = get_terms( 'lez_stations', array( 'hide_empty' => 0 ) );
$count        = wp_count_terms( 'lez_stations' );
$shows_count  = LWTV_Stats::generate( 'shows', 'total', 'count' );

// Current URL
$current_url = add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) );

// Title
switch( $station ) {
	case 'all':
		$title_station = 'All Stations (' . $count . ')';
		break;
	default:
		$characters     = LWTV_Stats::generate( 'characters', 'stations_' . $station . '_all' , 'count' );
		$shows          = LWTV_Stats::generate( 'shows', 'stations_' . $station . '_all' , 'count' );
		$station_object = get_term_by( 'slug', $station, 'lez_stations', 'ARRAY_A' );
		$title_station  = '<a href="' . home_url( '/station/' . $station ) . '">' . $station_object['name'] . '</a>' . ' (' . $shows . ' Shows / ' . $characters . ' Characters)';
}

?>

<h2><?php echo $title_station; ?></h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<form method="get" id="go" class="form-inline">
			<input type="hidden" name="view" value="<?php echo $view; ?>">
			<div class="form-group">
				<select name="station" id="station" class="form-control">
					<option value="all">Station</option>
					<?php
						foreach( $all_stations as $the_station ) {
							$selected = ( $station == $the_station->slug )? 'selected=selected' : '';
							$shows    = _n( 'show', 'shows', $the_station->count );
							echo '<option value="' . $the_station->slug . '" ' . $selected . '>' . $the_station->name . '</option>';
						}
					?>
				</select>
			</div>
			<div class="form-group">
				<button type="submit" id="submit" class="btn btn-default">Go</button>
			</div>
		</form>
	</nav>
</section>

<ul class="nav nav-tabs">
	<?php
	foreach ( $valid_views as $the_view ) {
		$active = ( $view == $the_view )? ' active' : '';
		echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . esc_url( add_query_arg( 'view', $the_view, $current_url ) ) . '">' . strtoupper( str_replace( '-', ' ', $the_view ) ) . '</a></li>';
	}
	?>
</ul>

<p>&nbsp;</p>

<?php
	$col_class = ( $station !== 'all' && $view !== 'overview' )? 'col-sm-6' : 'col';
?>

<div class="container">
	<div class="row">
		<div class="<?php echo $col_class; ?>">
		<?php
			$view = ( $view == 'overview' && $station !== 'all' )? 'all' : $view;
			// station-[substation]-[view]
			$view    = ( $view == 'overview' )? '_all' : '_' . $view; 
			$station = ( $station == 'overview' )? '_all' : '_' . $station; 
			
			if ( $station == '_all' ) {
				?>
				<script>
					jQuery(document).ready(function($){
						$("#stationsTable").tablesorter({
							theme : "bootstrap",
						});
					});
				</script>

				<p>For more information on individual stations, please use the dropdown menu, or click on a station listed below.</p>
				<table id="stationsTable" class="tablesorter table table-striped table-hover">
					<thead>
						<tr>
							<th scope="col">Station Name</th>
							<th scope="col">Total Shows</th>
							<th scope="col">Percentage (of all shows)</th>
							<th scope="col">Avg Score</th>
						</tr>
					</thead>
					<tbody>
						<?
						foreach( $all_stations as $the_station ) {
							echo '<tr>
									<th scope="row"><a href="?station=' . $the_station->slug . '">' . $the_station->name . '</a></th>
									<td>' . $the_station->count . '</td>
									<td>' . round( ( ( $the_station->count / $shows_count ) * 100 ) , 1 ) . '%</td>
									<td>' . LWTV_Stats::showcount( 'score', 'stations', $the_station->slug ) . '</td>
								</tr>';
						}
						?>
					</tbody>
				</table>
				<?php
			} else {
				$format = 'piechart';

				if ( $station !== '_all' ) {

					$onair      = LWTV_Stats::showcount( 'onair', 'stations', ltrim( $station, '_' ) );
					$allshows   = LWTV_Stats::showcount( 'total', 'stations', ltrim( $station, '_' ) );
					$showscore  = LWTV_Stats::showcount( 'score', 'stations', ltrim( $station, '_' ) );
					$onairscore = LWTV_Stats::showcount( 'onairscore', 'stations', ltrim( $station, '_' ) );

					if ( $view == '_all' ) {
						echo '<p>Currently, ' . $onair . ' of ' . $allshows . ' shows are on air. The average score for all shows in this station is ' . $showscore . ', and ' . $onairscore . ' for shows currently on air (out of a possible 100).</p>';
						$format = 'barchart';
					}
				}
				
				LWTV_Stats::generate( 'shows', 'stations' . $station . $view , $format );
			}

		?>
		</div>

	<?php
	if ( $station !== '_all' && $view !== '_all' ) {
		?>
		<div class="<?php echo $col_class; ?>">
			<?php LWTV_Stats::generate( 'shows', 'stations' . $station . $view , 'percentage' ); ?>
		</div>
		<?php
	}
	?>

	</div>
</div>