<?php
/**
 * The template for displaying show CPT Character Sidebar
 */

global $post;
$char_id = $post->ID;
?>

<section id="search" class="widget widget_search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="amazon" class="widget widget_text"><div class="widget-wrap">
	<?php echo LWTV_Amazon::show_amazon( $char_id ); ?>
</div></section>

<section id="tagcloud" class="widget widget_tags"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Sexuality</h4>
		<div class="ratings-container">
			<?php
				$args = array(
					'post_type' => 'post_type_characters',
					'taxonomy'  => array( 'lez_sexuality' ),
				);
				wp_tag_cloud( $args );
			?>
		</div>
</div></section>

<section id="tagcloud" class="widget widget_tags"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Gender Identity</h4>
		<div class="ratings-container">
			<?php
				$args = array(
					'post_type' => 'post_type_characters',
					'taxonomy'  => array( 'lez_gender' ),
				);
				wp_tag_cloud( $args );
			?>
		</div>
</div></section>

<section id="tagcloud" class="widget widget_tags"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Character Clichés</h4>
		<div class="ratings-container">
			<?php
				$args = array(
					'post_type' => 'post_type_characters',
					'taxonomy'  => array( 'lez_cliches' ),
				);
				wp_tag_cloud( $args );
			?>
		</div>
</div></section>