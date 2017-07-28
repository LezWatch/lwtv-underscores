<section id="search" class="widget widget_search"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Search</h4>
	<?php get_search_form(); ?>
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