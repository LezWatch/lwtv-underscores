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

// Empty when the show has nothing to show: no curated links, and no TMDB
// providers (or the override is on).
$ways_to_watch = lwtv_plugin()->get_ways_to_watch( $show_id );
if ( '' === $ways_to_watch ) {
	return;
}

// Both outputs carry an element with this id: the strip's h2, or the curated label.
echo '<section id="ways-to-watch-link" class="ways-to-watch-container" aria-labelledby="ways-to-watch-title">';
echo $ways_to_watch; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</section>';
