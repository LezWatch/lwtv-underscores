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

<section id="tagcloud" class="widget widget-tags">
	<h2 class="widget-title">Sexuality</h2>
	<div class="widget-tags-container">
		<?php
			$args = array(
				'post_type' => 'post_type_characters',
				'taxonomy'  => array( 'lez_sexuality' ),
			);
			wp_tag_cloud( $args );
		?>
	</div>
</section>

<section id="tagcloud" class="widget widget-tags">
	<h2 class="widget-title">Gender Identity</h2>
	<div class="widget-tags-container">
		<?php
			$args = array(
				'post_type' => 'post_type_characters',
				'taxonomy'  => array( 'lez_gender' ),
			);
			wp_tag_cloud( $args );
		?>
	</div>
</section>

<section id="tagcloud" class="widget widget-tags">
	<h2 class="widget-title">Character Clichés</h2>
	<div class="widget-tags-container">
		<?php
			$args = array(
				'post_type' => 'post_type_characters',
				'taxonomy'  => array( 'lez_cliches' ),
			);
			wp_tag_cloud( $args );
		?>
	</div>
</section>

<section id="amazon" class="widget widget_text">
	<?php echo LWTV_Amazon::show_amazon( $char_id ); ?>
</section>