<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

// Generate Character Type
// Usage: $character_type	
$character_type = '';

// Generate list of shows
// Usage: $appears
$all_shows = lwtv_yikes_chardata( get_the_ID(), 'shows' );
$show_title = array();

if ( $all_shows !== '' ) {
	foreach ( $all_shows as $each_show ) {
		if ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
			array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em> ('. $each_show['type'] .' character)' );
		} else {
			array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> (' . $each_show['type'] . ' character)' );
		}
	}
}

$on_shows = ( empty( $show_title ) )? ' no shows.' : ': ' . implode( ", ", $show_title );
$appears = '<strong>Appears on</strong>' . $on_shows;

// Generate actors
// Usage: $actors
$character_actors = lwtv_yikes_chardata( get_the_ID(), 'actors' );
$actor_count = count( $character_actors );
$actor_title = sprintf( _n( 'Actor', 'Actors', $actor_count ), $actor_count );
$actors = '<strong>' . $actor_title . ':</strong> ' . implode( ", ", $character_actors );

// Generate RIP
// Usage: $rip
$rip = '';
if ( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) ) {
	$character_death = get_post_meta( get_the_ID(), 'lezchars_death_year', true );
	if ( !is_array ( $character_death ) ) {
		$character_death = array( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) );
	}
	$echo_death = array();
	foreach( $character_death as $death ) {
		$date = date_create_from_format( 'm/j/Y', $death );
		$echo_death[] = date_format( $date, 'F d, Y');
	}
	$character_death = implode( ", ", $echo_death );
	$rip = '<strong>RIP:</strong> ' . $character_death;
}

// Generate list of Cliches
// Usage: $cliches
$cliches   = lwtv_yikes_chardata( get_the_ID(), 'cliches' );

// Generate Gender & Sexuality Data
// Usage: $gender_sexuality
$gender_sexuality = lwtv_yikes_chardata( get_the_ID(), 'gender' ) . lwtv_yikes_chardata( get_the_ID(), 'sexuality' );
?>

<div class="card-body">
	<?php the_post_thumbnail( 'character-img', array( 'class' => 'single-char-img' , 'alt' => get_the_title() , 'title' => get_the_title() ) ); ?>	
	
	<div class="card-meta">
		<div class="card-meta-item">
			<?php if ( $character_type !== '' ) echo '('.$character_type .')'; ?>
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
			<?php if ( count( $show_title ) !== 0 ) echo $appears; ?>
		</div>
		<div class="card-meta-item">
			<?php if ( isset( $rip ) ) echo $rip ; ?>
		</div>
	</div>
	<div class="characters-description">
		<?php echo the_content(); ?>
	</div>
</div>