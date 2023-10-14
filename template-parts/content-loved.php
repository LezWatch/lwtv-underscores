<?php
/**
 * @package YIKES Starter
 */
?>

	<div class="card">
		<?php
		if ( has_post_thumbnail() ) {
			?>
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
				<?php the_post_thumbnail( 'postloop-img', array( 'class' => 'card-img-top' ) ); ?>
			</a>
			<?php
		}
		?>
		<div class="card-body">
			<h3 class="card-title"><?php the_title(); ?></h3>
			<div class="card-meta text-muted">
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
			<div class="card-text">
				<?php the_excerpt(); ?>
			</div>
		</div>
		<div class="card-footer">
			<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
				Go to Show Profile <span class="screen-reader-text">About <?php the_title(); ?></span>
			</a>
		</div>
	</div><!-- .card -->
