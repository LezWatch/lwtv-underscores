<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * This Year sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view       Current view slug.
 * @var string $ty_baseurl Base URL ('/this-year/' or '/this-year/{year}/').
 */

$lwtv_ty_subnav = array(
	'overview'          => __( 'Overview', 'lwtv' ),
	'characters-on-air' => __( 'Characters On Air', 'lwtv' ),
	'dead-characters'   => __( 'Dead Characters', 'lwtv' ),
	'shows-on-air'      => __( 'Shows On Air', 'lwtv' ),
	'new-shows'         => __( 'New Shows', 'lwtv' ),
	'canceled-shows'    => __( 'Canceled Shows', 'lwtv' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'This Year views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_ty_subnav as $lwtv_slug => $lwtv_label ) {
		$lwtv_is_active = ( $view === $lwtv_slug );
		$lwtv_url       = ( 'overview' === $lwtv_slug ) ? $ty_baseurl : $ty_baseurl . $lwtv_slug . '/';
		printf(
			'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
			$lwtv_is_active ? ' is-active' : '',
			esc_url( home_url( $lwtv_url ) ),
			$lwtv_is_active ? ' aria-current="page"' : '',
			esc_html( $lwtv_label )
		);
	}
	?>
</nav>
