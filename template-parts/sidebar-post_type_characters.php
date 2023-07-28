<?php
/**
 * The template for displaying show CPT Character Sidebar
 *
 * @package LezWatch.TV
 */

global $post;
$char_id = $post->ID;
?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="suggest-edits" class="widget widget_suggestedits">
	<?php get_template_part( 'template-parts/suggestedit', 'form' ); ?>
</section>
