<?php
/**
 * Template part for displaying a message that posts cannot be found
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */
 
// Post Thumbnail
$thumbnail = get_the_post_thumbnail( $post->ID, 'character-img', array( 'alt' => get_the_title($post->ID), 'title' => get_the_title($post->ID) ) );

// Are they dead?
$tombstone = '';
if ( has_term( 'dead', 'lez_cliches' ) && get_post_meta( $post->ID, 'lezchars_death_year', true ) ) {
	$tombstone = '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave">' . file_get_contents( LWTV_SYMBOLICONS_PATH.'/svg/rip_gravestone.svg' ) . '</span>';
}

// Character title and link
$chartitle = '<a href="' . get_permalink( $post->ID ) . '">' . get_the_title( $post->ID ) . '</a>';

$character_show_IDs = get_post_meta($post->ID, 'lezchars_show_group', true);
$show_title = array();

foreach ( $character_show_IDs as $each_show ) {
	// if the show isn't published, no links
	if ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
		array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em>' );
	} else {
		array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em>' );
	}
}

$showtitle = implode(", ", $show_title );

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header"></header>
	
	<div class="entry-content">
		<?php echo '<a href="' . get_permalink( $post->ID ) . '">' . $thumbnail . '</a>'; ?>
		<p>
			<span class="chartitle"><?php echo $tombstone.$chartitle; ?></span>
			<br><span class="showtitle"><?php echo $showtitle; ?></span>
		</p>
	</div>
</article>