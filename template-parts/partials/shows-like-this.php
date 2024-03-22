<?php
/**
 * Template part for displaying shows like this.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
$similar = $args['similar'] ?? false;

if ( is_null( $show_id ) || empty( $show_id ) || ! $similar ) {
	return;
}
?>
<section name="similar-shows" id="related-posts" class="showschar-section">
	<h2>Similar Shows</h2>
	<div class="card-body">
		<p>If you like <em><?php echo esc_html( get_the_title( $show_id ) ); ?></em> you may also like these shows.</p>
		<?php
			echo wp_kses_post( $similar );
		?>
	</div>
</section>
<?php

