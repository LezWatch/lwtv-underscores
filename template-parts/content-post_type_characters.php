<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Generate list of shows
// Usage: $appears
$all_shows = lwtv_yikes_chardata( get_the_ID(), 'shows' );
if ( '' !== $all_shows ) {
	$show_title = array();
	foreach ( $all_shows as $each_show ) {
		$chartype = $each_show['type'] . ' character';
		if ( isset( $each_show['appears'] ) && is_array( $each_show['appears'] ) ) {
			sort( $each_show['appears'] );
			$appears = ' - ' . implode( ', ', $each_show['appears'] );
		} else {
			$appears = '';
		}

		if ( get_post_status( $each_show['show'] ) !== 'publish' ) {
			$showlink = '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em>';
		} else {
			$showlink = '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em>';
		}

		array_push( $show_title, $showlink . ' (' . $chartype . $appears . ')' );
	}
}

$on_title = 'Shows:';
$on_shows = ' None';
if ( isset( $show_title ) && count( $show_title ) !== 0 ) {
	$on_shows = '<br />';
	$on_title = _n( 'Show:', 'Shows:', count( $show_title ) );
	foreach ( $show_title as $a_title ) {
		$on_shows .= '&bull;' . $a_title . '<br />';
	}
}
$appears = '<strong>' . $on_title . '</strong> ' . $on_shows;

// Generate actors
// Usage: $actors
$all_actors = lwtv_yikes_chardata( get_the_ID(), 'actors' );
$the_actors = array();

if ( '' !== $all_actors ) {
	foreach ( $all_actors as $each_actor ) {
		if ( get_post_status( $each_actor ) === 'private' ) {
			if ( current_user_can( 'author' ) ) {
				array_push( $the_actors, '<a href="' . get_permalink( $each_actor ) . '">' . get_the_title( $each_actor ) . ' - PRIVATE/UNLISTED</a>' );
			} else {
				array_push( $the_actors, '<a href="/actor/unknown/">Unknown</a>' );
			}
		} elseif ( get_post_status( $each_actor ) !== 'publish' ) {
			array_push( $the_actors, '<span class="disabled-show-link">' . get_the_title( $each_actor ) . '</span>' );
		} else {
			array_push( $the_actors, '<a href="' . get_permalink( $each_actor ) . '">' . get_the_title( $each_actor ) . '</a>' );
		}
	}
}

$is_actors = ( empty( $the_actors ) ) ? ' <a href="/actor/unknown/">Unknown</a>' : ': ' . implode( ', ', $the_actors );
if ( isset( $the_actors ) && count( $the_actors ) !== 0 ) {
	$actor_title = _n( 'Actor', 'Actors', count( $all_actors ) );
	$actors      = '<strong>' . $actor_title . '</strong>' . $is_actors;
}

// Generate Status
// Usage: $dead_or_alive
$doa_status    = ( has_term( 'dead', 'lez_cliches', get_the_ID() ) ) ? 'Dead' : 'Alive';
$dead_or_alive = '<strong>Status:</strong> ' . $doa_status;

// Generate RIP
// Usage: $rip
if ( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) ) {
	$character_death = get_post_meta( get_the_ID(), 'lezchars_death_year', true );
	if ( ! is_array( $character_death ) ) {
		$character_death = array( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) );
	}
	$echo_death = array();

	foreach ( $character_death as $death ) {
		if ( '/' !== substr( $death, 2, 1 ) ) {
			$date = date_format( date_create_from_format( 'Y-m-d', $death ), 'd F Y' );
		} else {
			$date = date_format( date_create_from_format( 'm/d/Y', $death ), 'F d, Y' );
		}
		$echo_death[] = $date;
	}
	$rip = '<strong>RIP:</strong> ' . implode( '; ', $echo_death );
}

// Generate list of Cliches
// Usage: $cliches
$cliches = '<strong>Clichés:</strong> ' . lwtv_yikes_chardata( get_the_ID(), 'cliches' );

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
	'class' => 'single-char-img',
	'alt'   => get_the_title(),
	'title' => $thumb_title,
);
?>

<div class="card-body">
	<?php the_post_thumbnail( 'character-img', $thumb_array ); ?>

	<div class="card-meta">
		<div class="card-meta-item">
			<?php
			if ( isset( $character_type ) ) {
				echo '(' . esc_html( $character_type ) . ')';
			}
			?>
		</div>
		<div class="card-meta-item">
			<?php echo wp_kses_post( $gender_sexuality ); ?>
		</div>
		<div class="card-meta-item">
			<?php echo $cliches; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<div class="card-meta-item">
			<?php
			if ( isset( $dead_or_alive ) ) {
				echo wp_kses_post( $dead_or_alive ) . '</br>';
			}
			?>
		</div>
		<div class="card-meta-item">
			<?php
			if ( isset( $actors ) ) {
				echo wp_kses_post( $actors );
			}
			?>
		</div>
		<div class="card-meta-item">
			<?php
			if ( isset( $show_title ) && count( $show_title ) !== 0 ) {
				echo wp_kses_post( $appears );
			}
			?>
		</div>
		<div class="card-meta-item">
			<?php
			if ( isset( $rip ) ) {
				echo wp_kses_post( $rip );
			}
			?>
		</div>
	</div>
	<div class="characters-description">
		<hr />
		<?php echo wp_kses_post( the_content() ); ?>
	</div>
</div>
