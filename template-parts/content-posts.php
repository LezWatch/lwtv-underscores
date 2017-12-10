<?php
/**
 * @package YIKES Starter
 */
?>

	<div class="card"> 
		<?php if ( has_post_thumbnail()) : ?>
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
				<?php the_post_thumbnail( 'postloop-img', array( 'class' => 'card-img-top' ) ); ?>
			</a>
		<?php endif; ?>
		<div class="card-body">
			<h3 class="card-title"><?php the_title(); ?></h3>
			<div class="card-meta text-muted">
				<?php the_date(); ?> 
				<?php echo lwtv_yikes_symbolicons( 'user-circle.svg', 'fa-user-circle' ); ?> 
				<?php the_author(); ?>
			</div>
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