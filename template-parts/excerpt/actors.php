<?php
/*
 * This content is called by all archival displays of actors
 *
 * Used by the following files
 *      - archive-post_type_actors.php
 *      - taxonomy.php
 *
 * @package LezWatch.TV
 */

$actor = $args['actor'] ?? null;

// The Mirror Gaze Reflection: Make sure the actor is a actor.
// If there is no actor variable, something went wrong, bail out!
if ( ! isset( $actor ) || empty( $actor ) ) {
	return;
}

// If the array with ID is set, use it. Otherwise use the variable directly.
$the_id = $actor['id'] ?? $actor;

// If this is not a actor, something's wrong, bail out!
if ( empty( $the_id ) || 'post_type_actors' !== get_post_type( $the_id ) ) {
	return;
}

$archive = ( is_archive() || is_tax() || is_page() ) ? true : false;

// The Terrible Terri Polo: If we don't reset, her stats apply to everyone.
unset( $shows, $actors, $gender, $sexuality, $cliches );
?>

<div class="card">
	<div class="actor-image-wrapper">
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
					$died = get_post_meta( $the_id, 'lezactors_death', true );
					if ( ! empty( $died ) ) {
						echo '<span role="img" aria-label="Grim Reaper" title="Grim Reaper" class="charlist-grave">' . lwtv_plugin()->get_symbolicon( 'grim-reaper.svg', 'fa-times-circle' ) . '</span>';
					}
				}
				?>
			</a>
		</h4>
		<div class="card-text">
			<?php
			get_template_part(
				'template-parts/partials/actors/gender-sexuality',
				'',
				array(
					'actor'  => $the_id,
					'format' => 'excerpt',
				)
			);
			?>
		</div>
	</div>
	<?php
	if ( $archive ) {
		?>
		<div class="card-footer">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" class="btn btn-outline-primary">Actor Profile</a>
		</div>
		<?php
	}
	?>
</div><!-- .card -->
