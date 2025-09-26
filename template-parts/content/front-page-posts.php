<?php
/**
 * @package LWTV Underscores
 */

$front_posts     = $args['front_posts'];
$front_posts_ids = $args['front_posts_ids'];
$post_counter    = 0;

if ( empty( $front_posts ) || ! $front_posts->have_posts() ) {
	return;
}

// Get the first post from front_posts_ids
$first_post_id = $front_posts_ids[0];

// remove the first post from front_posts_ids
unset( $front_posts_ids[0] );

?>
<div class="site-loop home-featured-post-loop">
	<div class="card">
		<?php
		$post_author_id = get_post_field( 'post_author', $first_post_id );
		if ( has_post_thumbnail( $first_post_id ) ) {
			?>
			<a href="<?php echo esc_url( get_permalink( $first_post_id ) ); ?>" title="<?php echo esc_attr( get_the_title( $first_post_id ) ); ?>" >
				<?php echo get_the_post_thumbnail( $first_post_id, 'large', array( 'class' => 'card-img-top' ) ); ?>
			</a>
			<?php
		}
		?>
		<div class="card-body">
			<h3 class="card-title"><?php echo esc_html( get_the_title( $first_post_id ) ); ?></h3>
			<div class="card-meta text-muted">
				<?php echo esc_html( get_the_date( get_option( 'date_format' ), $first_post_id ) ); ?>
				<?php echo lwtv_plugin()->get_symbolicon( svg: 'user-circle.svg', icon: 'svg-user-circle', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo esc_html( get_the_author_meta( 'display_name', $post_author_id ) ); ?>
			</div>
			<div class="card-text">
				<?php echo esc_html( get_the_excerpt( $first_post_id ) ); ?>
			</div>
		</div><!-- .card-body -->
		<div class="card-footer">
			<a href="<?php echo esc_url( get_permalink( $first_post_id ) ); ?>" class="btn btn-outline-primary">
				Read More <span class="screen-reader-text">about <?php echo esc_html( get_the_title( $first_post_id ) ); ?></span>
			</a>
		</div><!-- .card-footer -->
	</div><!-- .card -->
</div>

<div class="site-loop home-featured-secondary-loop">
	<?php

	// Loop through the remaining posts.
	foreach ( $front_posts_ids as $front_post_id ) {
		$post_author_id = get_post_field( 'post_author', $front_post_id );
		?>
		<div class="card-group">
			<div class="card col-sm-5"
				<?php
				if ( has_post_thumbnail( $front_post_id ) ) {
					$alt_src = get_post_meta( $front_post_id, '_wp_attachment_image_alt', true );
					$alt_txt = ( isset( $alt_src ) && '' !== $alt_src ) ? $alt_src : get_the_title( $front_post_id );
					?>
					style="background-image: url(<?php echo esc_url( get_the_post_thumbnail_url( $front_post_id, 'large' ) ); ?>);"
					<?php
				}
				?>
				>
			</div>
			<div class="card col-sm-7">
				<div class="card-body">
					<h3 class="card-title"><?php echo esc_html( get_the_title( $front_post_id ) ); ?></h3>
					<div class="card-meta text-muted">
						<?php echo esc_html( get_the_date( get_option( 'date_format' ), $front_post_id ) ); ?>
						<?php echo lwtv_plugin()->get_symbolicon( svg: 'user-circle.svg', icon: 'svg-user-circle', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( get_the_author_meta( 'display_name', $post_author_id ) ); ?>
					</div>
					<div class="card-text">
						<?php echo esc_html( get_the_excerpt( $front_post_id ) ); ?>
					</div>
				</div>
				<div class="card-footer">
					<a href="<?php echo esc_url( get_permalink( $front_post_id ) ); ?>" class="btn btn-sm btn-outline-primary">
						Read More <span class="screen-reader-text">about <?php echo esc_html( get_the_title( $front_post_id ) ); ?></span>
					</a>
				</div>
			</div><!-- .card -->
		</div><!-- .card-group -->
		<?php
	}
	?>
</div>
