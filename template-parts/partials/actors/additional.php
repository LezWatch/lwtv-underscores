<?php
/**
 * The template for displaying additional information for an actor.
 *
 * @package LezWatch.TV
 *
 */

// Extract variables from $args
$actor_id       = $args['actor_id'] ?? 0;
$has_char_count = $args['has_char_count'] ?? 0;
$related        = $args['related'] ?? false;
?>

<section name="overlays" id="overlays" class="overlay-section">
	<h2>Additional Information</h2>
	<div class="overlay-container">
		<div class="row">
		<?php

		if ( 0 !== $has_char_count ) {
			get_template_part( 'template-parts/overlays/statistics', 'actors', compact( 'actor_id' ) );
		}

		if ( isset( $related ) && $related ) {
			get_template_part( 'template-parts/overlays/related-articles', '', array( 'to_show' => $actor_id ) );
		}
		?>
		</div>
	</div>
</section>
