<?php
/**
 * Template part for displaying a show's Intersections
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
if ( ! $show_id ) {
	return;
}

// Intersectionality
$intersections = get_the_terms( $show_id, 'lez_intersections' );

if ( $intersections && ! is_wp_error( $intersections ) ) {
	?>
	<section id="ratings" class="widget widget_intersections">
		<div class="card">
			<div class="card-header">
				<h4>Intersectionality</h4>
			</div>
				<ul class="intersectionality-list list-group">
					<?php
					// loop over each returned trope
					foreach ( $intersections as $intersection ) {
						?>
						<li class="list-group-item show intersection intersection-<?php echo esc_attr( $intersection->slug ); ?>">
							<a href="<?php echo esc_url( get_term_link( $intersection->slug, 'lez_intersections' ) ); ?>" rel="show intersection" aria-label="Read more about the postivie intersectionality representation of <?php echo esc_attr( $intersection->name ); ?>.">
							<?php
							// Echo the taxonomy icon (default to squares if empty)
							$icon = get_term_meta( $intersection->term_id, 'lez_termsmeta_icon', true );
							echo lwtv_plugin()->get_symbolicon( $icon . '.svg', 'fa-lemon' );
							?>
							</a>
							<a href="<?php echo esc_url( get_term_link( $intersection->slug, 'lez_intersections' ) ); ?>" rel="show intersection" class="intersection-link"><?php echo esc_html( $intersection->name ); ?>
							</a>
						</li>
						<?php
					}
					?>
				</ul>
		</div>
	</section>
	<?php
}
?>
