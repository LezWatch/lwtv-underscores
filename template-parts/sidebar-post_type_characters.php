<?php
/**
 * The template for displaying show CPT Character Sidebar
 *
 * @package LezWatch.TV
 */

$char_id = $args['the_post_id'] ?? null;

if ( is_null( $char_id ) || empty( $char_id ) ) {
	return;
}
?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="suggest-edits" class="widget widget_suggestedits">
	<?php get_template_part( 'template-parts/partials/form', 'suggest-edit', array( 'for_post' => $char_id ) ); ?>
</section>

<?php
lwtv_plugin()->get_admin_tools( $char_id );

get_template_part( 'template-parts/partials/sidebar', 'slack' );
