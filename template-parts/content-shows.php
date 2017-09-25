<?php
/**
 * @package YIKES Starter
 */
?>

<div class="card">
	<?php if ( has_post_thumbnail()) : ?>
		<div class="character-image-wrapper">
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
				<?php the_post_thumbnail( 'postloop-img', array( 'class' => 'card-img-top' ) );; ?>
			</a>
		</div>
	<?php endif; ?>
	<div class="card-body">
  		<h4 class="card-title"><?php the_title(); ?></h4>
  		<div class="card-text">
			<div class="card-meta-item"><strong>Airs On:</strong> 
				<?php 
					$stations =  get_the_terms( $post, 'lez_stations');
					$station_string='
					foreach ($stations as $station) {
						$station_string .= $station->name . ', 		
					}
					echo trim($station_string , ', ');
				?>
			</div>
			<div class="card-meta-item">
				<?php 
					$field = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
					$start = isset( $field['start'] ) ? $field['start'] : '
					$finish = isset( $field['finish'] ) ? $field['finish'] : '
					echo '<strong>Airdates:</strong> ' . $start  . ' - ' . $finish  ;
				?>
			</div>
			<div class="card-excerpt">
				<?php get_the_excerpt() ;?>
			</div>
        </div>
	<div class="card-footer">
        <a href="<?php get_the_permalink() ?>" class="btn btn-outline-primary">
        	Show Profile
        </a>
    </div>
</div>