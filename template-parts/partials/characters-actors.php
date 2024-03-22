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
			if ( lwtv_plugin()->is_actor_queer( $each_actor ) ) {
				$this_actor .= ' <span role="img" aria-label="Queer IRL Actor" data-bs-target="tooltip" title="Queer IRL Actor" class="character-cliche queer-irl">' . lwtv_symbolicons( 'rainbow.svg', 'fa-cloud' ) . '</span>';
			}
			$the_actors[] = $this_actor;
		}
	} else {
		$all_actors = array( 'none' );
	}

	if ( empty( $the_actors ) && has_term( 'cartoon', 'lez_cliches', $character ) ) {
		$the_actors = array( 'None' );
	} else {
		$the_actors = ( empty( $the_actors ) ) ? array( '<a href="/actor/unknown/">Unknown</a>' ) : $the_actors;
	}
	?>

	<th scope="row"><?php echo wp_kses_post( _n( 'Actor', 'Actors', count( $all_actors ) ) ); ?></th>
	<td>&bull; <?php echo implode( '<br />&bull; ', $the_actors ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
	<?php
}
