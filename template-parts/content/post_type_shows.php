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
	$color = esc_attr( get_post_meta( get_the_ID(), 'lezshows_stars' , true ) );

	$star = '★';
	if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
		$starrequest  = wp_remote_get( LP_SYMBOLICONS_PATH.'star.svg' );
		$star = $starrequest['body'];
	}

	$icon = '<span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" title="' . ucfirst( $color ) . ' Star Show" class="show-star ' . $color . '">' . $star . '</span>';
}

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php
		if ( is_single() ) :
			the_title( '<h1 class="entry-title">', $icon . '</h1>' );
		else :
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a>' . $icon . '</h2>' );
		endif;
		
		the_post_thumbnail( 'show-img', array( 'alt' => get_the_title() , 'title' => get_the_title() ) );
		?>

		<div class="entry-meta">
			
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php
			
			LWTV_CPT_Shows::echo_content_warning();
			
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
