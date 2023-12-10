<?php
/**
 * Template part for displaying actor posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Related Posts.
$the_id   = get_the_ID();
$slug     = get_post_field( 'post_name', get_post( $the_id ) );
$get_tags = get_term_by( 'name', $slug, 'post_tag' );

// This just gets the numbers of all characters and how many are dead.
$all_chars     = lwtv_yikes_actordata( $the_id, 'characters' );
$havecharcount = ( is_array( $all_chars ) ) ? count( $all_chars ) : 0;
$havedeadcount = count( lwtv_yikes_actordata( $the_id, 'dead' ) );

$related = lwtv_plugin()->has_cpt_related_posts( $slug );

// Generate Life Stats.
// Usage: $life.
$life = array();
$born = get_post_meta( $the_id, 'lezactors_birth', true );
if ( ! empty( $born ) ) {
	$barr = explode( '-', $born );
}
if ( isset( $barr ) && isset( $barr[1] ) && isset( $barr[2] ) && checkdate( (int) $barr[1], (int) $barr[2], (int) $barr[0] ) ) {
	$get_birth    = new DateTime( $born );
	$age          = lwtv_yikes_actordata( $the_id, 'age', true );
	$life['born'] = date_format( $get_birth, 'F j, Y' );
	$life['age']  = ( is_object( $age ) ) ? $age->format( '%Y years old' ) : '';
}
$died = get_post_meta( $the_id, 'lezactors_death', true );
if ( ! empty( $died ) ) {
	$darr = explode( '-', $died );
}
if ( isset( $darr ) && isset( $darr[1] ) && isset( $darr[2] ) && checkdate( $darr[1], $darr[2], $darr[0] ) ) {
	$get_death    = new DateTime( $died );
	$life['died'] = date_format( $get_death, 'F j, Y' );
}

// Generate Gender & Sexuality & Pronoun Data.
// Usage: $gender_sexuality.
$gender_sexuality = array();
$gender           = lwtv_yikes_actordata( $the_id, 'gender', true );
$sexuality        = lwtv_yikes_actordata( $the_id, 'sexuality', true );
$pronouns         = lwtv_yikes_actordata( $the_id, 'sexuality', true );
if ( isset( $gender ) && ! empty( $gender ) ) {
	$gender_sexuality['Gender Orientation'] = $gender;
}
if ( isset( $sexuality ) && ! empty( $sexuality ) ) {
	$gender_sexuality['Sexual Orientation'] = $sexuality;
}

$pronoun_terms = get_the_terms( $the_id, 'lez_actor_pronouns', true );
if ( $pronoun_terms && ! is_wp_error( $pronoun_terms ) ) {
	$pronouns = '';
	$count    = 1;
	foreach ( $pronoun_terms as $pronoun_term ) {
		$pronouns .= $pronoun_term->name;
		$pronouns .= ( $count < count( $pronoun_terms ) ) ? '/' : '';
		++$count;
	}
	if ( isset( $pronouns ) && ! empty( $pronouns ) ) {
		$gender_sexuality['Pronouns'] = $pronouns;
	}
}

// Generate URLs.
// Usage: $actor_urls.
$actor_urls = array();
if ( get_post_meta( $the_id, 'lezactors_homepage', true ) ) {
	$actor_urls['home'] = array(
		'name' => 'Website',
		'url'  => esc_url( get_post_meta( $the_id, 'lezactors_homepage', true ) ),
		'fa'   => 'fas fa-home',
	);
}
if ( get_post_meta( $the_id, 'lezactors_imdb', true ) ) {
	$actor_urls['imdb'] = array(
		'name' => 'IMDb',
		'url'  => esc_url( 'https://www.imdb.com/name/' . get_post_meta( $the_id, 'lezactors_imdb', true ) ),
		'fa'   => 'fab fa-imdb',
	);
}
if ( get_post_meta( $the_id, 'lezactors_wikipedia', true ) ) {
	$actor_urls['wikipedia'] = array(
		'name' => 'WikiPedia',
		'url'  => esc_url( get_post_meta( $the_id, 'lezactors_wikipedia', true ) ),
		'fa'   => 'fab fa-wikipedia-w',
	);
}
if ( get_post_meta( $the_id, 'lezactors_twitter', true ) ) {
	$actor_urls['twitter'] = array(
		'name' => 'X (Twitter)',
		'url'  => esc_url( 'https://twitter.com/' . get_post_meta( $the_id, 'lezactors_twitter', true ) ),
		'fa'   => 'fab fa-x-twitter',
	);
}
if ( get_post_meta( $the_id, 'lezactors_instagram', true ) ) {
	$actor_urls['instagram'] = array(
		'name' => 'Instagram',
		'url'  => esc_url( 'https://www.instagram.com/' . get_post_meta( $the_id, 'lezactors_instagram', true ) ),
		'fa'   => 'fab fa-instagram',
	);
}
if ( get_post_meta( $the_id, 'lezactors_facebook', true ) ) {
	$actor_urls['facebook'] = array(
		'name' => 'Facebook',
		'url'  => esc_url( get_post_meta( $the_id, 'lezactors_facebook', true ) ),
		'fa'   => 'fab fa-facebook',
	);
}
if ( get_post_meta( $the_id, 'lezactors_tiktok', true ) ) {
	$actor_urls['tiktok'] = array(
		'name' => 'TikTok',
		'url'  => esc_url( 'https://tiktok.com/' . get_post_meta( $the_id, 'lezactors_tiktok', true ) ),
		'fa'   => 'fab fa-tiktok',
	);
}
if ( get_post_meta( $the_id, 'lezactors_bluesky', true ) ) {
	$actor_urls['bluesky'] = array(
		'name' => 'BlueSky',
		'url'  => esc_url( get_post_meta( $the_id, 'lezactors_bluesky', true ) ),
		'fa'   => 'fab fa-square',
	);
}
if ( get_post_meta( $the_id, 'lezactors_twitch', true ) ) {
	$actor_urls['twitch'] = array(
		'name' => 'Twitch',
		'url'  => esc_url( get_post_meta( $the_id, 'lezactors_twitch', true ) ),
		'fa'   => 'fab fa-twitch',
	);
}
if ( get_post_meta( $the_id, 'lezactors_tumblr', true ) ) {
	$actor_urls['tumblr'] = array(
		'name' => 'Tumblr',
		'url'  => esc_url( 'https://' . get_post_meta( $the_id, 'lezactors_tumblr', true ) . '.tumblr.com' ),
		'fa'   => 'fab fa-tumblr',
	);
}
if ( get_post_meta( $the_id, 'lezactors_mastodon', true ) ) {
	$actor_urls['mastodon'] = array(
		'name' => 'Mastodon',
		'url'  => esc_url( get_post_meta( $the_id, 'lezactors_mastodon', true ) ),
		'fa'   => 'fab fa-mastodon',
	);
}

// Microformats Fix.
lwtv_microformats_fix( $post->ID );

// Thumbnail attribution.
$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;
$thumb_array       = array(
	'class' => 'single-char-img',
	'alt'   => get_the_title(),
	'title' => $thumb_title,
);
?>

<section class="showschar-section" name="biography" id="biography">
	<div class="card-body"><?php the_post_thumbnail( 'character-img', $thumb_array ); ?>
		<div class="card-meta">
			<div class="card-meta-item">
				<?php
				echo '<h2>Biography</h2>';
				if ( ! empty( get_the_content() ) ) {
					the_content();
				} else {
					the_title( '<p>', ' is an actor who has played at least one queer character on TV. Information on this page has not yet been verified. Feel free to <a href="#" data-bs-toggle="modal" data-bs-target="#suggestForm">suggest an edit</a> with any corrections or additions.</p>' );
				}
				?>
			</div>
		</div>
	</div>
</section>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Table of Contents</h4>
		<a class="breadcrumb-item smoothscroll" href="#biography">Biography</a>
		<a class="breadcrumb-item smoothscroll" href="#vitals">Overview</a>
		<a class="breadcrumb-item smoothscroll" href="#stats">Stats</a>
		<?php
		if ( isset( $related ) && $related ) {
			?>
			<a class="breadcrumb-item smoothscroll" href="#related-posts">Related Posts</a>
			<?php
		}
		?>
		<a class="breadcrumb-item smoothscroll" href="#characters">Characters</a>
	</nav>
</section>

<section name="vitals" id="vitals" class="showschar-section">
	<h2>Overview</h2>
	<div class="card-body">
		<div class="card-meta">

			<div class="card-meta-item">
				<?php
				if ( count( $life ) > 0 ) {
					echo '<ul class="list-group list-group-horizontal">';
					foreach ( $life as $event => $date ) {
						echo '<li><strong>' . esc_html( ucfirst( $event ) ) . '</strong>: ' . wp_kses_post( $date ) . '</li>';
					}
					echo '</ul>';
					echo '<hr />';
				}
				?>
			</div>
			<div class="card-meta-item">
				<?php
				if ( count( $gender_sexuality ) > 0 ) {
					echo '<ul class="list-group list-group-horizontal">';
					foreach ( $gender_sexuality as $item => $data ) {
						echo '<li><strong>' . esc_html( ucfirst( $item ) ) . '</strong>:<br />' . wp_kses_post( $data ) . '</li>';
					}
					echo '</ul>';
					echo '<hr />';
				}
				?>
			</div>
			<div class="card-meta-item">
				<?php
				if ( count( $actor_urls ) > 0 ) {
					echo '<span ID="actor-links"><strong>Links: </strong></span> ';
					echo '<ul class="actor-meta-links" aria-labelledby="actor-links">';
					foreach ( $actor_urls as $source ) {
						echo '<li><i class="' . esc_attr( strtolower( $source['fa'] ) ) . '" aria-hidden="true"></i> <a href="' . esc_url( $source['url'] ) . '" target="_blank">' . esc_html( $source['name'] ) . '</a><span class="screen-reader-text">, opens in new tab</span></li>';
					}
					echo '</ul>';
				}
				?>
			</div>
		</div>
	</div>
</section>

<?php

// Related Posts.
if ( isset( $related ) && $related ) {
	?>
	<section name="related-posts" id="related-posts" class="relatedposts-section">
		<h2>Related Articles</h2>
		<div class="card-body">
			<?php
			if ( method_exists( 'LWTV_CPTs_Related_Posts', 'related_posts' ) && method_exists( 'LWTV_CPTs_Related_Posts', 'count_related_posts' ) ) {
				echo lwtv_plugin()->get_cpt_related_posts( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput
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

<?php

// If there are characters, show this:
if ( 0 !== $havecharcount ) {
	?>
	<section name="stats" id="stats" class="showschar-section">
		<h2>Character Statistics</h2>
		<div class="card-body">
			<div class="card-meta">
				<div class="card-meta-item">
					<?php
					$attributes = array(
						'posttype' => get_post_type(),
					);
					$stats      = lwtv_plugin()->generate_stats_block_actor( $attributes );

					if ( ! empty( $stats ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput
						echo $stats;
					} else {
						echo '<p>After this maintenance, statistics will be right back!</p>';
					}
					?>
					<p><em><small>Note: Character roles may exceed the number of characters played, if the character was on multiple TV shows.</small></em></p>
				</div>
			</div>
		</div>
	</section>

	<?php
}
