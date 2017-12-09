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
	$get_birth     = date_create_from_format( 'Y-m-j', get_post_meta( get_the_ID(), 'lezactors_birth', true ) );
	$life['birth date'] = date_format( $get_birth, 'F d, Y');
}
if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
	$get_death     = date_create_from_format( 'Y-m-j', get_post_meta( get_the_ID(), 'lezactors_death', true ) );
	$life['death'] = date_format( $get_death, 'F d, Y');
}

// Generate Gender & Sexuality Data
// Usage: $gender
// Usage: $sexuality
$gender    = lwtv_yikes_actordata( get_the_ID(), 'gender', true );
$sexuality = lwtv_yikes_actordata( get_the_ID(), 'sexuality', true );

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
					if ( isset( $gender ) && !empty( $gender ) ) echo '<strong>Gender 0rientation:</strong> ' . $gender; 
					if ( isset( $sexuality ) && !empty( $sexuality ) ) echo '<span class="actor-orientation"><strong>Sexual orientation:</strong> ' . $sexuality . '</span>'; 
				?>
			</div>
			<div class="card-meta-item">
				<?php 
					$life_total = count( $life );
					$life_count = 1;
					foreach ( $life as $event => $date ) {
						echo '<strong>' . ucfirst( $event ) . '</strong>: ' . $date;
						if ( $life_count !== $life_total ) echo ' &bull; ' ;
						$life_count++;
					}
				?>
			</div>
			<div class="card-meta-item">
				<?php 
					$urls_total = count( $urls );
					$urls_count = 1;
					echo '<strong>Links:</strong> ';
					foreach ( $urls as $source => $link ) {
						echo '<a href="' . $link . '">' . $source . '</a>';
						if ( $urls_count !== $urls_total ) echo ' &bull; ' ;
						$urls_count++;
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
		$all_chars = lwtv_yikes_actordata( get_the_ID(), 'characters' );
		if ( empty( $all_chars ) || count( $all_chars ) == '0' ) {
			echo '<p>There are no queer characters listed yet for this actor.</p>';
		} else {
			echo '<p>There '. sprintf( _n( 'is <strong>%s</strong> queer character', 'are <strong>%s</strong> queer characters', count( $all_chars ) ), count( $all_chars ) ).' played by this actor.</p>';
		
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