<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

// Generate list of shows
// Usage: $shows_group
$all_shows   = lwtv_yikes_chardata( get_the_ID(), 'shows' );
$shows_group = array();

if ( '' !== $all_shows && is_array( $all_shows ) ) {
	foreach ( $all_shows as $each_show ) {
		$chartype = ( isset( $each_show['type'] ) ) ? $each_show['type'] . ' character' : '';

		// Years appears
		$appears = '';
		if ( isset( $each_show['appears'] ) && is_array( $each_show['appears'] ) ) {
			sort( $each_show['appears'] );
			$appears = ' - ' . implode( ', ', $each_show['appears'] );
		}

		// Link to Show
		$showlink = '';
		if ( isset( $each_show['show'] ) && '' !== $each_show['show'] ) {

			// If it's an array, de-array it.
			if ( is_array( $each_show['show'] ) ) {
				$each_show['show'] = reset( $each_show['show'] );
			}
			if ( get_post_status( $each_show['show'] ) !== 'publish' ) {
				$showlink = '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em>';
			} else {
				$showlink = '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em>';
			}
		}

		// Output ex: Legends of Tomorrow (regular character)
		$shows_group[] = $showlink . ' <small>(' . $chartype . $appears . ')</small>';
	}
} else {
	$shows_group[] = 'None';
}

// Generate actors
// Usage: $the_actors
$all_actors = lwtv_yikes_chardata( get_the_ID(), 'actors' );
$the_actors = array();

if ( '' !== $all_actors ) {
	foreach ( $all_actors as $each_actor ) {
		if ( get_post_status( $each_actor ) === 'private' ) {
			if ( is_user_logged_in() ) {
				$this_actor = '<a href="' . get_permalink( $each_actor ) . '">' . get_the_title( $each_actor ) . ' - UNLISTED</a>';
			} else {
				$this_actor = '<a href="/actor/unknown/">Unknown</a>';
			}
		} elseif ( get_post_status( $each_actor ) !== 'publish' ) {
			$this_actor = '<span class="disabled-show-link">' . get_the_title( $each_actor ) . '</span>';
		} else {
			$this_actor = '<a href="' . get_permalink( $each_actor ) . '">' . get_the_title( $each_actor ) . '</a>';
		}
		if ( lwtv_yikes_is_queer( $each_actor ) ) {
			$this_actor .= ' <span role="img" aria-label="Queer IRL Actor" data-bs-target="tooltip" title="Queer IRL Actor" class="character-cliche queer-irl">' . lwtv_symbolicons( 'rainbow.svg', 'fa-cloud' ) . '</span>';
		}
		$the_actors[] = $this_actor;
	}
} else {
	$all_actors = array( 'none' );
}

if ( empty( $the_actors ) && has_term( 'cartoon', 'lez_cliches', get_the_ID() ) ) {
	$the_actors = array( 'None' );
} else {
	$the_actors = ( empty( $the_actors ) ) ? array( '<a href="/actor/unknown/">Unknown</a>' ) : $the_actors;
}

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

// Generate list of Cliches
// Usage: $cliches
$cliches = lwtv_yikes_chardata( get_the_ID(), 'cliches' );

// Generate Gender & Sexuality & Romantic Data
// Usage: $gender_sexuality
$gender_sexuality = lwtv_yikes_chardata( get_the_ID(), 'gender' ) . ' &bull; ' . lwtv_yikes_chardata( get_the_ID(), 'sexuality' );
if ( ! is_null( lwtv_yikes_chardata( get_the_ID(), 'romantic' ) ) && lwtv_yikes_chardata( get_the_ID(), 'romantic' ) !== '' ) {
	$gender_sexuality .= ' &bull; ' . lwtv_yikes_chardata( get_the_ID(), 'romantic' );
}

// Microformats Fix
lwtv_microformats_fix( $post->ID );

// Thumbnail attribution
$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;
$thumb_array       = array(
	'class' => 'single-char-img rounded float-left',
	'alt'   => get_the_title(),
	'title' => $thumb_title,
);

// Alt Images
$alt_images = get_post_meta( get_the_ID(), 'lezchars_character_image_group' );
if ( $alt_images ) {
	$image_tabs = array();
	foreach ( $alt_images[0] as $an_image ) {
		$attr_array   = array(
			'class' => 'single-char-img rounded float-left',
			'alt'   => get_the_title() . ' ' . $an_image['alt_image_text'],
			'title' => $thumb_title . ' - ' . $an_image['alt_image_text'],
		);
		$image_tabs[] = array(
			'title' => $an_image['alt_image_text'],
			'slug'  => sanitize_title( $an_image['alt_image_text'] ),
			'image' => wp_get_attachment_image( $an_image['alt_image_file_id'], 'character-img', false, $attr_array ),
		);
	}
}

?>
<div class="card-body">
	<div class="character-image-wrapper">
		<?php
		if ( ! isset( $image_tabs ) || ! is_array( $image_tabs ) ) {
			the_post_thumbnail( 'character-img', $thumb_array );
		} else {
			?>
			<div class="featured-image-tabs ">
				<!-- Nav tabs -->
				<ul class="nav nav-tabs" id="v-pills-tab" role="tablist">
					<li class="nav-item" role="presentation">
						<a class="nav-link active" id="v-pills-primary_image-tab" data-bs-toggle="pill" href="#v-pills-primary_image" role="tab" aria-controls="v-pills-primary_image" aria-selected="true">Primary</a>
					</li>
					<?php
					foreach ( $image_tabs as $a_tab ) {
						?>
						<li class="nav-item" role="presentation">
							<a class="nav-link" id="v-pills-<?php echo esc_attr( $a_tab['slug'] ); ?>-tab" data-bs-toggle="pill" href="#v-pills-<?php echo esc_attr( $a_tab['slug'] ); ?>" role="tab" aria-controls="v-pills-<?php echo esc_attr( $a_tab['slug'] ); ?>" aria-selected="false"><?php echo esc_html( ucfirst( $a_tab['title'] ) ); ?></a>
						</li>
						<?php
					}
					?>
				</ul>
				<!-- Tab panes -->
				<div class="tab-content" id="altImagesContent">
					<div class="tab-pane fade show active" id="v-pills-primary_image" role="tabpanel" aria-labelledby="v-pills-primary_image-tab">
						<?php the_post_thumbnail( 'character-img', $thumb_array ); ?>
					</div>
					<?php
					foreach ( $image_tabs as $a_tab ) {
						?>
						<div class="tab-pane fade" id="v-pills-<?php echo esc_attr( $a_tab['slug'] ); ?>" role="tabpanel" aria-labelledby="v-pills-<?php echo esc_attr( $a_tab['slug'] ); ?>-tab">
							<?php echo wp_kses_post( $a_tab['image'] ); ?>
						</div>
						<?php
					}
					?>
				</div>
			</div>
			<?php
		}
		?>
	</div>

	<div class="card-meta">
		<div class="card-meta-item">
			<table class="table table-sm" style="width: auto !important;">
				<tbody>
					<tr>
						<th scope="row" colspan="2"><center><?php echo wp_kses_post( $gender_sexuality ); ?></center></th>
					</tr>
					<tr>
						<th scope="row">Clichés</th>
						<td><?php echo $cliches; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					</tr>
					<tr>
						<th scope="row">Status</th>
						<td><?php echo wp_kses_post( $doa_status ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo wp_kses_post( _n( 'Actor', 'Actors', count( $all_actors ) ) ); ?></th>
						<td>&bull; <?php echo implode( '<br />&bull; ', $the_actors ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo wp_kses_post( _n( 'Show', 'Shows', count( $shows_group ) ) ); ?></th>
						<td>&bull; <?php echo wp_kses_post( implode( '<br />&bull; ', $shows_group ) ); ?></td>
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
