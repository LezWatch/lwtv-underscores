<?php
/**
 * The template for displaying show CPT Archive Sidebar
 */

global $post;
$show_id = $post->ID;

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
			?>
			<li><a href="#characters">Characters</a></li>
		</ul>
</div></section>
<?php

if((get_post_meta($show_id, "lezshows_worthit_rating", true))) { ?>
	<section id="ratings" class="widget widget_text"><div class="widget-wrap">
		<h4 class="widget-title widgettitle">Is it Worth Watching?</h4>
			<div class="ratings-icons">
				<div class="worthit worthit-<?php echo esc_attr(get_post_meta($show_id, 'lezshows_worthit_rating', true)); ?>">
					<?php
					$thumb_rating = get_post_meta($show_id, 'lezshows_worthit_rating', true);
					if ( $thumb_rating == "Yes" ) { $thumb = "thumbs_up.svg"; }
					if ( $thumb_rating == "Meh" ) { $thumb = "meh-o.svg"; }
					if ( $thumb_rating == "No" ) { $thumb = "thumbs_down.svg"; }
					$thumb_image = file_get_contents(LP_SYMBOLICONS_PATH.'/svg/'.$thumb);
					echo '<span role="img" class="show-worthit '.lcfirst($thumb_rating).'">'.$thumb_image.'</span>';
					echo get_post_meta($show_id, 'lezshows_worthit_rating', true);
					?>
				</div>
			</div>

			<?php if((get_post_meta($show_id, "lezshows_worthit_details", true))) { ?>
				<?php echo apply_filters('the_content', wp_kses_post(get_post_meta($show_id, 'lezshows_worthit_details', true))); ?>
			<?php } ?>
			<ul class="network-data">
			<?php
			$stations = get_the_terms( $show_id, 'lez_stations' );
			if ( $stations && ! is_wp_error( $stations ) ) {
				echo '<li class="network names">'. get_the_term_list( $post->ID, 'lez_stations', '<strong>Airs On:</strong> ', ', ' ) .'</li>';
			}
			$formats = get_the_terms( $show_id, 'lez_formats' );
			if ( $formats && ! is_wp_error( $formats ) ) {
				echo '<li class="network formats">'. get_the_term_list( $post->ID, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) .'</li>';
			}
			if ( get_post_meta($show_id, 'lezshows_airdates', true) ) {
				$airdates = get_post_meta($show_id, 'lezshows_airdates', true);
				echo '<li class="network airdates"><strong>Airdates:</strong> '. $airdates['start'] .' - '. $airdates['finish'] .'</li>';
			}
			?>
			</ul>
	</div></section><?php
}
?>

<section id="ratings" class="widget widget_text"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Tropes</h4>
	<ul class="trope-list"><?php
		// get the tropes associated with this show
		$terms = get_the_terms( $show_id, 'lez_tropes' );
		// if tropes are found, and no errors are returned
		if ( $terms && ! is_wp_error( $terms ) ) {
			// loop over each returned trope
			foreach( $terms as $term ) { ?>
				<li class="show trope trope-<?php echo $term->slug; ?>">
					<a href="<?php echo get_term_link( $term->slug, 'lez_tropes'); ?>" rel="show trope"><?php
						$icon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
						$iconpath = LP_SYMBOLICONS_PATH.'/svg/'.$icon.'.svg';
						if ( empty( $icon ) || !file_exists( $iconpath ) ) {
							$iconpath = LP_SYMBOLICONS_PATH.'/svg/square.svg';
						}
						echo file_get_contents( $iconpath );
					?></a>
					<a href="<?php echo get_term_link( $term->slug, 'lez_tropes'); ?>" rel="show trope" class="trope-link"><?php
						echo $term->name;
					?></a>
				</li><?php
			}
		} ?>
	</ul>
</div></section>

<?php
if((get_post_meta($show_id, "lezshows_realness_rating", true))) { ?>

	<section id="ratings" class="widget widget_text"><div class="widget-wrap">
		<h4 class="widget-title widgettitle">Realness</h4>
			<div class="ratings-icons"><?php
				// while loop to display filled in hearts
				// based on set ratings
				$i = 1;
				while( $i <= $realness_rating ) {
					echo $positive_heart;
					$i++;
				}
				// calculate the remaining empty hearts
				if ( $i >= 1 ) {
					$loop_count = $i - 1;
				} else {
					$loop_count = 0;
				}
				while ( $loop_count < 5 ) {
					echo $negative_heart;
					$loop_count++;
				}
				?><span class="screen-reader-text">Rating: <?php echo $realness_rating ?> Hearts (out of 5)</span>
			</div>

			<?php if((get_post_meta($show_id, "lezshows_realness_details", true))) { ?>
				<?php echo apply_filters('the_content', wp_kses_post(get_post_meta($show_id, 'lezshows_realness_details', true))); ?>
			<?php } ?>

	</div></section><?php
}

if((get_post_meta($show_id, "lezshows_quality_rating", true))) { ?>

	<section id="ratings" class="widget widget_text"><div class="widget-wrap">
		<h4 class="widget-title widgettitle">Show Quality</h4>
			<div class="ratings-icons"><?php
				// while loop to display filled in hearts -- based on set ratings
				$i = 1;
				while( $i <= $show_quality ) {
					echo $positive_heart;
					$i++;
				}
				// calculate the remaining empty hearts
				if ( $i >= 1 ) {
					$loop_count = $i - 1;
				} else {
					$loop_count = 0;
				}
				while ( $loop_count < 5 ) {
					echo $negative_heart;
					$loop_count++;
				}
				?><span class="screen-reader-text">Rating: <?php echo $show_quality ?> Hearts (out of 5)</span>
			</div>
			<?php if((get_post_meta($show_id, "lezshows_quality_details", true))) { ?>
				<?php echo apply_filters('the_content', wp_kses_post(get_post_meta($show_id, 'lezshows_quality_details', true))); ?>
			<?php } ?>
	</div></section><?php
}

if((get_post_meta($show_id, "lezshows_screentime_rating", true))) { ?>

	<section id="ratings" class="widget widget_text"><div class="widget-wrap">
		<h4 class="widget-title widgettitle">Screen Time</h4>
			<div class="ratings-icons"><?php
				// while loop to display filled in hearts -- based on set ratings
				$i = 1;
				while( $i <= $screen_time ) {
					echo $positive_heart;
					$i++;
				}
				// calculate the remaining empty hearts
				if ( $i >= 1 ) {
					$loop_count = $i - 1;
				} else {
					$loop_count = 0;
				}
				while ( $loop_count < 5 ) {
					echo $negative_heart;
					$loop_count++;
				}
				?><span class="screen-reader-text">Rating: <?php echo $screen_time ?> Hearts (out of 5)</span>
			</div>
			<?php if((get_post_meta($show_id, "lezshows_screentime_details", true))) { ?>
				<?php echo apply_filters('the_content', wp_kses_post(get_post_meta($show_id, 'lezshows_screentime_details', true))); ?>
			<?php } ?>
	</div></section><?php
}

get_template_part( 'template-parts/widgets/googleads' );