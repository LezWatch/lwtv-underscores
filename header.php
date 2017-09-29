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
	<nav id="site-navigation" class="navbar fixed-top navbar-expand-sm navbar-light bg-light main-nav" role="navigation">
		<div class="container">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home" class="navbar-brand">
				<img src="<?php echo get_template_directory_uri(); ?>/images/lezwatch-logo-icon.png" alt="<?php bloginfo( 'name' ); ?>">
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
					'menu_class' 	=> 'navbar-nav ml-auto',
					'fallback_cb' 	=> 'wp_page_menu',
					'walker'	 	=> new wp_bootstrap_navwalker(),
				) );
			?>

			<span class="nav-item search" id="search-btn">
				<a class="nav-link" data-toggle="collapse" href="#collapseSearch">
					<i class="fa fa-search" aria-hidden="true"></i>
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

	<div class="site-subheader">

		<?php if (is_front_page() ) {?>

			<div class="alert alert-danger" role="alert"> 
				<div class="container">
					<div class="row">
						<div class="col dead-widget-container">    
							<?php dynamic_sidebar( 'dead-1' ); ?>
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
								<?php yks_the_custom_logo()?>
							</div>
						</div>

						<div class="col-sm-9">
							<h1 class="site-description">
								<?php bloginfo( 'description' ); ?>
							</h1>

							<?php while ( have_posts() ) : the_post(); ?>	
								
								<?php the_content(); ?>

							<?php endwhile; ?>
						</div><!-- .col -->
					</div><!-- .row -->
				</div><!-- .container -->
			</div><!-- /.jumbotron -->
  			<div class="rainbow"></div>

		<?php } ?>
	</div><!-- .site-subheader -->
</header><!-- #masthead -->
