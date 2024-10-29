<?php
/**
 * Various shortcodes used on LezWatch.TV
 * Version: 2.1.0
 */

namespace LWTV\Features;

class Shortcodes {

	protected static $version;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'widget_text', 'do_shortcode' );
		self::$version = '2.1.0';
	}

	/**
	 * Init
	 */
	public function init() {
		add_shortcode( 'thismonth', array( $this, 'this_month' ) );
		add_shortcode( 'firstyear', array( $this, 'first_year' ) );
		add_shortcode( 'screener', array( $this, 'screener' ) );
		add_shortcode( 'glossary', array( $this, 'glossary' ) );
		add_shortcode( 'author-box', array( $this, 'author_box' ) );

		// Inline (will remain shortcodes)
		add_shortcode( 'copyright', array( $this, 'copyright' ) );
		add_shortcode( 'numposts', array( $this, 'numposts' ) );
		add_shortcode( 'badge', array( $this, 'badge' ) );

		// Blocks (all have been converted to Gutenblocks)
		add_shortcode( 'spoilers', array( $this, 'spoilers' ) );

		// Embeds (all work in Gutenberg)
		add_shortcode( 'gleam', array( $this, 'gleam' ) );
		add_shortcode( 'indiegogo', array( $this, 'indiegogo_shortcode' ) );
		add_shortcode( 'disneypress', array( $this, 'disneypress_shortcode' ) );
	}

	/*
	 * Display The first year we had queers
	 *
	 * Usage:
	 *  [firstyear]
	 *
	 * @since 1.1
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function first_year( $atts ) {
		return LWTV_FIRST_YEAR;
	}

	/*
	 * Display This Month recap
	 *
	 * Usage:
	 *  [thismonth]
	 *  [thismonth date="2017-01"]
	 *
	 * @since 1.0
	 */
	public function this_month( $atts ) {

		$default     = get_the_date( 'Y-m' );
		$count_array = array();
		$attributes  = shortcode_atts(
			array(
				'date' => $default,
			),
			$atts
		);

		// A little sanity checking
		// If it's not a valid date, we default to the time of the post
		if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}$/', $attributes['date'] ) ) {
			$attributes['date'] = $default;
		}
		$datetime = \DateTime::createFromFormat( 'Y-m', $attributes['date'] );
		$errors   = \DateTime::getLastErrors();
		if ( ! empty( $errors['warning_count'] ) ) {
			$datetime = \DateTime::createFromFormat( 'Y-m', $default );
		}

		// Regular post queerys
		$valid_post_types = array(
			'shows'      => 'post_type_shows',
			'characters' => 'post_type_characters',
		);

		foreach ( $valid_post_types as $name => $type ) {
			$post_args = array(
				'post_type'      => $type,
				'posts_per_page' => '-1',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'year'  => $datetime->format( 'Y' ),
						'month' => $datetime->format( 'm' ),
					),
				),
			);
			$queery    = new \WP_Query( $post_args );

			$count_array[ $name ] = $queery->post_count;
			wp_reset_postdata();
		}

		// Death count
		$death_query       = lwtv_plugin()->queery_post_meta_and_tax( 'post_type_characters', 'lezchars_death_year', $datetime->format( 'Y' ), 'lez_cliches', 'slug', 'dead', 'REGEXP' );
		$death_list_array  = lwtv_plugin()->get_list_of_dead_characters( $death_query );
		$death_query_count = 0;
		foreach ( $death_list_array as $the_dead ) {
			if ( is_array( $the_dead ) && isset( $the_dead['died'] ) && $datetime->format( 'm' ) === gmdate( 'm', $the_dead['died'] ) ) {
				++$death_query_count;
			}
		}
		$count_array['dead'] = $death_query_count;

		$output = '<ul>';
		foreach ( $count_array as $topic => $count ) {
			$subject = ( 'dead' === $topic ) ? 'characters' : $topic;
			// translators: %s is the subject
			$type    = ( 0 === $count ) ? 'no ' . $subject : sprintf( _n( '%s ' . rtrim( $subject, 's' ), '%s ' . $subject, $count ), $count );
			$added   = ( 'dead' === $topic ) ? 'died' : 'added';
			$output .= '<li>' . $type . ' ' . $added . '</li>';
		}
		$output .= '</ul>';

		return $output;
	}


	/**
	 * Screeners.
	 *
	 * @access public
	 * @param mixed $atts
	 * @return void
	 */
	public function screener( $atts ) {

		$attributes = shortcode_atts(
			array(
				'title'   => 'Coming Soon',
				'summary' => 'Coming soon ...',
				'queer'   => '3',
				'worth'   => 'meh',
				'trigger' => 'none',
				'star'    => 'none',
			),
			$atts
		);

		$queer = (float) $attributes['queer'];
		$queer = ( $queer < 0 ) ? 0 : $queer;
		$queer = ( $queer > 5 ) ? 5 : $queer;

		$worth = ( in_array( $attributes['worth'], array( 'yes', 'no', 'meh', 'tbd' ), true ) ) ? $attributes['worth'] : 'meh';
		switch ( $worth ) {
			case 'yes':
				$worth_icon  = 'thumbs-up';
				$worth_color = 'success';
				break;
			case 'no':
				$worth_icon  = 'thumbs-down';
				$worth_color = 'danger';
				break;
			case 'tbd':
				$worth_icon  = 'clock-retro';
				$worth_color = 'info';
				break;
			case 'meh':
				$worth_icon  = 'meh';
				$worth_color = 'warning';
				break;
		}
		$worth_image = lwtv_plugin()->get_symbolicon( $worth_icon . '.svg', 'fa-' . $worth_icon );

		// Get proper trigger warning data
		$warning = '';
		$trigger = ( in_array( $attributes['trigger'], array( 'high', 'medium', 'low' ), true ) ) ? $attributes['trigger'] : 'none';

		if ( 'none' !== $trigger ) {
			$warn_image = lwtv_plugin()->get_symbolicon( 'warning.svg', 'fa-exclamation-triangle' );
			switch ( $trigger ) {
				case 'high':
					$warn_color = 'danger';
					break;
				case 'medium':
					$warn_color = 'warning';
					break;
				case 'low':
					$warn_color = 'info';
					break;
			}
			$warning = '<span data-bs-toggle="tooltip" aria-label="Warning - This show contains triggers" title="Warning - This show contains triggers"><button type="button" class="btn btn-' . $warn_color . '"><span class="screener screener-warn ' . $warn_color . '" role="img">' . $warn_image . '</span></button></span>';
		}

		// Get proper Star
		$stars = '';
		$star  = ( in_array( $attributes['star'], array( 'gold', 'silver', 'bronze', 'anti' ), true ) ) ? $attributes['star'] : 'none';

		if ( 'none' !== $star ) {
			$stars = '<span data-bs-toggle="tooltip" aria-label="' . ucfirst( $star ) . ' Star Show" title="' . ucfirst( $star ) . ' Star Show"><button type="button" class="btn btn-info"><span role="img" class="screener screener-star ' . $star . '">' . lwtv_plugin()->get_symbolicon( 'star.svg', 'fa-star' ) . '</span></button></span>';
		}

		$output = '<div class="bd-callout screener-shortcode"><h5 id="' . esc_attr( $attributes['title'] ) . '">Screener Review on <em>' . esc_html( $attributes['title'] ) . '</em></h5>
		<p>' . esc_html( $attributes['summary'] ) . '</p>
		<p><span data-bs-toggle="tooltip" aria-label="How good is this show for queers?" title="How good is this show for queers?"><button type="button" class="btn btn-dark">Queer Score: ' . $queer . '</button></span> <span data-bs-toggle="tooltip" aria-label="Is this show worth watching? ' . ucfirst( $worth ) . '" title="Is this show worth watching? ' . ucfirst( $worth ) . '"><button type="button" class="btn btn-' . $worth_color . '">Worth It? <span role="img" class="screener screener-worthit ' . lcfirst( $worth ) . '">' . $worth_image . '</span></button></span> ' . $warning . $stars . '</p>
		</div>';

		return $output;
	}

	/*
	 * Outputs Glossary Terms
	 *
	 * Usage: [glossary taxonomy="taxonomy slug"]
	 *
	 * Attributes:
	 *  taxonomy = taxonomy slug
	 *
	 * @since 1.0
	 */
	public static function glossary( $atts ) {
		$attr = shortcode_atts(
			array(
				'taxonomy' => '',
			),
			$atts
		);

		$the_taxonomy = sanitize_text_field( $attr['taxonomy'] );
		$the_terms    = get_terms( $the_taxonomy );

		if ( '' === $the_taxonomy || is_array( $atts['taxonomy'] ) || ! $the_terms || is_wp_error( $the_terms ) ) {
			$return = '<p><em>Invalid Taxonomy (' . esc_attr( $atts['taxonomy'] ) . ') selected.';
		} else {
			$return = '<ul class="trope-list list-group">';
			// loop over each returned trope
			foreach ( $the_terms as $term ) {
				$icon    = lwtv_plugin()->get_symbolicon( get_term_meta( $term->term_id, 'lez_termsmeta_icon', true ) . '.svg', 'fa-square' );
				$return .= '<li class="list-group-item glossary term term-' . $term->slug . '"><a href="' . get_term_link( $term->slug, $the_taxonomy ) . '" rel="glossary term">' . $icon . '</a> <a href="' . get_term_link( $term->slug, $the_taxonomy ) . '" rel="glossary term" class="trope-link">' . $term->name . ' (' . get_term_meta( $term->term_id, 'lez_termsmeta_icon', true ) . ')</a></li>';
			}
			$return .= '</ul>';
		}

		return $return;
	}

	/*
	 * Display Author Box
	 *
	 * Usage: [author-box users=username]
	 *
	 * @since 1.2
	*/
	public static function author_box( $attributes ) {
		return ( new Author_Box() )->make( $attributes );
	}

	/*
	 * Display Copyright Year
	 *
	 * Usage: [copyright year=(start year) text=(copyright text)]
	 *
	 * Attributes:
	 *  year = (int) start year. (default: current year)
	 *  text = (text) copyright message. (default: &copy; )
	 *
	 * @since 1.0
	 */
	public function copyright( $atts ) {
		$attributes = shortcode_atts(
			array(
				'year' => 'auto',
				'text' => '&copy;',
			),
			$atts
		);

		$year = ( '' === $attributes['year'] || false === ctype_digit( $attributes['year'] ) ) ? gmdate( 'Y' ) : intval( $attributes['year'] );
		$text = ( '' === $attributes['text'] ) ? '&copy;' : sanitize_text_field( $attributes['text'] );

		if ( gmdate( 'Y' ) === $year || $year > gmdate( 'Y' ) ) {
			$output = gmdate( 'Y' );
		} elseif ( $year < gmdate( 'Y' ) ) {
			$output = $year . ' - ' . gmdate( 'Y' );
		}

		return $text . ' ' . $output;
	}

	/*
	 * Number of Posts via shortcodes
	 *
	 * Usage: [numposts data="posts" posttype="post type" term="term slug" taxonomy="taxonomy slug"]
	 *
	 * Attributes:
	 *    data     = [posts|taxonomy]
	 *    posttype = post type
	 *    term     = term slug
	 *    taxonomy = taxonomy slug
	 *
	 * @since 1.0
	 */
	public function numposts( $atts ) {
		$attr = shortcode_atts(
			array(
				'data'     => 'posts',
				'posttype' => 'post',
				'term'     => '',
				'taxonomy' => '',
			),
			$atts
		);

		if ( 'posts' === $attr['data'] ) {
			// Collect posts
			$posttype = sanitize_text_field( $attr['posttype'] );

			if ( true !== post_type_exists( $posttype ) ) {
				$posttype = 'post';
			}

			$to_count = wp_count_posts( $posttype );
			$return   = $to_count->publish;

		} elseif ( 'taxonomy' === $attr['data'] ) {

			// Collect Taxonomies
			$the_term     = sanitize_text_field( $attr['term'] );
			$the_taxonomy = sanitize_text_field( $attr['taxonomy'] );

			if ( ! is_null( $the_term ) && false !== $the_taxonomy ) {
				$all_taxonomies = ( empty( $the_taxonomy ) ) ? get_taxonomies() : array( $the_taxonomy );

				foreach ( $all_taxonomies as $taxonomy ) {
					$does_term_exist = term_exists( $the_term, $taxonomy );
					if ( 0 !== $does_term_exist && null !== $does_term_exist ) {
						$the_taxonomy = $taxonomy;
						break;
					} else {
						$the_taxonomy = false;
					}
				}
				$to_count = get_term_by( 'slug', $the_term, $the_taxonomy );
				$return   = $to_count->count;
			} else {
				$return = 'n/a';
			}
		} else {
			$return = 'n/a';
		}
		return $return;
	}

	/*
	 * Shortcode for an IndieGoGo Campaign
	 *
	 * Usage: [indiegogo url="https://www.indiegogo.com/projects/riley-parra-season-2-lgbt"]
	 *
	 * Attributes:
	 *  url: The URL of the project
	 *
	 * @since 1.3
	 */
	public function indiegogo_shortcode( $atts ) {
		$attr = shortcode_atts(
			array(
				'url' => '',
			),
			$atts
		);

		$url    = esc_url( $attr['url'] );
		$url    = rtrim( $url, '#/' );
		$url    = str_replace( 'projects/', 'project/', $url );
		$return = '<iframe src="' . $url . '/embedded" width="222px" height="445px" frameborder="0" scrolling="no"></iframe>';

		return $return;
	}

	/*
	 * Display Spoiler Warning
	 *
	 * Usage:
	 *  [spoilers]
	 *  [spoilers warning="OMG! SPIDERS!!!"]
	 *
	 * @since 1.3
	 */
	public function spoilers( $atts ) {
		$default    = 'Warning: This post contains spoilers!';
		$attributes = shortcode_atts(
			array(
				'warning' => $default,
			),
			$atts
		);
		$warning    = ( '' === $attributes['warning'] ) ? $default : sanitize_text_field( $attributes['warning'] );

		return '<div class="alert alert-danger" role="alert"><strong>' . $warning . '</strong></div>';
	}

	/*
	 * Display Badge Link
	 *
	 * Usage:
	 *  [badge url=LINK class="class class" role="role"]TEXT[/badge]
	 *
	 * @since 1.3
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function badge( $atts, $content = '', $tag = '' ) {
		$attributes = shortcode_atts(
			array(
				'url'   => '',
				'class' => '',
				'role'  => '',
			),
			$atts
		);
		$content    = ( '' === $content ) ? '' : sanitize_text_field( $content );
		$url        = esc_url( $attributes['url'] );
		$class      = esc_attr( $attributes['class'] );
		$role       = esc_attr( $attributes['role'] );

		return '<a class="' . $class . '" role="' . $role . '" href="' . $url . '">' . do_shortcode( $content ) . '</a>';
	}

	/*
	 * Display Gleam Contest
	 *
	 * Usage:
	 *  [gleam url="https://gleam.io/iR0GQ/gleam-demo-competition"]Gleam Demo Competition[/gleam]
	 *
	 * @since 1.3.1
	 */
	public function gleam( $atts, $content = null ) {
		$attributes = shortcode_atts(
			array(
				'url' => '',
			),
			$atts
		);

		// Bail if empty
		if ( empty( $attributes['url'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		return sprintf( '<a class="e-gleam" href="%s" rel="nofollow">%s</a><script src="//js.gleam.io/e.js" async="true"></script>', esc_url( $attributes['url'] ), do_shortcode( $content ) );
	}

	/*
	 * Display Disney/ABC Press Video
	 *
	 * Usage:
	 *  [disneypress url="https://www.disneyabcpress.com/freeform/video/B11DA0A8-9C3A-D70E-E864-3A4261C207FB/embed"]
	 *
	 * @since 2.0
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function disneypress( $atts, $content = null ) {
		$attributes = shortcode_atts(
			array(
				'url' => '',
			),
			$atts
		);

		// Bail if empty
		if ( empty( $attributes['url'] ) ) {
			return;
		}

		return sprintf( '<div class="embed-responsive embed-responsive-16by9"><iframe id="embed" src="%s" frameborder="0"></iframe></div>', esc_url( $attributes['url'] ) );
	}
}
