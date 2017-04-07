<?php
/**
 * The front page template file
 *
 * If the user has selected a static page for their homepage, this is what will
 * appear.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */

get_header(); ?>

	<div id="primary" class="content-area">
		
		<main id="main" class="site-main" role="main">

		<?php
		if ( is_dynamic_sidebar( 'front-page-top' ) ) { 
			dynamic_sidebar( 'front-page-top' );
		}
		
		if ( have_posts() ) :

			if ( is_home() && ! is_front_page() ) : ?>
				<header>
					<h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
				</header>

			<?php
			elseif ( is_front_page() ) :?>
				<header>
					<h1 class="page-title">Recent Posts</h1>
				</header>
			<?php
			
			endif;
	
			/* Start the Loop */
			while ( have_posts() ) : the_post();

				/*
				 * Include the Post-Format-specific template for the content.
				 * If you want to override this in a child theme, then include a file
				 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
				 */
				get_template_part( 'template-parts/content/'.get_post_type() );

			endwhile;

			lwtv_underscore_numeric_posts_nav();

		else :

			get_template_part( 'template-parts/content/none' );

		endif; 
		
		if ( is_dynamic_sidebar( 'front-page-bottom' ) ) dynamic_sidebar( 'front-page-bottom' );
		
		?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();
