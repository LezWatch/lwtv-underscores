<?php
/**
 * Search results page (Meilisearch): the current tab has no results.
 *
 * Says so, and links to the tabs that do. With no results anywhere, it's
 * the standard "Nothing Found" part, search form included.
 *
 * @package LWTV Underscores
 *
 * @var array $args results (from lwtv_plugin()->meili_search_results()) and labels.
 */

$lwtv_meili_results = $args['results'];
$lwtv_meili_others  = array();
foreach ( $args['labels'] as $lwtv_meili_type => $lwtv_meili_label ) {
	if ( $lwtv_meili_type !== $lwtv_meili_results['type'] && (int) ( $lwtv_meili_results['counts'][ $lwtv_meili_type ] ?? 0 ) > 0 ) {
		$lwtv_meili_others[ $lwtv_meili_type ] = $lwtv_meili_label;
	}
}

if ( ! $lwtv_meili_others ) {
	get_template_part( 'template-parts/content/none' );
	return;
}

$lwtv_meili_messages = array(
	/* translators: %s: the search term. */
	'shows'      => __( 'No shows match “%s”.', 'lwtv-underscores' ),
	/* translators: %s: the search term. */
	'characters' => __( 'No characters match “%s”.', 'lwtv-underscores' ),
	/* translators: %s: the search term. */
	'actors'     => __( 'No actors match “%s”.', 'lwtv-underscores' ),
	/* translators: %s: the search term. */
	'content'    => __( 'No posts match “%s”.', 'lwtv-underscores' ),
);
?>
<div class="lwtv-meili-page-empty">
	<p><?php echo esc_html( sprintf( $lwtv_meili_messages[ $lwtv_meili_results['type'] ], $lwtv_meili_results['q'] ) ); ?></p>
	<p><?php esc_html_e( 'Results in other tabs:', 'lwtv-underscores' ); ?></p>
	<ul>
		<?php
		foreach ( $lwtv_meili_others as $lwtv_meili_type => $lwtv_meili_label ) :
			$lwtv_meili_href = add_query_arg(
				array(
					's'    => rawurlencode( $lwtv_meili_results['q'] ),
					'type' => $lwtv_meili_type,
				),
				home_url( '/' )
			);
			?>
			<li>
				<a href="<?php echo esc_url( $lwtv_meili_href ); ?>">
					<?php
					/* translators: 1: tab name, e.g. "Characters"; 2: number of results. */
					echo esc_html( sprintf( __( '%1$s (%2$s)', 'lwtv-underscores' ), $lwtv_meili_label, number_format_i18n( (int) $lwtv_meili_results['counts'][ $lwtv_meili_type ] ) ) );
					?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
