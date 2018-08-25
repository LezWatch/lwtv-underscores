<?php
/**
 * AMP functions and definitions
 *
 * This will only run if AMP by Automattic is installed and active.
 * All the magic sauce is run by this.
 *
 * @package LezWatch.TV
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
	public function amp_post_template_file( $file, $type, $post ) {
		if ( in_array( $post->post_type, array( 'post_type_shows', 'post_type_characters', 'post_type_actors' ), true ) ) {
			$file = get_stylesheet_directory() . '/template-parts/amp.php';
		}
		return $file;
	}

	/*
	 * Add Content Action
	 *
	 * Filter the content to add some of our own extras
	 *
	 */
	public function add_custom_actions() {
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
	public function amp_add_content( $content ) {
		$post_type    = get_post_type( get_the_ID() );
		$post_type_is = rtrim( str_replace( 'post_type_', '', $post_type ), 's' );
		$return       = '';

		// Set Image
		if ( has_post_thumbnail() && 'post' !== $post_type ) {
			$image   = '<p class="lezwatch-featured-image ' . $post_type_is . '-img">' . get_the_post_thumbnail( $post_id, $post_type_is . '-img' ) . '</p>';
			$return .= $image;
		}

		// Warning
		$warning_card = lwtv_yikes_content_warning( get_the_ID() );
		if ( ! isset( $warning_card ) || 'none' !== $warning_card['card'] ) {
			$warning = '<div class="callout trigger-' . $warning_card['card'] . '" role="alert">' . $warning_card['content'] . '</div>';
			$return .= $warning;
		}

		// Actor adjustments
		if ( empty( $content ) && 'post_type_actors' === $post_type ) {
			$content = esc_html( get_the_title() ) . ' is an actor who has played at least one character on TV. Information on this page has not yet been verified. Please <a href="/about/contact/">contact us</a> with any corrections or amendments.';
		}

		$return .= $content;

		switch ( $post_type ) {
			case 'post_type_shows':
				$return .= $this->post_type_shows( get_the_ID() );
				break;
			case 'post_type_characters':
				$return .= $this->post_type_characters( get_the_ID() );
				break;
			case 'post_type_actors':
				$return .= $this->post_type_actors( get_the_ID() );
				break;
		}

		$return .= '<div style="clear:both"></div>';

		return $return;
	}

	/*
	 * Generate the Actor content
	 *
	 * @return return
	 */
	public function post_type_actors( $post_id ) {

		// Add character info:
		// This just gets the numbers of all characters and how many are dead.
		$all_chars     = lwtv_yikes_actordata( $post_id, 'characters' );
		$havecharcount = count( $all_chars );
		$havedeadcount = count( lwtv_yikes_actordata( $post_id, 'dead' ) );
		$characters    = '<section id="characters" class="shows-extras"><h2>Characters</h2>';

		if ( empty( $havecharcount ) || '0' === $havecharcount ) {
			$characters .= '<p>There are no characters listed yet for this actor.</p>';
		} else {
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) {
				// translators: %s is a number.
				$deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
			}
			// translators: %s is 'are' or a number.
			$characters .= wp_kses_post( '<p>There ' . sprintf( _n( 'is <strong>%s</strong> character', 'are <strong>%s</strong> characters', $havecharcount ), $havecharcount ) . ' listed for this actor; ' . $deadtext . '.</p>' );

			$characters .= '<ul class="character-list">';
			foreach ( $all_chars as $a_character ) {
				$characters .= '<li class="clearfix"><a href="' . $a_character['url'] . 'amp/">' . $a_character['title'] . '</a></li>';
			}
			$characters .= '</ul>';
		}
		$characters .= '</section>';

		$return .= $characters;
		$return .= '<section class="amp-wp-show-footer">';

		// Generate Life Stats
		// Usage: $life
		$life = array();
		if ( get_post_meta( get_the_ID(), 'lezactors_birth', true ) ) {
			$get_birth          = new DateTime( get_post_meta( get_the_ID(), 'lezactors_birth', true ) );
			$age                = lwtv_yikes_actordata( get_the_ID(), 'age', true );
			$life['age']        = $age->format( '%Y years old' );
			$life['birth date'] = date_format( $get_birth, 'F d, Y' );
		}
		if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
			$get_death     = new DateTime( get_post_meta( get_the_ID(), 'lezactors_death', true ) );
			$life['death'] = date_format( $get_death, 'F d, Y' );
		}

		// Generate Gender & Sexuality Data
		// Usage: $gender_sexuality
		$gender_sexuality = array();
		$gender           = lwtv_yikes_actordata( get_the_ID(), 'gender', true );
		$sexuality        = lwtv_yikes_actordata( get_the_ID(), 'sexuality', true );
		if ( isset( $gender ) && ! empty( $gender ) ) {
			$gender_sexuality['Gender Orientation'] = $gender;
		}
		if ( isset( $sexuality ) && ! empty( $sexuality ) ) {
			$gender_sexuality['Sexual Orientation'] = $sexuality;
		}

		// Generate URLs
		// Usage: $urls
		$urls = array();
		if ( get_post_meta( get_the_ID(), 'lezactors_homepage', true ) ) {
			$urls['home'] = array(
				'name' => 'Website',
				'url'  => esc_url( get_post_meta( get_the_ID(), 'lezactors_homepage', true ) ),
				'fa'   => 'fas fa-home',
			);
		}
		if ( get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ) {
			$urls['imdb'] = array(
				'name' => 'IMDb',
				'url'  => esc_url( 'https://www.imdb.com/name/' . get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ),
				'fa'   => 'fab fa-imdb',
			);
		}
		if ( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ) {
			$urls['wikipedia'] = array(
				'name' => 'WikiPedia',
				'url'  => esc_url( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ),
				'fa'   => 'fab fa-wikipedia-w',
			);
		}
		if ( get_post_meta( get_the_ID(), 'lezactors_twitter', true ) ) {
			$urls['twitter'] = array(
				'name' => 'Twitter',
				'url'  => esc_url( 'https://twitter.com/' . get_post_meta( get_the_ID(), 'lezactors_twitter', true ) ),
				'fa'   => 'fab fa-twitter',
			);
		}
		if ( get_post_meta( get_the_ID(), 'lezactors_instagram', true ) ) {
			$urls['instagram'] = array(
				'name' => 'Instagram',
				'url'  => esc_url( 'https://www.instagram.com/' . get_post_meta( get_the_ID(), 'lezactors_instagram', true ) ),
				'fa'   => 'fab fa-instagram',
			);
		}

		$return .= '<ul>';

		if ( count( $gender_sexuality ) > 0 ) {
			foreach ( $gender_sexuality as $title => $data ) {
				$return .= '<li><strong>' . esc_html( ucfirst( $title ) ) . '</strong>: ' . wp_kses_post( $data ) . '</li>';
			}
		}

		if ( count( $life ) > 0 ) {
			foreach ( $life as $event => $date ) {
				$return .= '<li><strong>' . esc_html( ucfirst( $event ) ) . '</strong>: ' . wp_kses_post( $date ) . '</li>';
			}
		}

		if ( count( $urls ) > 0 ) {
			$return .= '<li><strong>Links</strong> ';
			foreach ( $urls as $source ) {
				$return .= ' &bull; <a href="' . esc_url( $source['url'] ) . '">' . esc_html( $source['name'] ) . '</a>';
			}
			echo '</li>';
		}

		$return .= '</ul>';

		$return .= '</section>';

		return $return;
	}

	/*
	 * Generate the Character content
	 *
	 * @return return
	 */
	public function post_type_characters( $post_id ) {

		$return = '<section class="amp-wp-show-footer">';

		// Generate Character Type
		// Usage: $character_type
		$character_type = '';

		// Generate list of shows
		// Usage: $appears
		$all_shows  = get_post_meta( $post_id, 'lezchars_show_group', true );
		$show_title = array();
		foreach ( $all_shows as $each_show ) {
			array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> (' . $each_show['type'] . ' character)' );
		}
		$on_shows = ( empty( $show_title ) ) ? ' no shows.' : ': ' . implode( ', ', $show_title );
		$appears  = '<strong>Appears on</strong>' . $on_shows;

		// Generate actors
		// Usage: $actors
		$all_actors = lwtv_yikes_chardata( $post_id, 'actors' );
		if ( '' !== $all_actors ) {
			$the_actors = array();
			foreach ( $all_actors as $each_actor ) {
				if ( get_post_status( $each_actor ) !== 'publish' ) {
					array_push( $the_actors, '<span class="disabled-show-link">' . get_the_title( $each_actor ) . '</span>' );
				} else {
					array_push( $the_actors, '<a href="' . get_permalink( $each_actor ) . 'amp/">' . get_the_title( $each_actor ) . '</a>' );
				}
			}
		}

		$is_actors = ( empty( $the_actors ) ) ? ' None' : ': ' . implode( ', ', $the_actors );
		if ( isset( $the_actors ) && count( $the_actors ) !== 0 ) {
			$actor_title = _n( 'Actor', 'Actors', count( $all_actors ) );
			$actors      = '<strong>' . $actor_title . '</strong>' . $is_actors;
		}

		// Generate RIP
		// Usage: $rip
		$rip = '';
		if ( get_post_meta( $post_id, 'lezchars_death_year', true ) ) {
			$character_death = get_post_meta( $post_id, 'lezchars_death_year', true );
			if ( ! is_array( $character_death ) ) {
				$character_death = array( get_post_meta( $post_id, 'lezchars_death_year', true ) );
			}
			$echo_death = array();
			foreach ( $character_death as $death ) {
				if ( '/' !== substr( $death, 2, 1 ) ) {
					$date = date_format( date_create_from_format( 'Y-m-d', $death ), 'd F Y' );
				} else {
					$date = date_format( date_create_from_format( 'm/d/Y', $death ), 'F d, Y' );
				}
				$echo_death[] = $date;
			}
			$echo_death = implode( '; ', $echo_death );
			$rip        = '(RIP ' . $echo_death . ')';
		}

		// Generate Status
		// Usage: $dead_or_alive
		$doa_status    = ( has_term( 'dead', 'lez_cliches', $post_id ) ) ? 'Dead' : 'Alive';
		$dead_or_alive = '<strong>Status:</strong> ' . $doa_status;

		// Generate list of Cliches
		// Usage: $cliches
		$cliches = get_the_term_list( $post_id, 'lez_cliches', '<strong>Clichés:</strong> ', ', ' );

		// Generate Gender & Sexuality Data
		// Usage: $gender_sexuality
		$gender_sexuality = '';
		$gender_terms     = get_the_terms( $post_id, 'lez_gender', true );
		if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
			foreach ( $gender_terms as $gender_term ) {
				$gender_sexuality .= '<a href="' . get_term_link( $gender_term->slug, 'lez_gender' ) . '" rel="tag" title="' . $gender_term->name . '">' . $gender_term->name . '</a> ';
			}
		}
		$sexuality_terms = get_the_terms( get_the_ID(), 'lez_sexuality', true );
		if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
			foreach ( $sexuality_terms as $sexuality_term ) {
				$gender_sexuality .= '<a href="' . get_term_link( $sexuality_term->slug, 'lez_sexuality' ) . '" rel="tag" title="' . $sexuality_term->name . '">' . $sexuality_term->name . '</a> ';
			}
		}

		$content = '<p>' . $gender_sexuality . '</p><ul><li>' . $cliches . '</li><li>' . $actors . '</li><li>' . $appears . '</li><li>' . $dead_or_alive . ' ' . $rip . '</li></ul>';

		$return .= $content;
		$return .= '</section>';

		return $return;
	}

	/*
	 * Generate the SHOW content
	 *
	 * @return return
	 */
	public function post_type_shows( $post_id ) {
		$return = '';

		// Add plots
		if ( ( get_post_meta( $post_id, 'lezshows_plots', true ) ) ) {
			$plots   = '<section id="timeline" class="shows-extras"><h2>Queer Plotline Timeline</h2>' . wp_kses_post( get_post_meta( $post_id, 'lezshows_plots', true ) ) . '</section>';
			$return .= $plots;
		}

		// Add episodes
		if ( ( get_post_meta( $post_id, 'lezshows_episodes', true ) ) ) {
			$episodes = '<section id="timeline" class="shows-extras"><h2>Notable Lez-Centric Episodes</h2>' . wp_kses_post( get_post_meta( $post_id, 'lezshows_episodes', true ) ) . '</section>';
			$return  .= $episodes;
		}

		// Add character info:
		$havecharacters = LWTV_CPT_Characters::list_characters( $post_id, 'query' );
		$havecharcount  = LWTV_CPT_Characters::list_characters( $post_id, 'count' );
		$havedeadcount  = LWTV_CPT_Characters::list_characters( $post_id, 'dead' );
		$characters     = '<section id="characters" class="shows-extras"><h2>Characters (' . (int) $havecharcount . ')</h2>';

		if ( empty( $havecharacters ) ) {
			$characters .= '<p>There are no characters listed for this show.</p>';
		} else {
			$characters .= '<ul class="character-list">';
			foreach ( $havecharacters as $character ) {
				$characters .= '<li class="clearfix"><a href="' . $character['url'] . 'amp/">' . $character['title'] . '</a></li>';
			}
			$characters .= '</ul>';
		}
		$characters = '</section>';
		$return    .= $characters;

		// Add Info Box
		$networks        = get_the_terms( get_the_ID(), 'lez_stations' );
		$airdates        = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
		$tropes          = get_the_terms( get_the_ID(), 'lez_tropes' );
		$genres          = get_the_terms( get_the_ID(), 'lez_genres' );
		$intersections   = get_the_terms( get_the_ID(), 'lez_intersections' );
		$realness_rating = (int) get_post_meta( get_the_ID(), 'lezshows_realness_rating', true );
		$realness_rating = min( $realness_rating, 5 );
		$show_quality    = (int) get_post_meta( get_the_ID(), 'lezshows_quality_rating', true );
		$show_quality    = min( $show_quality, 5 );
		$screen_time     = (int) get_post_meta( get_the_ID(), 'lezshows_screentime_rating', true );
		$screen_time     = min( $screen_time, 5 );
		$info_array      = array();
		$ratings_array   = array();

		$info_array = array(
			'worthit' => '<strong>Worth Watching?</strong> ' . get_post_meta( get_the_ID(), 'lezshows_worthit_rating', true ),
			'score'   => '<strong>Show Score:</strong> ' . round( get_post_meta( get_the_ID(), 'lezshows_the_score', true ), 2 ),
		);

		if ( $networks && ! is_wp_error( $networks ) ) {
			$info_array['networks'] = get_the_term_list( get_the_ID(), 'lez_stations', '<strong>Network(s):</strong> ', ', ' );
		}

		if ( $airdates ) {
			$airdate = $airdates['start'] . ' - ' . $airdates['finish'];
			if ( $airdates['start'] === $airdates['finish'] ) {
				$airdate = $airdates['finish'];
			}
			$info_array['airdates'] = '<strong>Airdates:</strong> ' . $airdate;
		}

		if ( $tropes && ! is_wp_error( $tropes ) ) {
			$trope_echo = get_the_term_list( get_the_ID(), 'lez_tropes', '<p class="entry-meta"><strong>Tropes:</strong> ', ', ' );
		}

		if ( $genres && ! is_wp_error( $genres ) ) {
			$genre_echo = get_the_term_list( get_the_ID(), 'lez_genres', '<p class="entry-meta"><strong>Genres:</strong> ', ', ' );
		}

		if ( $intersections && ! is_wp_error( $intersections ) ) {
			$intersection_echo = get_the_term_list( get_the_ID(), 'lez_intersections', '<p class="entry-meta"><strong>Intersectionality:</strong> ', ', ' );
		}

		if ( $realness_rating ) {
			$ratings_array['realness'] = '<strong>Realness:</strong> ' . $realness_rating . ' (out of 5)';
		}
		if ( $show_quality ) {
			$ratings_array['quality'] = '<strong>Quality:</strong> ' . $show_quality . ' (out of 5)';
		}
		if ( $screen_time ) {
			$ratings_array['screen_time'] = '<strong>Screen Time:</strong> ' . $screen_time . ' (out of 5)';
		}

		$infobox  = '<section class="amp-wp-show-footer"><center>';
		$infobox .= '<p class="entry-meta">' . join( ' &bull; ', $info_array ) . '</p>';
		if ( $trope_echo ) {
			$infobox .= $trope_echo;
		}
		if ( $genre_echo ) {
			$infobox .= $genre_echo;
		}
		if ( $intersection_echo ) {
			$infobox .= $intersection_echo;
		}
		$infobox .= '<p class="entry-meta">' . join( ' &bull; ', $ratings_array ) . '</p>';
		$infobox .= '</center></section>';

		$return .= $infobox;

		return $return;
	}

	/**
	 * Edit Head Action.
	 *
	 * Add custom code to the header
	 */
	public function amp_post_template_head( $amp_template ) {
		// Nothing Yet
	}

	/*
	 * Edit CSS Action
	 *
	 * Add our own custom CSS
	 *
	 */
	public function amp_post_template_css( $amp_template ) {
		// only CSS here please...
		?>
		p.lezwatch-featured-image.character-img {
			padding: 10px;
			float: left;
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
		.listicle {counter-reset:listicle-counter;padding:10px}
		.listicle dt:before {content:counter(listicle-counter) '. ';counter-increment:listicle-counter;font-size:2rem;font-weight:600;margin-right:6px}
		.listicle.reversed dt:before{counter-increment:listicle-counter -1}
		.listicle dt {font-size:2rem;font-weight:400;margin-bottom:1rem}
		.listicle dt:first-child {margin-top:0}
		.listicle dd {margin:0 2rem 0 2rem}
		<?php
	}

	public function amp_add_custom_analytics( $analytics ) {
		if ( ! is_array( $analytics ) ) {
			$analytics = array();
		}

		// https://developers.google.com/analytics/devguides/collection/amp-analytics/
		$analytics['lwtv-googleanalytics'] = array(
			'type'        => 'googleanalytics',
			'attributes'  => array(
				// 'data-credentials' => 'include',
			),
			'config_data' => array(
				'vars'     => array(
					'account' => 'UA-3187964-11',
				),
				'triggers' => array(
					'trackPageview' => array(
						'on'      => 'visible',
						'request' => 'pageview',
					),
				),
			),
		);
		return $analytics;
	}

}

new LWTV_AMP();
