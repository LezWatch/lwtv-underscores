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

// Is this actor created less than 24 hours ago?
$treat_as_new = lwtv_plugin()->maybe_has_new_characters( $actor_id );

// This just gets the numbers of all characters and how many are dead.
$all_chars      = get_post_meta( $actor_id, 'lezactors_char_list', true );
$all_dead       = get_post_meta( $actor_id, 'lezactors_dead_list', true );
$has_char_count = ( is_array( $all_chars ) ) ? count( $all_chars ) : 0;
$has_dead_count = ( is_array( $all_dead ) ) ? count( $all_dead ) : 0;

// Get the related articles.
$related = lwtv_plugin()->get_cpt_related_posts( $actor_id );

// Microformats Fix.
lwtv_plugin()->get_microformats_fix( $actor_id );
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
		<span class="toc-title">Table of Contents</span>
		<a class="breadcrumb-item smoothscroll" href="#biography">Biography</a>
		<a class="breadcrumb-item smoothscroll" href="#vitals">Overview</a>
		<?php
		if ( get_post_meta( $actor_id, 'lezactors_imdb', true ) ) {
			// Only show external links if they have IMDb -- odds are if they don't, they'd rather we not show socials.
			echo '<a class="breadcrumb-item smoothscroll" href="#external">Links</a>';
		}
		?>
		<a class="breadcrumb-item smoothscroll" href="#overlays">Add. Info.</a>
		<a class="breadcrumb-item smoothscroll" href="#characters">Characters</a>
	</nav>
</section>

<section name="vitals" id="vitals" class="showschar-section">
	<h2>Overview</h2>

	<div class="overview-container">
		<div class="row align-items-start">
			<div class="col">
				<?php get_template_part( 'template-parts/partials/actors/life', '', array( 'actor' => $actor_id ) ); ?>
			</div>
			<div class="col">
				<?php get_template_part( 'template-parts/partials/actors/gender-sexuality', '', array( 'actor' => $actor_id ) ); ?>
			</div>
		</div>
	</div>
</section>

<?php
if ( get_post_meta( $actor_id, 'lezactors_imdb', true ) ) {
	?>
	<section name="external" id="external" class="external-section">
		<h2>External Links</h2>
		<div class="overview-container">
			<div class="row align-items-start">
				<div class="col">
					<?php get_template_part( 'template-parts/partials/actors/links', '', array( 'actor' => $actor_id ) ); ?>
				</div>
				<div class="col">
					<?php get_template_part( 'template-parts/partials/actors/social', '', array( 'actor' => $actor_id ) ); ?>
				</div>
			</div>
		</div>
	</section>
	<?php
}

if ( empty( $has_char_count ) || 0 === (int) $has_char_count ) {
	get_template_part( 'template-parts/partials/actors/new', '', compact( 'actor_id' ) );
} else {
	get_template_part( 'template-parts/partials/actors/additional', '', compact( 'actor_id', 'has_char_count', 'related' ) );
	get_template_part( 'template-parts/partials/actors/characters', '', compact( 'has_char_count', 'has_dead_count', 'all_chars', 'treat_as_new' ) );
}
