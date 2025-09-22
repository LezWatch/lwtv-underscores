<?php
/**
 * Navigation for the This Year pages
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $valid_views
 * @var string $view
 * @var string $baseurl
 */


$baseurl = ( gmdate( 'Y' ) !== $this_year ) ? '/this-year/' . $this_year . '/' : '/this-year/';
?>
<ul class="nav nav-tabs">
	<?php
	echo '<li class="nav-item"><a class="nav-link' . esc_attr( ( 'overview' === $view ) ? ' active' : '' ) . '" href="' . esc_url( $baseurl ) . '">OVERVIEW</a></li>';
	foreach ( $valid_views as $the_view ) {
		$active = ( $view === $the_view ) ? ' active' : '';
		echo '<li class="nav-item"><a class="nav-link' . esc_attr( $active ) . '" href="' . esc_url( $baseurl . $the_view ) . '/">' . esc_html( strtoupper( str_replace( '-', ' ', $the_view ) ) ) . '</a></li>';
	}
	?>
</ul>
