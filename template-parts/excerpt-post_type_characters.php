<?php
/*
 * This content is called by all archival displays of characters
 *
 * Thanks to the L Word having over 60 characters (I don't know
 * why I'm surprised by this...) having the full content loop was
 * causing server overload. This code shows the individuals as
 * headshots with alt-text as their character post content.
 * 
 * It's used by the following files
 *      - archive-post_type_characters.php
 *      - content-post_type_shows.php
 *      - taxonomy.php
 *
 * @package LezWatchTV
 */

global $post;

$the_ID      = ( isset( $character['id'] ) )? $character['id'] : $post->ID;
$the_content = ( isset( $character['content'] ) )? $character['content'] : get_the_content();
$alttext     = get_the_title( $the_ID ) . ' - ' . wp_strip_all_tags( $the_content );

$role      = ( isset( $character['role_from'] ) )? $character['role_from'] : 'regular';
$grave     = lwtv_yikes_chardata( $the_ID, 'dead' );
?>
<div class="card"> 
	<?php if ( has_post_thumbnail( $the_ID ) ) : ?>
		<div class="character-image-wrapper">
		   <a href="<?php the_permalink( $the_ID ); ?>" title="<?php the_title_attribute( $the_ID ); ?>" >
		  	 <?php echo get_the_post_thumbnail( $the_ID, 'character-img', array( 'class' => 'card-img-top' , 'alt' => $alttext, 'title' => $alttext ) ); ?>
		   </a>
		</div>
	<?php endif; ?>
	<div class="card-body">
		<h4 class="card-title">
			<a href="<?php the_permalink( $the_ID ); ?>" title="<?php the_title_attribute( $the_ID ); ?>" >
				<?php echo get_the_title( $the_ID ) . $grave; ?>
			</a>
		</h4>
		<div class="card-text">
			<?php
			// If they're not a regular, we're not going to show this, OR run the calculations,
			// becuase the L Word has too many people.
			if ( $role == 'regular' ) {

				// We don't show this on show pages
				$shows     = array();
				if ( 'post_type_shows' !== get_post_type( ) ) {
					$shows     = lwtv_yikes_chardata( $the_ID, 'shows' );
				}
				
				$actors    = lwtv_yikes_chardata( $the_ID, 'actors' );
				$gender    = lwtv_yikes_chardata( $the_ID, 'gender' );
				$sexuality = lwtv_yikes_chardata( $the_ID, 'sexuality' );
				$cliches   = lwtv_yikes_chardata( $the_ID, 'cliches' );


				// List of Shows (will not show on show pages)
				if ( !empty( $shows ) ) {
					foreach ( $shows as $show ) {
						$show_post = get_post( $show['show']);
						echo '<div class="card-meta-item shows"><i class="fa fa-television" aria-hidden="true"></i> <a href="' . get_the_permalink( $show_post->ID )  .'">' . $show_post->post_title .'</a></div>';
					}
				}
	
				// List of Actors
				foreach ( $actors as $actor ) {
					echo '<div class="card-meta-item actors">'. lwtv_yikes_symbolicons( 'person.svg', 'fa-user' ) . ' ' . $actor . '</div>';
				}
	
				// Gender and Sexuality
				echo '<div class="card-meta-item"> ' . $gender . ' &bull; ' . $sexuality . '</div>';
	
				// List of Cliches
				echo '<div class="card-meta-item cliches"> ' . $cliches . '</div>';
			}
			?>
		</div>
	</div>
</div><!-- .card -->