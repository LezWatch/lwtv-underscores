<?php
/*
 * This content is called by all archival displays of characters
 */

global $post;

$the_ID = ( isset( $character['id'] ) )? $character['id'] : $post->ID;
$the_content = ( isset( $character['content'] ) )? $character['content'] : get_the_content();

$alttext = get_the_title( $the_ID ) . ' - ' . wp_strip_all_tags( $the_content );

echo '<a href="' . get_permalink( $the_ID ) . '">' . get_the_post_thumbnail( $the_ID, 'thumbnail', array( 'alt' => $alttext, 'title' => $alttext ) ) . '</a>';

$deadpage = false;
$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
if ( !empty( $term ) && $term->slug == 'dead' ) $deadpage = true;

if ( get_post_meta( $the_ID, 'lezchars_death_year', true ) && $deadpage != true ) {

	// Make sure symbolicons exist
	$iconpath = '⚰';
	if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
		$request  = wp_remote_get( LP_SYMBOLICONS_PATH . 'rip_gravestone.svg' );
		$iconpath = $request['body'];
	}

	echo '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave">' . $iconpath . '</span>';
}

echo '<span class="chartitle"><a href="' . get_permalink( $the_ID ) . '">' . get_the_title($the_ID) . '</a></span>';