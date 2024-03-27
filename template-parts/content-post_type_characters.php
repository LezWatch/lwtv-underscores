<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$character = $post->ID;

// Generate Status
// Usage: $doa_status
$doa_status = ( has_term( 'dead', 'lez_cliches', $character ) ) ? 'Dead' : 'Alive';

// Generate RIP
// Usage: $rip
$is_dead = get_post_meta( $character, 'lezchars_death_year', true );
if ( $is_dead ) {
	$char_death = ( ! is_array( $is_dead ) ) ? array( $is_dead ) : $is_dead;
	$rip        = array();

	foreach ( $char_death as $death ) {
		if ( '/' !== substr( $death, 2, 1 ) ) {
			$date = date_format( date_create_from_format( 'Y-m-d', $death ), 'd F Y' );
		} else {
			$date = date_format( date_create_from_format( 'm/d/Y', $death ), 'F d, Y' );
		}
		$rip[] = $date;
	}
}

// Microformats Fix
lwtv_microformats_fix( $character );
?>
<div class="card-body">
	<div class="character-image-wrapper">
		<?php
		get_template_part(
			'template-parts/partials/image',
			'headshot',
			array(
				'character' => $post->ID,
				'format'    => 'full',
			)
		);
		?>
	</div>

	<div class="card-character-content">
		<div class="card-meta">
			<div class="card-meta-item">
				<table class="table table-sm" style="width: auto !important;">
					<tbody>
						<tr>
							<th scope="row" colspan="2"><center>
								<?php get_template_part( 'template-parts/partials/characters', 'gender-sexuality', compact( 'character' ) ); ?>
							</center></th>
						</tr>
						<tr>
							<th scope="row">Clichés</th>
							<td><?php echo lwtv_plugin()->get_character_data( $character, 'cliches' ); ?></td>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td><?php echo wp_kses_post( $doa_status ); ?></td>
						</tr>
						<tr>
							<?php get_template_part( 'template-parts/partials/characters', 'actors', compact( 'character' ) ); ?>
						</tr>
						<tr>
							<?php get_template_part( 'template-parts/partials/characters', 'shows', compact( 'character' ) ); ?>
						</tr>
						<?php
						if ( isset( $rip ) ) {
							?>
							<tr>
								<th scope="row">RIP</th>
								<td><?php echo wp_kses_post( implode( ' &bull; ', $rip ) ); ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="characters-description">
			<?php
			// Seems to be running twice, so we need this catch.
			$post_content = get_the_content();
			if ( ! empty( $post_content ) ) {
				echo wp_kses_post( $post_content );
			}
			?>
		</div>
	</div>
</div>
