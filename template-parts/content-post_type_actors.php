<?php
/**
 * Template part for displaying actor posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
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
$gender    = lwtv_yikes_actordata( get_the_ID(), 'gender' );
$sexuality = lwtv_yikes_actordata( get_the_ID(), 'sexuality' );

?>

<section class="showschar-section" name="overview" id="overview">
	<div class="card-body">
		<?php 
			if ( !empty( get_the_content() ) ) {
				the_content(); 
			} else {
				the_title( '<p>', ' is an actor who has played at least one queer character on TV.</p>' );
			}
			?>
	</div>
</section>

<section name="vitals" id="vitals" class="showschar-section">
	<h2>Vitals</h2>
	<div class="card-body">
		<div class="card-meta">
			<div class="card-meta-item">
				<?php if ( isset( $gender ) && !empty( $gender ) ) echo '&bull; ' . $gender . '</br>'; ?>
				<?php if ( isset( $sexuality ) && !empty( $sexuality ) ) echo '&bull; ' . $sexuality; ?>
			</div>
			<div class="card-meta-item">
				<?php if ( isset( $birth ) ) echo $birth . '</br>'; ?>
			</div>
			<div class="card-meta-item">
				<?php if ( isset( $rip ) ) echo $rip; ?>
			</div>
		</div>
	</div>
</section>

<?php
// Great big characters section!
?>
<section name="characters" id="characters" class="showschar-section">
	<h2>Characters</h2>
	<div class="card-body">
		<?php
		$all_chars = lwtv_yikes_actordata( get_the_ID(), 'characters' );
		if ( empty( $all_chars ) || count( $all_chars ) == '0' ) {
			echo '<p>There are no queers listed yet for this actor.</p>';
		} else {
			echo '<p>There '. sprintf( _n( 'is <strong>%s</strong> queer character', 'are <strong>%s</strong> queer characters', count( $all_chars ) ), count( $all_chars ) ).' played by this actor.</p>';
		
			echo '<div class="container characters-regulars-container"><div class="row site-loop character-show-loop equal-height">';
				foreach( $all_chars as $character ) {
					echo '<div class="col-sm-4">';
						include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
					echo '</div>';
				}
			echo '</div></div>';
		}
		?>
	</div>
</section>