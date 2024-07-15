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
				get_template_part( 'template-parts/partials/shows/airdates', '', array( 'show_id' => $show_id ) );

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
