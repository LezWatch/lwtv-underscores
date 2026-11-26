<?php
/**
 * Search results page (Meilisearch): numbered pagination for the current tab.
 *
 * Links keep `s` and `type`. Nothing prints for a single page.
 *
 * @package LWTV Underscores
 *
 * @var array $args results (from lwtv_plugin()->meili_search_results()).
 */

$lwtv_meili_results = $args['results'];

if ( (int) $lwtv_meili_results['pages'] < 2 ) {
	return;
}

$lwtv_meili_links = paginate_links(
	array(
		'base'      => add_query_arg(
			array(
				's'     => rawurlencode( $lwtv_meili_results['q'] ),
				'type'  => $lwtv_meili_results['type'],
				'paged' => '%#%',
			),
			home_url( '/' )
		),
		'format'    => '',
		'current'   => (int) $lwtv_meili_results['page'],
		'total'     => (int) $lwtv_meili_results['pages'],
		'type'      => 'list',
		'prev_text' => __( 'Previous', 'lwtv-underscores' ),
		'next_text' => __( 'Next', 'lwtv-underscores' ),
	)
);

if ( empty( $lwtv_meili_links ) ) {
	return;
}
?>
<nav class="lwtv-meili-page-pagination" aria-label="<?php esc_attr_e( 'Search results pages', 'lwtv-underscores' ); ?>">
	<?php echo wp_kses_post( $lwtv_meili_links ); ?>
</nav>
