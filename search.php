<?php
/**
 * The template for displaying Search Results pages.
 *
 * @package YIKES Starter
 */

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<div class="row">
					<div class="col-10"><h1 class="entry-title">
					<?php
						// translators: %s is whatever you just searched for.
						printf( esc_attr__( 'Search Results for: %s', 'lwtv-underscores' ), '<span>' . get_search_query() . '</span>' );
					?>
					</h1></div>
					<div class="col-2 icon plain">
						<span role="img" aria-label="Search Results" title="Search Results" class="taxonomy-svg 404"><?php echo lwtv_symbolicons( 'search.svg', 'fa-search' ); ?></span>
					</div>
				</div>
			</section><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix">
						<?php
						if ( have_posts() ) {
							echo '<div class="row site-loop main-posts-loop four-across-loop">';
							while ( have_posts() ) {
								the_post();
								get_template_part( 'template-parts/content/search' );
							}
							echo '</div>';
							yikes_starter_paging_nav();
						} else {
							get_template_part( 'template-parts/content/none' );
						}
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
