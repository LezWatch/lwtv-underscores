<?php
/*
 * The AMP template for shows, actors, and characters.
 *
 * Customized AMP Template used by the custom post types.
 * This file is called by /inc/amp.php - if that file is
 * missing, this does nothing.
 *
 * @package LezWatch.TV
 */
?>
<!doctype html>
<html amp <?php echo AMP_HTML_Utils::build_attributes_string( $this->get( 'html_tag_attributes' ) ); // phpcs:ignore WordPress.Security.EscapeOutput. ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
	<?php do_action( 'amp_post_template_head', $this ); ?>
	<style amp-custom>
		<?php
			require_once WP_PLUGIN_DIR . '/amp/templates/style.php';
			do_action( 'amp_post_template_css', $this );
		?>
	</style>
</head>

<body class="<?php echo esc_attr( $this->get( 'body_class' ) ); ?>">

<?php require_once WP_PLUGIN_DIR . '/amp/templates/header-bar.php'; ?>

<article class="amp-wp-article">

	<header class="amp-wp-article-header">
		<h1 class="amp-wp-title"><?php echo wp_kses_data( $this->get( 'post_title' ) ); ?></h1>
	</header>

	<div class="amp-wp-article-content">
		<?php echo lwtv_sanitized( $this->get( 'post_amp_content' ) ); ?>
	</div>

</article>

<footer class="amp-wp-footer">
	<div>
		<h2><?php echo esc_html( $this->get( 'blog_name' ) ); ?></h2>
		<p><a href="<?php echo esc_url( get_permalink() ); ?>">View Full Site</a> </p>
		<a href="#top" class="back-to-top"><?php esc_html_e( 'Back to top', 'amp' ); ?></a>
	</div>
</footer>

<?php do_action( 'amp_post_template_footer', $this ); ?>

</body>
</html>
