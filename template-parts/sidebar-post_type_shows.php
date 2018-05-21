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
$watch_where  = get_post_meta( $show_id, 'lezshows_watch_where', true );
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
					if ( $thumb_rating == "Yes" ) { $thumb_icon = "thumbs-up"; }
					if ( $thumb_rating == "Meh" ) { $thumb_icon = "meh"; }
					if ( $thumb_rating == "No" )  { $thumb_icon = "thumbs-down"; }
	
					$thumb_image = lwtv_yikes_symbolicons( $thumb_icon . '.svg', 'fa-' . $thumb_icon );
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

					// Calculate the show score and echo
					LWTV_Shows_Calculate::do_the_math( $show_id );
					echo '<strong>Show Score:</strong> ' . round( get_post_meta( $show_id, 'lezshows_the_score', true ), 2 );
					?>
				</div>
	
				<ul class="network-data list-group">
					<?php
					$stations = get_the_terms( $show_id, 'lez_stations' );
					if ( $stations && ! is_wp_error( $stations ) ) {
						echo '<li class="list-group-item network names">' . get_the_term_list( $show_id, 'lez_stations', '<strong>Airs On:</strong> ', ', ' ) . '</li>';
					}
					$countries = get_the_terms( $show_id, 'lez_country' );
					if ( $countries && ! is_wp_error( $countries ) ) {
						echo '<li class="list-group-item network country">'. get_the_term_list( $show_id, 'lez_country', '<strong>Airs In:</strong> ', ', ' ) .'</li>';
					}
					$formats = get_the_terms( $show_id, 'lez_formats' );
					if ( $formats && ! is_wp_error( $formats ) ) {
						echo '<li class="list-group-item network formats">'. get_the_term_list( $show_id, 'lez_formats', '<strong>Show Format:</strong> ', ', ' ) .'</li>';
					}
					if ( get_post_meta( $show_id, 'lezshows_airdates', true ) ) {
						$airdates = get_post_meta( $show_id, 'lezshows_airdates', true );
						$airdate  = $airdates['start'] . ' - ' . $airdates['finish'];
						if ( $airdates['start'] == $airdates['finish'] ) { $airdate = $airdates['finish']; }
						if ( is_numeric( $airdates['finish'] ) && get_post_meta( $show_id, 'lezshows_seasons', true ) ) {
							$airdate .= ' (' . get_post_meta( $show_id, 'lezshows_seasons', true ) . ' seasons)';
						}

						
						echo '<li class="list-group-item network airdates"><strong>Airdates:</strong> '. $airdate .'</li>';
					}
					$genres = get_the_terms( $show_id, 'lez_genres' );
					if ( $genres && ! is_wp_error( $genres ) ) {
						echo '<li class="list-group-item network genres">'. get_the_term_list( $show_id, 'lez_genres', '<strong>Genres:</strong> ', ', ' ) .'</li>';
					}
					if ( get_post_meta( $show_id, 'lezshows_imdb', true ) ) {
						echo '<li class="list-group-item network imdb"><a href="https://www.imdb.com/title/'. get_post_meta( $show_id, 'lezshows_imdb', true ) .'">IMDb</a></li>';
					}
					?>
				</ul>
	
			</div><?php
		} ?>
	</div>
</section>

<section id="affiliates watch-now" class="widget widget_text">
	<?php echo LWTV_Affilliates::shows( $show_id, 'widget' ); ?>
</section>

<section id="ratings" class="widget widget_text">
	<div class="card">
		<div class="card-header">
			<h4>Tropes</h4>
		</div>
		<?php
			// get the tropes associated with this show
			$terms = get_the_terms( $show_id, 'lez_tropes' );

			if ( !$terms || is_wp_error( $terms ) ) {
				// If there are no terms, throw a message
				?><p><em>Coming soon...</em></p><?php
			} else {
				?><ul class="trope-list list-group"><?php
					// loop over each returned trope
					foreach( $terms as $term ) { ?>
					<li class="list-group-item show trope trope-<?php echo $term->slug; ?>">
						<a href="<?php echo get_term_link( $term->slug, 'lez_tropes'); ?>" rel="show trope"><?php
							// Echo the taxonomy icon (default to squares if empty)
							$icon = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );
							echo lwtv_yikes_symbolicons( $icon .'.svg', 'fa-lemon' );
						?></a>
						<a href="<?php echo get_term_link( $term->slug, 'lez_tropes'); ?>" rel="show trope" class="trope-link"><?php
							echo $term->name;
						?></a>
					</li><?php
				}
			?></ul><?php
		} ?>
	</div>
</section>

<?php
// Intersectionality
$intersections = get_the_terms( $show_id, 'lez_intersections' );
if ( $intersections && !is_wp_error( $intersections ) ) {
?>
	<section id="ratings" class="widget widget_text">
		<div class="card">
			<div class="card-header">
				<h4>Intersectionality</h4>
			</div>
				<ul class="trope-list list-group"><?php
					// loop over each returned trope
					foreach( $intersections as $intersection ) { ?>
						<li class="list-group-item show trope trope-<?php echo $intersection->slug; ?>">
							<a href="<?php echo get_term_link( $intersection->slug, 'lez_intersections'); ?>" rel="show trope"><?php
								// Echo the taxonomy icon (default to squares if empty)
								$icon = get_term_meta( $intersection->term_id, 'lez_termsmeta_icon', true );
								echo lwtv_yikes_symbolicons( $icon .'.svg', 'fa-lemon' );
							?></a>
							<a href="<?php echo get_term_link( $intersection->slug, 'lez_intersections'); ?>" rel="show trope" class="trope-link"><?php
								echo $intersection->name;
							?></a>
						</li><?php
					}
				?></ul>
		</div>
	</section>
<?php
}
?>

<section id="ratings" class="widget widget_text">
	<div class="card">
		<div class="card-header">
			<h4>Ratings</h4>
		</div>
		<div class="card-body">
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
		</div>
	</div>
</section>