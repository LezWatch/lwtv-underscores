<?php
/**
 * Sample implementation of the Custom Header feature.
 *
 *
 * @link https://developer.wordpress.org/themes/functionality/custom-headers/
 *
 * @package LWTV Underscores
 */

/**
 * Set up the WordPress core custom header feature.
 *
 * @uses lwtv_theme_header_style()
 */
function lwtv_theme_custom_header_setup() {
	add_theme_support(
		'custom-header',
		apply_filters(
			'lwtv_theme_custom_header_args',
			array(
				'default-image'    => '',
				'width'            => 2250,
				'height'           => 602,
				'flex-height'      => true,
				'wp-head-callback' => 'lwtv_theme_header_style',
			)
		)
	);
}
add_action( 'after_setup_theme', 'lwtv_theme_custom_header_setup' );

if ( ! function_exists( 'lwtv_theme_header_style' ) ) :
	/**
	 * Styles the header image and text displayed on the blog.
	 *
	 * @see lwtv_theme_custom_header_setup().
	 */
	function lwtv_theme_header_style() {
		$header_text_color = get_header_textcolor();

		/*
		 * If no custom options for text are set, let's bail.
		 * get_header_textcolor() options: Any hex value, 'blank' to hide text. Default: HEADER_TEXTCOLOR.
		 */
		if ( get_theme_support( 'custom-header', 'default-text-color' ) === $header_text_color ) {
			return;
		}

		// If we get this far, we have custom styles. Let's do this.

		$custom_css = display_header_text()
			? sprintf(
				'.site-title a,
				.site-description {
					color: #%s;
				}',
				esc_attr( $header_text_color )
			)
			: '.site-title,
				.site-description {
					position: absolute;
					clip: rect(1px, 1px, 1px, 1px);
				}';
		?>
		<style type="text/css">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $custom_css is built from esc_attr() for user input; rest is static.
			echo $custom_css;
			?>
		</style>
		<?php
	}
endif;
