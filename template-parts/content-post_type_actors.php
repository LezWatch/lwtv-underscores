<?php
/**
 * Template part for displaying actor posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

// Generate list of chars
// Usage: $characters
/*
$all_chars = lwtv_yikes_actordata( get_the_ID(), 'characters' );
if ( $all_chars !== '' ) {
	$char_title = array();
	foreach ( $all_chars as $each_char ) {
		if ( get_post_status ( $each_char['char'] ) !== 'publish' ) {
			array_push( $char_title, '<em><span class="disabled-char-link">' . get_the_title( $each_char['char'] ) . '</span></em> (' . $each_char['type'] . ' character)' );
		} else {
			array_push( $char_title, '<em><a href="' . get_permalink( $each_char['char'] ) . '">' . get_the_title( $each_char['char'] ) . '</a></em> (' . $each_char['type'] . ' character)' );
		}
	}
}

$is_chars = ( empty( $char_title ) )? ' None' : ': ' . implode( ', ', $char_title );
if ( isset( $char_title ) && count( $char_title ) !== 0 ) {
	$is_title =  _n( 'Character', 'Characters', count( $char_title ) );
	$characters  = '<strong>' . $is_title . '</strong>' . $is_chars;
}
*/

// Generate Birth
// Usage: $birth
if ( get_post_meta( get_the_ID(), 'lezactors_birth', true ) ) {
	$death = get_post_meta( get_the_ID(), 'lezactors_birth', true );
	$rip = '<strong>Born:</strong> ' . $birth;
}
// Generate RIP
// Usage: $death
if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
	$death = get_post_meta( get_the_ID(), 'lezactors_death', true );
	$rip = '<strong>Died:</strong> ' . $death;
}

// Generate Gender & Sexuality Data
// Usage: $gender_sexuality
$gender_sexuality = lwtv_yikes_actordata( get_the_ID(), 'gender' ) . ' &bull; ' . lwtv_yikes_actordata( get_the_ID(), 'sexuality' );
?>

<div class="card-body">
	<?php the_post_thumbnail( 'character-img', array( 'class' => 'single-char-img' , 'alt' => get_the_title() , 'title' => get_the_title() ) ); ?>	
	
	<div class="card-meta">
		<div class="card-meta-item">
			<?php echo $gender_sexuality; ?>
		</div>
		<div class="card-meta-item">
			<?php if ( isset( $characters ) ) echo $characters . '</br>'; ?>
		</div>
		<div class="card-meta-item">
			<?php if ( isset( $char_title ) && count( $char_title ) !== 0 ) echo $appears; ?>
		</div>
		<div class="card-meta-item">
			<?php if ( isset( $birth ) ) echo $birth . '</br>'; ?>
		</div>
		<div class="card-meta-item">
			<?php if ( isset( $rip ) ) echo $rip; ?>
		</div>
	</div>
	<div class="actor-description">
		<hr />
		<?php echo the_content(); ?>
	</div>
</div>