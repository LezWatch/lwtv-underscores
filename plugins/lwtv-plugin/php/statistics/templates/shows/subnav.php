<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows statistics sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view    Current view slug.
 * @var string $baseurl Base URL for the shows stats section.
 */

$lwtv_shows_subnav = array(
	'overview'          => __( 'Overview', 'lwtv' ),
	'formats'           => __( 'Formats', 'lwtv' ),
	'tropes'            => __( 'Tropes', 'lwtv' ),
	'genres'            => __( 'Genres', 'lwtv' ),
	'intersectionality' => __( 'Intersectionality', 'lwtv' ),
	'stars'             => __( 'Stars', 'lwtv' ),
	'triggers'          => __( 'Triggers', 'lwtv' ),
	'on-air'            => __( 'On Air', 'lwtv' ),
	'worth-it'          => __( 'Worth It', 'lwtv' ),
	'we-love-it'        => __( 'We Love It', 'lwtv' ),
);
?>
<nav class="lwtv-shows-subnav" aria-label="<?php esc_attr_e( 'Shows statistics views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_shows_subnav as $lwtv_slug => $lwtv_label ) {
		$lwtv_is_active = ( $view === $lwtv_slug );
		$lwtv_url       = ( 'overview' === $lwtv_slug ) ? $baseurl : $baseurl . $lwtv_slug . '/';
		printf(
			'<a class="lwtv-shows-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
			$lwtv_is_active ? ' is-active' : '',
			esc_url( $lwtv_url ),
			$lwtv_is_active ? ' aria-current="page"' : '',
			esc_html( $lwtv_label )
		);
	}
	?>
</nav>
