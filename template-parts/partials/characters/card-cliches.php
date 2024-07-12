<?php
/**
 * Template part for displaying a character's cliches
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$char_id = $args['char_id'] ?? null;
if ( ! $char_id ) {
	return;
}

$cliches = get_the_terms( $char_id, 'lez_cliches' );
?>

<section id="cliches" class="widget widget_cliches">
	<div class="card">
		<div class="card-header">
			<h4>Cliches</h4>
		</div>

		<ul class="cliche-list list-group">
			<?php
			if ( ! $cliches || is_wp_error( $cliches ) ) {
				// If there are no cliches, default to NONE
				$cliche = get_term_by( 'slug', 'none', 'lez_cliches' );
				?>
				<li class="list-group-item show cliche cliche-<?php echo esc_attr( $cliche->slug ); ?>">
					<a href="<?php echo esc_url( get_term_link( $cliche->slug, 'lez_cliches' ) ); ?>" rel="show cliche" aria-label="Read more about the cliche <?php echo esc_attr( $cliche->name ); ?>.">
					<?php
						// Echo the taxonomy icon (default to squares if empty)
						$icon = get_term_meta( $cliche->term_id, 'lez_termsmeta_icon', true );
						echo lwtv_symbolicons( $icon . '.svg', 'fa-lemon' );
					?>
					</a>&nbsp;
					<a href="<?php echo esc_url( get_term_link( $cliche->slug, 'lez_cliches' ) ); ?>" rel="show cliche" class="cliche-link"><?php echo esc_html( $cliche->name ); ?></a>
				</li>
				<?php
			} else {
				// loop over each returned cliche
				foreach ( $cliches as $cliche ) {
					?>
					<li class="list-group-item show cliche cliche-<?php echo esc_attr( $cliche->slug ); ?>">
						<a href="<?php echo esc_url( get_term_link( $cliche->slug, 'lez_cliches' ) ); ?>" rel="show cliche" aria-label="Read more about the cliche <?php echo esc_attr( $cliche->name ); ?>.">
						<?php
							// Echo the taxonomy icon (default to squares if empty)
							$icon = get_term_meta( $cliche->term_id, 'lez_termsmeta_icon', true );
							echo lwtv_symbolicons( $icon . '.svg', 'fa-lemon' );
						?>
						</a>
						<a href="<?php echo esc_url( get_term_link( $cliche->slug, 'lez_cliches' ) ); ?>" rel="show cliche" class="cliche-link"><?php echo esc_html( $cliche->name ); ?></a>
					</li>
					<?php
				}
			}
			?>
		</ul>
	</div>
</section>
