<?php
/**
 * Template part for displaying a show's ratings
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
if ( ! $show_id ) {
	return;
}

$realness   = ( get_post_meta( $show_id, 'lezshows_realness_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ), 5 ) : 0;
$quality    = ( get_post_meta( $show_id, 'lezshows_quality_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ), 5 ) : 0;
$screentime = ( get_post_meta( $show_id, 'lezshows_screentime_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ), 5 ) : 0;

?>

<section id="ratings" class="widget widget_ratings">
	<div class="card">
		<div class="card-header">
			<h4>Ratings</h4>
		</div>
		<div class="card-body">
			<?php
			// Bail if not set
			if ( ! $realness && ! $quality && ! $screentime ) {
				echo '<p><em>Coming soon...</em></p>';
			} else {
				// We have some love, let's show it
				$heart_types    = array( 'realness', 'quality', 'screentime' );
				$heart          = lwtv_symbolicons( 'heart.svg', 'fa-heart' );
				$positive_heart = '<span role="img" class="show-heart positive">' . $heart . '</span>';
				$negative_heart = '<span role="img" class="show-heart negative">' . $heart . '</span>';

				foreach ( $heart_types as $heart ) {

					switch ( $heart ) {
						case 'realness':
							$rating = $realness;
							$detail = 'lezshows_realness_details';
							break;
						case 'quality':
							$rating = $quality;
							$detail = 'lezshows_quality_details';
							break;
						case 'screentime':
							$rating = $screentime;
							$detail = 'lezshows_screentime_details';
							break;
					}

					?>
					<div class="ratings-icons">
						<h3><?php echo esc_html( ucfirst( $heart ) ); ?></h3>
						<?php
						if ( $rating >= '0' ) {
							$leftover = 5 - $rating;

							// phpcs:ignore WordPress.Security.EscapeOutput -- We're outputting SVGs
							echo str_repeat( $positive_heart, $rating );

							// phpcs:ignore WordPress.Security.EscapeOutput -- We're outputting SVGs
							echo str_repeat( $negative_heart, $leftover );
						}
						?>
						<span class="screen-reader-text">Rating: <?php echo (int) $rating; ?> Hearts (out of 5)</span>
					</div>
					<?php
					if ( ( get_post_meta( $show_id, $detail, true ) ) ) {
						echo wp_kses_post( apply_filters( 'the_content', get_post_meta( $show_id, $detail, true ) ) );
					}
				}
			}
			?>
		</div>
	</div>
</section>
