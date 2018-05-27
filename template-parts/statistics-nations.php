<?php
/**
 * The template for displaying national statistics
 *
 * @package LezWatchTV
 */

// Country
$valid_country = ( isset( $_GET['country'] ) )? term_exists( $_GET['country'], 'lez_country' ) : '';
$country       = ( !isset( $_GET['country'] ) || !is_array( $valid_country ) )? 'all' : sanitize_title( $_GET['country'] );

// Views
$valid_views   = array( 'overview', 'gender', 'sexuality' );
$view          = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'overview' : sanitize_title( $_GET['view'] );

// Format
$valid_formats = array( 'bar', 'pie' );
$format        = ( !isset( $_GET['format'] ) || !in_array( $_GET['format'], $valid_formats ) )? 'bar' : sanitize_title( $_GET['format'] );

// Current URL
$current_url = add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) );
?>

<h2>Statistics of Nations (<?php echo LWTV_Stats::generate( 'shows', 'country', 'count' ); ?>)</h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<form method="get" id="go" class="form-inline">
			<input type="hidden" name="view" value="<?php echo $view; ?>">
			<div class="form-group">
				<select name="country" id="country" class="form-control">
					<option value="all">Country (All)</option>
					<?php
						$nations = get_terms( 'lez_country', array( 'hide_empty' => 0 ) );
						foreach( $nations as $nation ) {
							$selected = ( $country == $nation->slug )? 'selected=selected' : '';
							$shows    = _n( 'Show', 'Shows', $nation->count );
							echo '<option value="' . $nation->slug . '" ' . $selected . '>' . $nation->name . ' (' . $nation->count . ' ' . $shows . ')</option>';
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

<div class="container">
	<div class="row">
		<div class="col">
		<?php
			// Title
			switch( $country ) {
				case 'all':
					$title_country = 'Shows in All Countries';
					break;
				default:
					$country_object = get_term_by( 'slug', $country, 'lez_country', 'ARRAY_A' );
					$title_country  = $country_object['name'];
			}

			switch ( $view ) {
				case 'overview':
					$title = 'Overview of ' . $title_country;
					break;
				case 'sexuality':
					$title = 'Details on ' . $title_country . ' by Character Sexual Orientation';
					break;
				case 'gender':
					$title = 'Details on ' . $title_country . ' by Character Gender Identity';
					break;
				case 'romantic':
					$title = 'Details on ' . $title_country . ' by Character Romantic Orientation';
			}

			echo '<h3>' . $title . '</h3>';

			// Format tweak for all things
			$format = ( $country == 'all' && in_array( $view, array( 'sexuality', 'gender', 'romantic' ) ) )? 'stackedbar' : 'barchart';

			// country_[subcountry]_[view]
			$view    = ( $view == 'overview' )? '_all' : '_' . $view; 
			$country = ( $country == 'overview' )? '_all' : '_' . $country; 

				if ( $country !== '_all' ) {

					$onair      = LWTV_Stats::showcount( 'onair', 'country', ltrim( $country, '_' ) );
					$allshows   = LWTV_Stats::showcount( 'total', 'country', ltrim( $country, '_' ) );
					$showscore  = LWTV_Stats::showcount( 'score', 'country', ltrim( $country, '_' ) );
					$onairscore = LWTV_Stats::showcount( 'onairscore', 'country', ltrim( $country, '_' ) );
					
					echo '<p>Currently, ' . $onair . ' of ' . $allshows . ' shows are on air. The average score for all shows in this country is ' . $showscore . ', and ' . $onairscore . ' for shows currently on air (out of a possible 100).</p>';
				}


			LWTV_Stats::generate( 'shows', 'country' . $country . $view , $format );
		?>
		</div>
	</div>
</div>