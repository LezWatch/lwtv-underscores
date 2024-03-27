<?php
/**
 * Template part for displaying ways to watch a show
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
if ( ! $show_id ) {
	return;
}

// Ways to Watch section (yes all ways-to-watch URLs are in a badly named post_meta).
$ways_to_watch = get_post_meta( $show_id, 'lezshows_affiliate', true );
if ( ! $ways_to_watch ) {
	return;
}

echo '<section id="affiliate-watch-link" class="affiliate-watch-container">';
echo lwtv_plugin()->get_ways_to_watch( $show_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</section>';
