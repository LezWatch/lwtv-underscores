<?php
/**
 * The template for displaying the footer.
 *
 * @package YIKES Starter
 */

?>

<footer id="colophon" class="site-footer" role="contentinfo">
	<h2 class="screen-reader-text">Footer</h2>
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
					<h3 id="copyright-information" class="screen-reader-text">Copyright Information</h3>
					<?php dynamic_sidebar( 'subfooter-1' ); ?>
				</div>
			</div><!-- .row -->
		</div><!-- .container -->
	</div><!-- .bottom-footer -->
</footer><!-- #colophon -->

<?php wp_footer(); ?>

<!--
	If you're reading this source code, hi!
	Everything can be found at the following URLs:
	Theme - https://github.com/lezwatch/lwtv-underscores
	Plugin - https://github.com/lezwatch/lwtv-plugin

	You're welcome to fork our code and make your own site with it.
-->

</body>
</html>
