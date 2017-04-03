<section id="search" class="widget widget_search"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Search</h4>
	<?php get_search_form(); ?>
</div></section>

<section id="facet" class="widget widget_facet"><div class="widget-wrap">

	<h4 class="widget-title widgettitle">Clichés</h4>
	<?php 
		echo facetwp_display( 'facet', 'char_cliches' ); 
	?>

	<h4 class="widget-title widgettitle">Sexuality</h4>
	<?php 
		echo facetwp_display( 'facet', 'char_sexuality' ); 
	?>

	<h4 class="widget-title widgettitle">Gender</h4>
	<?php 
		echo facetwp_display( 'facet', 'char_gender' ); 
	?>

	<h4 class="widget-title widgettitle">Died</h4>
	<?php 
		echo facetwp_display( 'facet', 'char_dead' ); 
	?>
	
	<button class="facetwp-reset" onclick="FWP.reset()"><?php _e('Reset','rc'); ?></button>
</div></section>

<?php

get_template_part( 'template-parts/widgets/googleads' );