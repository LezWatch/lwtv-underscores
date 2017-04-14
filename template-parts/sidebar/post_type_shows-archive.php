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

	<h4 class="widget-title widgettitle">Tropes</h4>
	<?php
		echo facetwp_display( 'facet', 'show_tropes' );
	?>

	<h4 class="widget-title widgettitle">Formats</h4>
	<?php
		echo facetwp_display( 'facet', 'show_format' );
	?>

	<h4 class="widget-title widgettitle">Worth It?</h4>
	<?php
		echo facetwp_display( 'facet', 'show_worthit' );
	?>

	<h4 class="widget-title widgettitle">Stars</h4>
	<?php
		echo facetwp_display( 'facet', 'show_stars' );
	?>

	<h4 class="widget-title widgettitle">Trigger Warning</h4>
	<?php
		echo facetwp_display( 'facet', 'show_trigger_warning' );
	?>

	<h4 class="widget-title widgettitle">On Air Between...</h4>
	<?php
		echo facetwp_display( 'facet', 'show_airdates' );
	?>

	<h4 class="widget-title widgettitle">&nbsp;</h4>
		<center><button class="facetwp-reset" onclick="FWP.reset()"><?php _e('Reset All Parameters','rc'); ?></button></center>

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

<?php

get_template_part( 'template-parts/widgets/googleads' );