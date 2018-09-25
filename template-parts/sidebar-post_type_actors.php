<?php
/**
 * The template for displaying show CPT Character Sidebar
 */

global $post;

$actor_id = $post->ID;

// Do the math to make sure we're up to date.
if ( class_exists( 'LWTV_Actors_Calculate' ) ) {
	LWTV_Actors_Calculate::do_the_math( $actor_id );
}

?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="affiliates" class="widget widget_text">
	<?php
	if ( class_exists( 'LWTV_Affilliates' ) ) {
		echo LWTV_Affilliates::actors( $actor_id, 'widget' ); // WPCS: XSS okay
	}
	?>
</section>
