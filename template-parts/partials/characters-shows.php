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
		$shows_group[] = array(
			'name'    => get_the_title( $each_show['show'] ),
			'link'    => $show_link,
			'type'    => $char_type,
			'appears' => $appears,
		);
	}
} else {
	$shows_group[] = 'TBD';
}

?>

<th scope="row"><?php echo wp_kses_post( _n( 'Show', 'Shows', count( $shows_group ) ) ); ?></th>
<td>
<?php
// If we have two or fewer shows, just list them.
if ( 2 >= count( $shows_group ) ) {
	foreach ( $shows_group as $show ) {
		if ( 'TBD' === $show ) {
			echo 'TBD';
			continue;
		}
		echo '&bull; ' . wp_kses_post( $show['link'] ) . ' <small>( ' . esc_html( $show['type'] ) . ' ' . esc_html( $show['appears'] ) . ')</small><br />';
	}
} else {
	?>
	<div class="accordion accordion-flush" id="characterShows">
		<div class="accordion-item">
			<h6 class="accordion-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseShows" aria-expanded="true" aria-controls="collapseShows">
				<?php echo wp_kses_post( $shows_group[0]['name'] ); ?> and <?php echo esc_html( count( $shows_group ) - 1 ); ?> more...
				</button>
			</h6>

			<div id="flush-collapseShows" class="accordion-collapse collapse" data-bs-parent="#characterShows">
				<div class="accordion-body">
				<?php
				foreach ( $shows_group as $show ) {
					if ( 'TBD' === $show ) {
						echo 'TBD';
						continue;
					}
					echo '&bull; ' . wp_kses_post( $show['link'] ) . ' <small>( ' . esc_html( $show['type'] ) . ' ' . esc_html( $show['appears'] ) . ')</small><br />';
				}
				?>
				</div>
		</div>
	</div>
	<?php
}
?>
</td>
