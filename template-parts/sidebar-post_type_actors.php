<?php
/**
 * The template for displaying show CPT Character Sidebar
 */

global $post;
$actor_id = $post->ID;

// Do the math to make sure we're up to date.
if ( method_exists( 'LWTV_Actors_Calculate', 'do_the_math' ) ) {
	( new LWTV_Actors_Calculate() )->do_the_math( $actor_id );
}
?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="affiliates" class="widget widget_text">
	<?php
	if ( method_exists( 'LWTV_Affilliates', 'actors' ) ) {
		echo ( new LWTV_Affilliates() )->actors( $actor_id, 'wide' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
</section>
