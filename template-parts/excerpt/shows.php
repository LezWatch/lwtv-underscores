<?php
/**
 * This content is called by all archival displays of shows
 *
 * It's used by the following files
 *      - archive-post_type_shows.php
 *      - taxonomy.php
 *
 * @package LezWatch.TV
 */

global $post;

// Thumbnail attribution.
$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;
?>

<div class="show-group" id="post-<?php the_ID(); ?>">
	<div class="card mb-3">
		<div class="row g-0">
			<div class="image col-md-5" title="<?php echo esc_html( $thumb_title ); ?>"
				<?php if ( has_post_thumbnail() ) { ?>
					style="background-image: url(<?php the_post_thumbnail_url( 'show-img' ); ?>);"
				<?php } ?>
			>
			</div>
			<div class="col-sm-7">
				<div class="card-body">
					<h3 class="card-title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>

						<span class="callout">
							<?php
							// The Game of Thrones Flag of Gratuitous Violence.
							$warning    = lwtv_plugin()->get_show_content_warning( get_the_ID() );
							$warn_image = lwtv_plugin()->get_symbolicon( svg: 'warning.svg', icon: 'svg-exclamation-triangle', max_size: '25' );
							if ( 'none' !== $warning['card'] ) {
								// phpcs:ignore WordPress.Security.EscapeOutput
								echo '<span class="callout callout-' . esc_attr( $warning['card'] ) . '" role="img" data-bs-target="tooltip" aria-label="Warning - This show contains triggers" title="Warning - This show contains triggers">' . $warn_image . '</span>';
							}

							// Stars of Queerness.
							echo '<span class="callout callout-star">' . lwtv_plugin()->get_show_stars( get_the_ID(), 25 ) . '</span>';

							// Hearts of Lurve.
							if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true ) ) {
								$heart = lwtv_plugin()->get_symbolicon( svg: 'hearts.svg', icon: 'svg-heart', max_size: '25' );
								// phpcs:ignore WordPress.Security.EscapeOutput
								echo ' <span role="img" aria-label="We Love This Show!" data-bs-target="tooltip" title="We Love This Show!" class="callout callout-we-love">' . $heart . '</span>';
							}

							// Skulls of Death.
							if ( has_term( 'dead-queers', 'lez_tropes', get_the_ID() ) ) {
								$skull = lwtv_plugin()->get_symbolicon( svg: 'skull-crossbones.svg', icon: 'svg-times-circle', max_size: '25' );
								// phpcs:ignore WordPress.Security.EscapeOutput
								echo ' <span role="img" aria-label="Warning - There is death on this show." data-bs-target="tooltip" title="Warning - There is death on this show." class="callout callout-death">' . $skull . '</span>';
							}
							?>
						</span>
					</h3>

					<div class="card-info">
						<ul class="list-group list-group-horizontal">
							<?php
							echo get_the_term_list( get_the_ID(), 'lez_formats', '<li class="list-group-item">', ', ', '</li>' );

							$airdates = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
							if ( $airdates ) {
								echo '<li class="list-group-item">';
								$airdate = $airdates['start'] . ' - ' . $airdates['finish'];
								if ( $airdates['start'] === $airdates['finish'] ) {
									$airdate = $airdates['finish'];
								}
								echo esc_html( $airdate );
								echo '</li>';
							}

							$stations = get_the_terms( get_the_ID(), 'lez_stations' );
							if ( $stations && ! is_wp_error( $stations ) ) {
								echo get_the_term_list( get_the_ID(), 'lez_stations', '<li class="list-group-item">', ', ', '</li>' );
							}
							?>
						</ul>
					</div>

					<div class="card-text"><?php the_excerpt(); ?></div>

					<div class="card-meta">
						<?php
						// Intersectionality: only shown in intersection-focused views, i.e.
						// on lez_intersections term archives or when the FacetWP
						// intersectionality facet has a selection. Keeps default browsing clean.
						$show_intersections = is_tax( 'lez_intersections' );
						if ( ! $show_intersections && function_exists( 'FWP' ) ) {
							// AJAX refresh: selections live in the facet object; initial
							// page load: they come from the URL vars. Check both.
							$selected_facets    = FWP()->facet->facets['show_intersectionality']['selected_values'] ?? array();
							$facet_url_vars     = FWP()->request->url_vars['show_intersectionality'] ?? array();
							$show_intersections = ! empty( $selected_facets ) || ! empty( $facet_url_vars );
						}
						if ( $show_intersections ) {
							$intersections = get_the_terms( get_the_ID(), 'lez_intersections' );
							if ( $intersections && ! is_wp_error( $intersections ) ) {
								echo '<span class="intersection-icons">';
								foreach ( $intersections as $intersection ) {
									$icon = get_term_meta( $intersection->term_id, 'lez_termsmeta_icon', true );
									$icon = ( ! empty( $icon ) ) ? $icon : 'flag-wave';
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo '<a href="' . esc_url( get_term_link( $intersection, 'lez_intersections' ) ) . '" class="intersection-icon" data-bs-target="tooltip" title="' . esc_attr( $intersection->name ) . '" aria-label="' . esc_attr( $intersection->name ) . '">' . lwtv_plugin()->get_symbolicon( svg: $icon . '.svg', icon: 'svg-lemon', max_size: '20' ) . '</a> ';
								}
								echo '</span>';
							}
						}
						?>
					</div>
				</div>

				<div class="card-footer">
					<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
						Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
					</a>
				</div>
			</div>
		</div><!-- .row -->
	</div><!-- .card -->
</div><!-- .card-group -->
