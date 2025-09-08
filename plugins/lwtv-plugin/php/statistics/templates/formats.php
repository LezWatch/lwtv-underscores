<?php
/**
 * The template for displaying formats statistics - Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// showform
$sent_form      = get_query_var( 'showform', '' );
$valid_showform = term_exists( $sent_form, 'lez_formats' );
$showform       = ( '' === $sent_form || ! is_array( $valid_showform ) ) ? 'all' : sanitize_title( $sent_form );

// Views
$valid_views = array(
	// taxonomy     => CPT
	'sexuality'     => 'characters',
	'gender'        => 'characters',
	'tropes'        => 'shows',
	'intersections' => 'shows',
);
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! array_key_exists( $sent_view, $valid_views ) ) ? 'overview' : $sent_view;

// Format
$valid_formats = array( 'bar', 'pie' );
$sent_format   = get_query_var( 'format', 'bar' );
$format        = ( ! in_array( $sent_format, $valid_formats, true ) ) ? 'bar' : $sent_format;

// OPTIMIZED: Get all format data in a single query instead of N+1 queries
$optimized_taxonomy = new Build_Taxonomy_Optimized();
$all_formats_data   = $optimized_taxonomy->make_comprehensive( 'post_type_shows', 'lez_formats', true );

// OPTIMIZED: Pre-load all character counts in a single query to eliminate N+1 pattern
$character_counts = $optimized_taxonomy->get_bulk_character_counts( 'lez_formats', array_keys( $all_formats_data ) );

// Get total counts efficiently
$count       = count( $all_formats_data );
$shows_count = lwtv_plugin()->generate_statistics( 'shows', 'total', 'count' );

switch ( $showform ) {
	case 'all':
		$title_showform = 'All Show Formats (' . $count . ')';
		break;
	default:
		// Use the cached data instead of making new queries
		if ( isset( $all_formats_data[ $showform ] ) ) {
			$format_data = $all_formats_data[ $showform ];
			$shows       = $format_data['count'];

			// OPTIMIZED: Use pre-loaded character counts instead of individual query
			$characters = $character_counts[ $showform ]['total'] ?? 0;

			$title_showform = '<a href="/format/' . $showform . '">' . $format_data['name'] . '</a> (' . $shows . ' Shows / ' . $characters . ' Characters)';
		} else {
			$title_showform = 'Format Not Found';
		}
}

?>
<h2><?php echo wp_kses_post( $title_showform ); ?></h2>

<form method="get" id="go">
<div class="container-fluid text-center">
	<div class="row">
		<div class="col-8">
			<select name="showform" id="showform">
			<option value="all">All Show Formats</option>
				<?php
				foreach ( $all_formats_data as $format_slug => $format_data ) {
					$selected = ( $showform === $format_slug ) ? 'selected=selected' : '';
					echo '<option value="' . esc_attr( $format_slug ) . '" ' . esc_html( $selected ) . '>' . esc_html( $format_data['name'] ) . '</option>';
				}
				?>
			</select>
		</div>
		<div class="col-2">
			<button type="submit" id="submit" class="btn btn-default btn-outline-primary">Go</button>
			<?php
			if ( 'all' !== $showform ) {
				echo '&nbsp;<a class="btn btn-default btn-outline-primary" href="/statistics/formats/" role="button">Reset</a>';
			}
			?>
		</div>
	</div>
</form>

<ul class="nav nav-tabs">
	<?php
	$baseurl   = '/statistics/formats/';
	$query_arg = array();
	if ( 'all' !== $showform ) {
		$query_arg['showform'] = $showform;
	}

	echo '<li class="nav-item"><a class="nav-link' . esc_attr( ( 'overview' === $view ) ? ' active' : '' ) . '" href="' . esc_url( add_query_arg( $query_arg, $baseurl ) ) . '">OVERVIEW</a></li>';
	if ( 'all' !== $showform ) {
		foreach ( $valid_views as $the_view => $the_post_type ) {
			$active = ( $view === $the_view ) ? ' active' : '';
			echo '<li class="nav-item"><a class="nav-link' . esc_attr( $active ) . '" href="' . esc_url( add_query_arg( $query_arg, $baseurl . $the_view . '/' ) ) . '">' . esc_html( strtoupper( str_replace( '-', ' ', $the_view ) ) ) . '</a></li>';
		}
	}
	?>
</ul>

<p>&nbsp;</p>

<?php
	$col_class = ( 'all' !== $showform && 'overview' !== $view ) ? 'col-sm-6' : 'col';
	$cpts_type = ( 'overview' === $view ) ? 'shows' : $valid_views[ $view ];
?>

<div class="container chart-container">
	<div class="row">
		<div class="<?php echo esc_attr( $col_class ); ?>">
		<?php
		$view = ( 'overview' === $view && 'all' !== $showform ) ? 'all' : $view;
		// Remember: showform [subshowform] [view]
		$view     = ( 'overview' === $view ) ? '_all' : '_' . $view;
		$showform = ( 'overview' === $showform ) ? '_all' : '_' . $showform;

		if ( '_all' === $showform ) {
			?>
			<p>For more information on individual formats, please use the dropdown menu, or click on a format listed below.</p>
			<table id="formatsTable" class="tablesorter table table-striped table-hover">
				<thead>
					<tr>
						<th scope="col">Format Name</th>
						<th scope="col">Total Shows</th>
						<th scope="col">Percentage (of all shows)</th>
						<th scope="col"># of Characters</th>
						<th scope="col"># of Dead Characters</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $all_formats_data as $format_slug => $format_data ) {

						if ( 0 === $format_data['count'] ) {
							continue;
						}

						// OPTIMIZED: Use pre-loaded character counts instead of individual queries
						$characters = $character_counts[ $format_slug ]['total'] ?? 0;
						$dead       = $character_counts[ $format_slug ]['dead'] ?? 0;
						$percent    = round( ( ( $format_data['count'] / $shows_count ) * 100 ), 1 );
						echo '<tr>
								<th scope="row"><a href="?showform=' . esc_attr( $format_slug ) . '">' . esc_html( $format_data['name'] ) . '</a></th>
								<td>' . (int) $format_data['count'] . '</td>
								<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $percent ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $percent ) . '%</td>
								<td>' . (int) $characters . '</td>
								<td>' . (int) $dead . '</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<?php
		} else {
			// There is a specific Format!
			$this_format = $all_formats_data[ ltrim( $showform, '_' ) ];

			$format     = 'piechart';
			$onair      = lwtv_plugin()->generate_shows_count( 'onair', 'formats', ltrim( $showform, '_' ) );
			$allshows   = lwtv_plugin()->generate_shows_count( 'total', 'formats', ltrim( $showform, '_' ) );
			$showscore  = lwtv_plugin()->generate_shows_count( 'score', 'formats', ltrim( $showform, '_' ) );
			$onairscore = lwtv_plugin()->generate_shows_count( 'onairscore', 'formats', ltrim( $showform, '_' ) );

			if ( '_all' === $view ) {
				echo wp_kses_post( '<p>Currently, ' . $onair . ' out of a total of ' . $allshows . ' shows are on air.</p><p>The average score for all shows in this format is ' . $showscore );

				if ( 0 !== $onair ) {
					echo wp_kses_post( ', and ' . $onairscore . ' for shows currently on air' );
				}

				echo wp_kses_post( ' (out of a possible 100).</p>' );

				$format = 'barchart';
			}

			lwtv_plugin()->generate_statistics( $cpts_type, 'formats' . $showform . $view, $format );
		}
		?>
		</div>

	<?php
	if ( '_all' !== $showform && '_all' !== $view ) {
		$format = ( 'shows' === $cpts_type ) ? 'list' : 'percentage';
		?>
		<div class="<?php echo esc_attr( $col_class ); ?>">
			<?php lwtv_plugin()->generate_statistics( $cpts_type, 'formats' . $showform . $view, $format ); ?>
		</div>
		<?php
	}
	?>

	</div>
</div>

<?php
// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Character count N+1 queries eliminated. Queries reduced from ~' . ( count( $all_formats_data ) * 3 + 10 ) . ' to ' . esc_html( get_num_queries() ) . ' -->';
}
