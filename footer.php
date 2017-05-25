<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package LezWatch_TV
 */

?>

	</div><!-- #content -->

	<footer id="colophon" class="site-footer" role="contentinfo">
		<div class="footer-widget">
			<?php dynamic_sidebar( 'footer-1' ); ?>
		</div><!-- .site-info -->
	</footer><!-- #colophon -->

	<div class="after-footer-widget">
		<?php dynamic_sidebar( 'after-footer' ); ?>
	</div>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
