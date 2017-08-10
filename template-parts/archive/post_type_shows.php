<?php
/**
 * Template part for displaying Archive content for Shows CPT
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */

// Get Post Thumbnail
$thumbnail = get_the_post_thumbnail( $post->ID, 'home-middle', array( 'class' => 'alignleft', 'alt' => get_the_title($post->ID), 'title' => get_the_title($post->ID) ) );
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header"></header>

	<div class="entry-content">
		<?php 
			echo '<a href="' . get_permalink( $post->ID ) . '">' . $thumbnail . '</a>';
			echo '<h3 class="post-title excerpt"><a href="' . get_permalink( $post->ID ) . '">' . get_the_title( $post->ID ) . '</a></h3>';
			the_excerpt();
		?>
	</div>
</article>