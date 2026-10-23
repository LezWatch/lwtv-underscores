<?php
/**
 * Navbar template
 *
 * @package LWTV Underscores
 */
?>

<nav id="site-navigation" class="navbar fixed-top xl:navbar-expand main-nav" role="navigation">
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

		<button class="navbar-toggler" type="button" data-bs-toggle="drawer" data-bs-target="#navbarToggleLWTV" aria-controls="navbarToggleLWTV" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>

		<dialog class="drawer drawer-end" id="navbarToggleLWTV" aria-labelledby="navbarToggleLWTVLabel">
			<div class="drawer-header">
				<span class="drawer-title" id="navbarToggleLWTVLabel">Menu</span>
				<button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="Close"></button>
			</div>

			<div class="drawer-body">
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
					<?php
					if ( function_exists( 'searchwp_modal_form_trigger' ) ) {
						get_template_part( 'template-parts/header/searchbox-searchwp' );
					} else {
						get_template_part( 'template-parts/header/searchbox' );
					}
					?>
				</span>

				<span class="dark-mode-toggle">
					<?php get_template_part( 'template-parts/header/dark-mode' ); ?>
				</span>
			</div>
		</dialog>
	</div>
</nav><!-- #site-navigation -->
