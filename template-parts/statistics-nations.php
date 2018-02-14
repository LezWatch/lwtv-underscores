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
$valid_views = array( 'overview', 'gender', 'sexuality' );
$view        = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'overview' : sanitize_title( $_GET['view'] );

// Format
$valid_formats = array( 'bar', 'pie' );
$format        = ( !isset( $_GET['format'] ) || !in_array( $_GET['format'], $valid_formats ) )? 'bar' : sanitize_title( $_GET['format'] );

// Columns
$columns = ( $format == 'pie' )? 'col-sm-6' : 'col';
?>

<h2>Statistics of Nations (<?php echo LWTV_Stats::generate( 'shows', 'country', 'count' ); ?>)</h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<form method="get" action="<?php esc_url( add_query_arg( 'view', $view, '/statistics/shows/' ) ); ?>" id="go" class="form-inline">
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
					<?php
						foreach( $valid_formats as $fmat ) {
							$selected   = ( $format == $fmat )? 'selected=selected' : '';
							$disabled   = ( $fmat !== 'bar' && $country == 'all' )? 'disabled' : '';
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
			switch( $country ) {
				case 'all':
					$title_country = 'All Countries';
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

			// Adjust Format
			switch ( $format ) {
				case 'bar':
					$format = ( $country == 'all' && in_array( $view, array( 'sexuality', 'gender' ) ) )? 'stackedbar' : 'barchart';
					break;
				case 'pie':
					$format = 'piechart';
					break;
			}

			// country_[subcountry]_[view]
			$view    = '_' . $view;
			$country = ( $country == 'overview' )? '_all' : '_' . $country; 
			LWTV_Stats::generate( 'shows', 'country' . $country . $view , $format );
		?>
		</div>
	</div>
</div>