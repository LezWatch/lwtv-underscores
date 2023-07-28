<?php
/**
 * The template for displaying show CPT Archive Sidebar
 *
 * @package LezWatch.TV
 */

global $post;

$show_id      = $post->ID;
$thumb_rating = ( get_post_meta( $show_id, 'lezshows_worthit_rating', true ) ) ? get_post_meta( $show_id, 'lezshows_worthit_rating', true ) : 'TBD';
$realness     = ( get_post_meta( $show_id, 'lezshows_realness_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ), 5 ) : 0;
$quality      = ( get_post_meta( $show_id, 'lezshows_quality_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ), 5 ) : 0;
$screentime   = ( get_post_meta( $show_id, 'lezshows_screentime_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ), 5 ) : 0;
?>

<section id="search" class="widget widget_search">
	<?php get_search_form(); ?>
</section>

<section id="suggest-edits" class="widget widget_suggestedits">
	<?php get_template_part( 'template-parts/suggestedit', 'form' ); ?>
</section>

<section id="ratings" class="widget widget_text">
	<div class="card">
		<div class="card-header">
			<h4>Is it Worth Watching?</h4>
		</div>

		<div class="ratings-icons worthit-<?php echo esc_attr( strtolower( $thumb_rating ) ); ?>">
			<div class="worthit">
				<?php
				switch ( $thumb_rating ) {
					case 'Yes':
						$thumb_icon = 'thumbs-up';
						break;
					case 'Meh':
						$thumb_icon = 'meh';
						break;
					case 'No':
						$thumb_icon = 'thumbs-down';
						break;
					case 'TBD':
						$thumb_icon = 'clock-retro';
						break;
				}

				$thumb_image = lwtv_symbolicons( $thumb_icon . '.svg', 'fa-' . $thumb_icon );
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo '<span role="img" class="show-worthit ' . esc_attr( strtolower( $thumb_rating ) ) . '">' . $thumb_image . '</span>';
				echo wp_kses_post( $thumb_rating );
				?>
			</div>
		</div>

		<div class="ratings-details">
			<div class="card-body">
				<?php
				if ( ( get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) && 'TBD' !== $thumb_rating ) {
					echo wp_kses_post( apply_filters( 'the_content', get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) );
				} else {
					echo wp_kses_post( '<p><em>This show has not yet been reviewed. Have you seen it? Please <a href="/about/contact/">let us know</a>.</em></p>' );
				}

				// Collect all the scores.
				$scores = ( new LWTV_Grading() )->all_scores( $show_id );
				if ( isset( $scores ) ) {
					echo '<center><h4>Show Scores</h4></center>';
					( new LWTV_Grading() )->display( $scores );
				}
				?>
			</div>

			<ul class="network-data list-group">
				<?php
				$stations = get_the_terms( $show_id, 'lez_stations' );
				if ( $stations && ! is_wp_error( $stations ) ) {
					echo '<li class="list-group-item network names">' . get_the_term_list( $show_id, 'lez_stations', '<strong>Airs On:</strong> ', ', ' ) . '</li>';
				}
				$countries = get_the_terms( $show_id, 'lez_country' );
				if ( $countries && ! is_wp_error( $countries ) ) {
					echo '<li class="list-group-item network country">' . get_the_term_list( $show_id, 'lez_country', '<strong>Airs In:</strong> ', ', ' ) . '</li>';
				}

				// If the show is on air, we'll see when it airs next!
				$on_air = get_post_meta( $show_id, 'lezshows_on_air', true );
				if ( 'yes' === $on_air ) {
					( new LWTV_External_APIs() )->tvmaze_episodes( $show_id );
				}

				$formats = get_the_terms( $show_id, 'lez_formats' );
				if ( $formats && ! is_wp_error( $formats ) ) {
					echo '<li class="list-group-item network formats">' . get_the_term_list( $show_id, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) . '</li>';
				}

				if ( get_post_meta( $show_id, 'lezshows_airdates', true ) ) {
					$airdates  = get_post_meta( $show_id, 'lezshows_airdates', true );
					$air_title = 'Airdate';

					// If the start is 'current' make it this year (though it really never should be.)
					if ( 'current' === $airdates['start'] ) {
						$airdates['start'] = gmdate( 'Y' );
					}

					// Link the year to the year.
					$airdate = '<a href="/this-year/' . $airdates['start'] . '/shows-on-air/">' . $airdates['start'] . '</a>';

					// If the start and end date are NOT the same, then let's show the end.
					if ( $airdates['finish'] && $airdates['start'] !== $airdates['finish'] ) {
						// If the end date is a number, it's a year, so link it.
						if ( is_numeric( $airdates['finish'] ) && $airdates['finish'] <= gmdate( 'Y' ) ) {
							$airdates['finish'] = '<a href="/this-year/' . $airdates['finish'] . '/shows-on-air/">' . $airdates['finish'] . '</a>';
						}
						// No matter what, add it.
						$airdate  .= ' - ' . $airdates['finish'];
						$air_title = 'Airdates';
					}

					// If the end is a number (i.e. a year) AND we have a seasons value,
					// and it's NOT a TV movie, show it.
					$season_count = get_post_meta( $show_id, 'lezshows_seasons', true );
					if ( ! has_term( 'movie', 'lez_formats' ) && 'current' !== $airdates['finish'] && isset( $season_count ) && $season_count >= 1 ) {
							$seasons  = _n( 'season', 'seasons', $season_count );
							$airdate .= ' (' . $season_count . ' ' . $seasons . ')';
					}
					echo '<li class="list-group-item network airdates"><strong>' . wp_kses_post( $air_title ) . ':</strong> ' . wp_kses_post( $airdate ) . '</li>';
				}
				$genres = get_the_terms( $show_id, 'lez_genres' );
				if ( $genres && ! is_wp_error( $genres ) ) {
					echo '<li class="list-group-item network genres">' . get_the_term_list( $show_id, 'lez_genres', '<strong>Genres:</strong> ', ', ' ) . '</li>';
				}
				if ( get_post_meta( $show_id, 'lezshows_imdb', true ) ) {
					$imdb = 'https://www.imdb.com/title/' . get_post_meta( $show_id, 'lezshows_imdb', true );
					echo '<li class="list-group-item network imdb"><a href="' . esc_url( $imdb ) . '">IMDb</a></li>';
				}
				?>
			</ul>
		</div>
	</div>
</section>

<section id="ratings" class="widget widget_text">
	<div class="card">
		<div class="card-header">
			<h4>Tropes</h4>
		</div>
		<?php
		// get the tropes associated with this show
		$tropes = get_the_terms( $show_id, 'lez_tropes' );

		if ( ! $tropes || is_wp_error( $tropes ) ) {
			// If there are no terms, throw a message
			echo '<p><em>Coming soon...</em></p>';
		} else {
			echo '<ul class="trope-list list-group">';
			// loop over each returned trope
			foreach ( $tropes as $trope ) {
				?>
				<li class="list-group-item show trope trope-<?php echo esc_attr( $trope->slug ); ?>">
					<a href="<?php echo esc_url( get_term_link( $trope->slug, 'lez_tropes' ) ); ?>" rel="show trope">
					<?php
						// Echo the taxonomy icon (default to squares if empty)
						$icon = get_term_meta( $trope->term_id, 'lez_termsmeta_icon', true );
						echo lwtv_symbolicons( $icon . '.svg', 'fa-lemon' );
					?>
					</a>
					<a href="<?php echo esc_url( get_term_link( $trope->slug, 'lez_tropes' ) ); ?>" rel="show trope" class="trope-link"><?php echo esc_html( $trope->name ); ?></a>
				</li>
				<?php
			}
			echo '</ul>';
		}
		?>
	</div>
</section>

<?php
// Intersectionality
$intersections = get_the_terms( $show_id, 'lez_intersections' );
if ( $intersections && ! is_wp_error( $intersections ) ) {
	?>
	<section id="ratings" class="widget widget_text">
		<div class="card">
			<div class="card-header">
				<h4>Intersectionality</h4>
			</div>
				<ul class="trope-list list-group">
					<?php
					// loop over each returned trope
					foreach ( $intersections as $intersection ) {
						?>
						<li class="list-group-item show trope trope-<?php echo esc_attr( $intersection->slug ); ?>">
							<a href="<?php echo esc_url( get_term_link( $intersection->slug, 'lez_intersections' ) ); ?>" rel="show trope">
							<?php
							// Echo the taxonomy icon (default to squares if empty)
							$icon = get_term_meta( $intersection->term_id, 'lez_termsmeta_icon', true );
							echo lwtv_symbolicons( $icon . '.svg', 'fa-lemon' );
							?>
							</a>
							<a href="<?php echo esc_url( get_term_link( $intersection->slug, 'lez_intersections' ) ); ?>" rel="show trope" class="trope-link"><?php echo esc_html( $intersection->name ); ?>
							</a>
						</li>
						<?php
					}
					?>
				</ul>
		</div>
	</section>
	<?php
}
?>

<section id="ratings" class="widget widget_text">
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
							// phpcs:ignore WordPress.Security.EscapeOutput
							echo str_repeat( $positive_heart, $rating );
							// phpcs:ignore WordPress.Security.EscapeOutput
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

