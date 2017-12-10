<?php
/**
 * Template part for displaying actor posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
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
if ( get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ) {
	$urls['IMDb'] = esc_url( 'https://imdb.com/name/' . get_post_meta( get_the_ID(), 'lezactors_imdb', true ) );
}
if ( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ) {
	$urls['WikiPedia'] = esc_url( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) );
}
?>

<section class="showschar-section" name="overview" id="overview">
	<div class="card-body">
		<?php the_post_thumbnail( 'character-img', array( 'class' => 'single-char-img' , 'alt' => get_the_title() , 'title' => get_the_title() ) ); ?>	

		<div class="card-meta">
			<div class="card-meta-item">
				<?php 
					if ( !empty( get_the_content() ) ) {
					?>
						<h2>Actor Bio</h2> <?php the_content(); 
					} else {
						the_title( '<p>', ' is an actor who has played at least one queer character on TV.</p>' );
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
						echo '<ul>';
						foreach ( $urls as $source => $link ) {
							echo '<li><a href="' . $link . '">' . $source . '</a></li>';
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
			echo '<p>There are no queers characters listed yet for this actor.</p>';
		} else {
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) $deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
			echo '<p>There '. sprintf( _n( 'is <strong>%s</strong> queer character', 'are <strong>%s</strong> queer characters', $havecharcount ), $havecharcount ).' listed for this actor; ' . $deadtext . '.</p>';
		
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