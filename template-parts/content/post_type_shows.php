<?php
/**
 * Template part for displaying shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */

// Add star if the show is deserving
$icon = '';
if ( get_post_meta( get_the_ID(), 'lezshows_stars', true) ) {
	$color = esc_attr( get_post_meta( get_the_ID( ), 'lezshows_stars' , true ) );
	$icon = '<span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" title="' . ucfirst( $color ) . ' Star Show" class="show-star ' . $color . '">' . file_get_contents( LWTV_SYMBOLICONS_PATH . '/svg/star.svg' ) . '</span>';
}

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php
		if ( is_single() ) :
			the_title( '<h1 class="entry-title">', $icon.'</h1>' );
		else :
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a>'.$icon.'</h2>' );
		endif;
		
		the_post_thumbnail( 'show-img', array( 'alt' => get_the_title() , 'title' => get_the_title() ) );
		?>

		<div class="entry-meta">
			
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php
			
			if ( get_post_meta( get_the_ID(), 'lezshows_triggerwarning', true ) == 'on' ) {
				echo '<div class="callout callout-trigger"><span role="img" aria-label="Warning Hand" title="Warning Hand">' . file_get_contents( LWTV_SYMBOLICONS_PATH . '/svg/hand.svg' ) . '</span><p><strong>TRIGGER WARNING!</strong> This show is intended for <em>mature audiences</em> only. If explicit violence, sex, and abuse aren\'t your speed, neither is this show.</p></div>';
			}
			
			?>
			<section class="shows-extras" name="overview" id="overview">
				<h2>Overview</h2>
				<?php
			
				the_content();
				?>
			</section>
			<?php
				
			get_template_part( 'template-parts/content/post_type_shows', 'meta' );
			

		?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php lwtv_underscore_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->
