<?php
/**
 * Navbar template
 *
 * @package LWTV Underscores
 */
?>

<nav id="site-navigation" class="navbar fixed-top navbar-expand-lg main-nav" role="navigation">
	<div class="container-fluid">
		<div class="screen-reader-text">
			<a href="#main">Skip to Main Content</a>
		</div>

		<a class="navbar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home" class="navbar-brand">
			<span class="navbar-brand-logo">
				<img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/lezwatch-logo-icon.png" alt="Return to <?php bloginfo( 'name' ); ?> homepage" width="72px" height="80px">
			</span>
			<span class="navbar-brand-text">
				<span><?php bloginfo( 'name' ); ?></span>
			</span>
		</a>

		<button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarToggleLWTV" aria-controls="navbarToggleLWTV" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="offcanvas offcanvas-end" tabindex="-1" id="navbarToggleLWTV" aria-labelledby="navbarToggleLWTVLabel">
			<div class="offcanvas-header">
				<span class="offcanvas-title" id="navbarToggleLWTVLabel">Menu</span>
				<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
			</div>

			<div class="offcanvas-body">
				<div class="ms-auto">
					<?php
					wp_nav_menu(
						array(
							'menu'           => 'primary',
							'theme_location' => 'primary',
							'depth'          => 3,
							'container'      => false,
							'menu_class'     => 'navbar-nav justify-content-end flex-grow-1 pe-0',
							'fallback_cb'    => 'wp_page_menu',
							'walker'         => new WP_Bootstrap_Navwalker(),
							'items_wrap'     => '<ul id="%1$s" class="%2$s" aria-labelledby="main-navigation">%3$s</ul>',
						)
					);
					?>
				</div>

				<span class="nav-item search" id="search-btn">
					<a class="nav-link" data-bs-toggle="collapse" role="button" data-bs-target="#collapseSearch" href="#collapseSearch" aria-expanded="false">
						<?php echo lwtv_plugin()->get_symbolicon( 'search.svg', 'fa-search' ); ?>
						<span class="screen-reader-text">Search the Site</span>
					</a>
				</span>

				<span class="dark-mode-toggle">
					<?php get_template_part( 'template-parts/header/dark-mode' ); ?>
				</span>
			</div>
		</div>
	</div>
</nav><!-- #site-navigation -->
