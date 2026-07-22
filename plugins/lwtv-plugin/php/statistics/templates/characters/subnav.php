<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters statistics sub-nav (bottom-border tabs).
 *
 * @package LezWatch.TV
 *
 * @var string $view    Current view slug.
 * @var string $baseurl Base URL for the characters stats section.
 */

$lwtv_char_subnav = array(
	'overview'     => __( 'Overview', 'lwtv' ),
	'cliches'      => __( 'Clichés', 'lwtv' ),
	'most-cliches' => __( 'Most Clichés', 'lwtv' ),
	'gender'       => __( 'Gender', 'lwtv' ),
	'sexuality'    => __( 'Sexuality', 'lwtv' ),
	'queer-irl'    => __( 'Queer IRL', 'lwtv' ),
	'on-air'       => __( 'On Air', 'lwtv' ),
);
?>
<nav class="lwtv-stats-subnav" aria-label="<?php esc_attr_e( 'Characters statistics views', 'lwtv' ); ?>">
	<?php
	foreach ( $lwtv_char_subnav as $lwtv_slug => $lwtv_label ) {
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
