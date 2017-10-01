<?php
/*
 * This content is called by all archival displays of shows
 * 
 * It's used by the following files
 *      - archive-post_type_shows.php
 *      - taxonomy.php
 *
 * @package LezWatchTV
 */

global $post;
?>

<div class="card-group" id="post-<?php the_ID(); ?>">
	<div class="card col-sm-5"	
		<?php if ( has_post_thumbnail() ) : ?>
		    style="background-image: url(<?php the_post_thumbnail_url( 'large' ); ?>);"
		<?php endif; ?>
	>
	</div>
	<div class="card col-sm-7">
		<div class="card-body">
			<h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
			<div class="card-text"><?php the_excerpt(); ?></div>

			<div class="card-meta">
				<?php 
					$stations = get_the_terms( get_the_ID(), 'lez_stations' );
					if ( $stations && ! is_wp_error( $stations ) ) {
						echo get_the_term_list( get_the_ID(), 'lez_stations', '<strong>Network:</strong> ', ', ' ) .'<br />';
					}
					$airdates = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
					if ( $airdates ) {
						echo '<strong>Airdates:</strong> '. $airdates['start'] .' - '. $airdates['finish'] .'<br />';
					}
				?>
			</div>
		</div>

  		<div class="card-footer">
			<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
				Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
			</a>
		</div>
	</div><!-- .card -->
</div><!-- .card-group -->