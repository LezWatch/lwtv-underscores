<?php
/**
 * Template part for displaying actor posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// The Post ID, which we'll pass to templates.
$actor_id = get_the_ID();

// This just gets the numbers of all characters and how many are dead.
$all_chars     = get_post_meta( $actor_id, 'lezactors_char_list', true );
$all_dead      = get_post_meta( $actor_id, 'lezactors_dead_list', true );
$havecharcount = ( is_array( $all_chars ) ) ? count( $all_chars ) : 0;
$havedeadcount = ( is_array( $all_dead ) ) ? count( $all_dead ) : 0;

// Get the related articles.
$related = lwtv_plugin()->get_cpt_related_posts( $actor_id );

// Microformats Fix.
lwtv_microformats_fix( $actor_id );
?>

<section class="showschar-section" name="biography" id="biography">
	<div class="card-body">
		<div class="actor-image-wrapper">
			<?php
			get_template_part(
				'template-parts/partials/image',
				'headshot',
				array(
					'to_show' => $actor_id,
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
				lwtv_plugin()->the_actor_privacy_warning( $actor_id );

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
		<a class="breadcrumb-item smoothscroll" href="#characters">Characters</a>
	</nav>
</section>

<section name="vitals" id="vitals" class="showschar-section">
	<h2>Overview</h2>
	<div class="card-body">
		<div class="card-meta">
			<div class="card-meta-item">
				<?php get_template_part( 'template-parts/partials/actors', 'life', array( 'actor' => $actor_id ) ); ?>
			</div>
			<div class="card-meta-item">
				<?php get_template_part( 'template-parts/partials/actors', 'gender-sexuality', array( 'actor' => $actor_id ) ); ?>
			</div>
			<div class="card-meta-item">
				<?php get_template_part( 'template-parts/partials/actors', 'socials', array( 'actor' => $actor_id ) ); ?>
			</div>
		</div>
	</div>
</section>

<section name="overlays" id="overlays" class="overlay-section">
	<div class="container">
		<div class="row">
		<?php
		if ( 0 !== $havecharcount ) {
			get_template_part( 'template-parts/overlays/statistics', 'actors', compact( 'actor_id' ) );
		}

		if ( isset( $related ) && $related ) {
			get_template_part( 'template-parts/overlays/related-articles', '', array( 'to_show' => $actor_id ) );
		}
		?>
		</div>
	</div>
	<p>&nbsp;</p>
</section>

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
