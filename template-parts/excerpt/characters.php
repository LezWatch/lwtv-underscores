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

$character = $args['character'] ?? null;
$format    = $args['format'] ?? null;

// The Mirror Gaze Reflection: Make sure the character is a character.
// If there is no character variable, something went wrong, bail out!
if ( ! isset( $character ) || empty( $character ) ) {
	return;
}

// If the array with ID is set, use it. Otherwise use the variable directly.
$the_id = $character['id'] ?? $character;

// If this is not a character, something's wrong, bail out!
if ( empty( $the_id ) || 'post_type_characters' !== get_post_type( $the_id ) ) {
	return;
}

// The Steph Adams-Foster Takeover: Reset to prevent Teri Polo from overtaking the world
unset( $grave, $char_role, $archive, $cliches );

$char_role = $character['role_from'] ?? 'regular';
$archive   = ( is_archive() || is_tax() || is_page() ) ? true : false;
$cliches   = lwtv_plugin()->get_character_data( $the_id, 'cliches' );

// Show a gravestone for recurring characters
if ( ( 'recurring' === $char_role && 'post_type_shows' === get_post_type() ) || 'post_type_actors' === get_post_type() ) {
	$grave = ( has_term( 'dead', 'lez_cliches', $the_id ) ) ? '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave-sm">' . lwtv_plugin()->get_symbolicon( 'rest-in-peace.svg', 'fa-ban' ) . '</span>' : '';
}
?>

<div class="card">
	<div class="character-image-wrapper">
		<?php
		get_template_part(
			'template-parts/partials/image',
			'headshot',
			array(
				'to_show' => $the_id,
				'format'  => 'excerpt',
			)
		);
		?>
	</div>
	<div class="card-body">
		<h4 class="card-title">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" >
			<?php
			echo esc_html( get_the_title( $the_id ) );
			if ( $archive ) {
				echo lwtv_plugin()->get_character_data( $the_id, 'dead' );
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
			// List of Shows (will not show on show pages)
			if ( 'post_type_characters' === get_post_type() || 'post_type_actors' === get_post_type() ) {
				echo lwtv_plugin()->get_character_data( $the_id, 'oneshow' );
			}

			// Actor information
			if ( ( 'regular' === $char_role && 'post_type_shows' === get_post_type() ) || $archive ) {
				get_template_part(
					'template-parts/partials/characters/actors',
					'',
					array(
						'character' => $the_id,
						'format'    => 'oneactor',
					)
				);
			}

			// Gender and Sexuality
			if ( 'regular' === $char_role && 'post_type_shows' === get_post_type() ) {
				get_template_part(
					'template-parts/partials/characters/gender-sexuality',
					'',
					array(
						'character' => $the_id,
						'format'    => 'simple',
					)
				);
			}

			// List of Cliches
			if ( isset( $cliches ) ) {
				?>
				<div class="card-meta-item cliches">
					Clichés: <?php echo $cliches; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<?php
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
