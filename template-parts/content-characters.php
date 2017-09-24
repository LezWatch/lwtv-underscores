<?php
/**
 * @package YIKES Starter
 */
?>

	<div class="card"> 
		<?php if ( has_post_thumbnail()) : ?>
		   <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
		  	 <?php the_post_thumbnail( 'widget-img', array( 'class' => 'card-img-top' ) );; ?>
		   </a>
		<?php endif; ?>
		<div class="card-body">
	  		<h4 class="card-title"><?php the_title(); ?></h4>
	  		<div class="card-text">
	  			<?php
					$shows = get_post_meta( get_the_ID(), 'lezchars_show_group', true );
					foreach ($shows as $show) {
						$show_post = get_post( $show['show']);
						echo '<div class="card-meta-item"><i class="fa fa-television" aria-hidden="true"></i> <a href="' . get_the_permalink($show_post->ID)  .'">' . $show_post->post_title .'</a></div>';
					}
				?>

				<?php
					$field = get_post_meta( get_the_ID(), 'lezchars_actor', true );
					$field_value = isset( $field[0] ) ? $field[0] : ''; 
					echo '<div class="card-meta-item"><i class="fa fa-user" aria-hidden="true"></i> ' . $field_value  . '</div>';
				?>
		  	</div>
		</div>
  		<div class="card-footer">
			<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
				<span class="screen-reader-text">Go to</span> Character Profile
			</a>
		</div>
	</div><!-- .card -->