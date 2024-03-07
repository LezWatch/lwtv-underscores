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

<?php
$image = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' );
?>
<!-- Preload the LCP image with a high fetchpriority so it starts loading with the stylesheet. -->
<link rel="preload" fetchpriority="high" as="image" href="<?php echo esc_url( $image[0] ); ?>" type="image/png">

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<header id="masthead" class="site-header" role="banner">
	<nav id="site-navigation" class="navbar fixed-top navbar-expand navbar-light bg-light main-nav" role="navigation">
		<div class="container">
			<div class="screen-reader-text">
				<a href="#main">Skip to Main Content</a>
			</div>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home" class="navbar-brand">
				<img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/lezwatch-logo-icon.png" alt="<?php bloginfo( 'name' ); ?>" width="72px" height="80px">
				<span class="navbar-brand-text">
					<?php bloginfo( 'name' ); ?>
				</span>
			</a>
			<div class="collapse navbar-collapse" id="primary">
			<?php
			wp_nav_menu(
				array(
					'menu'           => 'primary',
					'theme_location' => 'primary',
					'depth'          => 3,
					'container'      => false,
					'menu_class'     => 'navbar-nav ms-auto',
					'fallback_cb'    => 'wp_page_menu',
					'walker'         => new WP_Bootstrap_Navwalker(),
					'items_wrap'     => '<ul id="%1$s" class="%2$s" aria-labelledby="main-navigation">%3$s</ul>',
				)
			);
			?>
			</div>

			<span class="nav-item search" id="search-btn">
				<a class="nav-link" data-bs-toggle="collapse" role="button" data-bs-target="#collapseSearch" href="#collapseSearch" aria-expanded="false">
					<?php echo lwtv_symbolicons( 'search.svg', 'fa-search' ); ?>
					<span class="screen-reader-text">Search the Site</span>
				</a>
			</span>
		</div>
	</nav><!-- #site-navigation -->

	<div class="collapse fixed-top header-search-bar" id="collapseSearch">
		<div class="container">
			<div class="row">
				<div class="col">
					<div class="search-body">
						<div class="search-box">
							<?php get_template_part( 'template-parts/searchbox' ); ?>
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
			<div class="alert alert-danger" role="alert">
				<div class="container">
					<div class="row">
						<div class="col dead-widget-container">
							<center><?php echo wp_kses_post( lwtv_last_death() ); ?></center>
						</div>
					</div>
				</div>
			</div>
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
