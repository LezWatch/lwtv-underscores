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

<div class="card-group" id="post-<?php the_ID(); ?>">
	<div title="<?php echo esc_html( $thumb_title ); ?>" class="card col-sm-5"
		<?php if ( has_post_thumbnail() ) { ?>
			style="background-image: url(<?php the_post_thumbnail_url( 'large' ); ?>);"
		<?php } ?>
	>
	</div>
	<div class="card col-sm-7">
		<div class="card-body">
			<h3 class="card-title">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>

				<span class="callout">
					<?php
					// The Game of Thrones Flag of Gratuitous Violence.
					$warning    = lwtv_yikes_content_warning( get_the_ID() );
					$warn_image = lwtv_yikes_symbolicons( 'warning.svg', 'fa-exclamation-triangle' );
					if ( 'none' !== $warning['card'] ) {
						echo '<span class="callout callout-' . esc_attr( $warning['card'] ) . '" role="img" data-toggle="tooltip" aria-label="Warning - This show contains triggers" title="Warning - This show contains triggers">' . lwtv_sanitized( $warn_image ) . '</span>';
					}

					// Stars of Queerness.
					echo '<span class="callout callout-star">' . lwtv_yikes_show_star( get_the_ID() ) . '</span>';

					// Hearts of Lurve.
					if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true ) ) {
						$heart = lwtv_yikes_symbolicons( 'hearts.svg', 'fa-heart' );
						echo ' <span role="img" aria-label="We Love This Show!" data-toggle="tooltip" title="We Love This Show!" class="callout callout-we-love">' . lwtv_sanitized( $heart ) . '</span>';
					}

					// Skulls of Death.
					if ( has_term( 'dead-queers', 'lez_tropes', get_the_ID() ) ) {
						$skull = lwtv_yikes_symbolicons( 'skull-crossbones.svg', 'fa-ban' );
						echo ' <span role="img" aria-label="Warning - There is death on this show." data-toggle="tooltip" title="Warning - There is death on this show." class="callout callout-death">' . lwtv_sanitized( $skull ) . '</span>';
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
	</div><!-- .card -->
</div><!-- .card-group -->
