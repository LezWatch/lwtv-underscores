<?php
/**
 * The template for displaying national statistics
 *
 * @package LezWatchTV
 */
 
$valid_views = array( 
	'country'   => array( 'format' => 'barchart', 'slug' => 'country' ),
	'gender'    => array( 'format' => 'stackedbar', 'slug' => 'country-gender' ),
	'sexuality' => array( 'format' => 'stackedbar', 'slug' => 'country-sexuality' ),
);
$view        = ( !isset( $_GET['view'] ) || !array_key_exists( $_GET['view'], $valid_views ) )? 'country' : $_GET['view'];

?>

<h2>
	Total Nations (<?php echo LWTV_Stats::generate( 'shows', 'country', 'count' ); ?>)
</h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Go to:</h4>
		<?php
		foreach ( $valid_views as $the_view => $the_format ) {
			echo '<a class="breadcrumb-item" href="' . esc_url( add_query_arg( 'view', $the_view, '/statistics/nations/' ) ) . '">Shows by ' . ucfirst( $the_view ) . '</a> ';
		}
		?>
	</nav>
</section>

<hr>

<div class="container">
	<div class="row">
		<div class="col">
		<?php
			LWTV_Stats::generate( 'shows', $valid_views[$view]['slug'], $valid_views[$view]['format'] );
		?>
		</div>
	</div>
</div>