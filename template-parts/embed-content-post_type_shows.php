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
	.wp-embed {
		align-content: start;
		border: 1px solid #d1548e;
		color: #222;
		display: grid;
		grid-template-areas: 
		"title title"
		"image details"
		"excerpt excerpt"
		"footer footer";
		grid-template-columns: 50% 50%;
		padding: 0;
	}

	.wp-embed-heading {
		background-color: #d1548e;
		color: white;
		font-size: 20px;
		font-size: 1.25rem;
		grid-area: title;
		margin: 0;
		padding: 8px 10px;
	}

	.wp-embed-heading a,
	.wp-embed-heading a:visited {
		color: white;
	}

	.wp-embed-featured-image {
		grid-area: image;
		margin-bottom: 0;
	}

	.wp-embed-details p:last-of-type,
	.wp-embed-excerpt p:last-of-type {
		margin-bottom: 0;
	}

	.wp-embed-details {
		grid-area: details;
		padding: 10px;
	}

	.wp-embed-details p {
		margin-bottom: 4px;
	}

	.wp-embed-details a,
	.wp-embed-details a:visited {
		color: #d1548e;
	}

	.wp-embed-excerpt {
		font-size: 16px;
		grid-area: excerpt;
		padding: 4px 10px 10px 10px;
	}

	.wp-embed-footer {
		align-items: center;
		grid-area: footer;
		display: grid;
		grid-template-areas: 
			"site flag";
		grid-template-columns: 50% 50%;
		margin:  0;
	}

	.wp-embed-footer .wp-embed-site {
		grid-area: site;
		padding: 0 10px 10px 10px;
	}

	.wp-embed-footer .wp-embed-site a,
	.wp-embed-footer .wp-embed-site a:visited {
		color: #d1548e;
	}

	.wp-embed-footer .wp-embed-flag {
		grid-area: flag;
		justify-self: end;
		padding: 0 10px 10px 10px;
	}

	.flag svg {
		height: 25px;
		width: 25px;
		vertical-align: top;
		padding-right: 5px;
	}

	.flag-death svg * {
		fill: #222y;
	}
	.flag-danger svg *, .flag-we-love svg * {
		fill: #c0392b;
	}
	.flag-info svg *, .flag-warning svg * {
		fill: #f1c40f;
	}
	.flag-star-gold svg * {
		fill: #ffd700;
	}
	.flag-star-silver svg * {
		fill: #c0c0c0;
	}
	.flag-star-bronze svg * {
		fill: #b87333;
	}

	@media all and (max-width: 500px) {
		.wp-embed {
			grid-template-areas:
				"title"
				"image"
				"details"
				"excerpt"
				"footer";
			grid-template-columns: 100%;
		}

		.wp-embed-site-title a {
			padding-left: 26px;
		}
	}
</style>

	<div <?php post_class( 'wp-embed' ); ?>>
		<h3 class="wp-embed-heading">
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
		</h3>

		<div class="wp-embed-featured-image">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php echo wp_get_attachment_image( get_post_thumbnail_id(), 'medium' ); ?>
			</a>
		</div>

		<div class="wp-embed-details">
			<?php
			$show_score = ( get_post_meta( get_the_ID(), 'lezshows_the_score', true ) ) ? min( (int) get_post_meta( get_the_ID(), 'lezshows_the_score', true ), 100 ) : '<p>N/A</p>';
			echo '<p><strong>Score:</strong> ' . esc_html( $show_score ) . ' (out of 100)</p>';
			?>

			<p>
				<?php
				$havecharcount = lwtv_list_characters( get_the_ID(), 'count' );
				$havedeadcount = lwtv_list_characters( get_the_ID(), 'dead' );

				if ( ! empty( $havecharcount ) || '0' !== $havecharcount ) {
					$deadtext = 'none are dead';
					if ( $havedeadcount > '0' ) {
						// translators: %s is the number of dead characters.
						$deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
					}

					echo wp_kses_post( sprintf( _n( 'is <strong>%s</strong> queer character', '<strong>%s</strong> queer characters', $havecharcount ), $havecharcount ) . ' - ' . $deadtext );
				}
				?>
			</p>

			<p>
				<?php
				$on_air = get_post_meta( get_the_ID(), 'lezshows_on_air', true );
				if ( 'yes' === $on_air ) {
					$tvmaze = ( new LWTV_Whats_On_JSON() )->whats_on_show( get_the_ID() );
					echo '<strong>Next Episode:</strong> ' . wp_kses_post( $tvmaze['next'] );
				}

				$stations = get_the_terms( get_the_ID(), 'lez_stations' );
				if ( $stations && ! is_wp_error( $stations ) ) {
					echo get_the_term_list( get_the_ID(), 'lez_stations', ' on ', ', ' ) . '.';
				}
				?>
			</p>
		</div>

		<div class="wp-embed-excerpt">
			<?php the_excerpt_embed(); ?>
		</div>

		<div class="wp-embed-footer">
			<div class="wp-embed-site">
				<?php the_embed_site_title(); ?>				
			</div>

			<div class="wp-embed-flag">
				<?php
				// The Game of Thrones Flag of Gratuitous Violence.
				$warning    = lwtv_yikes_content_warning( get_the_ID() );
				$warn_image = lwtv_symbolicons( 'warning.svg', 'fa-exclamation-triangle' );
				if ( 'none' !== $warning['card'] ) {
					// phpcs:ignore WordPress.Security.EscapeOutput
					echo '<span class="flag flag-' . esc_attr( $warning['card'] ) . '" title="Content Warning">' . $warn_image . '</span>';
				}

				$term_list = wp_get_post_terms( get_the_ID(), 'lez_stars', array( 'fields' => 'slugs' ) );
				if ( ! is_wp_error( $term_list ) ) {
					// Stars of Queerness.
					echo '<a href="https://lezwatchtv.com/star/' . esc_attr( $term_list[0] ) . '" title="' . esc_attr( ucfirst( $term_list[0] ) ) . ' Star" target="_blank"><span class="flag flag-star-' . esc_attr( $term_list[0] ) . '" target="_blank">' . lwtv_yikes_show_star( get_the_ID() ) . '</span></a>';
				}

				// Hearts of Lurve.
				if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true ) ) {
					$heart = lwtv_symbolicons( 'hearts.svg', 'fa-heart' );
					// phpcs:ignore WordPress.Security.EscapeOutput
					echo ' <a href="https://lezwatchtv.com/shows/?fwp_show_loved=yes" title="We Love This Show!" target="_blank"><span role="img" class="flag flag-we-love" title="We Love This Show!">' . $heart . '</span></a>';
				}

				// Skulls of Death.
				if ( has_term( 'dead-queers', 'lez_tropes', get_the_ID() ) ) {
					$skull = lwtv_symbolicons( 'skull-crossbones.svg', 'fa-ban' );
					// phpcs:ignore WordPress.Security.EscapeOutput
					echo ' <span role="img" title="Warning - There is death on this show." class="flag flag-death">' . $skull . '</span>';
				}
				?>
			</div>
		</div>
	</div>
<?php
