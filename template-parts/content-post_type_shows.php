<?php
/**
 * Template part for displaying shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

// Default to a blank icon
$icon = '';

// If there's a star, we'll show it:
if ( get_post_meta( get_the_ID(), 'lezshows_stars', true) ) {
	$color = esc_attr( get_post_meta( get_the_ID(), 'lezshows_stars' , true ) );

	$star  = lwtv_yikes_symbolicons( 'star.svg', 'fa-star' );
	$icon .= ' <span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" title="' . ucfirst( $color ) . ' Star Show" class="show-star ' . $color . '">' . $star . '</span>';
}

// If we love this show, we'll show it:
if ( get_post_meta( get_the_ID(), 'lezshows_worthit_show_we_love', true) ) {
	$heart = lwtv_yikes_symbolicons( 'heart.svg', 'fa-heart' );
	$icon .= ' <span role="img" aria-label="We Love This Show!" title="We Love This Show!" class="show-we-love">' . $heart . '</span>';
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php		
		the_post_thumbnail( 'show-img', array( 'alt' => get_the_title() , 'title' => get_the_title() ) );

		if ( is_single() ) :
			the_title( '<h1 class="entry-title">', $icon . '</h1>' );
		else :
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a>' . $icon . '</h2>' );
		endif;

		?>

		<div class="entry-meta">
			
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php 
			$warning    = LWTV_Shows_Display::content_warning( get_the_ID() );
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
			<?php
		
			the_content();
			?>
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
	
		$havecharacters = LWTV_CPT_Characters::list_characters( $show_id, 'query' );
		$havecharcount  = LWTV_CPT_Characters::list_characters( $show_id, 'count' );
		$havedeadcount  = LWTV_CPT_Characters::list_characters( $show_id, 'dead' );
	
		if ( empty($havecharacters) || $havecharacters == '0' ) {
			echo '<p>There are no queers listed yet for this show.</p>';
		} else {
	
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' )
				$deadtext = sprintf( _n( '%s is dead', '%s are dead', $havedeadcount ), $havedeadcount );
	
			echo '<p>There '. sprintf( _n( 'is %s queer character', 'are %s queer characters', $havecharcount ), $havecharcount ).' listed for this show. Of those, ' . $deadtext . '.</p>';

			echo '<div class="container"><div class="row">';
			foreach( $havecharacters as $character ) {
				?><div class="col-sm-4"><?php
				include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
				?></div><?php
			}
			echo '</div></div>';
		}
		echo '</section>';
	?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php yikes_starter_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->