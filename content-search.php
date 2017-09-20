<?php
/**
 * The search content template
 *
 * @package YIKES Starter
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h3 class="search-entry-title"><i class="fa fa-search"></i> <a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h3>
	</header><!-- .entry-header -->

	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div><!-- .entry-summary -->
</article><!-- #post-## -->
