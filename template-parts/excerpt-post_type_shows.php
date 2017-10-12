<?php
/*
 * This content is called by all archival displays of shows
 * 
 * It's used by the following files
 *      - archive-post_type_shows.php
 *      - taxonomy.php
 *
 * @package LezWatchTV
 */

global $post;
?>

<div class="card-group" id="post-<?php the_ID(); ?>">
	<div class="card col-sm-5"	
		<?php if ( has_post_thumbnail() ) { ?>
			style="background-image: url(<?php the_post_thumbnail_url( 'large' ); ?>);"
		<?php } ?>
	>
	</div>
	<div class="card col-sm-7">
		<div class="card-body">
			<h3 class="card-title">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				
				<?php
				// The Game of Thrones Flag of Gratuitious Violence
				$warning    = lwtv_yikes_content_warning( get_the_ID() );
				$warn_image = lwtv_yikes_symbolicons( 'alert.svg', 'fa-exclamation-triangle' );
				
				if ( $warning['card'] != 'none' ) {
					echo '<span class="callout callout-' . $warning['card'] . '" role="img" aria-label="Warning - This show contains triggers" title="Warning - This show contains trigers">' . $warn_image . '</span>';
				}

				// Stars of Queerness
				if ( get_post_meta( get_the_ID(), 'lezshows_stars', true) ) {
					$color = esc_attr( get_post_meta( get_the_ID(), 'lezshows_stars' , true ) );
					$star  = lwtv_yikes_symbolicons( 'star.svg', 'fa-star' );
					echo '<span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" data-toggle="tooltip" title="' . ucfirst( $color ) . ' Star Show" class="callout callout-star-' . $color . '">' . $star . '</span>';
				}

				// Hearts of Lurve
				if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true) ) {
					$heart = lwtv_yikes_symbolicons( 'heart.svg', 'fa-heart' );
					echo ' <span role="img" aria-label="We Love This Show!" data-toggle="tooltip" title="We Love This Show!" class="callout callout-we-love">' . $heart . '</span>';
				}

				?>
				
				</h3>
			<div class="card-text"><?php the_excerpt(); ?></div>

			<div class="card-meta">
				<?php 
					$stations = get_the_terms( get_the_ID(), 'lez_stations' );
					if ( $stations && ! is_wp_error( $stations ) ) {
						echo get_the_term_list( get_the_ID(), 'lez_stations', '<strong>Network:</strong> ', ', ' ) .'<br />';
					}
					$airdates = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
					if ( $airdates ) {
						$airdate  = $airdates['start'] . ' - ' . $airdates['finish'];
						if ( $airdates['start'] == $airdates['finish'] ) { $airdate = $airdates['finish']; }
						echo '<strong>Airdates:</strong> '. $airdate .'<br />';
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