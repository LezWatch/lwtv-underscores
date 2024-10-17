<?php
/**
 * Template part for displaying a show's tropes
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
if ( ! $show_id ) {
	return;
}

$tropes = get_the_terms( $show_id, 'lez_tropes' );
?>

<section id="tropes" class="widget widget_tropes">
	<div class="card">
		<div class="card-header">
			<h4>Tropes</h4>
		</div>
		<?php
		if ( ! $tropes || is_wp_error( $tropes ) ) {
			// If there are no tropes, default to NONE
			$trope = get_term_by( 'slug', 'none', 'lez_tropes' );
			?>
			<li class="list-group-item show trope trope-<?php echo esc_attr( $trope->slug ); ?>">
				<a href="<?php echo esc_url( get_term_link( $trope->slug, 'lez_tropes' ) ); ?>" rel="show trope" aria-label="Read more about the trope <?php echo esc_attr( $trope->name ); ?>.">
				<?php
					// Echo the taxonomy icon (default to squares if empty)
					$icon = get_term_meta( $trope->term_id, 'lez_termsmeta_icon', true );
					echo lwtv_plugin()->get_symbolicon( $icon . '.svg', 'fa-lemon' );
				?>
				</a>
				<a href="<?php echo esc_url( get_term_link( $trope->slug, 'lez_tropes' ) ); ?>" rel="show trope" class="trope-link"><?php echo esc_html( $trope->name ); ?></a>
			</li>
			<?php
		} else {
			echo '<ul class="trope-list list-group">';
			// loop over each returned trope
			foreach ( $tropes as $trope ) {
				?>
				<li class="list-group-item show trope trope-<?php echo esc_attr( $trope->slug ); ?>">
					<a href="<?php echo esc_url( get_term_link( $trope->slug, 'lez_tropes' ) ); ?>" rel="show trope" aria-label="Read more about the trope <?php echo esc_attr( $trope->name ); ?>.">
					<?php
						// Echo the taxonomy icon (default to squares if empty)
						$icon = get_term_meta( $trope->term_id, 'lez_termsmeta_icon', true );
						echo lwtv_plugin()->get_symbolicon( $icon . '.svg', 'fa-lemon' );
					?>
					</a>
					<a href="<?php echo esc_url( get_term_link( $trope->slug, 'lez_tropes' ) ); ?>" rel="show trope" class="trope-link"><?php echo esc_html( $trope->name ); ?></a>
				</li>
				<?php
			}
			echo '</ul>';
		}
		?>
	</div>
</section>
