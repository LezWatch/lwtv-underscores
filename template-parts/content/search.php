<?php
/**
 * The search content template
 *
 * @package LWTV Underscores
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
			switch ( get_post_type() ) {
				case 'page':
					$svg        = 'chalkboard.svg';
					$symbolicon = 'svg-file';
					break;
				case 'post_type_shows':
					$svg        = 'tv.svg';
					$symbolicon = 'svg-tv';
					break;
				case 'post_type_characters':
					$svg        = 'rubber-stamp.svg';
					$symbolicon = 'svg-user';
					break;
				case 'post_type_actors':
					$svg        = 'award-academy.svg';
					$symbolicon = 'svg-user-tie';
					break;
				default:
					$svg        = 'newspaper.svg';
					$symbolicon = 'svg-newspaper';
					break;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo lwtv_plugin()->get_symbolicon( svg: $svg, icon: $symbolicon ) . '&nbsp;';
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
