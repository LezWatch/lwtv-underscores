<?php
/**
 * The search content template
 *
 * @package YIKES Starter
 */

switch ( get_post_type( $post->ID ) ) {
	case 'post_type_shows':
		$searchicon = 'fa-television';
		break;
	case 'post_type_characters':
		$searchicon = 'fa-female';
		break;
	default:
		$searchicon = 'fa-search';
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h3 class="search-entry-title"><i class="fa <?php echo $searchicon; ?>"></i> <a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h3>
	</header><!-- .entry-header -->

	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div><!-- .entry-summary -->
</article><!-- #post-## -->