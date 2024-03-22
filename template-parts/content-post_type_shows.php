<?php
/**
 * Template part for displaying shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id        = $post->ID;
$slug           = get_post_field( 'post_name', get_post( $show_id ) );
$get_tags       = get_term_by( 'name', $slug, 'post_tag' );
$related        = lwtv_plugin()->has_cpt_related_posts( $slug );
$rpbt_shortcode = lwtv_plugin()->get_shows_like_this_show( $show_id );

// Microformats Fix.
lwtv_microformats_fix( $show_id );

get_template_part( 'template-parts/partials/shows', 'image', array( 'show_id' => $show_id ) );
?>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Table of Contents</h4>
		<a class="breadcrumb-item smoothscroll" href="#overview">Overview</a>
		<?php
		if ( get_post_meta( $show_id, 'lezshows_plots', true ) && '<p><br data-mce-bogus="1"></p>' !== get_post_meta( $show_id, 'lezshows_plots', true ) ) {
			?>
			<a class="breadcrumb-item smoothscroll" href="#timeline">Timeline</a>
			<?php
		}
		if ( get_post_meta( $show_id, 'lezshows_episodes', true ) && '<p><br data-mce-bogus="1"></p>' !== get_post_meta( $show_id, 'lezshows_episodes', true ) ) {
			?>
			<a class="breadcrumb-item smoothscroll" href="#episodes">Episodes</a>
			<?php
		}
		if ( $related ) {
			// Related Posts (if available).
			?>
			<a class="breadcrumb-item smoothscroll" href="#related-posts">Articles</a>
			<?php
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
// The Game of Thrones Flag of Gratuitous Violence.
// This shows a notice if the show has trigger warnings.
$warning    = lwtv_plugin()->get_show_content_warning( $show_id );
$warn_image = lwtv_symbolicons( 'hand.svg', 'fa-hand-paper' );

if ( is_array( $warning ) && 'none' !== $warning['card'] ) {
	?>
	<section id="trigger-warning" class="trigger-warning-container">
		<div class="alert alert-<?php echo esc_attr( $warning['card'] ); ?>" role="alert">
			<span class="callout-<?php echo esc_attr( $warning['card'] ); ?>" role="img" aria-label="Warning Hand" title="Warning Hand"><?php echo $warn_image; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<?php echo wp_kses_post( $warning['content'] ); ?>
		</div>
	</section>
	<?php
}
?>

<?php
// Ways to Watch section (yes all ways-to-watch URLs are in a badly named post_meta).
if ( ( get_post_meta( $show_id, 'lezshows_affiliate', true ) ) ) {
	echo '<section id="affiliate-watch-link" class="affiliate-watch-container">' . lwtv_plugin()->get_ways_to_watch( $show_id ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>

<section class="showschar-section" name="overview" id="overview">
	<h2>Overview</h2>
	<div class="card-body">
		<?php the_content(); ?>
	</div>
</section>

<?php
// Queer Plots - Only display if they exist.
if ( ( get_post_meta( $show_id, 'lezshows_plots', true ) && '<p><br data-mce-bogus="1"></p>' !== get_post_meta( $show_id, 'lezshows_plots', true ) ) ) {
	?>
	<section name="timeline" id="timeline" class="showschar-section">
		<h2>Queer Plotline Timeline</h2>
		<div class="card-body">
			<?php echo wp_kses_post( apply_filters( 'the_content', get_post_meta( $show_id, 'lezshows_plots', true ) ) ); ?>
		</div>
	</section>
	<?php
}

// Best Episodes - Only display if they exist.
if ( ( get_post_meta( $show_id, 'lezshows_episodes', true ) && '<p><br data-mce-bogus="1"></p>' !== get_post_meta( $show_id, 'lezshows_episodes', true ) ) ) {
	?>
	<section name="episodes" id="episodes" class="showschar-section">
		<h2>Notable Queer-Centric Episodes</h2>
		<div class="card-body">
			<?php echo wp_kses_post( get_post_meta( $show_id, 'lezshows_episodes', true ) ); ?>
		</div>
	</section>
	<?php
}

get_template_part( 'template-parts/partials/related', 'posts', array( 'to_check' => $the_id ) );

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
					get_template_part( 'template-parts/excerpt', 'post_type_characters', array( 'character' => $character ) );
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
					get_template_part( 'template-parts/excerpt', 'post_type_characters', array( 'character' => $character ) );
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
					$grave = ( has_term( 'dead', 'lez_cliches', $character['id'] ) ) ? '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave-sm">' . lwtv_symbolicons( 'rest-in-peace.svg', 'fa-times-circle' ) . '</span>' : '';
					?>
					<li><a href="<?php the_permalink( $character['id'] ); ?>" title="<?php echo esc_html( get_the_title( $character['id'] ) ); ?>" ><?php echo esc_html( get_the_title( $character['id'] ) ) . ' ' . $grave; // phpcs:ignore WordPress.Security.EscapeOutput ?></a></li>
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
	get_template_part(
		'template-parts/partials/shows',
		'like-this',
		array(
			'show_id' => $the_id,
			'similar' => $rpbt_shortcode,
		)
	);
}
