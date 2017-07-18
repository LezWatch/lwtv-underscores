<?php
/**
 * Name: Lez Amp
 * Decription: The AMP Stuff
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
		$post_type=$post->post_type;

		if ( 'post_type_characters' === $post_type || 'post_type_shows' === $post_type ) {
			$file = get_stylesheet_directory() . '/template-parts/amp-shows_chars.php';
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
		$image_size = 'featured-image';

		if ( $post_type === 'post_type_characters' ) {
			$image_size = 'character-img';

			// Generate Character Type
			// Usage: $character_type

			$character_type = '';

			// Generate list of shows
			// Usage: $appears

			$all_shows = get_post_meta( $post_id, 'lezchars_show_group', true );
			$show_title = array();
			foreach ( $all_shows as $each_show ) {
				array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> ('. $each_show['type'] .' character)' );
			}
			$on_shows = ( empty( $show_title ) )? ' no shows.' : ': '.implode(", ", $show_title );
			$appears = '<strong>Appears on</strong>' . $on_shows;

			// Generate actors
			// Usage: $actors

			$character_actors = get_post_meta( $post_id, 'lezchars_actor', true);
			if ( !is_array ( $character_actors ) ) {
				$character_actors = array( get_post_meta( $post_id, 'lezchars_actor', true) );
			}
			$actor_count = count( $character_actors );
			$actor_title = sprintf( _n( 'Actor', 'Actors', $actor_count ), $actor_count );
			$actors = '<strong>'. $actor_title .':</strong> '.implode(", ", $character_actors );

			// Generate RIP
			// Usage: $rip
			$rip = '';
			if ( get_post_meta( $post_id, 'lezchars_death_year', true) ) {
				$character_death = get_post_meta( $post_id, 'lezchars_death_year', true);
				if ( !is_array ( $character_death ) ) {
					$character_death = array( get_post_meta( $post_id, 'lezchars_death_year', true) );
				}
				$echo_death = array();
				foreach( $character_death as $death ) {
					$date = date_create_from_format('j/m/Y', $death );
					$echo_death[] = date_format($date, 'F d, Y');
				}
				$echo_death = implode(", ", $echo_death );
				$rip = '<strong>RIP:</strong> '. $echo_death;
			}

			// Generate list of Cliches
			// Usage: $cliches

			$cliches = get_the_term_list( $post_id, 'lez_cliches', 'Clichés: ', ', ' );

			// Generate Gender & Sexuality Data
			// Usage: $gender_sexuality
			$gender_sexuality = '';
			$gender_terms = get_the_terms( $post_id, 'lez_gender', true);
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				foreach($gender_terms as $gender_term) {
					$gender_sexuality .= '<a href="'. get_term_link( $gender_term->slug, 'lez_gender') .'" rel="tag" title="'. $gender_term->name .'">'. $gender_term->name .'</a> ';
				}
			}
			$sexuality_terms = get_the_terms( $post_id, 'lez_sexuality', true);
			if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
				foreach($sexuality_terms as $sexuality_term) {
					$gender_sexuality .= '<a href="'. get_term_link( $sexuality_term->slug, 'lez_sexuality') .'" rel="tag" title="'. $sexuality_term->name .'">'. $sexuality_term->name .'</a> ';
				}
			}


			$content =
				'<p>'. $appears .'</p>'
				.'<p><span class="entry-meta">' .$cliches .'</span></p>'
				.'<p>'. $gender_sexuality .'</p>'
				. $rip 
				.'<p>' .$actors. '</p>'
				.$content;
		}

		if ( $post_type === 'post_type_shows' ) {
			$image_size = 'show-img';
		}

		if ( has_post_thumbnail() && $post_type !== 'post' ) {
			$image = '<p class="lezwatch-featured-image '.$image_size.'">'.get_the_post_thumbnail( $post_id, $image_size ).'</p>';
			$content = $image . $content;
		}
		return $content;
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
			float: left;
			padding: 10px;
		}
		.entry-meta png {
			width: 16px;
			height: 16px;
		}
		.entry-meta png * {
			fill: #333;
		}
		.amp-wp-article-content {
			min-height: 300px;
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
		.callout-trigger {
			background: #fbeaea;
			border: 2px solid #dc3232;
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