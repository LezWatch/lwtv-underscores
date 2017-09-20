<?php
/**
 * The template for displaying the footer.
 *
 * @package YIKES Starter
 */
?>

<footer id="colophon" class="site-footer" role="contentinfo">
	<div class="top-footer">
		<div class="container">
			<div class="row footer-widgets">
				<div class="col-md-3 footer-col first">
					<?php dynamic_sidebar( 'footer-1' ); ?>
				</div>

				<div class="col-md-3 footer-col">
					<?php dynamic_sidebar( 'footer-2' ); ?>
				</div>

				<div class="col-md-3 footer-col">
					<?php dynamic_sidebar( 'footer-3' ); ?>
				</div>

				<div class="col-md-3 footer-col">
					<?php dynamic_sidebar( 'footer-4' ); ?>
				</div>
			</div>
		</div>
	</div><!-- .top-footer -->

	<div class="bottom-footer">
		<div class="container">
			<div class="row">
				<div class="col credits">
					Copyright &#169;  2014–<?php esc_attr_e( date( 'Y' ) ); ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr__( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
					Powered by WordPress and hosted on DreamPress
				</div>
			</div><!-- .row -->
		</div><!-- .container -->
	</div><!-- .bottom-footer -->
</footer><!-- #colophon -->

<?php wp_footer(); ?>

</body>
</html>
