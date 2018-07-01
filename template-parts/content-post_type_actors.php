<?php
/**
 * Template part for displaying actor posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Generate Life Stats
// Usage: $life
$life = array();
if ( get_post_meta( get_the_ID(), 'lezactors_birth', true ) ) {
	$get_birth          = new DateTime( get_post_meta( get_the_ID(), 'lezactors_birth', true ) );
	$age                = lwtv_yikes_actordata( get_the_ID(), 'age', true );
	$life['age']        = $age->format( '%Y years old' );
	$life['birth date'] = date_format( $get_birth, 'F d, Y');
}
if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
	$get_death     = new DateTime( get_post_meta( get_the_ID(), 'lezactors_death', true ) );
	$life['death'] = date_format( $get_death, 'F d, Y');
}

// Generate Gender & Sexuality Data
// Usage: $gender_sexuality
$gender_sexuality = array();
$gender           = lwtv_yikes_actordata( get_the_ID(), 'gender', true );
$sexuality        = lwtv_yikes_actordata( get_the_ID(), 'sexuality', true );
if ( isset( $gender ) && !empty( $gender ) ) {
	$gender_sexuality['Gender Orientation'] = $gender; 
}
if ( isset( $sexuality ) && !empty( $sexuality ) ) {
	$gender_sexuality['Sexual Orientation'] = $sexuality; 
}

// Generate URLs
// Usage: $urls
$urls = array();
if ( get_post_meta( get_the_ID(), 'lezactors_homepage', true ) ) {
	$urls['home'] = array(
		'name' => 'Website',
		'url'  => esc_url( get_post_meta( get_the_ID(), 'lezactors_homepage', true ) ),
		'fa'   => 'fas fa-home',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ) {
	$urls['imdb'] = array(
		'name' => 'IMDb',
		'url'  => esc_url( 'https://www.imdb.com/name/' . get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ),
		'fa'   => 'fab fa-imdb',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ) {
	$urls['wikipedia'] = array(
		'name' => 'WikiPedia',
		'url'  => esc_url( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ),
		'fa'   => 'fab fa-wikipedia-w',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_twitter', true ) ) {
	$urls['twitter'] = array(
		'name' => 'Twitter',
		'url'  => esc_url( 'https://twitter.com/' . get_post_meta( get_the_ID(), 'lezactors_twitter', true ) ),
		'fa'   => 'fab fa-twitter',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_instagram', true ) ) {
	$urls['instagram'] = array(
		'name' => 'Instagram',
		'url'  => esc_url( 'https://www.instagram.com/' . get_post_meta( get_the_ID(), 'lezactors_instagram', true ) ),
		'fa'   => 'fab fa-instagram',
	);
}

// Microformats Fix
lwtv_microformats_fix( $post->ID );

// Thumbnail attribution
$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) )? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;

?>

<section class="showschar-section" name="overview" id="overview">
	<div class="card-body">
		<?php the_post_thumbnail( 'character-img', array( 'class' => 'single-char-img' , 'alt' => get_the_title() , 'title' => $thumb_title ) ); ?>	

		<div class="card-meta">
			<div class="card-meta-item">
				<?php 
					if ( !empty( get_the_content() ) ) {
					?>
						<h2>Actor Bio</h2> <?php the_content(); 
					} else {
						the_title( '<p>', ' is an actor who has played at least one character on TV. Information on this page has not yet been verified. Please <a href="/about/contact/">contact us</a> with any corrections or amendments.</p>' );
					}
				?>
			</div>
		</div>
	</div>
</section>

<section name="vitals" id="vitals" class="showschar-section">
	<h2>Actor Information</h2>
	<div class="card-body">
		<div class="card-meta">
			<div class="card-meta-item">
				<?php 
					if ( count( $gender_sexuality ) > 0 ) {
						echo '<ul>';
						foreach ( $gender_sexuality as $title => $data ) {
							echo '<li><strong>' . ucfirst( $title ) . '</strong>: ' . $data . '</li>';
						}
						echo '</ul>';
					}
				?>
			</div>
			<div class="card-meta-item">
				<?php 
					if ( count( $life ) > 0 ) {
						echo '<ul>';
						foreach ( $life as $event => $date ) {
							echo '<li><strong>' . ucfirst( $event ) . '</strong>: ' . $date . '</li>';
						}
						echo '</ul>';
					}
				?>
			</div>
			<div class="card-meta-item">
				<?php 
					if ( count( $urls ) > 0 ) {
						echo '<strong>Links:</strong> ';
						echo '<ul class="actor-meta-links">';
						foreach ( $urls as $source ) {
							echo '<li><i class="' . strtolower( $source['fa'] ) . '" aria-hidden="true"></i> <a href="' . $source['url'] . '">' . $source['name'] . '</a></li>';
						}
						echo '</ul>';
					}
				?>
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

		// This just gets the numbers of all characters and how many are dead.
		$all_chars     = lwtv_yikes_actordata( get_the_ID(), 'characters' );
		$havecharcount = count( $all_chars );
		$havedeadcount = count ( lwtv_yikes_actordata( get_the_ID(), 'dead' ) );

		if ( empty( $havecharcount ) || $havecharcount == '0' ) {
			echo '<p>There are no characters listed yet for this actor.</p>';
		} else {
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) $deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
			echo '<p>There '. sprintf( _n( 'is <strong>%s</strong> character', 'are <strong>%s</strong> characters', $havecharcount ), $havecharcount ).' listed for this actor; ' . $deadtext . '.</p>';
		
			echo '<div class="container characters-regulars-container">
					<div class="row site-loop character-show-loop equal-height">';
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