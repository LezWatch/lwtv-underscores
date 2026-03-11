<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package YIKES Starter
 */

get_header();
?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<div class="row">
					<div class="col-10"><h1 class="entry-title"><?php esc_attr_e( 'Oops! This isn\'t the page you thought it was.', 'lwtv-underscores' ); ?></h1></div>
					<div class="col-2 icon plain"><span role="img" aria-label="404" title="404 - Page Not Found" class="taxonomy-svg 404">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput -- We're outputting SVGs
						echo lwtv_plugin()->get_symbolicon( svg: 'easter-egg-alt.svg', icon: 'svg-easter-egg-alt', max_size: '50' );
						?>
					</span></div>
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
						<article id="post-0" class="post not-found">
							<div class="entry-content clearfix">
								<p><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/rose.gif" alt="<?php esc_attr_e( 'Rose revealing herself by peeling off a face mask in Jane the Virgin', 'lwtv-underscores' ); ?>" class="alignleft"/></p>
								<?php get_template_part( 'template-parts/partials/ai/discovery-404' ); ?>
							</div><!-- .entry-content -->
						</article><!-- #post-0 .post .not-found -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
