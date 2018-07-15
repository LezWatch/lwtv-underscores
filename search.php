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
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><h1 class="entry-title">
					<?php
						// translators: %s is whatever you just searched for
						printf( esc_attr__( 'Search Results for: %s', 'yikes_starter' ), '<span>' . get_search_query() . '</span>' );
					?>
					</h1></div>
					<div class="col-2 icon plain"><span role="img" aria-label="Search Results" title="Search Results" class="taxonomy-svg 404"><?php echo lwtv_yikes_symbolicons( 'search.svg', 'fa-search' ); ?></span></div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<?php
						if ( have_posts() ) :
							echo '<div class="row site-loop main-posts-loop equal-height">';
							while ( have_posts() ) :
								the_post();
								echo '<div class="col-sm-3">';
								get_template_part( 'template-parts/content', 'search' );
								echo '</div>';
							endwhile;
							echo '</div>';
							echo '<!-- Alt Bootstrap pagination is page_navi() -->';
							yikes_starter_paging_nav();
						else :
							get_template_part( 'template-parts/content', 'none' );
						endif;
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
