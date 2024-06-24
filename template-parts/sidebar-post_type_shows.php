<?php
/**
 * The template for displaying show CPT Archive Sidebar
 *
 * @package LezWatch.TV
 */

$show_id = $args['the_post_id'] ?? null;

if ( is_null( $show_id ) || empty( $show_id ) ) {
	return;
}

$thumb_rating = ( get_post_meta( $show_id, 'lezshows_worthit_rating', true ) ) ? get_post_meta( $show_id, 'lezshows_worthit_rating', true ) : 'TBD';
$realness     = ( get_post_meta( $show_id, 'lezshows_realness_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ), 5 ) : 0;
$quality      = ( get_post_meta( $show_id, 'lezshows_quality_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ), 5 ) : 0;
$screentime   = ( get_post_meta( $show_id, 'lezshows_screentime_rating', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ) ) ) ? min( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ), 5 ) : 0;
$alt_names    = ( get_post_meta( $show_id, 'lezshows_show_names', true ) ) ? get_post_meta( $show_id, 'lezshows_show_names', true ) : false;

?>

<section id="search" class="widget widget_search">
	<?php get_search_form(); ?>
</section>

<section id="suggest-edits" class="widget widget_suggestedits">
	<?php get_template_part( 'template-parts/overlays/form', 'suggest-edit', array( 'for_post' => $show_id ) ); ?>
</section>

<?php
lwtv_plugin()->get_admin_tools( $show_id );
?>

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
				echo '<span role="img" class="show-worthit ' . esc_attr( strtolower( $thumb_rating ) ) . '" aria-label="This show has an overall review of ' . esc_attr( $thumb_rating ) . ' ">' . $thumb_image . '</span>';
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
				$scores = lwtv_plugin()->get_all_scores( $show_id );
				if ( isset( $scores ) ) {
					echo '<center><h4>Show Scores</h4></center>';
					lwtv_plugin()->display_scores( $scores );
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
					lwtv_plugin()->get_tvmaze_episodes( $show_id );
				}

				$formats = get_the_terms( $show_id, 'lez_formats' );
				if ( $formats && ! is_wp_error( $formats ) ) {
					echo '<li class="list-group-item network formats">' . get_the_term_list( $show_id, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) . '</li>';
				}

				// Airdates:
				get_template_part( 'template-parts/partials/shows', 'airdates', array( 'show_id' => $show_id ) );

				$genres = get_the_terms( $show_id, 'lez_genres' );
				if ( $genres && ! is_wp_error( $genres ) ) {
					echo '<li class="list-group-item network genres">' . get_the_term_list( $show_id, 'lez_genres', '<strong>Genres:</strong> ', ', ' ) . '</li>';
				}
				if ( get_post_meta( $show_id, 'lezshows_imdb', true ) ) {
					$imdb = 'https://www.imdb.com/title/' . get_post_meta( $show_id, 'lezshows_imdb', true );
					echo '<li class="list-group-item network imdb text-center">' . lwtv_symbolicons( 'imdb.svg', 'fa-imdb' ) . ' <a href="' . esc_url( $imdb ) . '">IMDb</a></li>';
				}
				?>
			</ul>
		</div>
	</div>
</section>

<?php
if ( false !== $alt_names && ! empty( $alt_names ) ) {
	?>
	<section id="alt-names" class="widget widget_text">
		<div class="card">
			<div class="card-header">
				<h4>Also Known As</h4>
			</div>

			<ul class="name-list list-group">
				<?php
				foreach ( $alt_names as $aka ) {
					?>
						<li class="list-group-item show name lang-<?php echo esc_attr( $aka['type'] ); ?>">
							<em><?php echo esc_html( $aka['lezshows_alt_show_name'] ); ?></em> (<?php echo esc_attr( $aka['type'] ); ?>)
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

<section id="tropes" class="widget widget_text">
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
					<a href="<?php echo esc_url( get_term_link( $trope->slug, 'lez_tropes' ) ); ?>" rel="show trope" aria-label="Read more about the trope <?php echo esc_attr( $trope->name ); ?>.">
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
				<ul class="intersectionality-list list-group">
					<?php
					// loop over each returned trope
					foreach ( $intersections as $intersection ) {
						?>
						<li class="list-group-item show intersection intersection-<?php echo esc_attr( $intersection->slug ); ?>">
							<a href="<?php echo esc_url( get_term_link( $intersection->slug, 'lez_intersections' ) ); ?>" rel="show intersection" aria-label="Read more about the postivie intersectionality representation of <?php echo esc_attr( $intersection->name ); ?>.">
							<?php
							// Echo the taxonomy icon (default to squares if empty)
							$icon = get_term_meta( $intersection->term_id, 'lez_termsmeta_icon', true );
							echo lwtv_symbolicons( $icon . '.svg', 'fa-lemon' );
							?>
							</a>
							<a href="<?php echo esc_url( get_term_link( $intersection->slug, 'lez_intersections' ) ); ?>" rel="show intersection" class="intersection-link"><?php echo esc_html( $intersection->name ); ?>
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

<?php
get_template_part( 'template-parts/partials/sidebar', 'slack' );
