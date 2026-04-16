<?php
/**
 * @package LWTV Underscores
 */

// Get 4 random loved shows directly from database
$loved_post_ids = lwtv_plugin()->get_random_loved_shows_ids( 4 );

if ( empty( $loved_post_ids ) ) {
	?>
	<div class="alert alert-info">
		<p>No loved shows found.</p>
	</div>
	<?php
} else {
	// The Loop
	foreach ( $loved_post_ids as $loved_post_id ) {
		?>
		<div class="card">
		<?php
		if ( has_post_thumbnail( $loved_post_id ) ) {
			?>
			<a href="<?php echo esc_url( get_permalink( $loved_post_id ) ); ?>" title="<?php echo esc_attr( get_the_title( $loved_post_id ) ); ?>" >
				<?php echo get_the_post_thumbnail( $loved_post_id, 'postloop-img', array( 'class' => 'card-img-top' ) ); ?>
			</a>
			<?php
		}
		?>
			<div class="card-body">
				<h3 class="card-title"><?php echo esc_html( get_the_title( $loved_post_id ) ); ?></h3>
				<div class="card-meta text-muted">
					<?php
					$stations = get_the_terms( $loved_post_id, 'lez_stations' );
					if ( $stations && ! is_wp_error( $stations ) ) {
						echo get_the_term_list( $loved_post_id, 'lez_stations', '<strong>Network:</strong> ', ', ' ) . '<br />';
					}
					$airdates = get_post_meta( $loved_post_id, 'lezshows_airdates', true );
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
					<?php echo wp_kses_post( get_the_excerpt( $loved_post_id ) ); ?>
				</div>
			</div>
			<div class="card-footer">
				<a href="<?php echo esc_url( get_permalink( $loved_post_id ) ); ?>" class="btn btn-sm btn-outline-primary">
					Go to Show Profile <span class="screen-reader-text">About <?php echo esc_html( get_the_title( $loved_post_id ) ); ?></span>
				</a>
			</div>
		</div><!-- .card -->
		<?php
	}
}
