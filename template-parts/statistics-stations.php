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

// Format
$valid_formats = array( 'bar', 'pie' );
$format        = ( !isset( $_GET['format'] ) || !in_array( $_GET['format'], $valid_formats ) )? 'bar' : sanitize_title( $_GET['format'] );

// All the stations crash things, so we have to do this:
$format = ( $station == 'all' )? '' : $format;
$disabled = ( $station == 'all' )? 'disabled' : '';

// Count
$all_stations = get_terms( 'lez_stations', array( 'hide_empty' => 0 ) );
$count        = LWTV_Stats::generate( 'shows', 'stations', 'count' );

// Columns
$columns = ( $format == 'pie' )? 'col-sm-6' : 'col';

?>
<h2>
	Total Stations (<?php echo $count; ?>)
</h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<form method="get" action="<?php esc_url( add_query_arg( 'view', $view, '/statistics/shows/' ) ); ?>" id="go" class="form-inline">
			<div class="form-group">
				<select name="station" id="station" class="form-control">
					<option value="all">Station</option>
					<?php
						foreach( $all_stations as $the_station ) {
							$selected = ( $station == $the_station->slug )? 'selected=selected' : '';
							$shows    = _n( 'show', 'shows', $the_station->count );
							echo '<option value="' . $the_station->slug . '" ' . $selected . '>' . $the_station->name . ' (' . $the_station->count . ' ' . $shows . ')</option>';
						}
					?>
				</select>
			</div>
			<div class="form-group">
				<select name="view" id="view" class="form-control">
					<option value="overview">Overview</option>
					<?php
						$statstype = array( 'gender', 'sexuality' );
						foreach( $statstype as $stat ) {
							$selected = ( $view == $stat )? 'selected=selected' : '';
							echo '<option value="' . $stat . '" ' . $selected . '>' . ucfirst( $stat ) . '</option>';
						}
					?>
				</select>
			</div>
			<div class="form-group">
				<select name="format" id="format" class="form-control">
					<option value="">Format</option>
					<?php
						foreach( $valid_formats as $fmat ) {
							$selected   = ( $format == $fmat )? 'selected=selected' : '';
							echo '<option value="' . $fmat . '" ' . $selected . ' ' . $disabled . '>' . ucfirst( $fmat ) . ' Chart</option>';
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

<div class="container">
	<div class="row">
		<div class="<?php echo $columns; ?>">
		<?php
			// Title
			switch( $station ) {
				case 'all':
					$title_station = 'All Station';
					break;
				default:
					$station_object = get_term_by( 'slug', $station, 'lez_stations', 'ARRAY_A' );
					$title_station  = '<a href="' . home_url( '/station/' . $station ) . '">' . $station_object['name'] . '</a>';
			}

			$title = 'Overview of ' . $title_station . ' Information';

			switch ( $view ) {
				case 'overview':
					$title = 'Overview of ' . $title_station;
					break;
				case 'sexuality':
					$title = 'Details on ' . $title_station . ' by Character Sexual Orientation';
					break;
				case 'gender':
					$title = 'Details on ' . $title_station . ' by Character Gender Identity';
					break;
				case 'romantic':
					$title = 'Details on ' . $title_station . ' by Character Romantic Orientation';
			}

			echo '<h3>' . $title . '</h3>';

			// Adjust Format
			switch ( $format ) {
				case 'bar':
					$format = 'barchart';
					break;
				case 'pie':
					$format = 'piechart';
					break;
			}

			// Adjust Title...
			if ( $view == 'overview' && $station !== 'all' ) $view = 'all';

			// station-[substation]-[view]
			$view    = '_' . $view;
			$station = ( $station == 'overview' )? '_all' : '_' . $station; 
			
			if ( $station == '_all' ) {
				echo '<p>Due to the high number of stations (' . $count . '), we cannot display an overview. For information on statistics per station, please use the dropdowns to select a station and drill down for more information.</p>';
				echo '<ul>';
					foreach( $all_stations as $the_station ) {
						$shows = _n( 'show', 'shows', $the_station->count );
						echo '<li><a href="?station=' . $the_station->slug . '">' . $the_station->name . '</a> (' . $the_station->count . ' ' . $shows . ', average score ' . $showscore = LWTV_Stats::showcount( 'score', 'stations', $the_station->slug ) . ')</li>';
					}
				echo '</ul>';

				
			} else {
				if ( $view == '_all' && $station !== '_all' ) {

					$onair      = LWTV_Stats::showcount( 'onair', 'stations', ltrim( $station, '_' ) );
					$allshows   = LWTV_Stats::showcount( 'total', 'stations', ltrim( $station, '_' ) );
					$showscore  = LWTV_Stats::showcount( 'score', 'stations', ltrim( $station, '_' ) );
					$onairscore = LWTV_Stats::showcount( 'onairscore', 'stations', ltrim( $station, '_' ) );
					
					echo '<p>Currently, ' . $onair . ' of ' . $allshows . ' shows are on air. The average score for all shows on this station is ' . $showscore . ', and ' . $onairscore . ' for shows currently on air (out of a possible 100).</p>';
				}
				
				LWTV_Stats::generate( 'shows', 'stations' . $station . $view , $format );
			}

		?>
		</div>
	</div>
</div>