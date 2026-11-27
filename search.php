<?php
/**
 * The template for displaying Search Results pages.
 *
 * With the Meilisearch switch on (LWTV_MEILI_MODAL), results come from
 * lwtv_plugin()->meili_search_results(): tabs, rows and pagination from
 * template-parts/search/. Otherwise, or when Meilisearch failed and the
 * request fell back to SearchWP, it's the standard card grid.
 *
 * @package LWTV Underscores
 */

$lwtv_meili_results    = lwtv_plugin()->meili_search_results();
$lwtv_meili_count_line = '';

if ( null !== $lwtv_meili_results ) {
	$lwtv_meili_labels = array(
		'shows'      => __( 'Shows', 'lwtv-underscores' ),
		'characters' => __( 'Characters', 'lwtv-underscores' ),
		'actors'     => __( 'Actors', 'lwtv-underscores' ),
		'content'    => __( 'Posts', 'lwtv-underscores' ),
	);
	$lwtv_meili_total  = (int) ( $lwtv_meili_results['counts'][ $lwtv_meili_results['type'] ] ?? 0 );
	$lwtv_meili_counts = array(
		/* translators: %s: number of shows. */
		'shows'      => _n( '%s show', '%s shows', $lwtv_meili_total, 'lwtv-underscores' ),
		/* translators: %s: number of characters. */
		'characters' => _n( '%s character', '%s characters', $lwtv_meili_total, 'lwtv-underscores' ),
		/* translators: %s: number of actors. */
		'actors'     => _n( '%s actor', '%s actors', $lwtv_meili_total, 'lwtv-underscores' ),
		/* translators: %s: number of posts and pages. */
		'content'    => _n( '%s post', '%s posts', $lwtv_meili_total, 'lwtv-underscores' ),
	);

	// The count line under the heading, for the current tab ("14 characters").
	$lwtv_meili_count_line = '<p class="lwtv-meili-page-count">' . esc_html( sprintf( $lwtv_meili_counts[ $lwtv_meili_results['type'] ], number_format_i18n( $lwtv_meili_total ) ) ) . '</p>';
}

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<div class="row">
					<div class="col-10"><h1 class="entry-title">
					<?php
						// translators: %s is whatever you just searched for.
						printf( esc_attr__( 'Search Results for: %s', 'lwtv-underscores' ), '<span>' . get_search_query() . '</span>' );
					?>
					</h1><?php echo $lwtv_meili_count_line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above; '' with the switch off, so the markup is unchanged. ?></div>
					<div class="col-2 icon plain">
						<span role="img" aria-label="Search Results" title="Search Results" class="taxonomy-svg 404"><?php echo lwtv_plugin()->get_symbolicon( svg: 'search.svg', icon: 'svg-search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>
				</div>
			</section><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col">
				<div id="primary" class="content-area">
					<div id="content" class="site-content d-flow-root">
						<?php
						if ( null !== $lwtv_meili_results ) {
							echo '<div class="lwtv-meili-page">';
							get_template_part(
								'template-parts/search/meili-tabs',
								null,
								array(
									'results' => $lwtv_meili_results,
									'labels'  => $lwtv_meili_labels,
								)
							);

							if ( ! empty( $lwtv_meili_results['rows'] ) ) {
								echo '<ol class="lwtv-meili-page-results">';
								foreach ( $lwtv_meili_results['rows'] as $lwtv_meili_i => $lwtv_meili_row ) {
									get_template_part(
										'template-parts/search/meili-row',
										null,
										array(
											'row'   => $lwtv_meili_row,
											'first' => 0 === $lwtv_meili_i,
											'q'     => $lwtv_meili_results['q'],
										)
									);
								}
								echo '</ol>';
								get_template_part( 'template-parts/search/meili-pagination', null, array( 'results' => $lwtv_meili_results ) );
							} else {
								get_template_part(
									'template-parts/search/meili-empty',
									null,
									array(
										'results' => $lwtv_meili_results,
										'labels'  => $lwtv_meili_labels,
									)
								);
							}
							echo '</div>';
						} elseif ( have_posts() ) {
							echo '<div class="row site-loop main-posts-loop four-across-loop">';
							while ( have_posts() ) {
								the_post();
								get_template_part( 'template-parts/content/search' );
							}
							echo '</div>';
							lwtv_theme_paging_nav();
						} else {
							get_template_part( 'template-parts/content/none' );
						}
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
