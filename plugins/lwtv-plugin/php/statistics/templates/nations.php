<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

?>
<div class="lwtv-stats-overview">
	<div class="lwtv-nations-picker">
		<form method="get" id="go" class="lwtv-nations-pickerform">
			<label for="nation" class="lwtv-stats-eyebrow"><?php esc_html_e( 'Nation', 'lwtv' ); ?></label>
			<select name="nation" id="nation" class="form-select lwtv-nations-select" onchange="this.form.submit()">
				<option value="all"><?php esc_html_e( 'All Nations', 'lwtv' ); ?></option>
				<?php
				foreach ( $all_nations_data as $lwtv_n_slug => $lwtv_n_data ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $lwtv_n_slug ),
						selected( $nation, $lwtv_n_slug, false ),
						esc_html( $lwtv_n_data['name'] )
					);
				}
				?>
			</select>
			<noscript><button type="submit" id="submit" class="btn btn-outline-primary btn-sm"><?php esc_html_e( 'Go', 'lwtv' ); ?></button></noscript>
			<?php if ( 'all' !== $nation ) : ?>
				<a class="lwtv-nations-reset" href="/statistics/nations/"><?php esc_html_e( 'Reset to all nations', 'lwtv' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<?php
	// Nation sub-nav (single nation only). The primary tab bar is in the page shell.
	if ( 'all' !== $nation ) {
		$lwtv_sub_base  = '/statistics/nations/';
		$lwtv_sub_query = array( 'nation' => $nation );
		$lwtv_subnav    = array_merge( array( 'overview' => 'shows' ), $valid_views );
		echo '<nav class="lwtv-stats-subnav" aria-label="' . esc_attr__( 'Nation statistics views', 'lwtv' ) . '">';
		foreach ( $lwtv_subnav as $lwtv_v => $lwtv_pt ) {
			$lwtv_is  = ( $view === $lwtv_v );
			$lwtv_url = ( 'overview' === $lwtv_v ) ? add_query_arg( $lwtv_sub_query, $lwtv_sub_base ) : add_query_arg( $lwtv_sub_query, $lwtv_sub_base . $lwtv_v . '/' );
			printf(
				'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
				$lwtv_is ? ' is-active' : '',
				esc_url( $lwtv_url ),
				$lwtv_is ? ' aria-current="page"' : '',
				esc_html( ucwords( str_replace( '-', ' ', $lwtv_v ) ) )
			);
		}
		echo '</nav>';
	}
	?>
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

	</div>
</div>
</div><!-- .lwtv-stats-overview -->

