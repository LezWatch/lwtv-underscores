<?php
/**
 * The template for displaying show CPT Character Sidebar
 */

global $post;
$actor_id = $post->ID;
?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="affiliates" class="widget widget_text">
	<?php echo LWTV_Affilliates::actors( $actor_id, 'widget' ); // WPCS: XSS okay ?>
</section>
