<?php
/**
 * Template part for displaying the shows the character has been on.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$character = $args['character'] ?? null;


// Generate list of shows
// Usage: $shows_group
$all_shows   = lwtv_plugin()->get_character_data( $character, 'shows' );
$shows_group = array();

if ( '' !== $all_shows && is_array( $all_shows ) ) {
	foreach ( $all_shows as $each_show ) {
		$char_type = ( isset( $each_show['type'] ) ) ? $each_show['type'] . ' character' : '';

		// Years appears
		$appears = '';
		if ( isset( $each_show['appears'] ) && is_array( $each_show['appears'] ) ) {
			sort( $each_show['appears'] );
			$appears = ' - ' . implode( ', ', $each_show['appears'] );
		}

		// Link to Show
		$show_link = '';
		if ( isset( $each_show['show'] ) && '' !== $each_show['show'] ) {

			// If it's an array, de-array it.
			if ( is_array( $each_show['show'] ) ) {
				$each_show['show'] = reset( $each_show['show'] );
			}
			if ( get_post_status( $each_show['show'] ) !== 'publish' ) {
				$show_link = '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em>';
			} else {
				$show_link = '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em>';
			}
		}

		// Output ex: Legends of Tomorrow (regular character)
		$shows_group[] = $show_link . ' <small>(' . $char_type . $appears . ')</small>';
	}
} else {
	$shows_group[] = 'None';
}
?>

<th scope="row"><?php echo wp_kses_post( _n( 'Show', 'Shows', count( $shows_group ) ) ); ?></th>
<td>&bull; <?php echo wp_kses_post( implode( '<br />&bull; ', $shows_group ) ); ?></td>
