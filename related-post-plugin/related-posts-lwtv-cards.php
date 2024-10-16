<?php
/**
 * Widget and shortcode template: excerpts template.
 *
 * This template is used by the plugin: Related Posts by Taxonomy.
 *
 * plugin:        https://wordpress.org/plugins/related-posts-by-taxonomy
 * Documentation: https://keesiemeijer.wordpress.com/related-posts-by-taxonomy/
 *
 * Only edit this file after you've copied it to your (child) theme's related-post-plugin folder.
 * See: https://keesiemeijer.wordpress.com/related-posts-by-taxonomy/templates/
 *
 * @package Related Posts by Taxonomy
 * @since 0.1
 *
 * The following variables are available:
 *
 * @var array $related_posts Array with related post objects or related post IDs.
 *                           Empty array if no related posts are found.
 * @var array $rpbt_args     Widget or shortcode arguments.
 */

/**
 * Note: global $post; is used before this template by the widget and the shortcode.
 */

if ( is_array( $related_posts ) && $related_posts ) {
	?>
	<div class="container related-posts-by-taxonomy-container">
		<div class="row site-loop related-posts-by-taxonomy-loop">
			<?php
			foreach ( $related_posts as $the_id ) {
				// Thumbnail attribution.
				$thumb_attribution = get_post_meta( get_post_thumbnail_id( $the_id ), 'lwtv_attribution', true );
				$thumb_title       = ( empty( $thumb_attribution ) ) ? get_the_title( $the_id ) : get_the_title( $the_id ) . ' &copy; ' . $thumb_attribution;
				$thumb_array       = array(
					'class' => 'card-img-top',
					'alt'   => $thumb_title,
					'title' => $thumb_title,
				);
				?>
				<div class="card mb-3">
					<div class="show-like-this-image-wrapper">
						<a href="<?php the_permalink( $the_id ); ?>">
							<img src="<?php echo esc_url( get_the_post_thumbnail_url( $the_id, 'relatedshow-img' ) ); ?>" class="card-img-top" alt="<?php echo esc_html( $thumb_title ); ?>">
						</a>
					</div>

					<div class="card-body">
						<span class="card-title similar-shows-header">
							<a href="<?php the_permalink( $the_id ); ?>"><?php echo esc_html( get_the_title( $the_id ) ); ?></a>
						</span>
						<?php
						$airdates = get_post_meta( $the_id, 'lezshows_airdates', true );
						if ( $airdates ) {
							$airdate = $airdates['start'] . ' - ' . $airdates['finish'];
							if ( $airdates['start'] === $airdates['finish'] ) {
								$airdate = $airdates['finish'];
							}
							echo '<span class="card-subtitle mb-2 text-body-secondary similar-shows-airdates"><strong>Airdates:</strong> ' . esc_html( $airdate ) . '</span>';
						}
						?>
					</div>

				</div> <!-- .card -->
				<?php
			}
			?>
		</div>
	</div><!-- .container -->
	<?php
} else {
	?>
	<p><?php km_rpbt_no_posts_found_notice( $rpbt_args ); ?></p>
	<?php
}

/**
 * Note: wp_reset_postdata(); is used after this template by the widget and the shortcode
 */
