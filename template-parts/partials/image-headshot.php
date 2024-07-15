<?php
/**
 * Template part for displaying the character or actor image
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$this_id = $args['to_show'] ?? null;
$format  = $args['format'] ?? 'full';

$thumb_class = ( 'full' === $format ) ? 'rounded float-left' : 'float-left';

// Show Meta
$show_meta    = get_post_meta( $this_id, 'lezchars_show_group', true );
$show_appears = '';

if ( isset( $show_meta ) && ! empty( $show_meta ) && is_array( $show_meta ) ) {
	foreach ( $show_meta as $show ) {
		if ( ! is_array( $show ) || ! isset( $show['show'] ) || ! isset( $show['appears'] ) ) {
			continue;
		}

		$show_id = ( is_array( $show['show'] ) ) ? $show['show'][0] : $show['show'];
		if ( (int) get_the_ID() === (int) $show_id ) {
			sort( $show['appears'] );
			$show_appears = ' (' . implode( ', ', $show['appears'] ) . ')';
		}
	}
}

// Thumbnail attribution
$thumb_attribution = get_post_meta( get_post_thumbnail_id( $this_id ), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title( $this_id ) : get_the_title( $this_id ) . ' &copy; ' . $thumb_attribution;
$thumb_array       = array(
	'class' => 'single-char-img ' . $thumb_class,
	'alt'   => get_the_title( $this_id ),
	'title' => $thumb_title . $show_appears,
);

// Alt Images
$alt_images = ( 'full' === $format ) ? get_post_meta( $this_id, 'lezchars_character_image_group' ) : false;
if ( $alt_images ) {
	$image_tabs = array();
	foreach ( $alt_images[0] as $an_image ) {
		$attr_array   = array(
			'class' => 'single-char-img ' . $thumb_class,
			'alt'   => get_the_title( $this_id ) . ' ' . $an_image['alt_image_text'],
			'title' => $thumb_title . ' - ' . $an_image['alt_image_text'] . $show_appears,
		);
		$image_tabs[] = array(
			'title' => $an_image['alt_image_text'],
			'slug'  => sanitize_title( $an_image['alt_image_text'] ),
			'image' => wp_get_attachment_image( $an_image['alt_image_file_id'], 'character-img', false, $attr_array ),
		);
	}
}

if ( ! has_post_thumbnail( $this_id ) ) {
	?>
	<img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/mystery-woman.jpg" class="single-char-img rounded float-left" alt="<?php echo esc_attr( get_the_title() ); ?>" title="<?php echo esc_attr( get_the_title() ); ?>" />
	<?php
} elseif ( ! isset( $image_tabs ) || ! is_array( $image_tabs ) ) {
	if ( 'excerpt' === $format ) {
		echo '<a href="' . esc_url( get_permalink( $this_id ) ) . '" title="' . esc_attr( $thumb_title ) . '">';
	}
	echo get_the_post_thumbnail( $this_id, 'character-img', $thumb_array );
	if ( 'excerpt' === $format ) {
		echo '</a>';
	}
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
				<?php echo get_the_post_thumbnail( $this_id, 'character-img', $thumb_array ); ?>
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
