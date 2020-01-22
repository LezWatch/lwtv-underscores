<?php
/*
 * This content is called by all archival displays of characters
 *
 * Thanks to the L Word having over 60 characters (I don't know
 * why I'm surprised by this...) having the full content loop was
 * causing server overload. This code shows the individuals as
 * headshots with alt-text as their character post content.
 *
 * It's used by the following files
 *      - archive-post_type_characters.php
 *      - content-post_type_shows.php
 *      - taxonomy.php
 *
 * @package LezWatch.TV
 */

global $post;

$the_id      = ( isset( $character['id'] ) ) ? $character['id'] : $post->ID;
$the_content = ( isset( $character['content'] ) ) ? $character['content'] : get_the_content();
$alttext     = 'A picture of the character ' . get_the_title( $the_id );
$char_role   = ( isset( $character['role_from'] ) ) ? $character['role_from'] : 'regular';
$archive     = ( is_archive() || is_tax() || is_page() ) ? true : false;

if ( isset( $character['shows'] ) && is_array( $character['shows'] ) ) {
	foreach ( $character['shows'] as $one_show ) {
		if ( (int) $one_show['show'] === (int) $character['show_from'] ) {
			asort( $one_show['appears'] );
			$appears = ' - Years: ' . implode( ', ', $one_show['appears'] );
		}
	}
}

$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? $alttext : $alttext . ' &copy; ' . $thumb_attribution;
$thumb_title       = ( isset( $appears ) ) ? $thumb_title . $appears : $thumb_title;
$thumb_array       = array(
	'class' => 'card-img-top',
	'alt'   => $thumb_title,
	'title' => $thumb_title,
);

// The Steph Adams-Foster Takeover: Reset to prevent Teri Polo from overtaking the world
unset( $shows, $actors, $gender, $sexuality, $cliches, $grave );

// Show a gravestone for recurring characters
if ( ( 'recurring' === $char_role && 'post_type_shows' === get_post_type() ) || 'post_type_actors' === get_post_type() ) {
	$grave = ( has_term( 'dead', 'lez_cliches', $the_id ) ) ? '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave-sm">' . lwtv_symbolicons( 'rest-in-peace.svg', 'fa-ban' ) . '</span>' : '';
}
?>

<div class="card">
	<?php if ( has_post_thumbnail( $the_id ) ) : ?>
		<div class="character-image-wrapper">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php get_the_title( $the_id ); ?>" >
				<?php echo get_the_post_thumbnail( $the_id, 'character-img', $thumb_array ); ?>
			</a>
		</div>
	<?php endif; ?>
	<div class="card-body">
		<h4 class="card-title">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" >
			<?php
			echo esc_html( get_the_title( $the_id ) );
			if ( $archive ) {
				echo lwtv_yikes_chardata( $the_id, 'dead' );
			}
			if ( isset( $grave ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo ' ' . $grave;
			}
			?>
			</a>
		</h4>
		<div class="card-text">
			<?php
			// If we're a regular we show it all
			if ( 'regular' === $char_role && 'post_type_shows' === get_post_type() ) {
				$gender    = lwtv_yikes_chardata( $the_id, 'gender' );
				$sexuality = lwtv_yikes_chardata( $the_id, 'sexuality' );
				$cliches   = lwtv_yikes_chardata( $the_id, 'cliches' );
			}

			if ( ( 'regular' === $char_role && 'post_type_shows' === get_post_type() ) || $archive ) {
				$actors = lwtv_yikes_chardata( $the_id, 'actors' );
			}

			// List of Shows (will not show on show pages)
			if ( 'post_type_characters' === get_post_type() || 'post_type_actors' === get_post_type() ) {
				echo lwtv_yikes_chardata( $the_id, 'oneshow' );
			}

			// List of Actors
			if ( 'post_type_actors' !== get_post_type() ) {
				echo lwtv_yikes_chardata( $the_id, 'oneactor' );
			}

			// Gender and Sexuality
			if ( isset( $gender ) && isset( $sexuality ) ) {
				echo wp_kses_post( '<div class="card-meta-item"> ' . $gender . ' &bull; ' . $sexuality . '</div>' );
			}

			// List of Cliches
			if ( isset( $cliches ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo '<div class="card-meta-item cliches">Clichés: ' . $cliches . '</div>';
			}
			?>
		</div>
	</div>
	<?php
	if ( $archive ) {
		?>
		<div class="card-footer">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" class="btn btn-outline-primary">Character Profile</a>
		</div>
		<?php
	}
	?>
</div><!-- .card -->
