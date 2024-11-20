<?php
/**
 * Template part for displaying the actors who've played this character
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$character = $args['character'] ?? null;
$format    = $args['format'] ?? 'actors';

if ( ! $character ) {
	return;
}

$all_actors = lwtv_plugin()->get_character_data( $character, $format );

if ( 'oneactor' === $format ) {
	echo $all_actors; // phpcs:ignore WordPress.Security.EscapeOutput
}

if ( 'actors' === $format ) {
	$the_actors = array();

	if ( '' !== $all_actors ) {
		foreach ( $all_actors as $each_actor ) {
			if ( get_post_status( $each_actor ) === 'private' ) {
				if ( is_user_logged_in() ) {
					$this_actor = '<a href="' . get_permalink( $each_actor ) . '">' . get_the_title( $each_actor ) . ' - UNLISTED</a>';
				} else {
					$this_actor = '<a href="/actor/unknown/">Unknown</a>';
				}
			} elseif ( get_post_status( $each_actor ) !== 'publish' ) {
				$this_actor = '<span class="disabled-show-link">' . get_the_title( $each_actor ) . '</span>';
			} else {
				$this_actor = '<a href="' . get_permalink( $each_actor ) . '">' . get_the_title( $each_actor ) . '</a>';
			}

			$the_actors[ $each_actor ] = array(
				'link'  => $this_actor,
				'name'  => get_the_title( $each_actor ),
				'queer' => false,
			);

			if ( lwtv_plugin()->is_actor_queer( $each_actor ) ) {
				$the_actors[ $each_actor ]['queer'] = ' <span role="img" aria-label="Queer IRL Actor" data-bs-target="tooltip" title="Queer IRL Actor" class="character-cliche queer-irl">' . lwtv_plugin()->get_symbolicon( svg: 'rainbow.svg', fontawesome: 'fa-cloud' ) . '</span>';
			}
		}
	} else {
		$the_actors[0] = array(
			'link'  => '',
			'name'  => 'None',
			'queer' => false,
		);
	}

	// If there are no actors, and it's a cartoon, show that. Else show unknown.
	if ( empty( $the_actors ) && has_term( 'cartoon', 'lez_cliches', $character ) ) {
		$the_actors[0] = array(
			'link'  => '',
			'name'  => 'None',
			'queer' => false,
		);
	} elseif ( empty( $the_actors ) ) {
		$the_actors[0] = array(
			'link'  => '<a href="/actor/unknown/">Unknown</a>',
			'name'  => 'Unknown',
			'queer' => false,
		);
	}
	?>

	<th scope="row"><?php echo wp_kses_post( _n( 'Actor', 'Actors', count( $the_actors ) ) ); ?></th>
	<td>
	<?php
	// If we have two or fewer actors, just list them.
	if ( 2 >= count( $the_actors ) ) {
		foreach ( $the_actors as $actor_id => $actor ) {
			echo '&bull; ';
			if ( ! empty( $actor['link'] ) ) {
				echo wp_kses_post( $actor['link'] );
			} else {
				echo esc_html( $actor['name'] );
			}

			if ( false !== $actor['queer'] ) {
				echo $actor['queer']; // phpcs:ignore WordPress.Security.EscapeOutput
			}

			echo '</br>';
		}
	} else {
		?>
		<div class="accordion accordion-flush" id="characterActors">
			<div class="accordion-item">
				<h6 class="accordion-header">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseActors" aria-expanded="true" aria-controls="collapseActors">
					<?php
					$first_actor = current( $the_actors );
					echo wp_kses_post( $first_actor['name'] ) . ' and ' . esc_html( count( $the_actors ) - 1 ) . ' more...';
					?>
					</button>
				</h6>

				<div id="flush-collapseActors" class="accordion-collapse collapse" data-bs-parent="#characterActors">
					<div class="accordion-body">
					<?php
					foreach ( $the_actors as $actor_id => $actor ) {
						echo '&bull; ';

						if ( ! empty( $actor['link'] ) ) {
							echo wp_kses_post( $actor['link'] );
						} else {
							echo esc_html( $actor['name'] );
						}

						if ( false !== $actor['queer'] ) {
							echo $actor['queer']; // phpcs:ignore WordPress.Security.EscapeOutput
						}

						echo '</br>';
					}
					?>
					</div>
			</div>
		</div>
		<?php
	}
}
