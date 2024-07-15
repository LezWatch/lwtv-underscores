<?php
/**
 * Template part for displaying shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id        = $post->ID;
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
lwtv_microformats_fix( $show_id );

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
?>
<section name="characters" id="characters" class="showschar-section">
	<h2>Characters</h2>
	<div class="card-body">
		<?php

		// This just gets the numbers of all characters and how many are dead.
		$havecharcount = get_post_meta( $show_id, 'lezshows_char_count', true );
		$havedeadcount = get_post_meta( $show_id, 'lezshows_dead_count', true );

		// Get the list of characters.
		$chars_by_role = lwtv_plugin()->get_chars_for_show( $show_id, 'all' );

		if ( empty( $havecharcount ) || '0' === $havecharcount ) {
			echo '<p>There are no characters listed yet for this show.</p>';
		} else {
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) {
				// translators: %s is the number of dead characters.
				$deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
			}

			// translators: %s is the number of characters total.
			echo wp_kses_post( '<p>There ' . sprintf( _n( 'is <strong>%s</strong> queer character', 'are <strong>%s</strong> queer characters', $havecharcount ), $havecharcount ) . ' listed for this show; ' . $deadtext . '.</p>' );

			// If there are regulars...
			if ( isset( $chars_by_role['regular'] ) && is_array( $chars_by_role['regular'] ) ) {
				?>
				<h3 class="title-regulars"><?php echo esc_html( _n( 'Regular', 'Regulars', count( $chars_by_role['regular'] ) ) ); ?> (<?php echo (int) count( $chars_by_role['regular'] ); ?>)</h3>
				<div class="container characters-regulars-container"><div class="row site-loop character-show-loop">
				<?php
				foreach ( $chars_by_role['regular'] as $character ) {
					get_template_part( 'template-parts/excerpt/characters', '', compact( 'character' ) );
				}
				echo '</div></div>';
			}
			// If there are recurring...
			if ( isset( $chars_by_role['recurring'] ) && is_array( $chars_by_role['recurring'] ) ) {
				?>
				<h3 class="title-recurring">Recurring (<?php echo count( $chars_by_role['recurring'] ); ?>)</h3>
				<div class="container characters-recurring-container"><div class="row site-loop character-show-loop">
				<?php
				foreach ( $chars_by_role['recurring'] as $character ) {
					get_template_part( 'template-parts/excerpt/characters', '', compact( 'character' ) );
				}
				echo '</div></div>';
			}
			// If there are guests...
			if ( isset( $chars_by_role['guest'] ) && is_array( $chars_by_role['guest'] ) ) {
				?>
				<h3 class="title-guest"><?php echo esc_html( _n( 'Guest', 'Guests', count( $chars_by_role['guest'] ) ) ); ?> (<?php echo count( $chars_by_role['guest'] ); ?>)</h3>
				<ul class="guest-character-list">
				<?php
				foreach ( $chars_by_role['guest'] as $character ) {
					// Remove any parenthesis from the character display name.
					$guest_char_title = ( str_contains( get_the_title( $character['id'] ), ')' ) ) ? strstr( get_the_title( $character['id'] ), '(', true ) : get_the_title( $character['id'] );
					$grave            = ( has_term( 'dead', 'lez_cliches', $character['id'] ) ) ? '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave-sm">' . lwtv_symbolicons( 'rest-in-peace.svg', 'fa-times-circle' ) . '</span>' : '';
					?>
					<li><a href="<?php the_permalink( $character['id'] ); ?>" title="<?php echo esc_html( $guest_char_title ); ?>" ><?php echo esc_html( $guest_char_title ) . ' ' . $grave; // phpcs:ignore WordPress.Security.EscapeOutput ?></a></li>
					<?php
				}
				echo '</ul>';
			}
		}
		?>
	</div>
</section>

<?php
if ( false !== $rpbt_shortcode ) {
	get_template_part( 'template-parts/partials/shows/like-this', '', compact( 'show_id', 'rpbt_shortcode' ) );
}
