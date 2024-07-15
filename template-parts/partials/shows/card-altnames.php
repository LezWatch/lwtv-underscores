<?php
/**
 * Template part for displaying a show's alternate names
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
if ( ! $show_id ) {
	return;
}

$alt_names = ( get_post_meta( $show_id, 'lezshows_show_names', true ) ) ? get_post_meta( $show_id, 'lezshows_show_names', true ) : false;

if ( false !== $alt_names && ! empty( $alt_names ) ) {
	?>
	<section id="alt-names" class="widget widget_altnames">
		<div class="card">
			<div class="card-header">
				<h4>Also Known As</h4>
			</div>

			<ul class="name-list list-group">
				<?php
				foreach ( $alt_names as $aka ) {
					?>
						<li class="list-group-item show name lang-<?php echo esc_attr( $aka['type'] ); ?>">
							<em><?php echo esc_html( $aka['lezshows_alt_show_name'] ); ?></em> (<?php echo esc_attr( $aka['type'] ); ?>)
						</li>
					<?php
				}
				?>
			</ul>
		</div>
	</section>
	<?php
}
