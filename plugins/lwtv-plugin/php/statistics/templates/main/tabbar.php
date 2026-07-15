<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Statistics section tab bar (overview view).
 *
 * @package LezWatch.TV
 */

$lwtv_stats_tabs = array(
	array(
		'label' => __( 'Overview', 'lwtv' ),
		'url'   => home_url( '/statistics/' ),
	),
	array(
		'label' => __( 'Shows', 'lwtv' ),
		'url'   => home_url( '/statistics/shows/' ),
	),
	array(
		'label' => __( 'Characters', 'lwtv' ),
		'url'   => home_url( '/statistics/characters/' ),
	),
	array(
		'label' => __( 'Actors', 'lwtv' ),
		'url'   => home_url( '/statistics/actors/' ),
	),
	array(
		'label' => __( 'Nations', 'lwtv' ),
		'url'   => home_url( '/statistics/nations/' ),
	),
	array(
		'label' => __( 'Stations', 'lwtv' ),
		'url'   => home_url( '/statistics/stations/' ),
	),
	array(
		'label' => __( 'Death', 'lwtv' ),
		'url'   => home_url( '/statistics/death/' ),
	),
	array(
		'label' => __( 'This Year', 'lwtv' ),
		'url'   => home_url( '/this-year/' ),
	),
);
?>
<nav class="lwtv-stats-tabs" aria-label="<?php esc_attr_e( 'Statistics sections', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_stats_tabs as $lwtv_stats_tab ) {
		// Overview is the active tab on this view.
		$lwtv_is_active   = ( home_url( '/statistics/' ) === $lwtv_stats_tab['url'] );
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
