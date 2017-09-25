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

			<?php
				// This is fiddly because of Sara Lance
				$deadpage = false;
				$grave = '';
				$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
				if ( !empty( $term ) && $term->slug == 'dead' ) $deadpage = true;
				
				if ( get_post_meta( $the_ID, 'lezchars_death_year', true ) && !$deadpage ) {
					$grave = '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave">' . lwtv_yikes_symbolicons( 'rip_gravestone.svg', 'fa-times-circle' ) . '</span>';
				}
			?>

			<h4 class="card-title"><?php 
				echo get_the_title( $the_ID ) . $grave;
			?></h4>
			<div class="card-text">
				<?php
				// Don't show on show pages
				if ( 'post_type_shows' !== get_post_type( ) ) {
					$shows = get_post_meta( $the_ID, 'lezchars_show_group', true );
					foreach ($shows as $show) {
						$show_post = get_post( $show['show']);
						echo '<div class="card-meta-item"><i class="fa fa-television" aria-hidden="true"></i> <a href="' . get_the_permalink( $show_post->ID )  .'">' . $show_post->post_title .'</a></div>';
					}
				}
				?>

				<?php
					$field = get_post_meta( $the_ID, 'lezchars_actor', true );
					$field_value = isset( $field[0] ) ? $field[0] : ''; 
					echo '<div class="card-meta-item"><i class="fa fa-user" aria-hidden="true"></i> ' . $field_value  . '</div>';
				?>
		  	</div>
		</div>
  		<div class="card-footer">
			<a href="<?php the_permalink( $the_ID ); ?>" class="btn btn-sm btn-outline-primary">
				<span class="screen-reader-text">Go to</span> Character Profile
			</a>
		</div>
	</div><!-- .card -->