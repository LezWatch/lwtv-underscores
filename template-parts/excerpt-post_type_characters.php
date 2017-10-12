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
$role        = ( isset( $character['role_from'] ) )? $character['role_from'] : 'regular';
$archive     = ( is_archive() || is_tax() || is_page() )? true : false;

// Reset to prevent Teri Polo from overtaking the world
unset( $shows, $actors, $gender, $sexuality, $cliches, $grave );
?>

<div class="card"> 
	<?php if ( has_post_thumbnail( $the_ID ) ) : ?>
		<div class="character-image-wrapper">
			<a href="<?php the_permalink( $the_ID ); ?>" title="<?php get_the_title( $the_ID ); ?>" >
				<?php echo get_the_post_thumbnail( $the_ID, 'character-img', array( 'class' => 'card-img-top' , 'alt' => $alttext, 'title' => $alttext ) ); ?>
			</a>
		</div>
	<?php endif; ?>
	<div class="card-body">
		<h4 class="card-title">
			<a href="<?php the_permalink( $the_ID ); ?>" title="<?php the_title_attribute( $the_ID ); ?>" >
				<?php echo get_the_title( $the_ID ); ?>
				<?php if ( $archive ) echo lwtv_yikes_chardata( $the_ID, 'dead' ); ?>
			</a>
		</h4>
		<div class="card-text">
			<?php

			// Shows: Only show on NON show pages
			if ( 'post_type_shows' !== get_post_type() ) {
				$shows = lwtv_yikes_chardata( $the_ID, 'shows' );
			}

			// If we're a regular we show it all
			if ( $role == 'regular' && 'post_type_shows' == get_post_type() ) {
				$gender    = lwtv_yikes_chardata( $the_ID, 'gender' );
				$sexuality = lwtv_yikes_chardata( $the_ID, 'sexuality' );
				$cliches   = lwtv_yikes_chardata( $the_ID, 'cliches' );
			}
			
			if ( ( $role == 'regular' && 'post_type_shows' == get_post_type() ) || $archive ) {
				$actors    = lwtv_yikes_chardata( $the_ID, 'actors' );
			}

			// List of Shows (will not show on show pages)
			if ( isset( $shows ) ) {
				$show_title = array();
				foreach ( $shows as $each_show ) {
					if ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
						array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em>' );
					} else {
						array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em>' );
					}
				}
				$on_shows = ( empty( $show_title ) )? '<em>None</em>' : implode( ', ', $show_title );
				echo '<div class="card-meta-item shows">' . lwtv_yikes_symbolicons( 'tv_flatscreen.svg', 'fa-television' ) . '&nbsp;' . $on_shows . '</div>';
			}

			// List of Actors
			if ( isset( $actors ) ) {
				echo '<div class="card-meta-item actors">' . lwtv_yikes_symbolicons( 'person.svg', 'fa-user' ) . '&nbsp;' . implode( ", ", $actors ) . '</div>';
			}

			// Gender and Sexuality
			if ( isset( $gender ) && isset( $sexuality ) ) {
				echo '<div class="card-meta-item"> ' . $gender . ' &bull; ' . $sexuality . '</div>';
			}

			// List of Cliches
			if ( isset( $cliches ) ) {
				echo '<div class="card-meta-item cliches">Clichés: ' . $cliches . '</div>';
			}
			?>
		</div>
	</div>
	<?php
	if ( $archive ) {
		?>
		<div class="card-footer">
			<a href="<?php the_permalink( $the_ID ); ?>" title="<?php the_title_attribute( $the_ID ); ?>" class="btn btn-outline-primary">Character Profile</a>
		</div>
		<?php
	} 
	?>
</div><!-- .card -->