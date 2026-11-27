<?php
/**
 * Search results page (Meilisearch): the tabs.
 *
 * A labelled <nav> of plain links, not ARIA tabs: each tab loads its own
 * page, so it works without JavaScript. Tabs keep their place at zero.
 *
 * @package LWTV Underscores
 *
 * @var array $args results (from lwtv_plugin()->meili_search_results()) and labels.
 */

$lwtv_meili_results = $args['results'];
?>
<nav class="lwtv-meili-page-tabs" aria-label="<?php esc_attr_e( 'Search results by type', 'lwtv-underscores' ); ?>">
	<ul>
		<?php
		foreach ( $args['labels'] as $lwtv_meili_type => $lwtv_meili_label ) :
			$lwtv_meili_href = add_query_arg(
				array(
					's'    => rawurlencode( $lwtv_meili_results['q'] ),
					'type' => $lwtv_meili_type,
				),
				home_url( '/' )
			);
			?>
			<li>
				<a href="<?php echo esc_url( $lwtv_meili_href ); ?>"<?php echo ( $lwtv_meili_type === $lwtv_meili_results['type'] ) ? ' aria-current="page"' : ''; ?>>
					<span class="lwtv-meili-tab-label"><?php echo esc_html( $lwtv_meili_label ); ?></span>
					<span class="lwtv-meili-count"><?php echo esc_html( number_format_i18n( (int) ( $lwtv_meili_results['counts'][ $lwtv_meili_type ] ?? 0 ) ) ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
