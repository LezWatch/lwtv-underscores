<?php
/**
 * The search content template
 *
 * @package YIKES Starter
 */

switch ( get_post_type( $post->ID ) ) {
	case 'post_type_shows':
		$searchicon = 'fa-television';
		break;
	case 'post_type_characters':
		$searchicon = 'fa-female';
		break;
	default:
		$searchicon = 'fa-newspaper-o';
	}
?>

<div class="card"> 
	<?php if ( has_post_thumbnail()) : ?>
		<div class="character-image-wrapper">
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
			 	<?php the_post_thumbnail( 'postloop-img', array( 'class' => 'card-img-top' ) ); ?>
			</a>
		</div>
	<?php endif; ?>
	<div class="card-body">
  		<h3 class="card-title"><i class="fa <?php echo $searchicon; ?>"></i> <?php the_title(); ?></h3>
  		<div class="card-text">
  			<?php the_excerpt(); ?>
  		</div>
  	</div>
		<div class="card-footer">
		<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
			Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
		</a>
	</div>
</div><!-- .card -->