<section id="search" class="widget widget_search"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Search</h4>
	<?php get_search_form(); ?>
</div></section>

<section id="facet" class="widget widget_facet"><div class="widget-wrap">

	<h4 class="widget-title widgettitle">Order Characters By ...</h4>
		<div class="facetwp-sort"><?php echo facetwp_display( 'sort' ); ?></div>

	<?php
		$facets = array (
		'Clichés'   => 'char_cliches',
		'Sexuality' => 'char_sexuality',
		'Gender'    => 'char_gender',
		'Actor(s)'  => 'char_actors',
	);

	foreach ( $facets as $title => $facet ) {
		?>
		<h4 class="widget-title widgettitle"><?php echo $title; ?></h4>
			<?php echo facetwp_display( 'facet', $facet ); ?>
		<?php
	}
	?>

	<h4 class="widget-title widgettitle">&nbsp;</h4>
		<center><button class="facetwp-reset" onclick="FWP.reset()"><?php _e('Reset All Parameters','rc'); ?></button></center>

</div></section>

<?php

get_template_part( 'template-parts/widgets/googleads' );