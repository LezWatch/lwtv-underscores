<?php
/**
 * The template for displaying show CPT Archive Sidebar
 */

global $post;

$show_id    = $post->ID;
$slug       = get_post_field( 'post_name', get_post( $show_id ) );
$term       = term_exists( $slug , 'post_tag' );
$worthit    = get_post_meta( $show_id, 'lezshows_worthit_rating', true );
$realness   = min( (int) get_post_meta($show_id, 'lezshows_realness_rating', true), 5 );
$quality    = min ( (int) get_post_meta($show_id, 'lezshows_quality_rating', true), 5 );
$screentime = min( (int) get_post_meta($show_id, 'lezshows_screentime_rating', true), 5 );
?>

<section id="search" class="widget widget_search"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Search</h4>
	<?php get_search_form(); ?>
</div></section>

<section id="toc" class="widget widget_text"><div class="widget-wrap">
		<h4 class="widget-title widgettitle">Table of Contents</h4>
		<ul>
			<li><a href="#overview">Overview</a></li>
			<?php
			if( ( get_post_meta( get_the_ID(), 'lezshows_plots', true) ) ) {
				?><li><a href="#timeline">Timeline</a></li><?php
			}
			if( ( get_post_meta( get_the_ID(), 'lezshows_episodes', true) ) ) {
				?><li><a href="#episodes">Episodes</a></li><?php
			}
			if ( $term !== 0 && $term !== null ) {
				?><li><a href="#related-posts">Related Posts</a></li><?php
			}
			?>
			<li><a href="#characters">Characters</a></li>
		</ul>
</div></section>

<section id="amazon" class="widget widget_text"><div class="widget-wrap">
	<?php 
		// The Amazon display code is INSANE and lives in lwtv-plugin
		echo LWTV_Amazon::show_amazon( $show_id ); 
	?>
</div></section>

<section id="ratings" class="widget widget_text"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Is it Worth Watching?</h4>
	<?php echo LWTV_CPT_Shows::display_worthit( $show_id, $worthit ); ?>
</div></section>

<section id="ratings" class="widget widget_text"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Tropes</h4>
	<?php echo LWTV_CPT_Shows::display_tropes( $show_id ); ?>
</div></section>

<section id="ratings" class="widget widget_text"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Ratings</h4>
	<?php echo LWTV_CPT_Shows::display_hearts( $show_id, $realness, $quality, $screentime ); ?>
</div></section>