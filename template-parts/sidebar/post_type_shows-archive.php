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

	<h4 class="widget-title widgettitle">Order Shows By ...</h4>
		<div class="facetwp-sort"><?php echo facetwp_display( 'sort' ); ?></div>

	<?php
		$facets = array (
		'Tropes'            => 'show_tropes',
		'Genres'            => 'show_genres',
		'Formats'           => 'show_format',
		'Worth It?'         => 'show_worthit',
		'Stars'             => 'show_stars',
		'Trigger Warning'   => 'show_trigger_warning',
		'On Air Between...' => 'show_airdates',
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