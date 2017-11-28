<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

// Generate list of shows
// Usage: $appears
$all_shows = lwtv_yikes_chardata( get_the_ID(), 'shows' );
if ( $all_shows !== '' ) {
	$show_title = array();
	foreach ( $all_shows as $each_show ) {
		if ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
			array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em> (' . $each_show['type'] . ' character)' );
		} else {
			array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> (' . $each_show['type'] . ' character)' );
		}
	}
}

$on_shows = ( empty( $show_title ) )? ' None' : ': ' . implode( ', ', $show_title );
if ( isset( $show_title ) && count( $show_title ) !== 0 ) {
	$on_title =  _n( 'Show', 'Shows', count( $show_title ) );
	$appears  = '<strong>' . $on_title . '</strong>' . $on_shows;
}

// Generate actors
// Usage: $actors
$all_actors  = lwtv_yikes_chardata( get_the_ID(), 'actors' );
$actor_title = _n( 'Actor', 'Actors', count( $all_actors ) );
$actors      = '<strong>' . $actor_title . ':</strong> ' . implode( ", ", $all_actors );

// Generate Status
// Usage: $dead_or_alive
$doa_status    = ( has_term( 'dead', 'lez_cliches' , get_the_ID() ) )? 'Dead' : 'Alive';
$dead_or_alive = '<strong>Status:</strong> ' . $doa_status;

// Generate RIP
// Usage: $rip
if ( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) ) {
	$character_death = get_post_meta( get_the_ID(), 'lezchars_death_year', true );
	if ( !is_array ( $character_death ) ) {
		$character_death = array( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) );
	}
	$echo_death = array();
	foreach( $character_death as $death ) {
		$date         = date_create_from_format( 'm/j/Y', $death );
		$echo_death[] = date_format( $date, 'F d, Y');
	}
	$rip = '<strong>RIP:</strong> ' . implode( ', ', $echo_death );
}

// Generate list of Cliches
// Usage: $cliches
$cliches   = '<strong>Clichés:</strong> ' . lwtv_yikes_chardata( get_the_ID(), 'cliches' );

// Generate Gender & Sexuality Data
// Usage: $gender_sexuality
$gender_sexuality = lwtv_yikes_chardata( get_the_ID(), 'gender' ) . ' &bull; ' . lwtv_yikes_chardata( get_the_ID(), 'sexuality' );
?>

<div class="card-body">
	<?php the_post_thumbnail( 'character-img', array( 'class' => 'single-char-img' , 'alt' => get_the_title() , 'title' => get_the_title() ) ); ?>	
	
	<div class="card-meta">
		<div class="card-meta-item">
			<?php if ( isset( $character_type ) ) echo '(' . $character_type . ')'; ?>
		</div>
		<div class="card-meta-item">
			<?php echo $gender_sexuality; ?>
		</div>
		<div class="card-meta-item">
			<?php echo $cliches; ?>
		</div>
		<div class="card-meta-item">
			<?php echo $actors; ?>
		</div>
		<div class="card-meta-item">
			<?php if ( isset( $show_title ) && count( $show_title ) !== 0 ) echo $appears; ?>
		</div>
		<div class="card-meta-item">
			<?php 
				if ( isset( $dead_or_alive ) ) echo $dead_or_alive . '</br>';
				if ( isset( $rip ) ) echo $rip;
			?>
		</div>
	</div>
	<div class="characters-description">
		<hr />
		<?php echo the_content(); ?>
	</div>
</div>