<?php
/*
 * This content is called by all archival displays of actors
 *
 * Used by the following files
 *      - archive-post_type_actors.php
 *      - taxonomy.php
 *
 * @package LezWatch.TV
 */

global $post;

$the_id      = ( isset( $actor['id'] ) ) ? $actor['id'] : $post->ID;
$the_content = ( isset( $actor['content'] ) ) ? $actor['content'] : get_the_content();
$alttext     = 'A picture of the actor ' . get_the_title( $the_id );
$archive     = ( is_archive() || is_tax() || is_page() ) ? true : false;

$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) ) ? $alttext : $alttext . ' &copy; ' . $thumb_attribution;
$thumb_array       = array(
	'class' => 'card-img-top',
	'alt'   => $thumb_title,
	'title' => $thumb_title,
);

// The Terrible Terri Polo: If we don't reset, her stats apply to everyone.
unset( $shows, $actors, $gender, $sexuality, $cliches, $grave );
?>

<div class="card">
	<?php if ( has_post_thumbnail( $the_id ) ) : ?>
		<div class="actor-image-wrapper">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php get_the_title( $the_id ); ?>" >
				<?php echo get_the_post_thumbnail( $the_id, 'character-img', $thumb_array ); ?>
			</a>
		</div>
	<?php endif; ?>
	<div class="card-body">
		<h4 class="card-title">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" >
				<?php
				echo get_the_title( $the_id );
				if ( $archive ) {
					echo lwtv_yikes_chardata( $the_id, 'dead' );
				}
				if ( isset( $grave ) ) {
					echo ' ' . lwtv_sanitized( $grave );
				}
				?>
			</a>
		</h4>
		<div class="card-text">
			<?php
			$gender    = lwtv_yikes_actordata( $the_id, 'gender' );
			$sexuality = lwtv_yikes_actordata( $the_id, 'sexuality' );

			// Gender and Sexuality
			if ( isset( $gender ) && isset( $sexuality ) ) {
				echo '<div class="card-meta-item"> ' . wp_kses_post( $gender . ' ' . $sexuality ) . '</div>';
			}
			?>
		</div>
	</div>
	<?php
	if ( $archive ) {
		?>
		<div class="card-footer">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" class="btn btn-outline-primary">Actor Profile</a>
		</div>
		<?php
	}
	?>
</div><!-- .card -->
