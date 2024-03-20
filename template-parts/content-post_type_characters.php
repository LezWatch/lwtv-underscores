<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Generate Status
// Usage: $doa_status
$doa_status = ( has_term( 'dead', 'lez_cliches', get_the_ID() ) ) ? 'Dead' : 'Alive';

// Generate RIP
// Usage: $rip
$isdead = get_post_meta( get_the_ID(), 'lezchars_death_year', true );
if ( $isdead ) {
	$char_death = ( ! is_array( $isdead ) ) ? array( $isdead ) : $isdead;
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
lwtv_microformats_fix( $post->ID );
?>
<div class="card-body">
	<div class="character-image-wrapper">
		<?php
		get_template_part(
			'template-parts/partials/output',
			'image',
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
								<?php get_template_part( 'template-parts/partials/output', 'gender-sexuality', array( 'character' => $post->ID ) ); ?>
							</center></th>
						</tr>
						<tr>
							<th scope="row">Clichés</th>
							<td><?php echo lwtv_plugin()->get_character_data( get_the_ID(), 'cliches' ); ?></td>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td><?php echo wp_kses_post( $doa_status ); ?></td>
						</tr>
						<tr>
							<?php get_template_part( 'template-parts/partials/output', 'character-actors', array( 'character' => $post->ID ) ); ?>
						</tr>
						<tr>
							<?php get_template_part( 'template-parts/partials/output', 'shows', array( 'character' => $post->ID ) ); ?>
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
			$post_content = the_content();
			if ( ! empty( $post_content ) ) {
				echo wp_kses_post( $post_content );
			}
			?>
		</div>
	</div>
</div>
