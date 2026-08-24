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

// This reads the raw meta rather than get_field() only to decide whether the
// section is worth opening; get_ways_to_watch() does the real work below. The
// value is the ACF repeater's row count, so '' (never set) and '0' (all rows
// deleted) are both correctly falsy.
//
// It used to also check the legacy 'lezshows_affiliate' key. That key is gone --
// no post carries it, and it is no longer registered -- so the fallback was
// always empty.
if ( ! get_post_meta( $show_id, 'lezshows_waystowatch', true ) ) {
	return;
}

echo '<section id="ways-to-watch-link" class="ways-to-watch-container">';
echo lwtv_plugin()->get_ways_to_watch( $show_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</section>';
