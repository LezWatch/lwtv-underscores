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
$thumb_array       = array(
	'class' => 'card-img-top',
	'alt'   => $thumb_title,
	'title' => $thumb_title,
);
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
							$warning    = lwtv_yikes_content_warning( get_the_ID() );
							$warn_image = lwtv_symbolicons( 'warning.svg', 'fa-exclamation-triangle' );
							if ( 'none' !== $warning['card'] ) {
								// phpcs:ignore WordPress.Security.EscapeOutput
								echo '<span class="callout callout-' . esc_attr( $warning['card'] ) . '" role="img" data-bs-target="tooltip" aria-label="Warning - This show contains triggers" title="Warning - This show contains triggers">' . $warn_image . '</span>';
							}

							// Stars of Queerness.
							echo '<span class="callout callout-star">' . lwtv_yikes_show_star( get_the_ID() ) . '</span>';

							// Hearts of Lurve.
							if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true ) ) {
								$heart = lwtv_symbolicons( 'hearts.svg', 'fa-heart' );
								// phpcs:ignore WordPress.Security.EscapeOutput
								echo ' <span role="img" aria-label="We Love This Show!" data-bs-target="tooltip" title="We Love This Show!" class="callout callout-we-love">' . $heart . '</span>';
							}

							// Skulls of Death.
							if ( has_term( 'dead-queers', 'lez_tropes', get_the_ID() ) ) {
								$skull = lwtv_symbolicons( 'skull-crossbones.svg', 'fa-ban' );
								// phpcs:ignore WordPress.Security.EscapeOutput
								echo ' <span role="img" aria-label="Warning - There is death on this show." data-bs-target="tooltip" title="Warning - There is death on this show." class="callout callout-death">' . $skull . '</span>';
							}
							?>
						</span>
					</h3>
					<div class="card-text"><?php the_excerpt(); ?></div>

					<div class="card-meta">
						<?php
						$stations = get_the_terms( get_the_ID(), 'lez_stations' );
						if ( $stations && ! is_wp_error( $stations ) ) {
							echo get_the_term_list( get_the_ID(), 'lez_stations', '<strong>Network:</strong> ', ', ' ) . '<br />';
						}
						$airdates = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
						if ( $airdates ) {
							$airdate = $airdates['start'] . ' - ' . $airdates['finish'];
							if ( $airdates['start'] === $airdates['finish'] ) {
								$airdate = $airdates['finish'];
							}
							echo '<strong>Airdates:</strong> ' . esc_html( $airdate ) . '<br />';
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
