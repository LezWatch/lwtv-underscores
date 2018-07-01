<?php
/**
 * The template for displaying national statistics
 *
 * @package LezWatch.TV
 */

// Country
$valid_country = ( isset( $_GET['country'] ) )? term_exists( $_GET['country'], 'lez_country' ) : '';
$country       = ( !isset( $_GET['country'] ) || !is_array( $valid_country ) )? 'all' : sanitize_title( $_GET['country'] );

// Views
$valid_views   = array( 
	'overview'  => 'shows',
	'sexuality' => 'characters',
	'gender'    => 'characters',
	'tropes'    => 'shows',
);
$view          = ( !isset( $_GET['view'] ) || ( ! array_key_exists( $_GET['view'], $valid_views ) ) )? 'overview' : sanitize_title( $_GET['view'] );

// Format
$valid_formats = array( 'bar', 'pie' );
$format        = ( !isset( $_GET['format'] ) || !in_array( $_GET['format'], $valid_formats ) )? 'bar' : sanitize_title( $_GET['format'] );

// Count
$nations     = get_terms( 'lez_country', array( 'hide_empty' => 0 ) );
$count       = wp_count_terms( 'lez_country' );
$shows_count = LWTV_Stats::generate( 'shows', 'total', 'count' );

// Current URL
$current_url = add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) );

switch( $country ) {
	case 'all':
		$title_country = 'All Countries (' . $count . ')';
		break;
	default:
		$characters     = LWTV_Stats::generate( 'characters', 'country_' . $country . '_all' , 'count' );
		$shows          = LWTV_Stats::generate( 'shows', 'country_' . $country . '_all' , 'count' );
		$country_object = get_term_by( 'slug', $country, 'lez_country', 'ARRAY_A' );
		$title_country  = '<a href="' . home_url( '/country/' . $country ) . '">' . $country_object['name'] . '</a>' . ' (' . $shows . ' Shows / ' . $characters . ' Characters)';
}

?>

<h2><?php echo $title_country; ?></h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<form method="get" id="go" class="form-inline">
			<input type="hidden" name="view" value="<?php echo $view; ?>">
			<div class="form-group">
				<select name="country" id="country" class="form-control">
					<option value="all">Country (All)</option>
					<?php
						foreach( $nations as $nation ) {
							$selected = ( $country == $nation->slug )? 'selected=selected' : '';
							$shows    = _n( 'Show', 'Shows', $nation->count );
							echo '<option value="' . $nation->slug . '" ' . $selected . '>' . $nation->name . '</option>';
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
	foreach ( $valid_views as $the_view => $the_post_type ) {
		$active = ( $view == $the_view )? ' active' : '';
		echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . esc_url( add_query_arg( 'view', $the_view, $current_url ) ) . '">' . strtoupper( str_replace( '-', ' ', $the_view ) ) . '</a></li>';
	}
	?>
</ul>

<p>&nbsp;</p>

<?php
	$col_class = ( $country !== 'all' && $view !== 'overview' )? 'col-sm-6' : 'col';
	$post_type = $valid_views[ $view ];
?>

<div class="container chart-container">

	<?php
		if ( 'all' !== $country && 'overview' !== $view ) {
			switch ( $post_type ) {
				case 'characters':
					echo '<p>The following statistics relate to characters on shows that air in this country.</p>';
					break;
				case 'shows':
					echo '<p>The following statistics relate to shows that air in this country.</p>';
					break;
			}
		}
	?>

	<div class="row">
		<div class="<?php echo $col_class; ?>">
		<?php

			// country_[subcountry]_[view]
			$view      = ( $view == 'overview' )? '_all' : '_' . $view; 
			$country   = ( $country == 'overview' )? '_all' : '_' . $country; 
			$format    = ( $view == '_all' )? 'barchart' : 'piechart';

			if ( '_all' === $country ) {
				if ( '_all' === $view ) {
				?>
					<p>For more information on individual nations, please use the dropdown menu, or click on a nation listed below.</p>
					<table id="nationsTable" class="tablesorter table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col">Country Name</th>
								<th scope="col">Shows</th>
								<th scope="col">Percentage (of all shows)</th>
								<th scope="col">Avg Score</th>
							</tr>
						</thead>
						<tbody>
							<?
							foreach( $nations as $nation ) {
								echo '<tr>
										<th scope="row"><a href="?view=overview&country=' . $nation->slug . '">' . $nation->name . '</a></th>
										<td>' . $nation->count . '</td>
										<td>' . round( ( ( $nation->count / $shows_count ) * 100 ) , 1 ) .'%</td>
										<td>' . LWTV_Stats::showcount( 'score', 'country', $nation->slug ) . '</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
				<?php
				} else {
					LWTV_Stats::generate( $post_type, 'country' . $country . $view , 'stackedbar' );
				}

			} else {
				$onair      = LWTV_Stats::showcount( 'onair', 'country', ltrim( $country, '_' ) );
				$allshows   = LWTV_Stats::showcount( 'total', 'country', ltrim( $country, '_' ) );
				$showscore  = LWTV_Stats::showcount( 'score', 'country', ltrim( $country, '_' ) );
				$onairscore = LWTV_Stats::showcount( 'onairscore', 'country', ltrim( $country, '_' ) );

				if ( $view == '_all' ) {
					echo '<p>Currently, ' . $onair . ' of ' . $allshows . ' shows are on air. The average score for all shows in this country is ' . $showscore . ', and ' . $onairscore . ' for shows currently on air (out of a possible 100).</p>';
				}

				LWTV_Stats::generate( $post_type, 'country' . $country . $view , $format );
			}

		?>
		</div>

	<?php
	if ( $country !== '_all' && $view !== '_all' ) {
		$format = ( 'shows' === $post_type )? 'list' : 'percentage';
		?>
		<div class="<?php echo $col_class; ?>">
			<?php LWTV_Stats::generate( $post_type, 'country' . $country . $view , $format ); ?>
		</div>
		<?php
	}
	?>
	</div>
</div>