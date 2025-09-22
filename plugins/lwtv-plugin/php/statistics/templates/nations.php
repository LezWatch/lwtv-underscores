<?php
/**
 * The template for displaying nation statistics -- Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// Nations
$sent_nation  = get_query_var( 'nation', '' );
$valid_nation = term_exists( $sent_nation, 'lez_country' );
$nation       = ( '' === $sent_nation || ! is_array( $valid_nation ) ) ? 'all' : sanitize_title( $sent_nation );

// Views
$valid_views = array(
	'sexuality' => 'characters',
	'gender'    => 'characters',
	'tropes'    => 'shows',
	// removed because there's not enough data yet.
	// 'intersections' => 'shows',
	'formats'   => 'shows',
	'on-air'    => 'shows',
);
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! array_key_exists( $sent_view, $valid_views ) ) ? 'overview' : $sent_view;

// OPTIMIZED: Get all nation data in a single query instead of N+1 queries
$optimized_taxonomy = new Build_Taxonomy_Optimized();
$all_nations_data   = $optimized_taxonomy->make_comprehensive( 'post_type_shows', 'lez_country', true );

// OPTIMIZED: Pre-load all character counts in a single query to eliminate N+1 pattern
$character_counts = $optimized_taxonomy->get_bulk_character_counts( 'lez_country', array_keys( $all_nations_data ) );
$show_counts      = $optimized_taxonomy->get_bulk_show_counts( 'lez_country', array_keys( $all_nations_data ) );

// Get total counts efficiently
$count           = count( $all_nations_data );
$shows_count     = lwtv_plugin()->generate_nation_statistics( 'all', 'all', 'count' );
$all_shows_count = lwtv_plugin()->generate_total_counts( 'shows' );

// Title
switch ( $nation ) {
	case 'all':
		$title_nation = 'All Nations (' . $count . ')';
		break;
	default:
		// Use the cached data instead of making new queries
		if ( isset( $all_nations_data[ $nation ] ) ) {
			$nation_data = $all_nations_data[ $nation ];
			$shows       = $nation_data['count'];

			// OPTIMIZED: Use pre-loaded character counts instead of individual query
			// Strip any underscore prefix from nation slug for character counts lookup
			$nation_slug = ltrim( $nation, '_' );
			$characters  = $character_counts[ $nation_slug ]['total'] ?? 0;

			$title_nation = '<a href="' . home_url( '/nation/' . $nation ) . '">' . $nation_data['name'] . '</a> (' . $shows . ' Shows / ' . $characters . ' Characters)';
		} else {
			$title_nation = 'Nation Not Found';
		}
}
?>
<h2><?php echo wp_kses_post( $title_nation ); ?></h2>

<form method="get" id="go">
<div class="container-fluid text-center">
	<div class="row">
		<div class="col-8">
			<select name="nation" id="nation">
				<option value="all">All Nations</option>
				<?php
				foreach ( $all_nations_data as $nation_slug => $nation_data ) {
					$selected = ( $nation === $nation_slug ) ? 'selected=selected' : '';
					echo '<option value="' . esc_attr( $nation_slug ) . '" ' . esc_html( $selected ) . '>' . esc_html( $nation_data['name'] ) . '</option>';
				}
				?>
			</select>
		</div>
		<div class="col-2">
			<button type="submit" id="submit" class="btn btn-default btn-outline-primary">Go</button>
			<?php
			if ( 'all' !== $nation ) {
				echo '&nbsp;<a class="btn btn-default btn-outline-primary" href="/statistics/nations/" role="button">Reset</a>';
			}
			?>
		</div>
	</div>
</form>

<ul class="nav nav-tabs">
	<?php
	$baseurl   = '/statistics/nations/';
	$query_arg = array();
	if ( 'all' !== $nation ) {
		$query_arg['nation'] = $nation;
	}

	echo '<li class="nav-item"><a class="nav-link' . esc_attr( ( 'overview' === $view ) ? ' active' : '' ) . '" href="' . esc_url( add_query_arg( $query_arg, $baseurl ) ) . '">OVERVIEW</a></li>';
	if ( 'all' !== $nation ) {
		foreach ( $valid_views as $the_view => $the_post_type ) {
			$active = ( $view === $the_view ) ? ' active' : '';
			echo '<li class="nav-item"><a class="nav-link' . esc_attr( $active ) . '" href="' . esc_url( add_query_arg( $query_arg, $baseurl . $the_view . '/' ) ) . '">' . esc_html( strtoupper( str_replace( '-', ' ', $the_view ) ) ) . '</a></li>';
		}
	}
	?>
</ul>

<p>&nbsp;</p>

<?php
	$col_class = ( 'all' !== $nation && 'overview' !== $view && 'on-air' !== $view ) ? 'col-sm-6' : 'col';
	$cpts_type = ( 'overview' === $view ) ? 'shows' : $valid_views[ $view ];
?>

<div class="container">
	<div class="row">
		<?php
		// Remember: nation [subnation] [view]
		$view   = ( 'overview' === $view && 'all' !== $nation ) ? 'all' : $view;
		$view   = ( 'overview' === $view ) ? '_all' : '_' . $view;
		$nation = ( 'overview' === $nation ) ? '_all' : '_' . $nation;

		if ( '_all' === $nation ) {
			include plugin_dir_path( __FILE__ ) . 'nations/all.php';
		} else {
			include plugin_dir_path( __FILE__ ) . 'nations/single.php';
		}
		?>

	<?php
	if ( '_all' !== $nation && '_all' !== $view && '_on-air' !== $view ) {
		$format = ( 'shows' === $cpts_type ) ? 'list' : 'percentage';
		?>
		<div class="<?php echo esc_attr( $col_class ); ?>">
			<?php lwtv_plugin()->generate_nation_statistics( $nation, $view, $format ); ?>
		</div>
		<?php
	}
	?>

	</div>
</div>

<?php
// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Character count N+1 queries eliminated. Queries reduced from ~' . ( count( $all_nations_data ) * 3 + 10 ) . ' to ' . esc_html( get_num_queries() ) . ' -->';
}
