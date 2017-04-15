<?php
/*
 * This shows the excerpts for show content
 */

global $post;

echo get_the_post_thumbnail( $post->ID, 'home-middle', array( 'class' => 'alignleft', 'alt' => get_the_title($post->ID), 'title' => get_the_title($post->ID) ) );

if((get_post_meta($post->ID, "lezshows_worthit_rating", true))) {
	$thumb_rating = get_post_meta( $post->ID, 'lezshows_worthit_rating', true);
	if ( $thumb_rating == "Yes" ) { $thumb_icon = "thumbs_up.svg"; $thumb_link = "up"; }
	if ( $thumb_rating == "Meh" ) { $thumb_icon = "meh-o.svg"; $thumb_link = "meh"; }
	if ( $thumb_rating == "No" ) { $thumb_icon = "thumbs_down.svg"; $thumb_link = "down"; }
	$thumb_image = file_get_contents(LP_SYMBOLICONS_PATH.'/svg/'.$thumb_icon);
	echo '<a href="/thumbs/'.$thumb_link.'/"><span role="img" aria-label="Worth Watching? '.$thumb_rating.'." title="Worth Watching? '.$thumb_rating.'." class="archive-worthit '.lcfirst($thumb_rating).'">'.$thumb_image.'</span></a>';
}

if((get_post_meta($post->ID, "lezshows_triggerwarning", true))) {
	$trigger_image = file_get_contents(LP_SYMBOLICONS_PATH.'/svg/alert.svg');
	echo '<span role="img" aria-label="This show has a trigger warning!" title="Trigger Warning!" class="archive-trigger">'.$trigger_image.'</span>';
}

$title = '<a href="' . get_permalink( $post->ID ) . '">'.get_the_title($post->ID).'</a>';
if ( get_post_meta( get_the_ID(), 'lezshows_stars', true) ) {
	$color = esc_attr( get_post_meta( get_the_ID(), 'lezshows_stars' , true) );
	$icon = '<a href="/stars/'.$color.'/"><span role="img" aria-label="'.ucfirst($color).' Star Show" title="'.ucfirst($color).' Star Show" class="showlist-star '.$color.'">'.file_get_contents(LP_SYMBOLICONS_PATH.'/svg/star.svg').'</span></a>';
	$title .= ' '.$icon;
}
echo '<h3 class="post-tile">'.$title.'</h3>';

$terms = get_the_terms( $post->ID, 'lez_tropes' );
if ( $terms && ! is_wp_error( $terms ) ) {
	$post_tags = 'Tropes: ';
	// loop over each returned trope

	foreach( $terms as $term ) {
		$iconpath = LP_SYMBOLICONS_PATH.'/svg/';
		$termicon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
		$icon = $termicon ? $termicon.'.svg' : 'square.svg';

		if ( !file_exists( $iconpath . $icon ) ) $icon = 'square.svg';

		$post_tags .= '&nbsp;<a href="'. get_term_link( $term ) .'" rel="tag" class="show tag trope trope-'. $term->slug .'" title="'. $term->name .'"><span role="img" aria-label="'. $term->name .'" title="'. $term->name .'" class="showlist trope trope-'. $term->slug .'">'. file_get_contents( $iconpath . $icon ) .'</span></a>';
	}
	echo '<p class="entry-meta">'.$post_tags.'</p>';
}

the_excerpt();