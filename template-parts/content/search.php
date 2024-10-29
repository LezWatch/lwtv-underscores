<?php
/**
 * The search content template
 *
 * @package YIKES Starter
 */

?>

<div class="card">
	<?php
	if ( has_post_thumbnail() ) {
		?>
		<div class="character-image-wrapper">
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
				<?php the_post_thumbnail( 'character-img', array( 'class' => 'card-img-top' ) ); ?>
			</a>
		</div>
		<?php
	}
	?>
	<div class="card-body">
		<h3 class="card-title">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput
			echo lwtv_plugin()->get_symbolicon( 'newspaper.svg', 'fa-newspaper' ) . '&nbsp;';
			the_title();
			?>
		</h3>
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
