<?php
/**
 * Template for displaying the show image.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
$size    = $args['size'] ?? 'large';

if ( is_null( $show_id ) || empty( $show_id ) ) {
	return;
}

// Thumbnail attribution.
$thumb_attribution = get_post_meta( get_post_thumbnail_id( $show_id ), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title( $show_id ) : get_the_title( $show_id ) . ' &copy; ' . $thumb_attribution;

// Echo the header image.
the_post_thumbnail(
	$size,
	array(
		'class' => 'card-img-top',
		'alt'   => get_the_title( $show_id ),
		'title' => $thumb_title,
	)
);
