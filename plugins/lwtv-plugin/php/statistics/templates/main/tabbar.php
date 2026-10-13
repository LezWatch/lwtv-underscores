<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Statistics section tab bar (shared across stats views).
 *
 * @package LezWatch.TV
 */

$lwtv_stats_tabs = array(
	array(
		'label' => __( 'Overview', 'lwtv-underscores' ),
		'url'   => home_url( '/statistics/' ),
	),
	array(
		'label' => __( 'Shows', 'lwtv-underscores' ),
		'url'   => home_url( '/statistics/shows/' ),
	),
	array(
		'label' => __( 'Characters', 'lwtv-underscores' ),
		'url'   => home_url( '/statistics/characters/' ),
	),
	array(
		'label' => __( 'Actors', 'lwtv-underscores' ),
		'url'   => home_url( '/statistics/actors/' ),
	),
	array(
		'label' => __( 'Nations', 'lwtv-underscores' ),
		'url'   => home_url( '/statistics/nations/' ),
	),
	array(
		'label' => __( 'Stations', 'lwtv-underscores' ),
		'url'   => home_url( '/statistics/stations/' ),
	),
	array(
		'label' => __( 'Death', 'lwtv-underscores' ),
		'url'   => home_url( '/statistics/death/' ),
	),
	array(
		'label' => __( 'This Year', 'lwtv-underscores' ),
		'url'   => home_url( '/this-year/' ),
	),
);

// Map the current stats section to its tab URL for active-state.
$lwtv_stats_active = home_url( '/statistics/' );
switch ( $statstype ?? 'main' ) {
	case 'shows':
	case 'characters':
	case 'actors':
	case 'nations':
	case 'stations':
	case 'death':
		$lwtv_stats_active = home_url( '/statistics/' . $statstype . '/' );
		break;
	case 'this-year':
		$lwtv_stats_active = home_url( '/this-year/' );
		break;
}
?>
<nav class="lwtv-stats-tabs" aria-label="<?php esc_attr_e( 'Statistics sections', 'lwtv-underscores' ); ?>">
	<?php
	foreach ( $lwtv_stats_tabs as $lwtv_stats_tab ) {
		$lwtv_is_active   = ( $lwtv_stats_active === $lwtv_stats_tab['url'] );
		$lwtv_tab_classes = 'lwtv-stats-tab' . ( $lwtv_is_active ? ' is-active' : '' );
		printf(
			'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
			esc_attr( $lwtv_tab_classes ),
			esc_url( $lwtv_stats_tab['url'] ),
			$lwtv_is_active ? ' aria-current="page"' : '',
			esc_html( $lwtv_stats_tab['label'] )
		);
	}
	?>
</nav>
