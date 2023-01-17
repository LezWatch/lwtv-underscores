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
$related        = ( new LWTV_Related_Posts() )->are_there_posts( $slug );
$rpbt_shortcode = ( new LWTV_Shows_Like_This() )->generate( $show_id );

// Microformats Fix.
lwtv_microformats_fix( $post->ID );

// Thumbnail attribution.
$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;

// Echo the header image.
the_post_thumbnail(
	'large',
	array(
		'class' => 'card-img-top',
		'alt'   => get_the_title(),
		'title' => $thumb_title,
	)
);
?>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Table of Contents</h4>
		<a class="breadcrumb-item smoothscroll" href="#overview">Overview</a>
		<?php
		if ( get_post_meta( get_the_ID(), 'lezshows_plots', true ) && '<p><br data-mce-bogus="1"></p>' !== get_post_meta( $show_id, 'lezshows_plots', true ) ) {
			?>
			<a class="breadcrumb-item smoothscroll" href="#timeline">Timeline</a>
			<?php
		}
		if ( get_post_meta( get_the_ID(), 'lezshows_episodes', true ) && '<p><br data-mce-bogus="1"></p>' !== get_post_meta( $show_id, 'lezshows_episodes', true ) ) {
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
$warning    = lwtv_yikes_content_warning( get_the_ID() );
$warn_image = lwtv_symbolicons( 'hand.svg', 'fa-hand-paper' );

if ( 'none' !== $warning['card'] ) {
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
if ( ( get_post_meta( $show_id, 'lezshows_affiliate', true ) ) ) {
	echo '<section id="affiliate-watch-link" class="affiliate-watch-container">' . ( new LWTV_Affilliates() )->shows( $show_id, 'affiliate' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

if ( $related ) {
	?>
	<section name="related-posts" id="related-posts" class="showschar-section">
		<h2>Articles</h2>
		<div class="card-body">
			<?php
			if ( method_exists( 'LWTV_Related_Posts', 'related_posts' ) && method_exists( 'LWTV_Related_Posts', 'count_related_posts' ) ) {
				echo ( new LWTV_Related_Posts() )->related_posts( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( count( ( new LWTV_Related_Posts() )->count_related_posts( $slug ) ) > '5' ) {
					$get_tags = term_exists( $slug, 'post_tag' );
					if ( ! is_null( $get_tags ) && $get_tags >= 1 ) {
						echo '<p><a href="' . esc_url( get_tag_link( $get_tags['term_id'] ) ) . '">Read More ...</a></p>';
					}
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
		$havecharcount = lwtv_list_characters( $show_id, 'count' );
		$havedeadcount = lwtv_list_characters( $show_id, 'dead' );

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

			// Get the list of REGULAR characters.
			$chars_regular = lwtv_get_chars_for_show( $show_id, $havecharcount, 'regular' );
			if ( ! empty( $chars_regular ) ) {
				?>
				<h3 class="title-regulars"><?php echo esc_html( _n( 'Regular', 'Regulars', count( $chars_regular ) ) ); ?> (<?php echo (int) count( $chars_regular ); ?>)</h3>
				<div class="container characters-regulars-container"><div class="row site-loop character-show-loop">
				<?php
				foreach ( $chars_regular as $character ) {
					include locate_template( 'template-parts/excerpt-post_type_characters.php' );
				}
				echo '</div></div>';
			}
			// Get the list of RECURRING characters.
			$chars_recurring = lwtv_get_chars_for_show( $show_id, $havecharcount, 'recurring' );
			if ( ! empty( $chars_recurring ) ) {
				?>
				<h3 class="title-recurring">Recurring (<?php echo count( $chars_recurring ); ?>)</h3>
				<div class="container characters-recurring-container"><div class="row site-loop character-show-loop">
				<?php
				foreach ( $chars_recurring as $character ) {
					include locate_template( 'template-parts/excerpt-post_type_characters.php' );
				}
				echo '</div></div>';
			}
			// Get the list of GUEST characters.
			$chars_guest = lwtv_get_chars_for_show( $show_id, $havecharcount, 'guest' );
			if ( ! empty( $chars_guest ) ) {
				?>
				<h3 class="title-guest"><?php echo esc_html( _n( 'Guest', 'Guests', count( $chars_guest ) ) ); ?> (<?php echo count( $chars_guest ); ?>)</h3>
				<ul class="guest-character-list">
				<?php
				foreach ( $chars_guest as $character ) {
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
	?>
	<section name="similar-shows" id="related-posts" class="showschar-section">
		<h2>Similar Shows</h2>
		<div class="card-body">
			<p>If you like <em><?php echo esc_html( get_the_title() ); ?></em> you may also like these shows.</p>
			<?php
				echo wp_kses_post( $rpbt_shortcode );
			?>
		</div>
	</section>
	<?php
}
