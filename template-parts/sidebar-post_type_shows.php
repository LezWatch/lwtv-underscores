<?php
/**
 * The template for displaying show CPT Archive Sidebar
 */

global $post;

$show_id      = $post->ID;
$thumb_rating = get_post_meta( $show_id, 'lezshows_worthit_rating', true );
$realness     = min( (int) get_post_meta( $show_id, 'lezshows_realness_rating', true ), 5 );
$quality      = min( (int) get_post_meta( $show_id, 'lezshows_quality_rating', true ), 5 );
$screentime   = min( (int) get_post_meta( $show_id, 'lezshows_screentime_rating', true ), 5 );
?>

<section id="search" class="widget widget_search">
	<?php get_search_form(); ?>
</section>

<section id="ratings" class="widget widget_text">
	<div class="card">		
		<div class="card-header">
			<h4>Is it Worth Watching?</h4>
		</div>
		
		<?php
		// If there's no rating, let's not show anything
		if ( $thumb_rating == null ) {
			?><p><em>Coming soon...</em></p><?php
		} else {
			?>
			<div class="ratings-icons worthit-<?php echo lcfirst( $thumb_rating ); ?>">
				<div class="worthit">
					<?php
					if ( $thumb_rating == "Yes" ) { $thumb_icon = "thumbs_up.svg"; }
					if ( $thumb_rating == "Meh" ) { $thumb_icon = "meh-o.svg"; }
					if ( $thumb_rating == "No" )  { $thumb_icon = "thumbs_down.svg"; }
	
					$thumb_image = lwtv_yikes_symbolicons( $thumb_icon, 'fa-square' );
					echo '<span role="img" class="show-worthit ' . lcfirst( $thumb_rating ) . '">' . $thumb_image . '</span>';
					echo get_post_meta( $show_id, 'lezshows_worthit_rating', true );
					?>
				</div>
			</div>
	
			<div class="ratings-details">
				<div class="card-body">
					<?php
						if ( ( get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) ) {
							echo apply_filters( 'the_content', wp_kses_post( get_post_meta( $show_id, 'lezshows_worthit_details', true ) ) );
						}
					?>
				</div>
	
				<ul class="network-data list-group">
					<?php
					$stations = get_the_terms( $show_id, 'lez_stations' );
					if ( $stations && ! is_wp_error( $stations ) ) {
						echo '<li class="list-group-item network names">'. get_the_term_list( $show_id, 'lez_stations', '<strong>Airs On:</strong> ', ', ' ) .'</li>';
					}
					$formats = get_the_terms( $show_id, 'lez_formats' );
					if ( $formats && ! is_wp_error( $formats ) ) {
						echo '<li class="list-group-item network formats">'. get_the_term_list( $show_id, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) .'</li>';
					}
					if ( get_post_meta($show_id, 'lezshows_airdates', true) ) {
						$airdates = get_post_meta( $show_id, 'lezshows_airdates', true );
						echo '<li class="list-group-item network airdates"><strong>Airdates:</strong> '. $airdates['start'] .' - '. $airdates['finish'] .'</li>';
					}
					?>
				</ul>
	
			</div><?php
		} ?>
	</div>
</section>

<section id="ratings" class="widget widget_text">
	<div class="widget-wrap">
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
							echo lwtv_yikes_symbolicons( $icon .'.svg', 'fa-square' );
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