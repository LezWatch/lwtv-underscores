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

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<header id="masthead" class="site-header" role="banner">
	<nav id="site-navigation" class="navbar fixed-top navbar-expand-sm navbar-dark main-nav" role="navigation">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home" class="navbar-brand">
			<?php bloginfo( 'name' ); ?>
		</a>
		<?php
			wp_nav_menu( array(
				'menu'       	=> 'primary',
				'theme_location' => 'primary',
				'depth'      	=> 3,
				'container'  	=> false,
				'link_before'	=> '<span class="menu-link-text">', // Use this for screen readers if using icons 
				'link_after'	=> '</span>', // Use this for screen readers if using icons 
				'menu_class' 	=> 'navbar-nav mr-auto',
				'fallback_cb' 	=> 'wp_page_menu',
				'walker'	 	=> new wp_bootstrap_navwalker(),
			) );
		?>
		<span class="navbar-text">
			Some Navbar text
		</span>

		<?php get_search_form(); ?>
	</nav><!-- #site-navigation -->

	<div class="site-subheader">
		<div class="jumbotron jumbotron-fluid">
			<div class="container">
				<div class="row">
					<div class="col">
						<div class="header-logo">
							<?php yks_the_custom_logo()?>
						</div>
						
						<h1 class="site-title">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
								<?php bloginfo( 'name' ); ?>
							</a>
						</h1>

						<h2 class="site-description">
							<?php bloginfo( 'description' ); ?>
						</h2>
					</div><!-- .col -->
				</div><!-- .row -->
			</div><!-- .container -->
		</div><!-- /.jumbotron -->
	</div><!-- .site-subheader -->
</header><!-- #masthead -->
