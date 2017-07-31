<?php
/**
 * The template for displaying show CPT Archive Sidebar
 */

global $post;
$show_id = $post->ID;

$slug = get_post_field( 'post_name', get_post( $show_id ) );
$term = term_exists( $slug , 'post_tag' );

$realness_rating = (int) get_post_meta($show_id, 'lezshows_realness_rating', true);
$realness_rating = min( $realness_rating, 5 );
$show_quality = (int) get_post_meta($show_id, 'lezshows_quality_rating', true);
$show_quality = min ( $show_quality, 5 );
$screen_time = (int) get_post_meta($show_id, 'lezshows_screentime_rating', true);
$screen_time = min( $screen_time, 5 );

$positive_heart = '<span role="img" class="show-heart positive">'.file_get_contents(LP_SYMBOLICONS_PATH.'/svg/heart.svg').'</span>';
$negative_heart = '<span role="img" class="show-heart negative">'.file_get_contents(LP_SYMBOLICONS_PATH.'/svg/heart.svg').'</span>';
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
			if( ( get_post_meta( get_the_ID(), 'lezshows_plots', true) )  ) {
				?><li><a href="#timeline">Timeline</a></li><?php
			}
			if( ( get_post_meta( get_the_ID(), 'lezshows_episodes', true) )  ) {
				?><li><a href="#episodes">Episodes</a></li><?php
			}
			if ( $term !== 0 && $term !== null ) {
				?><li><a href="#related-posts">Related Posts</a></li><?php
			}
			?>
			<li><a href="#characters">Characters</a></li>
		</ul>
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
	<?php echo LWTV_CPT_Shows::display_hearts( $show_id, $realness_rating, $show_quality, $screen_time ); ?>
</div></section>