<?php
/**
 * The template for displaying show CPT single post Sidebar
 */
?>

<section id="search" class="widget widget_search"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Search</h4>
	<?php get_search_form(); ?>
</div></section>

<section id="tagcloud" class="widget widget_tags"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Tropes</h4>
		<?php
			$args = array(
				'post_type' => 'post_type_shows',
				'taxonomy'  => array( 'lez_tropes' ),
			);
			wp_tag_cloud( $args );
		?>
</div></section>

<section id="tagcloud" class="widget widget_tags"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Stations</h4>
		<?php
			$args = array(
				'post_type' => 'post_type_shows',
				'taxonomy'  => array( 'lez_stations' ),
			);
			wp_tag_cloud( $args );
		?>
</div></section>

<section id="tagcloud" class="widget widget_tags"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Show Formats</h4>
		<?php
			$args = array(
				'post_type' => 'post_type_shows',
				'taxonomy'  => array( 'lez_formats' ),
			);
			wp_tag_cloud( $args );
		?>
</div></section>

<?php

get_template_part( 'template-parts/widgets/googleads' );