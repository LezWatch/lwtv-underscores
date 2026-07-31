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
	<section id="intersections" class="widget widget_intersections">
		<div class="card">
			<div class="card-header">
				<h4><?php esc_html_e( 'Intersectionality', 'lwtv' ); ?></h4>
			</div>
				<ul class="intersectionality-list list-group">
					<?php
					// Loop over each returned intersection.
					foreach ( $intersections as $intersection ) {
						?>
						<li class="list-group-item show intersection intersection-<?php echo esc_attr( $intersection->slug ); ?>">
							<a href="<?php echo esc_url( get_term_link( $intersection->slug, 'lez_intersections' ) ); ?>" rel="show intersection" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: intersectionality term name */ __( 'Read more about the positive intersectionality representation of %s.', 'lwtv' ), $intersection->name ) ); ?>">
							<?php
							// Echo the taxonomy icon (default to the wavy flag if empty).
							$icon = get_term_meta( $intersection->term_id, 'lez_termsmeta_icon', true );
							$icon = ( ! empty( $icon ) ) ? $icon : 'flag-wave';
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo lwtv_plugin()->get_symbolicon( svg: $icon . '.svg', icon: 'svg-lemon', max_size: '32' );
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
