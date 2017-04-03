<?php
/**
 * The template for displaying show CPT single post Sidebar
 */
?>

<section id="search" class="widget widget_search"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Search</h4>
	<?php get_search_form(); ?>
</div></section>

<section id="facet" class="widget widget_facet"><div class="widget-wrap">

	<h4 class="widget-title widgettitle">Stations</h4>
	<?php 
		echo facetwp_display( 'facet', 'show_stations' ); 
	?>

	<h4 class="widget-title widgettitle">Tropes</h4>
	<?php 
		echo facetwp_display( 'facet', 'show_tropes' ); 
	?>

	<h4 class="widget-title widgettitle">Worth It?</h4>
	<?php 
		echo facetwp_display( 'facet', 'show_worthit' ); 
	?>

	<h4 class="widget-title widgettitle">Realness</h4>
	<?php 
		echo facetwp_display( 'facet', 'show_realness' ); 
	?>

	<h4 class="widget-title widgettitle">Quality</h4>
	<?php 
		echo facetwp_display( 'facet', 'show_quality' ); 
	?>

	<h4 class="widget-title widgettitle">Screen Time</h4>
	<?php 
		echo facetwp_display( 'facet', 'show_screentime' ); 
	?>
	
	<button class="facetwp-reset" onclick="FWP.reset()"><?php _e('Reset','rc'); ?></button>
</div></section>

<?php

get_template_part( 'template-parts/widgets/googleads' );