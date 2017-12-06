<?php
/*
 * This content is called by all archival displays of actors
 *
 * Used by the following files
 *      - archive-post_type_actors.php
 *      - taxonomy.php
 *
 * @package LezWatchTV
 */

global $post;

$the_ID      = ( isset( $actor['id'] ) )? $actor['id'] : $post->ID;
$the_content = ( isset( $actor['content'] ) )? $actor['content'] : get_the_content();
$alttext     = get_the_title( $the_ID ) . ' - ' . wp_strip_all_tags( $the_content );
$archive     = ( is_archive() || is_tax() || is_page() )? true : false;

// Reset to prevent Teri Polo from overtaking the world
unset( $shows, $actors, $gender, $sexuality, $cliches, $grave );
?>

<div class="card"> 
	<?php if ( has_post_thumbnail( $the_ID ) ) : ?>
		<div class="actor-image-wrapper">
			<a href="<?php the_permalink( $the_ID ); ?>" title="<?php get_the_title( $the_ID ); ?>" >
				<?php echo get_the_post_thumbnail( $the_ID, 'actor-img', array( 'class' => 'card-img-top' , 'alt' => $alttext, 'title' => $alttext ) ); ?>
			</a>
		</div>
	<?php endif; ?>
	<div class="card-body">
		<h4 class="card-title">
			<a href="<?php the_permalink( $the_ID ); ?>" title="<?php the_title_attribute( $the_ID ); ?>" >
				<?php 
					echo get_the_title( $the_ID );
					if ( $archive ) echo lwtv_yikes_chardata( $the_ID, 'dead' ); 
					if ( isset( $grave ) ) echo ' ' . $grave;
				?>
			</a>
		</h4>
		<div class="card-text">
			<?php
			$gender    = lwtv_yikes_actordata( $the_ID, 'gender' );
			$sexuality = lwtv_yikes_actordata( $the_ID, 'sexuality' );

			// Gender and Sexuality
			if ( isset( $gender ) && isset( $sexuality ) ) {
				echo '<div class="card-meta-item"> ' . $gender . ' ' . $sexuality . '</div>';
			}
			?>
		</div>
	</div>
	<?php
	if ( $archive ) {
		?>
		<div class="card-footer">
			<a href="<?php the_permalink( $the_ID ); ?>" title="<?php the_title_attribute( $the_ID ); ?>" class="btn btn-outline-primary">Actor Profile</a>
		</div>
		<?php
	} 
	?>
</div><!-- .card -->