<?php
/**
 * The template for displaying the characters for an actor.
 *
 * @package LezWatch.TV
 */

// Extract variables from $args
$has_char_count = $args['has_char_count'] ?? 0;
$has_dead_count = $args['has_dead_count'] ?? 0;
$all_chars      = $args['all_chars'] ?? array();
?>

<section name="characters" id="characters" class="showschar-section">
	<h2>Characters</h2>
	<div class="card-body">
		<?php
		if ( empty( $has_char_count ) || 0 === (int) $has_char_count ) {
			echo '<p>There are no characters listed yet for this actor.</p>';
		} else {
			$dead_text = 'none are dead';
			if ( $has_dead_count > '0' ) {
				// translators: %s is a number.
				$dead_text = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $has_dead_count ), $has_dead_count );
			}
			// translators: %s is 'are' or a number.
			echo wp_kses_post( '<p>There ' . sprintf( _n( 'is <strong>%s</strong> character', 'are <strong>%s</strong> characters', $has_char_count ), $has_char_count ) . ' listed for this actor; ' . $dead_text . '.</p>' );

			echo '<div class="container characters-regulars-container"><div class="row site-loop character-show-loop">';
			if ( is_array( $all_chars ) ) {
				foreach ( $all_chars as $character ) {
					get_template_part( 'template-parts/excerpt/characters', '', array( 'character' => $character ) );
				}
			}
			echo '</div></div>';
		}
		?>
	</div>
</section>
<?php
