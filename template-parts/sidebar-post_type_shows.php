<?php
/**
 * The template for displaying show CPT Archive Sidebar
 */

global $post;

$show_id      = $post->ID;
$slug         = get_post_field( 'post_name', get_post( $show_id ) );
$term         = term_exists( $slug , 'post_tag' );
$thumb_rating = get_post_meta( $show_id, 'lezshows_worthit_rating', true );
$realness     = min( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ), 5 );
$quality      = min( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ), 5 );
$screentime   = min( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ), 5 );
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

<section id="ratings" class="widget widget_text"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Is it Worth Watching?</h4>
		<?php
		// If there's no rating, let's not show anything
		if ( $thumb_rating == null ) {
			?><p><em>Coming soon...</em></p><?php
		} else {
			?>
			<div class="ratings-icons">
				<div class="worthit worthit-<?php echo esc_attr( $thumb_rating ); ?>">
					<?php
					if ( $thumb_rating == "Yes" ) { $thumb_icon = "thumbs_up.svg"; }
					if ( $thumb_rating == "Meh" ) { $thumb_icon = "meh-o.svg"; }
					if ( $thumb_rating == "No" )  { $thumb_icon = "thumbs_down.svg"; }
	
					$thumb_image = '';
					if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
						$thumb_request = wp_remote_get( LP_SYMBOLICONS_PATH . '' . $thumb_icon );
						$thumb_image   = $thumb_request['body'];
					}
	
					echo '<span role="img" class="show-worthit ' . lcfirst( $thumb_rating ) . '">' . $thumb_image . '</span>';
					echo get_post_meta( $show_id, 'lezshows_worthit_rating', true );
					?>
				</div>
			</div>
	
			<div class="ratings-details">
				<?php
					if ( ( get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) ) {
						echo apply_filters( 'the_content', wp_kses_post( get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) );
					}
				?>
	
				<ul class="network-data">
					<?php
					$stations = get_the_terms( $show_id, 'lez_stations' );
					if ( $stations && ! is_wp_error( $stations ) ) {
						echo '<li class="network names">'. get_the_term_list( $show_id, 'lez_stations', '<strong>Airs On:</strong> ', ', ' ) .'</li>';
					}
					$formats = get_the_terms( $show_id, 'lez_formats' );
					if ( $formats && ! is_wp_error( $formats ) ) {
						echo '<li class="network formats">'. get_the_term_list( $show_id, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) .'</li>';
					}
					if ( get_post_meta($show_id, 'lezshows_airdates', true) ) {
						$airdates = get_post_meta( $show_id, 'lezshows_airdates', true );
						echo '<li class="network airdates"><strong>Airdates:</strong> '. $airdates['start'] .' - '. $airdates['finish'] .'</li>';
					}
					?>
				</ul>
	
			</div><?php
		} ?>
</div></section>

<section id="ratings" class="widget widget_text"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Tropes</h4>
	<?php
		// get the tropes associated with this show
		$terms = get_the_terms( $show_id, 'lez_tropes' );

		if ( !$terms || is_wp_error( $terms ) ) {
			// If there are no terms, throw a message
			?><p><em>Coming soon...</em></p><?php
		} else {
			?><ul class="trope-list"><?php
				// loop over each returned trope
				foreach( $terms as $term ) { ?>
					<li class="show trope trope-<?php echo $term->slug; ?>">
						<a href="<?php echo get_term_link( $term->slug, 'lez_tropes'); ?>" rel="show trope"><?php
							// Echo the taxonomy icon (default to squares if empty)
							$icon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
							echo lwtv_yikes_symbolicons( $icon, 'fa-square' );
						?></a>
						<a href="<?php echo get_term_link( $term->slug, 'lez_tropes'); ?>" rel="show trope" class="trope-link"><?php
							echo $term->name;
						?></a>
					</li><?php
				}
			?></ul><?php
		} ?>
</div></section>

<section id="ratings" class="widget widget_text"><div class="widget-wrap">
	<h4 class="widget-title widgettitle">Ratings</h4>
	<?php
		// Bail if not set
		if ( $realness == '0' && $quality == '0' && $screentime == '0' ) {
			?><p><em>Coming soon...</em></p><?php
		} else {
			// We have some love, let's show it
			$heart_types    = array( 'realness', 'quality', 'screentime' );
			$heart          = lwtv_yikes_symbolicons( 'heart.svg', 'fa-heart' );
			$positive_heart = '<span role="img" class="show-heart positive">' . $heart . '</span>';
			$negative_heart = '<span role="img" class="show-heart negative">' . $heart . '</span>';

			foreach ( $heart_types as $type ) {
	
				switch ( $type ) {
					case 'realness';
						$rating = $realness;
						$detail = 'lezshows_realness_details';
						break;
					case 'quality';
						$rating = $quality;
						$detail = 'lezshows_quality_details';
						break;
					case 'screentime';
						$rating = $screentime;
						$detail = 'lezshows_screentime_details';
						break;
				}
	
				if ( $rating > '0' ) {
					?>
					<div class="ratings-icons">
						<h3><?php echo ucfirst( $type ); ?></h3>
						<?php
						// while loop to display filled in hearts
						// based on set ratings
						$i = 1;
						while( $i <= $rating ) {
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
						?><span class="screen-reader-text">Rating: <?php echo $rating ?> Hearts (out of 5)</span>
					</div>
					<?php
	
					if( ( get_post_meta( $show_id, $detail, true) ) ) {
						echo apply_filters( 'the_content', wp_kses_post( get_post_meta( $show_id, $detail, true ) ) );
					}
				}
			}
		}
	?>
</div></section>

<section id="amazon" class="widget widget_text"><div class="widget-wrap">
	<?php 
		// The Amazon display code is INSANE and lives in lwtv-plugin
		// Trust me, it's better this way
		echo LWTV_Amazon::show_amazon( $show_id ); 
	?>
</div></section>