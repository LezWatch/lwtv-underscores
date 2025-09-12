<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$character = $post->ID;

// Is this character created less than 24 hours ago?
$is_new = ( time() - get_the_time( 'U', $character ) ) < DAY_IN_SECONDS;

// Generate Status
// Usage: $doa_status
$doa_status = ( has_term( 'dead', 'lez_cliches', $character ) ) ? 'Dead' : 'Alive';

// Generate RIP
// Usage: $rip_dates
$is_dead = get_post_meta( $character, 'lezchars_death_year', true );
if ( $is_dead ) {
	$char_death = ( ! is_array( $is_dead ) ) ? array( $is_dead ) : $is_dead;
	$rip        = array();

	foreach ( $char_death as $death ) {
		$date  = date_format( date_create_from_format( 'Y-m-d', $death ), 'd F Y' );
		$rip[] = $date;
	}

	// Strike through all dates _EXCEPT_ the last one.
	$rip_total = ( 'Alive' === $doa_status ) ? count( $rip ) : count( $rip ) - 1;
	$rip_dates = array_map(
		function ( $value, $index ) use ( $rip_total ) {
			return ( $index < $rip_total ) ? '<s>' . $value . '</s>' : $value;
		},
		$rip,
		array_keys( $rip )
	);
}

// Microformats Fix
lwtv_plugin()->get_microformats_fix( $character );
?>
<div class="card-body">
	<div class="character-image-wrapper">
		<?php
		get_template_part(
			'template-parts/partials/image',
			'headshot',
			array(
				'to_show' => $post->ID,
				'format'  => 'full',
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
								<?php get_template_part( 'template-parts/partials/characters/gender-sexuality', '', compact( 'character' ) ); ?>
							</center></th>
						</tr>
						<tr>
							<th scope="row">Status</th>
							<td><?php echo wp_kses_post( $doa_status ); ?></td>
						</tr>
						<tr>
							<?php get_template_part( 'template-parts/partials/characters/actors', '', compact( 'character' ) ); ?>
						</tr>
						<tr>
							<?php get_template_part( 'template-parts/partials/characters/shows', '', compact( 'character' ) ); ?>
						</tr>
						<?php
						if ( isset( $rip ) ) {
							?>
							<tr>
								<th scope="row">RIP</th>
								<td><?php echo wp_kses_post( implode( ' &bull; ', $rip_dates ) ); ?></td>
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
			if ( ! empty( get_the_content() ) ) {
				the_content();
			} else {
				the_title( '<p>', ' is a character who has appeared in at least one queer show on TV. Information on this page has not yet been verified. Feel free to <a href="#" data-bs-toggle="modal" data-bs-target="#suggestForm">suggest an edit</a> with any corrections or additions.</p>' );
			}

			lwtv_plugin()->maybe_show_actor_note( $character );
			?>
		</div>
	</div>
</div>
