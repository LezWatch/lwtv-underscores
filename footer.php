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
					<?php dynamic_sidebar( 'footer-4' ); ?>
				</div>
			</div><!-- .row -->
		</div><!-- .container -->
	</div><!-- .bottom-footer -->
</footer><!-- #colophon -->

<?php wp_footer(); ?>

</body>
</html>
