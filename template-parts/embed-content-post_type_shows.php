<?php
/**
 * Contains the post embed content template part
 *
 * When a post is embedded in an iframe, this file is used to create the content template part
 * output if the active theme does not include an embed-content.php template.
 *
 * @package WordPress
 * @subpackage Theme_Compat
 * @since 4.5.0
 */
?>

<style>

	.wp-embed-featured-image.square {
		float: left;
		max-width: 200px;
		margin-right: 20px;
	}

	.wp-embed p {
		margin-bottom: 1rem;
	}
	.callout {
		float: right;
	}

	.callout svg {
		height: 25px;
		width: 25px;
		vertical-align: top;
		padding-right: 5px;
	}

	.callout-death svg * {
		fill: #222y;
	}
	.callout-danger svg *, .callout-we-love svg * {
		fill: #c0392b;
	}
	.callout-info svg *, .callout-warning svg * {
		fill: #f1c40f;
	}
	.callout-star-gold svg * {
		fill: #FFD700;
	}
	.callout-star-silver svg * {
		fill: #C0C0C0;
	}
	.callout-star-bronze svg * {
		fill: #B87333;
	}
</style>

	<div <?php post_class( 'wp-embed' ); ?>>

		<p class="wp-embed-heading">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php the_title(); ?>
			</a>
			<?php
			$airdates = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
			if ( $airdates ) {
				$airdate = $airdates['start'] . ' - ' . $airdates['finish'];
				if ( $airdates['start'] === $airdates['finish'] ) {
					$airdate = $airdates['finish'];
				}
				echo ' (' . esc_html( $airdate ) . ')';
			}
			?>
		</p>

		<div class="wp-embed-featured-image square">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php echo wp_get_attachment_image( get_post_thumbnail_id(), 'medium' ); ?>
			</a>
		</div>

		<div class="wp-embed-excerpt">
			<?php

			the_excerpt_embed();

			$show_score = ( get_post_meta( get_the_ID(), 'lezshows_the_score', true ) ) ? min( (int) get_post_meta( get_the_ID(), 'lezshows_the_score', true ), 100 ) : 'N/A';
			echo '<strong>Score:</strong> ' . esc_html( $show_score ) . ' (out of 100)';

			$havecharcount = lwtv_list_characters( get_the_ID(), 'count' );
			$havedeadcount = lwtv_list_characters( get_the_ID(), 'dead' );

			if ( ! empty( $havecharcount ) || '0' !== $havecharcount ) {
				$deadtext = 'none are dead';
				if ( $havedeadcount > '0' ) {
					// translators: %s is the number of dead characters.
					$deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
				}

				echo wp_kses_post( ' with ' . sprintf( _n( 'is <strong>%s</strong> queer character', '<strong>%s</strong> queer characters', $havecharcount ), $havecharcount ) . ' - ' . $deadtext . '.</p>' );
			}

			?>

			<p>
				<?php
				$on_air = get_post_meta( get_the_ID(), 'lezshows_on_air', true );
				if ( 'yes' === $on_air ) {
					$tvmaze = ( new LWTV_Whats_On_JSON() )->whats_on_show( get_the_ID() );
					echo '<strong>Next Episode</strong>: ' . wp_kses_post( $tvmaze['next'] );
				}

				$stations = get_the_terms( get_the_ID(), 'lez_stations' );
				if ( $stations && ! is_wp_error( $stations ) ) {
					echo get_the_term_list( get_the_ID(), 'lez_stations', ' on ', ', ' ) . '.';
				}
				?>
			</p>
		</div>

		<div class="wp-embed-footer">
			<?php the_embed_site_title(); ?>

			<div class="wp-embed-meta">
				<span class="callout">
					<?php

					// The Game of Thrones Flag of Gratuitous Violence.
					$warning    = lwtv_yikes_content_warning( get_the_ID() );
					$warn_image = lwtv_symbolicons( 'warning.svg', 'fa-exclamation-triangle' );
					if ( 'none' !== $warning['card'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput
						echo '<span class="callout callout-' . esc_attr( $warning['card'] ) . '" title="Warning - This show contains triggers" title="Warning - This show contains triggers">' . $warn_image . '</span>';
					}

					$term_list = wp_get_post_terms( get_the_ID(), 'lez_stars', array( 'fields' => 'slugs' ) );
					if ( ! is_wp_error( $term_list ) ) {
						// Stars of Queerness.
						echo '<a href="https://lezwatchtv.com/star/' . esc_attr( $term_list[0] ) . '" title="' . esc_attr( ucfirst( $term_list[0] ) ) . ' Star" target="_blank"><span class="callout callout-star-' . esc_attr( $term_list[0] ) . '">' . lwtv_yikes_show_star( get_the_ID() ) . '</span></a>';
					}

					// Hearts of Lurve.
					if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true ) ) {
						$heart = lwtv_symbolicons( 'hearts.svg', 'fa-heart' );
						// phpcs:ignore WordPress.Security.EscapeOutput
						echo ' <a href="https://lezwatchtv.com/shows/?fwp_show_loved=yes" title="We Love This Show!" target="_blank"><span role="img" class="callout callout-we-love">' . $heart . '</span></a>';
					}

					// Skulls of Death.
					if ( has_term( 'dead-queers', 'lez_tropes', get_the_ID() ) ) {
						$skull = lwtv_symbolicons( 'skull-crossbones.svg', 'fa-ban' );
						// phpcs:ignore WordPress.Security.EscapeOutput
						echo ' <span role="img" title="Warning - There is death on this show." class="callout callout-death">' . $skull . '</span>';
					}
					?>
				</span>
			</div>
		</div>
	</div>
<?php
