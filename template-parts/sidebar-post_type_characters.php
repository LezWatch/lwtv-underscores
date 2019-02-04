<?php
/**
 * The template for displaying show CPT Character Sidebar
 */

global $post;
$char_id = $post->ID;
?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="affiliates" class="widget widget_text">
	<?php
	if ( class_exists( 'LWTV_Affilliates' ) ) {
		echo LWTV_Affilliates::characters( $char_id, 'wide' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
</section>
