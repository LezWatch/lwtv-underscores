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
$rounded = $args['rounded'] ?? true;

$thumb_class      = ( $rounded ) ? 'rounded float-left' : 'float-left';
$post_format      = get_post_format( $this_id );
$additional_title = '';

if ( 'post_type_characters' === get_post_type( $this_id ) ) {
	// Show Meta
	$show_meta = get_field( 'lezchars_show_group', $this_id );

	if ( is_array( $show_meta ) && ! empty( $show_meta ) ) {
		foreach ( $show_meta as $show ) {
			if ( ! is_array( $show ) || ! isset( $show['show'] ) || ! isset( $show['appears'] ) ) {
				continue;
			}

			$show_id = ( is_array( $show['show'] ) ) ? $show['show'][0] : $show['show'];
			if ( (int) get_the_ID() === (int) $show_id ) {
				sort( $show['appears'] );
				$additional_title = ' (' . implode( ', ', $show['appears'] ) . ')';
			}
		}
	}
}

// Thumbnail attribution
$thumb_attribution = get_post_meta( get_post_thumbnail_id( $this_id ), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title( $this_id ) : get_the_title( $this_id ) . ' &copy; ' . $thumb_attribution;
$thumb_array       = array(
	'class' => 'single-char-img ' . $thumb_class,
	'alt'   => get_the_title( $this_id ),
	'title' => $thumb_title . $additional_title,
);

// Alt Images
$alt_images = ( 'full' === $format ) ? get_field( 'lezchars_character_image_group', $this_id ) : false;
if ( is_array( $alt_images ) && ! empty( $alt_images ) ) {
	$image_tabs = array();
	foreach ( $alt_images as $attach_id ) {
		$attach_title = get_the_title( $attach_id );
		$attr_array   = array(
			'class' => 'single-char-img ' . $thumb_class,
			'alt'   => get_the_title( $this_id ) . ' ' . $attach_title,
			'title' => $thumb_title . ' - ' . $attach_title . $additional_title,
		);
		$image_tabs[] = array(
			'title' => $attach_title,
			'slug'  => sanitize_title( $attach_title ),
			'image' => wp_get_attachment_image( $attach_id, 'character-img', false, $attr_array ),
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
	$thumb_array['class'] = str_replace( 'rounded', 'rounded-bottom', $thumb_array['class'] );
	?>
	<div class="featured-image-tabs">
		<!-- Nav tabs -->
		<ul class="nav nav-tabs" id="char-image-tabs" role="tablist">
			<li class="nav-item" role="presentation">
				<a class="nav-link active" id="char-tab-primary_image" data-bs-toggle="tab" href="#char-pane-primary_image" role="tab" aria-controls="char-pane-primary_image" aria-selected="true"><?php esc_html_e( 'Primary', 'lwtv' ); ?></a>
			</li>
			<?php
			foreach ( $image_tabs as $a_tab ) {
				?>
				<li class="nav-item" role="presentation">
					<a class="nav-link" id="char-tab-<?php echo esc_attr( $a_tab['slug'] ); ?>" data-bs-toggle="tab" href="#char-pane-<?php echo esc_attr( $a_tab['slug'] ); ?>" role="tab" aria-controls="char-pane-<?php echo esc_attr( $a_tab['slug'] ); ?>" aria-selected="false"><?php echo esc_html( ucfirst( $a_tab['title'] ) ); ?></a>
				</li>
				<?php
			}
			?>
		</ul>
		<!-- Tab panes -->
		<div class="tab-content" id="char-image-tabs-content">
			<div class="tab-pane fade show active" id="char-pane-primary_image" role="tabpanel" aria-labelledby="char-tab-primary_image">
				<?php echo get_the_post_thumbnail( $this_id, 'character-img', $thumb_array ); ?>
			</div>
			<?php
			foreach ( $image_tabs as $a_tab ) {
				?>
				<div class="tab-pane fade" id="char-pane-<?php echo esc_attr( $a_tab['slug'] ); ?>" role="tabpanel" aria-labelledby="char-tab-<?php echo esc_attr( $a_tab['slug'] ); ?>">
					<?php echo wp_kses_post( $a_tab['image'] ); ?>
				</div>
				<?php
			}
			?>
		</div>
	</div>
	<?php
}
