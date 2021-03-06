<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Generate list of shows
// Usage: $shows_group
$all_shows   = lwtv_yikes_chardata( get_the_ID(), 'shows' );
$shows_group = array();

if ( '' !== $all_shows && is_array( $all_shows ) ) {
	foreach ( $all_shows as $each_show ) {
		$chartype = ( isset( $each_show['type'] ) ) ? $each_show['type'] . ' character' : '';

		// Years appears
		$appears = '';
		if ( isset( $each_show['appears'] ) && is_array( $each_show['appears'] ) ) {
			sort( $each_show['appears'] );
			$appears = ' - ' . implode( ', ', $each_show['appears'] );
		}

		// Link to Show
		$showlink = '';
		if ( isset( $each_show['show'] ) && '' !== $each_show['show'] ) {
			if ( get_post_status( $each_show['show'] ) !== 'publish' ) {
				$showlink = '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em>';
			} else {
				$showlink = '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em>';
			}
		}

		// Output ex: Legends of Tomorrow (regular character)
		$shows_group[] = $showlink . ' <small>(' . $chartype . $appears . ')</small>';
	}
} else {
	$shows_group[] = 'None';
}

// Generate actors
// Usage: $the_actors
$all_actors = lwtv_yikes_chardata( get_the_ID(), 'actors' );
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
		if ( lwtv_yikes_is_queer( $each_actor ) ) {
			$this_actor .= ' <span role="img" aria-label="Queer IRL Actor" data-toggle="tooltip" title="Queer IRL Actor" class="character-cliche queer-irl">' . lwtv_symbolicons( 'rainbow.svg', 'fa-cloud' ) . '</span>';
		}
		$the_actors[] = $this_actor;
	}
} else {
	$all_actors = array( 'none' );
}

if ( empty( $the_actors ) && has_term( 'cartoon', 'lez_cliches', get_the_ID() ) ) {
	$the_actors = array( 'None' );
} else {
	$the_actors = ( empty( $the_actors ) ) ? array( '<a href="/actor/unknown/">Unknown</a>' ) : $the_actors;
}

// Generate Status
// Usage: $doa_status
$doa_status = ( has_term( 'dead', 'lez_cliches', get_the_ID() ) ) ? 'Dead' : 'Alive';

// Generate RIP
// Usage: $rip
if ( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) ) {
	$character_death = get_post_meta( get_the_ID(), 'lezchars_death_year', true );
	if ( ! is_array( $character_death ) ) {
		$character_death = array( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) );
	}
	$rip = array();

	foreach ( $character_death as $death ) {
		if ( '/' !== substr( $death, 2, 1 ) ) {
			$date = date_format( date_create_from_format( 'Y-m-d', $death ), 'd F Y' );
		} else {
			$date = date_format( date_create_from_format( 'm/d/Y', $death ), 'F d, Y' );
		}
		$rip[] = $date;
	}
}

// Generate list of Cliches
// Usage: $cliches
$cliches = lwtv_yikes_chardata( get_the_ID(), 'cliches' );

// Generate Gender & Sexuality & Romantic Data
// Usage: $gender_sexuality
$gender_sexuality = lwtv_yikes_chardata( get_the_ID(), 'gender' ) . ' &bull; ' . lwtv_yikes_chardata( get_the_ID(), 'sexuality' );
if ( ! is_null( lwtv_yikes_chardata( get_the_ID(), 'romantic' ) ) && lwtv_yikes_chardata( get_the_ID(), 'romantic' ) !== '' ) {
	$gender_sexuality .= ' &bull; ' . lwtv_yikes_chardata( get_the_ID(), 'romantic' );
}

// Microformats Fix
lwtv_microformats_fix( $post->ID );

// Thumbnail attribution
$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;
$thumb_array       = array(
	'class' => 'single-char-img rounded float-left',
	'alt'   => get_the_title(),
	'title' => $thumb_title,
);
?>
<div class="card-body">

	<?php the_post_thumbnail( 'character-img', $thumb_array ); ?>

	<div class="card-meta">
		<div class="card-meta-item">
			<table class="table table-sm" style="width: auto !important;">
				<tbody>
					<tr>
						<th scope="row" colspan="2"><center><?php echo wp_kses_post( $gender_sexuality ); ?></center></th>
					</tr>
					<tr>
						<th scope="row">Clichés</th>
						<td><?php echo $cliches; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					</tr>
					<tr>
						<th scope="row">Status</th>
						<td><?php echo wp_kses_post( $doa_status ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo wp_kses_post( _n( 'Actor', 'Actors', count( $all_actors ) ) ); ?></th>
						<td>&bull; <?php echo implode( '<br />&bull; ', $the_actors ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo wp_kses_post( _n( 'Show', 'Shows', count( $shows_group ) ) ); ?></th>
						<td>&bull; <?php echo wp_kses_post( implode( '<br />&bull; ', $shows_group ) ); ?></td>
					</tr>
					<?php
					if ( isset( $rip ) ) {
						?>
						<tr>
							<th scope="row">RIP</th>
							<td><?php echo wp_kses_post( implode( ' &bull; ', $rip ) ); ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="characters-description">
		<hr />
		<?php echo wp_kses_post( the_content() ); ?>
	</div>
</div>
