<?php
/**
 * The template for displaying all pages.
 *
 * @package YIKES Starter
 */

get_header(); ?>

<div id="main" class="site-main" role="main">

	<?php while ( have_posts() ) : the_post(); ?>

		<?php get_template_part( 'template-parts/content', 'page' ); ?>

	<?php endwhile; // end of the loop. ?>

</div><!-- #main -->

<?php get_footer(); ?>
