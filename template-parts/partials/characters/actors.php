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
				$the_actors[ $each_actor ]['queer'] = ' <span role="img" aria-label="Queer IRL Actor" data-bs-target="tooltip" title="Queer IRL Actor" class="character-cliche queer-irl">' . lwtv_plugin()->get_symbolicon( 'rainbow.svg', 'fa-cloud' ) . '</span>';
			}
		}
	} else {
		$all_actors = array( 'none' );
	}

	// If there are no actors, and it's a cartoon, show that. Else show unknown.
	if ( empty( $the_actors ) && has_term( 'cartoon', 'lez_cliches', $character ) ) {
		$the_actors = array( 'None' );
	} else {
		$the_actors = ( empty( $the_actors ) ) ? array( '<a href="/actor/unknown/">Unknown</a>' ) : $the_actors;
	}
	?>

	<th scope="row"><?php echo wp_kses_post( _n( 'Actor', 'Actors', count( $the_actors ) ) ); ?></th>
	<td>
	<?php
	// If we have two or fewer shows, just list them.
	if ( 2 >= count( $the_actors ) ) {
		foreach ( $the_actors as $actor_id => $actor ) {
			echo '&bull; ' . wp_kses_post( $actor['link'] );
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
						if ( 'None' === $actor ) {
							echo 'None';
							continue;
						}
						echo '&bull; ' . wp_kses_post( $actor['link'] ) . ' ' . $actor['queer'] . '<br />'; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
					</div>
			</div>
		</div>
		<?php
	}
}
