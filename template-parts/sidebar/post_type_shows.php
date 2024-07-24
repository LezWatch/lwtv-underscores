<?php
/**
 * The template for displaying show CPT Archive Sidebar
 *
 * @package LezWatch.TV
 */

$show_id = $args['the_post_id'] ?? null;

if ( is_null( $show_id ) || empty( $show_id ) ) {
	return;
}
?>

<section id="search" class="widget widget_search">
	<?php get_search_form(); ?>
</section>

<section id="suggest-edits" class="widget widget_suggestedits">
	<?php get_template_part( 'template-parts/overlays/form', 'suggest-edit', array( 'for_post' => $show_id ) ); ?>
</section>

<?php
lwtv_plugin()->get_admin_tools( $show_id );

get_template_part( 'template-parts/partials/shows/card', 'worthit', array( 'show_id' => $show_id ) );
get_template_part( 'template-parts/partials/shows/card', 'altnames', array( 'show_id' => $show_id ) );
get_template_part( 'template-parts/partials/shows/card', 'tropes', array( 'show_id' => $show_id ) );
get_template_part( 'template-parts/partials/shows/card', 'intersections', array( 'show_id' => $show_id ) );
get_template_part( 'template-parts/partials/shows/card', 'ratings', array( 'show_id' => $show_id ) );

get_template_part( 'template-parts/partials/sidebar', 'slack' );
