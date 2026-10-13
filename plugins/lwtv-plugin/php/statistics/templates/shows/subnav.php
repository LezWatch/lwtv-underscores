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
	'overview'          => __( 'Overview', 'lwtv-underscores' ),
	'formats'           => __( 'Formats', 'lwtv-underscores' ),
	'tropes'            => __( 'Tropes', 'lwtv-underscores' ),
	'genres'            => __( 'Genres', 'lwtv-underscores' ),
	'intersectionality' => __( 'Intersectionality', 'lwtv-underscores' ),
	'stars'             => __( 'Stars', 'lwtv-underscores' ),
	'scores'            => __( 'Scores', 'lwtv-underscores' ),
	'triggers'          => __( 'Triggers', 'lwtv-underscores' ),
	'worth-it'          => __( 'Worth It', 'lwtv-underscores' ),
	'we-love-it'        => __( 'We Love It', 'lwtv-underscores' ),
	'on-air'            => __( 'On Air', 'lwtv-underscores' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'Shows statistics views', 'lwtv-underscores' ); ?>">
	<?php
	foreach ( $lwtv_shows_subnav as $lwtv_slug => $lwtv_label ) {
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
