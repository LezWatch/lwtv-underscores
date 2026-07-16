<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors statistics sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view    Current view slug.
 * @var string $baseurl Base URL for the actors stats section.
 */

$lwtv_actor_subnav = array(
	'overview'  => __( 'Overview', 'lwtv' ),
	'sexuality' => __( 'Sexuality', 'lwtv' ),
	'gender'    => __( 'Gender', 'lwtv' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'Actors statistics views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_actor_subnav as $lwtv_slug => $lwtv_label ) {
		$lwtv_is_active = ( $view === $lwtv_slug );
		$lwtv_url       = ( 'overview' === $lwtv_slug ) ? $baseurl : $baseurl . $lwtv_slug . '/';
		printf(
			'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
			$lwtv_is_active ? ' is-active' : '',
			esc_url( $lwtv_url ),
			$lwtv_is_active ? ' aria-current="page"' : '',
			esc_html( $lwtv_label )
		);
	}
	?>
</nav>
