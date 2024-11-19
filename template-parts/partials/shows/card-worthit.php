<?php
/**
 * Template part for if a show is worth watching
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
if ( ! $show_id ) {
	return;
}

$thumb_rating = ( get_post_meta( $show_id, 'lezshows_worthit_rating', true ) ) ? get_post_meta( $show_id, 'lezshows_worthit_rating', true ) : 'TBD';
?>

<section id="worthit" class="widget widget_worthit">
	<div class="card">
		<div class="card-header">
			<h4>Is it Worth Watching?</h4>
		</div>

		<div class="worthit-icons worthit-<?php echo esc_attr( strtolower( $thumb_rating ) ); ?>">
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

				$thumb_image = lwtv_plugin()->get_symbolicon( $thumb_icon . '.svg', 'fa-' . $thumb_icon );
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo '<span role="img" class="show-worthit ' . esc_attr( strtolower( $thumb_rating ) ) . '" aria-label="This show has an overall review of ' . esc_attr( $thumb_rating ) . ' " style="max-width: 50px; max-height: 50px">' . $thumb_image . '</span>';
				echo wp_kses_post( $thumb_rating );
				?>
			</div>
		</div>

		<div class="ratings-details">
			<div class="card-body">
				<?php
				// Worthit Review:
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

			<ul class="network-list list-group">
				<?php
				// Networks/Stations:
				$stations = get_the_terms( $show_id, 'lez_stations' );
				if ( $stations && ! is_wp_error( $stations ) ) {
					echo '<li class="list-group-item network names">' . get_the_term_list( $show_id, 'lez_stations', '<strong>Airs On:</strong> ', ', ' ) . '</li>';
				}

				// Countries:
				$countries = get_the_terms( $show_id, 'lez_country' );
				if ( $countries && ! is_wp_error( $countries ) ) {
					echo '<li class="list-group-item network country">' . get_the_term_list( $show_id, 'lez_country', '<strong>Airs In:</strong> ', ', ' ) . '</li>';
				}

				// If the show is on air, we'll see when it airs next!
				$on_air = get_post_meta( $show_id, 'lezshows_on_air', true );
				if ( 'yes' === $on_air ) {
					lwtv_plugin()->get_tvmaze_episodes( $show_id );
				}

				// Formats:
				$formats = get_the_terms( $show_id, 'lez_formats' );
				if ( $formats && ! is_wp_error( $formats ) ) {
					echo '<li class="list-group-item formats">' . get_the_term_list( $show_id, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) . '</li>';
				}

				// Airdates:
				get_template_part( 'template-parts/partials/shows/airdates', '', array( 'show_id' => $show_id ) );

				// Genres:
				$genres = get_the_terms( $show_id, 'lez_genres' );
				if ( $genres && ! is_wp_error( $genres ) ) {
					echo '<li class="list-group-item genres">' . get_the_term_list( $show_id, 'lez_genres', '<strong>Genres:</strong> ', ', ' ) . '</li>';
				}

				// Remote URLs
				$imdb_id = get_post_meta( $show_id, 'lezshows_imdb', true );
				$tmdb_id = get_post_meta( $show_id, 'lezshows_tmdb_id', true );
				if ( $imdb_id || $tmdb_id ) {
					echo '<li class="list-group-item imdb text-center">';

					if ( $imdb_id ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo lwtv_plugin()->get_symbolicon( svg: 'imdb.svg', fontawesome: 'fa-imdb', max_size: '20' );
						echo '&nbsp;<a href="' . esc_url( 'https://www.imdb.com/title/' . $imdb_id ) . '">IMDb</a>';
					}

					if ( $tmdb_id ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo lwtv_plugin()->get_symbolicon( svg: 'tmdb.svg', fontawesome: 'fa-grip-lines', max_size: '20' );
						echo '&nbsp;<a href="' . esc_url( 'https://www.themoviedb.org/tv/' . $tmdb_id ) . '">TMDB</a>';
					}

					echo '</li>';
				}
				?>
			</ul>
		</div>
	</div>
</section>
