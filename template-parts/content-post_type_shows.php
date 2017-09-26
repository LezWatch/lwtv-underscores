<?php
/**
 * Template part for displaying shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="entry-content">
		<?php
			$warning    = lwtv_yikes_content_warning( get_the_ID() );
			$warn_image = lwtv_yikes_symbolicons( 'hand.svg', 'fa-hand-paper-o' );
			
			if ( $warning['card'] != 'none' ) {
			?>
				<div class="callout card card-inverse alert-<?php echo $warning['card']; ?> text-center">
					<div class="card-block">
						<p><span class="callout-<?php echo $warning['card']; ?>" role="img" aria-label="Warning Hand" title="Warning Hand"><?php echo $warn_image; ?></span><?php echo $warning['content']; ?></p>
					</div>
				</div>
			<?php
			}
		?>

		<section class="shows-extras" name="overview" id="overview">
			<h2>Overview</h2>
			<?php the_content(); ?>
		</section>
		<?php
		
		$show_id = $post->ID;
	
		// Queer Plots - Only display if they exist
		if ( (get_post_meta($show_id, "lezshows_plots", true) ) ) { ?>
			<section name="timeline" id="timeline" class="shows-extras"><h2>Queer Plotline Timeline</h2>
			<?php echo apply_filters('the_content', wp_kses_post( get_post_meta($show_id, 'lezshows_plots', true) ) ); ?></section><?php
		}
	
		// Best Lez Episodes - Only display if they exist
		if ( ( get_post_meta($show_id, "lezshows_episodes", true ) ) ) { ?>
			<section name="episodes" id="episodes" class="shows-extras"><h2>Notable Lez-Centric Episodes</h2>
			<?php echo apply_filters('the_content', wp_kses_post( get_post_meta($show_id, 'lezshows_episodes', true) ) ); ?></section> <?php
		}
	
		// Related Posts
		$slug = get_post_field( 'post_name', get_post() );
		?>
		<section name="related-posts" id="related-posts" class="shows-extras">		
			<?php
				echo LWTV_CPT_Shows::related_posts( $slug, 'post' );
			?>
			</section> 
		<?php
	
		// Great big characters section!
		echo '<section name="characters" id="characters" class="shows-extras">';
		echo '<h2>Characters</h2>';
	
		// This just gets the numbers of all characters and how many are dead.
		$havecharcount  = LWTV_CPT_Characters::list_characters( $show_id, 'count' );
		$havedeadcount  = LWTV_CPT_Characters::list_characters( $show_id, 'dead' );
	
		if ( empty( $havecharcount ) || $havecharcount == '0' ) {
			echo '<p>There are no queers listed yet for this show.</p>';
		} else {
	
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) $deadtext = sprintf( _n( '%s is dead', '%s are dead', $havedeadcount ), $havedeadcount );
	
			echo '<p>There '. sprintf( _n( 'is %s queer character', 'are %s queer characters', $havecharcount ), $havecharcount ).' listed for this show. Of those, ' . $deadtext . '.</p>';

			// Get the list of REGULAR characters
			$chars_regular = lwtv_yikes_get_characters_for_show( $show_id, 'regular' );
			if ( !empty( $chars_regular ) ) {	
				?><h3>Regulars</h3>
				<div class="container"><div class="row"><?php
				foreach( $chars_regular as $character ) {
					?><div class="col-sm-4"><?php
						include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
					?></div><?php
				}
				echo '</div></div>';
			}
			// Get the list of RECURRING characters
			$chars_recurring = lwtv_yikes_get_characters_for_show( $show_id, 'recurring' );
			if ( !empty( $chars_recurring ) ) {	
				?><h3>Recurring</h3>
				<div class="container"><div class="row"><?php
				foreach( $chars_recurring as $character ) {
					?><div class="col-sm-4"><?php
						include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
					?></div><?php
				}
				echo '</div></div>';
			}
			// Get the list of GUEST characters
			$chars_guest = lwtv_yikes_get_characters_for_show( $show_id, 'guest' );
			if ( !empty( $chars_guest ) ) {	
				?><h3>Guest</h3>
				<ul><?php
				foreach( $chars_guest as $character ) {
					?><li><a href="<?php the_permalink( $character['id'] ); ?>" title="<?php the_title_attribute( $character['id'] ); ?>" ><?php echo get_the_title( $character['id'] ); ?></a></li><?php
				}
				echo '</ul>';
			}
		}
		echo '</section>';
	?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php yikes_starter_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->