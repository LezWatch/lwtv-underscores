<?php
/*
 * This shows the excerpts for show content
 */

global $post;

// Get Post Thumbnail
$thumbnail = get_the_post_thumbnail( $post->ID, 'home-middle', array( 'class' => 'alignleft', 'alt' => get_the_title($post->ID), 'title' => get_the_title($post->ID) ) );

// Get Thumb
$worthit = '';
if( ( get_post_meta( $post->ID, "lezshows_worthit_rating", true ) ) ) {
	$thumb_rating = get_post_meta( $post->ID, 'lezshows_worthit_rating', true );
	
	switch ( $thumb_rating ) {
		case 'Yes':
			$thumb_icon   = 'thumbs_up.svg';
			break;
		case 'No':
			$thumb_icon   = 'thumbs_down.svg'; 
			break;
		case 'Meh':
			$thumb_icon   = 'meh-o.svg';
			break;
		default:
			$thumb_rating = 'Unrated';
	}

	// Make sure symbolicons exist
	$thumb_image = $thumb_rating;
	if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
		$thumb_request = wp_remote_get( LP_SYMBOLICONS_PATH.''.$thumb_icon );
		$thumb_image   = $thumb_request['body'];
	}
	
	if ( $thumb_rating !== 'Unrated' ) {
		$worthit = '<a href="/shows/?fwp_show_worthit=' . lcfirst( $thumb_rating ) . '"><span role="img" aria-label="Worth Watching? ' . $thumb_rating . '." title="Worth Watching? ' . $thumb_rating . '." class="archive-worthit ' . lcfirst( $thumb_rating ) . '">' . $thumb_image . '</span></a>';
	}
}

// Trigger Warning
$trigger = '';
if ( ( get_post_meta( $post->ID, 'lezshows_triggerwarning', true ) ) ) {

	// Make sure symbolicons exist
	$trigger_image = '⚠';
	if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
		$trigger_request = wp_remote_get( LP_SYMBOLICONS_PATH . 'alert.svg' );
		$trigger_image   = $trigger_request['body'];
	}

	$trigger = '<span role="img" aria-label="This show has a trigger warning!" title="Trigger Warning!" class="archive-trigger archive-trigger-' . get_post_meta( $post->ID, "lezshows_triggerwarning", true ) . '">' . $trigger_image . '</span>';
}

// Show Title
$title = '<a href="' . get_permalink( $post->ID ) . '">' . get_the_title( $post->ID ) . '</a>';

// Add Icon (if the show has a star)
if ( get_post_meta( get_the_ID(), 'lezshows_stars', true ) ) {
	$color = esc_attr( get_post_meta( get_the_ID(), 'lezshows_stars' , true ) );

	// Make sure symbolicons exist
	$star_image = '★';
	if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
		$star_request = wp_remote_get( LP_SYMBOLICONS_PATH.'star.svg' );
		$star_image   = $star_request['body'];
	}

	$icon = '<a href="/shows/?fwp_show_stars=' . $color . '"><span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" title="' . ucfirst( $color ) . ' Star Show" class="showlist-star ' . $color . '">' . $star_image . '</span></a>';
	$title .= ' '.$icon;
}
$title = '<h3 class="post-title excerpt">'.$title.'</h3>';

$terms = get_the_terms( $post->ID, 'lez_tropes' );
if ( $terms && ! is_wp_error( $terms ) ) {
	$post_tags = 'Tropes: ';
	$iconpath = LP_SYMBOLICONS_PATH . '/svg/';

	// loop over each returned trope
	foreach( $terms as $term ) {

		// Make sure symbolicons exist
		$term_image = $term->name;
		if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
			$iconpath = LP_SYMBOLICONS_PATH.'';
			$termicon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
			$tropicon = $termicon ? $termicon : 'square';

			$svg = wp_remote_get( $iconpath . $tropicon . '.svg' );
			if ( $svg['response']['code'] !== '404' ) {
				$term_image = $svg['body'];
			}
		}

		$post_tags .= '&nbsp;<a href="' . get_term_link( $term ) . '" rel="tag" class="show tag trope trope-' . $term->slug . '" title="' . $term->name . '"><span role="img" aria-label="'. $term->name . '" title="' . $term->name . '" class="showlist trope trope-' . $term->slug . '">'. $term_image .'</span></a>';
	}
	$post_tags = '<p class="entry-meta">' . $post_tags . '</p>';
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header"></header>

	<div class="entry-content">
		<?php 
			echo '<a href="' . get_permalink( $post->ID ) . '">' . $thumbnail . '</a>';
			echo $worthit . $trigger . $title . $post_tags;
			the_excerpt();
		?>
	</div>
</article>