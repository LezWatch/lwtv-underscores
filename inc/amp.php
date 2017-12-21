<?php
/**
 * AMP functions and definitions
 *
 * This will only run if AMP by Automattic is installed and active.
 * All the magic sauce is run by this.
 *
 * @package LezWatchTV
 */


/**
 * class LWTV_AMP
 * @since 1.0
 */
class LWTV_AMP {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {
		add_filter( 'amp_post_template_file', array( $this, 'amp_post_template_file' ), 10, 3 );
		add_action( 'pre_amp_render_post', array( $this, 'add_custom_actions' ) );
		add_action( 'amp_post_template_css', array( $this, 'amp_post_template_css' ) );
		add_filter( 'amp_post_template_analytics', array( $this, 'amp_add_custom_analytics' ) );
		add_action( 'amp_post_template_head', array( $this, 'amp_post_template_head' ) );
	}

	/*
	 * Template Filter
	 *
	 * Use our custom template for CPTs
	 *
	 * @param string $file The template file being used
	 * @param string $type Unused
	 * @param string $post The post being called
	 *
	 * @return file
	 */
	function amp_post_template_file( $file, $type, $post ) {
		if ( in_array( $post->post_type, array( 'post_type_shows', 'post_type_characters', 'post_type_actors' ) ) ) {
			$file = get_stylesheet_directory() . '/template-parts/amp-' . $post->post_type . '.php';
		}
		return $file;
	}

	/*
	 * Add Content Action
	 *
	 * Filter the content to add some of our own extras
	 *
	 */
	function add_custom_actions() {
		add_filter( 'the_content', array( $this, 'amp_add_content' ) );
	}

	/*
	 * Add Content Filter
	 *
	 * Generate the special data we use on characters and shows.
	 *
	 * @param string $content
	 *
	 * @return content
	 */
	function amp_add_content( $content ) {
		global $post;

		$post_type=$post->post_type;
		$post_id=$post->ID;
		$image_size = 'full';

		if ( $post_type === 'post_type_characters' ) {
			$image_size = 'character-img';
		}

		if ( $post_type === 'post_type_shows' ) {
			$image_size = 'show-img';
		}

		if ( has_post_thumbnail() && $post_type !== 'post' ) {
			$image = '<p class="lezwatch-featured-image ' . $image_size . '">' . get_the_post_thumbnail( $post_id, $image_size ) . '</p>';
			$content = $image . $content;
		}
		return $content;
	}


	/**
	 * Edit Head Action.
	 * 
	 * Add custom code to the header
	 */
	function amp_post_template_head( $amp_template ) {
		// Nothing Yet
	}

	/*
	 * Edit CSS Action
	 *
	 * Add our own custom CSS
	 *
	 */
	function amp_post_template_css( $amp_template ) {
		// only CSS here please...
		?>
		p.lezwatch-featured-image.character-img {
			padding: 10px;
		}
		.amp-wp-show-footer {
			background: #eee;
			border: 2px solid #d1548e;
		}
		.amp-wp-show-footer, .callout {
			padding: 1em;
			margin: 1.5em;
			overflow: auto;
			position: relative;
			border-width: 1px;
			border-style: solid;
		}
		.trigger-danger {
			background: #fbeaea;
			border: 2px solid #dc3232;
		}
		.trigger-warning {
			background: #fff3cd;
			border: 2px solid #f1c40f;
		}
		.trigger-info {
			background: #d1ecf1;
			border: 2px solid #0c5460;
		}
		.callout img {
			width: 36px;
			height: 36px;
			float: left;
			margin: 10px;
		}
		<?php
	}

	function amp_add_custom_analytics( $analytics ) {
		if ( ! is_array( $analytics ) ) {
			$analytics = array();
		}

		// https://developers.google.com/analytics/devguides/collection/amp-analytics/
		$analytics['lwtv-googleanalytics'] = array(
			'type' => 'googleanalytics',
			'attributes' => array(
				// 'data-credentials' => 'include',
			),
			'config_data' => array(
				'vars' => array(
					'account' => "UA-3187964-11",
				),
				'triggers' => array(
					'trackPageview' => array(
						'on' => 'visible',
						'request' => 'pageview',
					),
				),
			),
		);
		return $analytics;
	}

}

new LWTV_AMP();