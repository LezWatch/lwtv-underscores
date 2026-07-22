<?php
/**
 * The template for displaying the Dark Mode toggle
 *
 * @package LWTV Underscores
 */
?>

<?php get_template_part( 'template-parts/header/svg' ); ?>

<div class="dark-mode-control" role="group" aria-label="Color mode">
	<button type="button" class="dark-mode-segment" data-bs-theme-value="light" aria-label="Light" aria-pressed="false">
		<svg class="bi"><use href="#sun-fill"></use></svg>
		<span class="segment-label d-lg-none">Light</span>
	</button>
	<button type="button" class="dark-mode-segment" data-bs-theme-value="dark" aria-label="Dark" aria-pressed="false">
		<svg class="bi"><use href="#moon-stars-fill"></use></svg>
		<span class="segment-label d-lg-none">Dark</span>
	</button>
	<button type="button" class="dark-mode-segment" data-bs-theme-value="auto" aria-label="Auto" aria-pressed="false">
		<svg class="bi"><use href="#circle-half"></use></svg>
		<span class="segment-label d-lg-none">Auto</span>
	</button>
</div>
