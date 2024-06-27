<?php
/**
 * The template for displaying the Dark Mode toggle
 *
 * @package LWTV Underscores
 */
?>

<?php get_template_part( 'template-parts/header/svg' ); ?>

<div class="form-check form-switch">
	<button class="btn btn-link nav-link py-2 px-0 px-lg-2 dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static" aria-label="Toggle theme (light)">
		<svg class="bi my-1 theme-icon-active"><use href="#sun-fill"></use></svg>
		<span class="d-lg-none ms-2 screen-reader-text" id="bd-theme-text">Toggle theme</span>
	</button>
	<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bd-theme-text">
		<li>
		<button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="light" aria-pressed="true">
			<svg class="bi me-2 opacity-50"><use href="#sun-fill"></use></svg>
			Light
			<svg class="bi ms-auto d-none"><use href="#check2"></use></svg>
		</button>
		</li>
		<li>
		<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
			<svg class="bi me-2 opacity-50"><use href="#moon-stars-fill"></use></svg>
			Dark
			<svg class="bi ms-auto d-none"><use href="#check2"></use></svg>
		</button>
		</li>
		<li>
		<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="auto" aria-pressed="false">
			<svg class="bi me-2 opacity-50"><use href="#circle-half"></use></svg>
			Auto
			<svg class="bi ms-auto d-none"><use href="#check2"></use></svg>
		</button>
		</li>
	</ul>


</div>
