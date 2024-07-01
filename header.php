<?php
/**
 * The Header for our theme.
 *
 * @package YIKES Starter
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

<?php get_template_part( 'template-parts/header/lcp', '', array( 'post_id' => get_the_ID() ) ); ?>

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<header id="masthead" class="site-header" role="banner">

	<?php get_template_part( 'template-parts/header/navbar' ); ?>

	<div class="collapse fixed-top header-search-bar" id="collapseSearch">
		<div class="container">
			<div class="row">
				<div class="col">
					<div class="search-body">
						<div class="search-box">
							<?php get_template_part( 'template-parts/header/searchbox' ); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<a name="top"></a>

	<div class="site-subheader">
		<?php
		if ( is_front_page() && ( 0 == get_query_var( 'page' ) || '' == get_query_var( 'page' ) ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			?>

			<?php get_template_part( 'template-parts/header/last-death' ); ?>

			<div class="jumbotron jumbotron-fluid"
				<?php if ( get_header_image() ) : ?>
					style="background-image: url(<?php header_image(); ?>);"
				<?php endif; ?>
			>
				<div class="container">
					<div class="row">
						<div class="col-sm-3">
							<div class="header-logo">
								<?php the_custom_logo(); ?>
							</div>
						</div>

						<div class="col-sm-9">
							<h1 class="site-description">
								<?php bloginfo( 'description' ); ?>
							</h1>

							<?php
							while ( have_posts() ) :
								the_post();
								the_content();
							endwhile;
							?>
						</div><!-- .col -->
					</div><!-- .row -->
				</div><!-- .container -->
			</div><!-- /.jumbotron -->
			<div class="rainbow"></div>
		<?php } ?>
	</div><!-- .site-subheader -->
</header><!-- #masthead -->
