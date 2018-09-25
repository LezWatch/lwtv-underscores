<?php
/**
 * The template for displaying show CPT Archive Sidebar
 */

global $post;

$show_id = $post->ID;

// Do the math to make sure we're up to date.
if ( class_exists( 'LWTV_Shows_Calculate' ) ) {
	LWTV_Shows_Calculate::do_the_math( $show_id );
}

$thumb_rating = get_post_meta( $show_id, 'lezshows_worthit_rating', true );
$realness     = min( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ), 5 );
$quality      = min( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ), 5 );
$screentime   = min( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ), 5 );
?>

<section id="search" class="widget widget_search">
	<?php get_search_form(); ?>
</section>

<section id="ratings" class="widget widget_text">
	<div class="card">
		<div class="card-header">
			<h4>Is it Worth Watching?</h4>
		</div>

		<?php
		// If there's no rating, let's not show anything
		if ( null === $thumb_rating ) {
			echo '<p><em>Coming soon...</em></p>';
		} else {
			?>
			<div class="ratings-icons worthit-<?php echo esc_attr( lcfirst( $thumb_rating ) ); ?>">
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
					}

					$thumb_image = lwtv_yikes_symbolicons( $thumb_icon . '.svg', 'fa-' . $thumb_icon );
					echo '<span role="img" class="show-worthit ' . esc_attr( lcfirst( $thumb_rating ) ) . '">' . lwtv_sanitized( $thumb_image ) . '</span>';
					echo wp_kses_post( get_post_meta( $show_id, 'lezshows_worthit_rating', true ) );
					?>
				</div>
			</div>

			<div class="ratings-details">
				<div class="card-body">
					<?php
					if ( ( get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) ) {
						echo wp_kses_post( apply_filters( 'the_content', wp_kses_post( get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) ) );
					}

					echo '<strong>Show Score:</strong> ' . esc_html( round( get_post_meta( $show_id, 'lezshows_the_score', true ), 2 ) );
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
					$formats = get_the_terms( $show_id, 'lez_formats' );
					if ( $formats && ! is_wp_error( $formats ) ) {
						echo '<li class="list-group-item network formats">' . get_the_term_list( $show_id, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) . '</li>';
					}
					if ( get_post_meta( $show_id, 'lezshows_airdates', true ) ) {
						$airdates = get_post_meta( $show_id, 'lezshows_airdates', true );
						$airdate  = $airdates['start'] . ' - ' . $airdates['finish'];
						if ( $airdates['start'] === $airdates['finish'] ) {
							$airdate = $airdates['finish'];
						}
						if ( is_numeric( $airdates['finish'] ) && get_post_meta( $show_id, 'lezshows_seasons', true ) ) {
							$airdate .= ' (' . get_post_meta( $show_id, 'lezshows_seasons', true ) . ' seasons)';
						}

						echo '<li class="list-group-item network airdates"><strong>Airdates:</strong> ' . esc_html( $airdate ) . '</li>';
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
			<?php
		}
		?>
	</div>
</section>

<section id="affiliates" class="widget widget_text">
	<?php
	if ( class_exists( 'LWTV_Affilliates' ) ) {
		echo LWTV_Affilliates::shows( $show_id, 'widget' ); // WPCS: XSS okay
	}
	?>
</section>

<section id="ratings" class="widget widget_text">
	<div class="card">
		<div class="card-header">
			<h4>Tropes</h4>
		</div>
		<?php
		// get the tropes associated with this show
		$terms = get_the_terms( $show_id, 'lez_tropes' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			// If there are no terms, throw a message
			echo '<p><em>Coming soon...</em></p>';
		} else {
			echo '<ul class="trope-list list-group">';
			// loop over each returned trope
			foreach ( $terms as $term ) {
				?>
				<li class="list-group-item show trope trope-<?php echo esc_attr( $term->slug ); ?>">
					<a href="<?php echo esc_url( get_term_link( $term->slug, 'lez_tropes' ) ); ?>" rel="show trope">
					<?php
						// Echo the taxonomy icon (default to squares if empty)
						$icon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
						echo lwtv_yikes_symbolicons( $icon . '.svg', 'fa-lemon' );
					?>
					</a>
					<a href="<?php echo esc_url( get_term_link( $term->slug, 'lez_tropes' ) ); ?>" rel="show trope" class="trope-link"><?php echo esc_html( $term->name ); ?></a>
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
							echo lwtv_yikes_symbolicons( $icon . '.svg', 'fa-lemon' );
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
				$heart          = lwtv_yikes_symbolicons( 'heart.svg', 'fa-heart' );
				$positive_heart = '<span role="img" class="show-heart positive">' . $heart . '</span>';
				$negative_heart = '<span role="img" class="show-heart negative">' . $heart . '</span>';

				foreach ( $heart_types as $type ) {

					switch ( $type ) {
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
						<h3><?php echo esc_html( ucfirst( $type ) ); ?></h3>
						<?php
						if ( $rating >= '0' ) {
							$leftover = 5 - $rating;
							echo lwtv_sanitized( str_repeat( $positive_heart, $rating ) );
							echo lwtv_sanitized( str_repeat( $negative_heart, $leftover ) );
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
