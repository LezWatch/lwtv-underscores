<?php
/**
 * The Template for inserting header images as LCP HIGH.
 *
 * @package LezWatch.TV
 */

$this_id = $args['post_id'] ?? null;

?>
<!-- Preload the LCP images with a high fetchpriority so it starts loading with the stylesheet. -->
<?php

// All Pages
$logo = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' );
if ( false !== $logo ) {
	$logo_image = $logo[0];
	$logo_type  = substr( $logo_image, strrpos( $logo_image, '.' ) + 1 );
	?>
	<link rel="preload" fetchpriority="high" as="image" href="<?php echo esc_url( $logo_image ); ?>" type="image/<?php echo esc_attr( $logo_type ); ?>">
	<?php
}

if ( is_front_page() && has_header_image() ) {
	// Front page
	$featured_image = get_header_image();
} elseif ( is_404() ) {
	$featured_image = get_template_directory_uri() . '/images/rose.gif';
} elseif ( has_post_thumbnail( $this_id ) ) {
	// Regular post/page
	$featured_size = array(
		'post'                 => 'large',
		'page'                 => 'large',
		'post_type_actors'     => 'character-img',
		'post_type_shows'      => 'show-img',
		'post_type_characters' => 'character-img',
	);

	$featured_image = get_the_post_thumbnail_url( $this_id, $featured_size[ get_post_type( $this_id ) ] );
} else {
	// Collect all attached images and use the first one.
	$all_images = get_attached_media( 'image', $this_id ) ?? array();

	foreach ( $all_images as $img ) {
		$img_src = wp_get_attachment_image_src( $img->ID, 'large' );
		break;
	}

	$featured_image = $img_src[0] ?? '';
}

if ( isset( $featured_image ) && ! empty( $featured_image ) ) {
	$image_type = substr( $featured_image, strrpos( $featured_image, '.' ) + 1 );
	?>
	<link rel="preload" fetchpriority="high" as="image" href="<?php echo esc_url( $featured_image ); ?>" type="image/<?php echo esc_attr( $image_type ); ?>">
	<?php
}
