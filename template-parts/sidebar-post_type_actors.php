<?php
/**
 * The template for displaying show CPT Character Sidebar
 *
 * @package LezWatch.TV
 */

$actor_id = $args['the_post_id'] ?? null;

if ( is_null( $actor_id ) || empty( $actor_id ) ) {
	return;
}
?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="suggest-edits" class="widget widget_suggestedits">
	<?php get_template_part( 'template-parts/partials/form', 'suggest-edit', array( 'for_post' => $actor_id ) ); ?>
</section>

<?php
lwtv_plugin()->get_admin_tools( $actor_id );

get_template_part( 'template-parts/partials/sidebar', 'slack' );

