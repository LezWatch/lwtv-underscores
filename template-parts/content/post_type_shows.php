<?php
/**
 * Template part for displaying shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */


$show_id = $post->ID;

// Is this show created less than 24 hours ago?
$treat_as_new = lwtv_plugin()->maybe_has_new_characters( $show_id );

// Get the shows like this shortcode.
$rpbt_shortcode = lwtv_plugin()->get_shows_like_this_show( $show_id );
$maybe_has      = array(
	'timeline'      => array(
		'title'   => 'Timeline',
		'section' => 'Queer Plotline Timeline',
		'meta'    => get_post_meta( $show_id, 'lezshows_plots', true ),
	),
	'episodes'      => array(
		'title'   => 'Episodes',
		'section' => 'Notable Queer-Centric Episodes',
		'meta'    => get_post_meta( $show_id, 'lezshows_episodes', true ),
	),
	'related-posts' => array(
		'title' => 'Articles',
		'meta'  => lwtv_plugin()->has_cpt_related_posts( $show_id ), // true-falsey
	),
);

// Microformats Fix.
lwtv_plugin()->get_microformats_fix( $show_id );

get_template_part( 'template-parts/partials/image', 'show', array( 'show_id' => $show_id ) );
?>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<span class="toc-title">Table of Contents</span>
		<a class="breadcrumb-item smoothscroll" href="#overview">Overview</a>
		<?php
		foreach ( $maybe_has as $key => $value ) {
			if ( $value['meta'] && '<p><br data-mce-bogus="1"></p>' !== $value['meta'] ) {
				?>
				<a class="breadcrumb-item smoothscroll" href="#<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value['title'] ); ?></a>
				<?php
			}
		}
		?>
		<a class="breadcrumb-item smoothscroll" href="#characters">Characters</a>
		<?php
		// Similar Shows.
		if ( false !== $rpbt_shortcode ) {
			?>
			<a class="breadcrumb-item smoothscroll" href="#similar-shows">Similar Shows</a>
			<?php
		}
		?>
	</nav>
</section>

<?php
// Warnings:
get_template_part( 'template-parts/partials/shows/warning', '', compact( 'show_id' ) );

// Ways to Watch:
get_template_part( 'template-parts/partials/shows/ways-to-watch', '', compact( 'show_id' ) );
?>

<section class="showschar-section" name="overview" id="overview">
	<h2>Overview</h2>
	<div class="card-body">
		<?php the_content(); ?>
	</div>
</section>

<?php
// Loop through the sections as maybe_has and, if there's content, display it.
foreach ( $maybe_has as $key => $value ) {
	if ( $value['meta'] && '<p><br data-mce-bogus="1"></p>' !== $value['meta'] ) {
		// if there's no section title, don't use.
		if ( ! isset( $value['section'] ) ) {
			continue;
		}
		?>
		<section name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" class="showschar-section">
			<h2><?php echo esc_html( $value['section'] ); ?></h2>
			<div class="card-body">
				<?php echo wp_kses_post( apply_filters( 'the_content', $value['meta'] ) ); ?>
			</div>
		</section>
		<?php
	}
}

if ( $maybe_has['related-posts']['meta'] ) {
	// Related Articles.
	get_template_part( 'template-parts/partials/related', 'articles', array( 'to_show' => $show_id ) );
}

// Great big characters section!
$havecharcount = get_post_meta( $show_id, 'lezshows_char_count', true );

if ( empty( $havecharcount ) || 0 === (int) $havecharcount ) {
	get_template_part( 'template-parts/partials/shows/new', '', compact( 'show_id', 'havecharcount' ) );
} else {
	get_template_part( 'template-parts/partials/shows/characters', '', compact( 'show_id', 'havecharcount', 'treat_as_new' ) );
}

if ( false !== $rpbt_shortcode ) {
	get_template_part( 'template-parts/partials/shows/like-this', '', compact( 'show_id', 'rpbt_shortcode' ) );
}
