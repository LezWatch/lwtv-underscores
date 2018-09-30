<?php
/**
 * Template part for displaying actor posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Related Posts
$slug     = get_post_field( 'post_name', get_post( get_the_ID() ) );
$get_tags = get_term_by( 'name', $slug, 'post_tag' );
$related  = LWTV_Related_Posts::are_there_posts( $slug );

// Generate Life Stats
// Usage: $life
$life = array();
if ( get_post_meta( get_the_ID(), 'lezactors_birth', true ) ) {
	$get_birth          = new DateTime( get_post_meta( get_the_ID(), 'lezactors_birth', true ) );
	$age                = lwtv_yikes_actordata( get_the_ID(), 'age', true );
	$life['age']        = $age->format( '%Y years old' );
	$life['birth date'] = date_format( $get_birth, 'F d, Y' );
}
if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
	$get_death     = new DateTime( get_post_meta( get_the_ID(), 'lezactors_death', true ) );
	$life['death'] = date_format( $get_death, 'F d, Y' );
}

// Generate Gender & Sexuality Data
// Usage: $gender_sexuality
$gender_sexuality = array();
$gender           = lwtv_yikes_actordata( get_the_ID(), 'gender', true );
$sexuality        = lwtv_yikes_actordata( get_the_ID(), 'sexuality', true );
if ( isset( $gender ) && ! empty( $gender ) ) {
	$gender_sexuality['Gender Orientation'] = $gender;
}
if ( isset( $sexuality ) && ! empty( $sexuality ) ) {
	$gender_sexuality['Sexual Orientation'] = $sexuality;
}

// Generate URLs
// Usage: $actor_urls
$actor_urls = array();
if ( get_post_meta( get_the_ID(), 'lezactors_homepage', true ) ) {
	$actor_urls['home'] = array(
		'name' => 'Website',
		'url'  => esc_url( get_post_meta( get_the_ID(), 'lezactors_homepage', true ) ),
		'fa'   => 'fas fa-home',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ) {
	$actor_urls['imdb'] = array(
		'name' => 'IMDb',
		'url'  => esc_url( 'https://www.imdb.com/name/' . get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ),
		'fa'   => 'fab fa-imdb',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ) {
	$actor_urls['wikipedia'] = array(
		'name' => 'WikiPedia',
		'url'  => esc_url( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ),
		'fa'   => 'fab fa-wikipedia-w',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_twitter', true ) ) {
	$actor_urls['twitter'] = array(
		'name' => 'Twitter',
		'url'  => esc_url( 'https://twitter.com/' . get_post_meta( get_the_ID(), 'lezactors_twitter', true ) ),
		'fa'   => 'fab fa-twitter',
	);
}
if ( get_post_meta( get_the_ID(), 'lezactors_instagram', true ) ) {
	$actor_urls['instagram'] = array(
		'name' => 'Instagram',
		'url'  => esc_url( 'https://www.instagram.com/' . get_post_meta( get_the_ID(), 'lezactors_instagram', true ) ),
		'fa'   => 'fab fa-instagram',
	);
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

<section class="showschar-section" name="overview" id="overview">
	<div class="card-body"><?php the_post_thumbnail( 'character-img', $thumb_array ); ?>

		<div class="card-meta">
			<div class="card-meta-item">
				<?php
				if ( ! empty( get_the_content() ) ) {
					echo '<h2>Actor Bio</h2>';
					the_content();
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
					foreach ( $gender_sexuality as $item => $data ) {
						echo '<li><strong>' . esc_html( ucfirst( $item ) ) . '</strong>: ' . wp_kses_post( $data ) . '</li>';
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
						echo '<li><strong>' . esc_html( ucfirst( $event ) ) . '</strong>: ' . wp_kses_post( $date ) . '</li>';
					}
					echo '</ul>';
				}
				?>
			</div>
			<div class="card-meta-item">
				<?php
				if ( count( $actor_urls ) > 0 ) {
					echo '<strong>Links:</strong> ';
					echo '<ul class="actor-meta-links">';
					foreach ( $actor_urls as $source ) {
						echo '<li><i class="' . esc_attr( strtolower( $source['fa'] ) ) . '" aria-hidden="true"></i> <a href="' . esc_url( $source['url'] ) . '">' . esc_html( $source['name'] ) . '</a></li>';
					}
					echo '</ul>';
				}
				?>
			</div>
		</div>
	</div>
</section>

<?php
// Related Posts
if ( $related ) {
	?>
	<section name="related-posts" id="related-posts" class="showschar-section">
		<h2>Related Articles</h2>
		<div class="card-body">
			<?php
			echo LWTV_Related_Posts::related_posts( $slug ); // WPCS: XSS okay
			if ( count( LWTV_Related_Posts::count_related_posts( $slug ) ) > '5' ) {
				$get_tags = term_exists( $slug, 'post_tag' );
				if ( ! is_null( $get_tags ) && $get_tags >= 1 ) {
					echo '<p><a href="' . esc_url( get_tag_link( $get_tags['term_id'] ) ) . '">Read More ...</a></p>';
				}
			}
			?>
		</div>
	</section>
	<?php
}

// Great big characters section!
?>
<section name="characters" id="characters" class="showschar-section">
	<h2>Characters</h2>
	<div class="card-body">
		<?php

		// This just gets the numbers of all characters and how many are dead.
		$all_chars     = lwtv_yikes_actordata( get_the_ID(), 'characters' );
		$havecharcount = count( $all_chars );
		$havedeadcount = count( lwtv_yikes_actordata( get_the_ID(), 'dead' ) );

		if ( empty( $havecharcount ) || '0' === $havecharcount ) {
			echo '<p>There are no characters listed yet for this actor.</p>';
		} else {
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) {
				// translators: %s is a number.
				$deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
			}
			// translators: %s is 'are' or a number.
			echo wp_kses_post( '<p>There ' . sprintf( _n( 'is <strong>%s</strong> character', 'are <strong>%s</strong> characters', $havecharcount ), $havecharcount ) . ' listed for this actor; ' . $deadtext . '.</p>' );

			echo '<div class="container characters-regulars-container"><div class="row site-loop character-show-loop">';
			foreach ( $all_chars as $character ) {
				include locate_template( 'template-parts/excerpt-post_type_characters.php' );
			}
			echo '</div></div>';
		}
		?>
	</div>
</section>
