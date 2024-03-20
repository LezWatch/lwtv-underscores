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
$related  = lwtv_plugin()->has_cpt_related_posts( $slug );

// This just gets the numbers of all characters and how many are dead.
$all_chars     = get_post_meta( $the_id, 'lezactors_char_list', true );
$all_dead      = get_post_meta( $the_id, 'lezactors_dead_list', true );
$havecharcount = ( is_array( $all_chars ) ) ? count( $all_chars ) : 0;
$havedeadcount = ( is_array( $all_dead ) ) ? count( $all_dead ) : 0;

// Generate Life Stats.
// Usage: $life.
$life = array();
$born = get_post_meta( $the_id, 'lezactors_birth', true );
if ( ! empty( $born ) && ! lwtv_plugin()->hide_actor_data( $the_id, 'dob' ) ) {
	$barr = explode( '-', $born );
}
if ( isset( $barr ) && isset( $barr[1] ) && isset( $barr[2] ) && checkdate( (int) $barr[1], (int) $barr[2], (int) $barr[0] ) ) {
	$get_birth    = new DateTime( $born );
	$life['born'] = date_format( $get_birth, 'F j, Y' );
}
$died = get_post_meta( $the_id, 'lezactors_death', true );
if ( ! empty( $died ) ) {
	$darr = explode( '-', $died );
}
if ( isset( $darr ) && isset( $darr[1] ) && isset( $darr[2] ) && checkdate( $darr[1], $darr[2], $darr[0] ) ) {
	$get_death    = new DateTime( $died );
	$life['died'] = date_format( $get_death, 'F j, Y' );
}
if ( isset( $life['born'] ) ) {
	$age         = lwtv_plugin()->get_actor_age( $the_id );
	$life['age'] = ( is_object( $age ) ) ? $age->format( '%Y years old' ) : '';
}

// Generate Gender & Sexuality & Pronoun Data.
// Usage: $gender_sexuality.
$gender_sexuality = array();
$gender           = lwtv_plugin()->get_actor_gender( $the_id );
$sexuality        = lwtv_plugin()->get_actor_sexuality( $the_id );
$pronouns         = lwtv_plugin()->get_actor_pronouns( $the_id );
if ( isset( $gender ) && ! empty( $gender ) ) {
	$gender_sexuality['Gender Orientation'] = $gender;
}
if ( isset( $sexuality ) && ! empty( $sexuality ) ) {
	$gender_sexuality['Sexual Orientation'] = $sexuality;
}
if ( isset( $pronouns ) && ! empty( $pronouns ) ) {
	$gender_sexuality['Pronouns'] = $pronouns;
}

// Microformats Fix.
lwtv_microformats_fix( $post->ID );

?>

<section class="showschar-section" name="biography" id="biography">
	<div class="card-body">
		<div class="actor-image-wrapper">
			<?php
			get_template_part(
				'template-parts/partials/output',
				'image',
				array(
					'to_show' => $the_id,
					'format'  => 'excerpt',
				)
			);
			?>
		</div>
		<div class="card-meta">
			<div class="card-meta-item">
				<?php
				echo '<h2>Biography</h2>';

				// Actor Privacy Warning.
				lwtv_plugin()->the_actor_privacy_warning( $the_id );

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
				<?php get_template_part( 'template-parts/partials/output', 'socials', array( 'to_show' => $post->ID ) ); ?>
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
		// phpcs:ignore WordPress.Security.EscapeOutput
		echo lwtv_plugin()->get_cpt_related_posts( $slug );
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
			if ( is_array( $all_chars ) ) {
				foreach ( $all_chars as $character ) {
					get_template_part( 'template-parts/excerpt', 'post_type_characters', array( 'character' => $character ) );
				}
			}
			echo '</div></div>';
		}
		?>
	</div>
</section>

<?php

// If there are characters, show this:
if ( 0 !== $havecharcount ) {
	get_template_part( 'template-parts/partials/output', 'actor-stats', array( 'to_show' => $post->ID ) );
}
