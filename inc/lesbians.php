<?php
/**
 * Weird LWTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play
 * nicely with each other. Including cursing at Jetpack.
 *
 * @package LezWatch.TV
 */

/** THE SECURITY SECTION **/

/**
 * Filter THEME updates in case some idiot ever submits lwtv-underscores as a theme.
 * Look, this should never happen, but the last thing we want is for this theme to
 * get updated by some rando with a grudge.
 */
// @codingStandardsIgnoreStart
add_filter( 'http_request_args', function ( $response, $url ) {
	if ( 0 === strpos( $url, 'https://api.wordpress.org/themes/update-check' ) ) {
		$themes = json_decode( $response['body']['themes'] );
		unset( $themes->themes->{get_option( 'template' )} );
		unset( $themes->themes->{get_option( 'stylesheet' )} );
		$response['body']['themes'] = json_encode( $themes );
	}
	return $response;
}, 10, 2 );
// @codingStandardsIgnoreEnd

/**
 * Disable update notifications for your theme. This doesn't change auto
 * updates, but it does hide things.
 */
function lwtv_disable_theme_update_notification( $value ) {
	if ( isset( $value ) && is_object( $value ) ) {
		unset( $value->response['lwtv-underscores'] );
	}
	return $value;
}
add_filter( 'site_transient_update_themes', 'lwtv_disable_theme_update_notification' );

/** THE GENERAL SECTION **/

/**
 * Loved Shows Shuffle
 *
 * This puts the loved show in a random order so it'll be different
 * for reloads.
 */
// @codingStandardsIgnoreStart
add_filter( 'the_posts', function( $posts, \WP_Query $query ) {
	$pick = $query->get( '_loved_shuffle' );
	if ( is_numeric( $pick ) ) {
		shuffle( $posts );
		$posts = array_slice( $posts, 0, (int) $pick );
	}
		return $posts;
}, 10, 2 );
// @codingStandardsIgnoreEnd

/*
 * Filter Comment Status
 *
 * Remove comments from attachment pages. This is for SEO and spam
 * purposes. Why do spammers spam?
 */
function lwtv_yikes_filter_media_comment_status( $open, $post_id ) {
	if ( 'attachment' === get_post_type() ) {
		return false;
	}
	return $open;
}
add_filter( 'comments_open', 'lwtv_yikes_filter_media_comment_status', 10, 2 );

/*
 * Auto apply alt tags
 *
 * If an image has no alt tags, we automagically apply the parent
 * post title if that exists, falling back to the image title
 * itself if not. This is for accessibility.
 */
function lwtv_auto_alt_fix( $attributes, $attachment ) {
	if ( ! isset( $attributes['alt'] ) || '' === $attributes['alt'] ) {
		$parent_titles     = get_the_title( $attachment->post_parent );
		$attributes['alt'] = ( isset( $parent_titles ) && '' !== $parent_titles ) ? $parent_titles : get_the_title( $attachment->ID );
	}
	return $attributes;
}
add_filter( 'wp_get_attachment_image_attributes', 'lwtv_auto_alt_fix', 10, 2 );

/** THE JETPACK SECTION **/

/**
 * Jetpack Setup
 *
 * This allows for responsive videos when using the [video]
 * shortcode, or VideoPress
 */
function lwtv_yikes_jetpack_setup() {
	add_theme_support( 'jetpack-responsive-videos' );
}
add_action( 'after_setup_theme', 'lwtv_yikes_jetpack_setup' );

/*
 * Remove Jetpack default share because it's stupid.
 *
 * This stops Jetpack from adding sharing after every post
 * on a home page or archive if you use a custom loop.
 */
function lwtv_yikes_jetpack_remove_share() {
	remove_filter( 'the_content', 'sharing_display', 19 );
	remove_filter( 'the_excerpt', 'sharing_display', 19 );
	if ( class_exists( 'Jetpack_Likes' ) ) {
		remove_filter( 'the_content', array( Jetpack_Likes::init(), 'post_likes' ), 30, 1 );
	}
}
add_action( 'loop_start', 'lwtv_yikes_jetpack_remove_share' );

/*
 * Force Jetpack to be where we want it, not where it wants.
 *
 * This is manually called by post-content pages.
 */
function lwtv_yikes_jetpack_post_meta() {
	if ( function_exists( 'sharing_display' ) ) {
		sharing_display( '', true );
	}

	if ( class_exists( 'Jetpack_Likes' ) ) {
		$custom_likes = new Jetpack_Likes();
		$post_meta    = $custom_likes->post_likes( '' );
	}
}


/** THE ARCHIVE SECTION **/

/*
 * https://wordpress.stackexchange.com/questions/172645/get-the-post-type-a-taxonomy-is-attached-to
 */
function lwtv_yikes_get_post_types_by_taxonomy( $tax = 'category' ) {
	$out        = '';
	$post_types = get_post_types();
	foreach ( $post_types as $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type );
		if ( in_array( $tax, $taxonomies ) ) {
			// There should only be one (Highlander)
			$out = $post_type;
		}
	}
	return $out;
}

/*
 * Archive Sort Order
 *
 * Characters, shows, and certain taxonmies will use a
 * special order: ASC by title
 */
function lwtv_yikes_archive_sort_order( $query ) {
	if ( $query->is_main_query() && ! is_admin() ) {
		$posttypes  = array( 'post_type_characters', 'post_type_shows', 'post_type_actors' );
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_tropes', 'lez_country', 'lez_stations', 'lez_formats', 'lez_genres', 'lez_stars', 'lez_triggers', 'lez_actor_gender', 'lez_actor_sexuality' );
		if ( is_post_type_archive( $posttypes ) || is_tax( $taxonomies ) ) {
			$query->set( 'order', 'ASC' );
			$query->set( 'orderby', 'title' );
		}
	}
}
add_action( 'pre_get_posts', 'lwtv_yikes_archive_sort_order' );

/**
 * Archive Query
 *
 * Characters and certain taxonomies show 24 posts per
 * page on archives instead of the normal 10.
 */
function lwtv_yikes_character_archive_query( $query ) {
	if ( $query->is_archive() && $query->is_main_query() && ! is_admin() ) {
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_romantic' );
		if ( is_post_type_archive( 'post_type_characters' ) || is_tax( $taxonomies ) ) {
			$query->set( 'posts_per_page', 24 );
		}
	}
}
add_action( 'pre_get_posts', 'lwtv_yikes_character_archive_query' );

/**
 * Taxonomy Archive Title
 *
 * Take the data from the taxonomy to determine a dynamic title.
 *
 * @access public
 * @param mixed $location
 * @param mixed $posttype
 * @param mixed $taxonomy
 * @return $title_prefix OR $title_suffix
 */
function lwtv_yikes_tax_archive_title( $location, $posttype, $taxonomy ) {

	// Bail if not set
	if ( ! isset( $location ) || ! isset( $posttype ) || ! isset( $taxonomy ) ) {
		return;
	}

	$title_prefix = '';
	$title_suffix = '';
	$term         = get_term_by( 'slug', get_query_var( 'term' ), $taxonomy );
	$termicon     = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );

	// FA defaults
	switch ( $taxonomy ) {
		case 'lez_cliches':
			$fa  = 'fa-bell';
			$svg = $termicon ? $termicon . '.svg' : 'bell.svg';
			break;
		case 'lez_tropes':
			$fa  = 'fa-pastafarianism';
			$svg = $termicon ? $termicon . '.svg' : 'octopus.svg';
			break;
		case 'lez_formats':
			$fa  = 'fa-film';
			$svg = $termicon ? $termicon . '.svg' : 'film-strip.svg';
			break;
		case 'lez_genres':
			$fa  = 'fa-th-large';
			$svg = $termicon ? $termicon . '.svg' : 'blocks.svg';
			break;
		case 'lez_intersections':
			$fa  = 'fa-flag';
			$svg = $termicon ? $termicon . '.svg' : 'flag-wave.svg';
			break;
		case 'lez_gender':
		case 'lez_actor_gender':
			$fa  = 'fa-female';
			$svg = 'female.svg';
			break;
		case 'lez_sexuality':
		case 'lez_actor_sexuality':
			$fa  = 'fa-venus-double';
			$svg = 'venus-double.svg';
			break;
		case 'lez_romantic':
			$fa  = 'fa-heartbeat';
			$svg = 'user-heart.svg';
			break;
		case 'lez_stars':
			$fa  = 'fa-star';
			$svg = 'star.svg';
			break;
		case 'lez_triggers':
			$fa  = 'fa-exclamation-triangle';
			$svg = 'warning.svg';
			break;
		case 'lez_stations':
			$fa  = 'fa-bullhorn';
			$svg = 'satellite-signal.svg';
			break;
		case 'lez_country':
			$fa  = 'fa-globe';
			$svg = 'globe.svg';
			break;
		default:
			$fa  = 'fa-square';
			$svg = 'square.svg';
			break;
	}

	$icon = lwtv_symbolicons( $svg, $fa );

	switch ( $posttype ) {
		case 'post_type_characters':
			$title_suffix = ' Characters';
			break;
		case 'post_type_actors':
			$title_suffix = ' Actors';
			break;
		case 'post_type_shows':
			$title_suffix = ' TV Shows';

			// TV Shows are harder to have titles
			switch ( $taxonomy ) {
				case 'lez_stars':
					$title_suffix = '';
					$title_prefix = 'TV Shows with ';
					break;
				case 'lez_country':
					$title_suffix = ' Based TV Shows';
					$title_prefix = '';
					break;
				case 'lez_formats':
					$title_suffix = '';
					break;
				case 'lez_triggers':
					$title_prefix = 'TV Shows with ';
					$title_suffix = ' Trigger Warnings';
					break;
			}
			break;
	}

	switch ( $location ) {
		case 'prefix':
			$return = $title_prefix;
			break;
		case 'suffix':
			$return = $title_suffix;
			break;
		case 'icon':
			$return = $icon;
			break;
	}

	return $return;
}


/** THE SEARCH SECTION **/

/**
 * Filter the except length to 25 words.
 *
 * @param int $length Excerpt length.
 * @return int (Maybe) modified excerpt length.
 */
function lwtv_search_custom_excerpt_length( $length ) {
	return 25;
}
if ( is_search() ) {
	add_filter( 'excerpt_length', 'lwtv_search_custom_excerpt_length', 999 );
}


/** THE DISPLAY SECTION **/

/**
 * Show star
 *
 * If a show has a star, let's show it.
 *
 * @access public
 * @return void
 */
function lwtv_yikes_show_star( $show_id ) {
	$star_terms = get_the_terms( $show_id, 'lez_stars' );

	if ( get_post_meta( $show_id, 'lezshows_stars', true ) || ( ! empty( $star_terms ) && ! is_wp_error( $star_terms ) ) ) {
		$color = esc_attr( get_post_meta( $show_id, 'lezshows_stars', true ) );
		if ( ! empty( $star_terms ) && ! is_wp_error( $star_terms ) ) {
			$color_term = get_the_terms( $show_id, 'lez_stars' );
			$color      = $color_term[0]->slug;
		}
		$star = ' <span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" data-toggle="tooltip" title="' . ucfirst( $color ) . ' Star Show" class="show-star ' . $color . '">' . lwtv_symbolicons( 'star.svg', 'fa-star' ) . '</span>';
		return $star;
	} else {
		return;
	}

}

/**
 * Show content warning
 *
 * If a show has a content warning, let's show it.
 *
 * @access public
 * @return void
 */
function lwtv_yikes_content_warning( $show_id ) {

	$warning_array = array(
		'card'    => 'none',
		'content' => 'none',
	);

	// If there's no post ID passed or it's not a show, we show nothing.
	if ( is_null( $show_id ) || get_post_type( $show_id ) !== 'post_type_shows' ) {
		return $warning_array;
	}

	$trigger_terms            = get_the_terms( $show_id, 'lez_triggers' );
	$trigger                  = ( ! empty( $trigger_terms ) && ! is_wp_error( $trigger_terms ) ) ? $trigger_terms[0]->slug : get_post_meta( $show_id, 'lezshows_triggerwarning', true );
	$warning_array['content'] = ( ! empty( $trigger_terms ) && ! is_wp_error( $trigger_terms ) ) ? term_description( $trigger_terms[0]->term_id, 'lez_triggers' ) : '<strong>WARNING</strong> This show may be upsetting to watch.';

	switch ( $trigger ) {
		case 'on':
		case 'high':
			$warning_array['card'] = 'danger';
			break;
		case 'med':
		case 'medium':
			$warning_array['card'] = 'warning';
			break;
		case 'low':
			$warning_array['card'] = 'info';
			break;
		default:
			$warning_array['card']    = 'none';
			$warning_array['content'] = 'none';
	}

	return $warning_array;
}

/**
 * Character Data
 *
 * Called on character pages to generate certain data bits
 *
 * @access public
 * @param mixed $the_id: the character ID
 * @param mixed $data:
 * @return void
 */
function lwtv_yikes_chardata( $the_id, $data ) {

	// Early Bail
	$valid_data = array( 'dead', 'shows', 'actors', 'gender', 'sexuality', 'romantic', 'cliches', 'oneshow', 'oneactor' );
	if ( ! isset( $the_id ) || ! isset( $data ) || ! in_array( $data, $valid_data, true ) ) {
		return;
	}

	$output = '';

	switch ( $data ) {
		case 'dead':
			$deadpage = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			// Show nothing on archive pages for dead
			if ( ! empty( $term ) && 'dead' === $term->slug ) {
				return;
			} elseif ( has_term( 'dead', 'lez_cliches', $the_id ) ) {
				$output = '<span role="img" aria-label="Grim Reaper" title="Grim Reaper" class="charlist-grave">' . lwtv_symbolicons( 'grim-reaper.svg', 'fa-times-circle' ) . '</span>';
			}
			break;
		case 'shows':
			$output = get_post_meta( $the_id, 'lezchars_show_group', true );
			break;
		case 'oneshow':
			$all_shows   = get_post_meta( $the_id, 'lezchars_show_group', true );
			$shows_value = isset( $all_shows[0] ) ? $all_shows[0] : '';
			if ( ! empty( $shows_value ) ) {
				$num_shows = count( $all_shows );
				$showsmore = ( $num_shows > 1 ) ? ' (plus ' . ( $num_shows - 1 ) . ' more)' : '';
				$show_post = get_post( $shows_value['show'] );
				$output   .= '<div class="card-meta-item shows">' . lwtv_symbolicons( 'tv-hd.svg', 'fa-tv' ) . '<em>';
				if ( get_post_status( $shows_value['show'] ) !== 'publish' ) {
					$output .= '<span class="disabled-show-link">' . $show_post->post_title . '</span>';
				} else {
					$output .= '<a href="' . get_the_permalink( $show_post->ID ) . '">' . $show_post->post_title . '</a>';
				}
				$output .= '</em> (' . $shows_value['type'] . ')' . $showsmore . '</div>';
			}
			break;
		case 'oneactor':
			$actors      = get_post_meta( $the_id, 'lezchars_actor', true );
			$actor_value = isset( $actors[0] ) ? $actors[0] : '';
			if ( ! empty( $actor_value ) ) {
				$num_actors = count( $actors );
				$actorsmore = ( $num_actors > 1 ) ? ' (plus ' . ( $num_actors - 1 ) . ' more)' : '';
				$actor_post = get_post( $actor_value );
				$output    .= '<div class="card-meta-item actors">' . lwtv_symbolicons( 'user.svg', 'fa-user' );
				if ( get_post_status( $actor_value ) === 'private' ) {
					if ( is_user_logged_in() ) {
						$output .= '<a href="' . get_permalink( $actor_value ) . '">' . get_the_title( $actor_value ) . ' - UNLISTED</a>';
					} else {
						$output .= '<a href="/actor/unknown/">Unknown</a>';
					}
				} elseif ( get_post_status( $actor_value ) !== 'publish' ) {
					$output .= '<span class="disabled-show-link">' . $actor_post->post_title . '</span>';
				} else {
					$output .= '<a href="' . get_the_permalink( $actor_post->ID ) . '">' . $actor_post->post_title . '</a>';
				}
				$output .= $actorsmore . '</div>';
			}
			break;
		case 'actors':
			$character_actors = get_post_meta( $the_id, 'lezchars_actor', true );
			if ( ! is_array( $character_actors ) && ! empty( $character_actors ) ) {
				$character_actors = array( get_post_meta( $the_id, 'lezchars_actor', true ) );
			}
			$output = $character_actors;
			break;
		case 'gender':
			$gender_terms = get_the_terms( $the_id, 'lez_gender', true );
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				foreach ( $gender_terms as $gender_term ) {
					$output .= '<a href="' . get_term_link( $gender_term->slug, 'lez_gender' ) . '" rel="tag" title="' . $gender_term->name . '">' . $gender_term->name . '</a> ';
				}
			}
			break;
		case 'sexuality':
			$sexuality_terms = get_the_terms( $the_id, 'lez_sexuality', true );
			if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
				foreach ( $sexuality_terms as $sexuality_term ) {
					$output .= '<a href="' . get_term_link( $sexuality_term->slug, 'lez_sexuality' ) . '" rel="tag" title="' . $sexuality_term->name . '">' . $sexuality_term->name . '</a> ';
				}
			}
			break;
		case 'romantic':
			$romantic_terms = get_the_terms( $the_id, 'lez_romantic', true );
			if ( $romantic_terms && ! is_wp_error( $romantic_terms ) ) {
				foreach ( $romantic_terms as $romantic_term ) {
					$output .= '<a href="' . get_term_link( $romantic_term->slug, 'lez_romantic' ) . '" rel="tag" title="' . $romantic_term->name . '">' . $romantic_term->name . '</a> ';
				}
			}
			break;
		case 'cliches':
			$lez_cliches = get_the_terms( $the_id, 'lez_cliches' );
			$cliches     = '';
			if ( $lez_cliches && ! is_wp_error( $lez_cliches ) ) {
				$cliches = '';
				foreach ( $lez_cliches as $the_cliche ) {
					$termicon = get_term_meta( $the_cliche->term_id, 'lez_termsmeta_icon', true );
					$tropicon = $termicon ? $termicon . '.svg' : 'square.svg';
					$icon     = lwtv_symbolicons( $tropicon, 'fa-square' );
					$cliches .= '<a href="' . get_term_link( $the_cliche->slug, 'lez_cliches' ) . '" data-toggle="tooltip" data-placement="bottom" rel="tag" title="' . $the_cliche->name . '"><span role="img" aria-label="' . $the_cliche->name . '" class="character-cliche ' . $the_cliche->slug . '">' . $icon . '</span></a>&nbsp;';
				}
			}
			$output = $cliches;
			break;
	}

	return $output;
}

/**
 * Actor Data
 *
 * Called on actor pages to generate certain data bits
 *
 * @access public
 * @param mixed $the_id: the actor post ID
 * @param mixed $data:
 * @return void
 */
function lwtv_yikes_actordata( $the_id, $data ) {

	// Early Bail
	$valid_data = array( 'characters', 'gender', 'sexuality', 'age', 'dead' );
	if ( ! isset( $the_id ) || ! isset( $data ) || ! in_array( $data, $valid_data, true ) ) {
		return;
	}

	$output = '';

	switch ( $data ) {
		case 'age':
			$end = new DateTime();
			if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
				$end = new DateTime( get_post_meta( get_the_ID(), 'lezactors_death', true ) );
			}
			if ( get_post_meta( get_the_ID(), 'lezactors_birth', true ) ) {
				$start = new DateTime( get_post_meta( get_the_ID(), 'lezactors_birth', true ) );
			}
			if ( isset( $start ) ) {
				$output = $start->diff( $end );
			}
			break;
		case 'characters':
			$characters     = array();
			$charactersloop = new WP_Query(
				array(
					'post_type'              => 'post_type_characters',
					'post_status'            => array( 'publish' ),
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'posts_per_page'         => '20',
					'no_found_rows'          => true,
					'update_post_term_cache' => true,
					'meta_query'             => array(
						array(
							'key'     => 'lezchars_actor',
							'value'   => $the_id,
							'compare' => 'LIKE',
						),
					),
				)
			);
			if ( $charactersloop->have_posts() ) {
				while ( $charactersloop->have_posts() ) {
					$charactersloop->the_post();
					$char_id      = get_the_ID();
					$actors_array = get_post_meta( $char_id, 'lezchars_actor', true );
					if ( 'publish' === get_post_status( $char_id ) && isset( $actors_array ) && ! empty( $actors_array ) ) {
						foreach ( $actors_array as $char_actor ) {
							if ( $char_actor == $the_id ) { // phpcs:ignore WordPress.PHP.StrictComparisons
								$characters[ $char_id ] = array(
									'id'      => $char_id,
									'title'   => get_the_title( $char_id ),
									'url'     => get_the_permalink( $char_id ),
									'content' => get_the_content( $char_id ),
									'shows'   => get_post_meta( $char_id, 'lezchars_show_group', true ),
								);
							}
						}
					}
				}
				wp_reset_query();
			}
			$output = $characters;
			break;
		case 'dead':
			$dead     = array();
			$deadloop = new WP_Query(
				array(
					'post_type'              => 'post_type_characters',
					'post_status'            => array( 'publish' ),
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'posts_per_page'         => '20',
					'no_found_rows'          => true,
					'update_post_term_cache' => true,
					'meta_query'             => array(
						array(
							'key'     => 'lezchars_actor',
							'value'   => $the_id,
							'compare' => 'LIKE',
						),
					),
					'tax_query'              => array(
						'taxonomy' => 'lez_cliches',
						'terms'    => 'dead',
						'field'    => 'slug',
						'operator' => 'IN',
					),
				)
			);
			if ( $deadloop->have_posts() ) {
				while ( $deadloop->have_posts() ) {
					$deadloop->the_post();
					$char_id = get_the_ID();
					$actors  = get_post_meta( $char_id, 'lezchars_actor', true );

					if ( 'publish' === get_post_status( $char_id ) && isset( $actors ) && ! empty( $actors ) ) {
						foreach ( $actors as $actor ) {
							if ( $actor == $the_id && has_term( 'dead', 'lez_cliches', $char_id ) ) {  // phpcs:ignore WordPress.PHP.StrictComparisons
								$dead[ $char_id ] = array(
									'id'    => $char_id,
									'title' => get_the_title( $char_id ),
									'url'   => get_the_permalink( $char_id ),
								);
							}
						}
					}
				}
				wp_reset_query();
			}
			$output = $dead;
			break;
		case 'gender':
			$gender_terms = get_the_terms( $the_id, 'lez_actor_gender', true );
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				foreach ( $gender_terms as $gender_term ) {
					$output .= '<a href="' . get_term_link( $gender_term->slug, 'lez_actor_gender' ) . '" rel="tag" title="' . $gender_term->name . '">' . $gender_term->name . '</a> ';
				}
			}
			break;
		case 'pronouns':
			$pronoun_terms = get_the_terms( $the_id, 'lez_actor_pronouns', true );
			if ( $pronoun_terms && ! is_wp_error( $pronoun_term ) ) {
				foreach ( $pronoun_terms as $pronoun_term ) {
					$output .= '<a href="' . get_term_link( $pronoun_term->slug, 'lez_actor_pronouns' ) . '" rel="tag" title="' . $pronoun_term->name . '">' . $pronoun_term->name . '</a> ';
				}
			}
			break;
		case 'sexuality':
			$sexuality_terms = get_the_terms( $the_id, 'lez_actor_sexuality', true );
			if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
				foreach ( $sexuality_terms as $sexuality_term ) {
					$output .= '<a href="' . get_term_link( $sexuality_term->slug, 'lez_actor_sexuality' ) . '" rel="tag" title="' . $sexuality_term->name . '">' . $sexuality_term->name . '</a> ';
				}
			}
			break;
		default:
			$output .= '';
	}
	return $output;
}

/**
 * Is the actor queer function
 *
 * @access public
 * @param mixed $the_id
 * @return void
 */
function lwtv_yikes_is_queer( $the_id ) {
	if ( ! method_exists( 'LWTV_Loops', 'is_actor_queer' ) ) {
		$is_queer = false;
	} else {
		$is_queer = ( 'yes' === ( new LWTV_Loops() )->is_actor_queer( $the_id ) ) ? true : false;
	}

	return $is_queer;
}


function lwtv_yikes_is_birthday( $the_id ) {
	$happy_birthday = false;
	$today_is       = gmdate( 'm-d' );
	$birthday       = substr( get_post_meta( $the_id, 'lezactors_birth', true ), 5 );
	if ( $birthday === $today_is ) {
		$happy_birthday = true;
	}
	return $happy_birthday;
}

/** THE SEO SECTION **/

/**
 * Fix microformats
 * We have to have author, updated, and entry-title IN the hentry data.
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function lwtv_microformats_fix( $post_id ) {
	$valid_types = array( 'post_type_authors', 'post_type_characters', 'post_type_shows' );
	if ( in_array( get_post_type( $post_id ), $valid_types, true ) ) {
		echo '<div class="hatom-extra" style="display:none;visibility:hidden;">
			<span class="entry-title">' . esc_html( get_the_title( $post_id ) ) . '</span>
			<span class="updated">' . esc_html( get_the_modified_time( 'F jS, Y', $post_id ) ) . '</span>
			<span class="author vcard"><span class="fn">' . esc_html( get_option( 'blogname' ) ) . '</span></span>
		</div>';
	}
}

/** LWTV Plugin **/
// This section includes all the code we call from the LWTV plugin, with sanity checks.

/**
 * Check if Symbolicons exists and then call it
 * @param  string $svg the name of the SVG symbolicon we want to run
 * @param  string $fa  the name of the fallback Font Awesome icon
 * @return string      Whatever we end up with
 */
function lwtv_symbolicons( $svg, $fa ) {
	if ( method_exists( 'LWTV_Functions', 'symbolicons' ) ) {
		$return = ( new LWTV_Functions() )->symbolicons( $svg, $fa );
	} else {
		$return = '<span class="symbolicon" role="img"><svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="spinner" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="svg-inline--fa fa-spinner fa-w-16 fa-3x"><path fill="currentColor" d="M304 48c0 26.51-21.49 48-48 48s-48-21.49-48-48 21.49-48 48-48 48 21.49 48 48zm-48 368c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zm208-208c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zM96 256c0-26.51-21.49-48-48-48S0 229.49 0 256s21.49 48 48 48 48-21.49 48-48zm12.922 99.078c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.491-48-48-48zm294.156 0c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.49-48-48-48zM108.922 60.922c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.491-48-48-48z" class=""></path></svg></span>';
	}
	return $return;
}

/**
 * List all characters
 * @param  int     $post_id Post ID we're trying to process
 * @param  string  $output  format of output
 * @return mixed   number or array listing the characters
 */
function lwtv_list_characters( $post_id, $output ) {
	$return = '';
	if ( method_exists( 'LWTV_CPT_Characters', 'list_characters' ) ) {
		$return = ( new LWTV_CPT_Characters() )->list_characters( $post_id, $output );
	}
	return $return;
}

/**
 * Data on characters
 * @param  int    $post_id post ID
 * @param  int    $count   number of characters
 * @param  string $roll    role of characters
 * @return array           List of all the characters
 */
function lwtv_get_chars_for_show( $post_id, $count, $roll ) {
	$return = '';
	if ( method_exists( 'LWTV_CPT_Characters', 'get_chars_for_show' ) ) {
		$return = ( new LWTV_CPT_Characters() )->get_chars_for_show( $post_id, $count, $roll );
	}
	return $return;
}

/**
 * Echo GDPR notice if users aren't logged in
 * (logged in users already know what they're in for, yo)
 */
function lwtv_gdpr_footer() {
	if ( ! is_user_logged_in() ) {
		?>
		<div id="GDPRAlert" class="alert alert-info alert-dismissible fade collapse alert-gdpr" role="alert">
			We use cookies to personalize content, provide features, analyze traffic, and optimize advertising. By continuing to use this website, you agree to their use. For more information, you may review our <a href="/tos/">Terms of Use</a> and <a href="/tos/privacy/">Privacy Policy</a>.
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		</div>
		<?php
	}
}
//add_action( 'wp_footer', 'lwtv_gdpr_footer' , 5 );

function lwtv_last_updated_date( $post_id ) {
	$updated_date = get_the_modified_time( 'F jS, Y', $post_id );
	$last_updated = '<div class="last-updated"><small class="text-muted">This page was last edited on ' . $updated_date . '.</small></div>';

	echo wp_kses_post( $last_updated );
}

function lwtv_last_death() {
	if ( ! class_exists( 'LWTV_BYQ_JSON' ) ) {
		$return = '<p>The LezWatch.TV API is temporarily unavailable.</p>';
	} else {
		$last_death = ( new LWTV_BYQ_JSON )->last_death();
		$return     = '<p>' . sprintf( 'It has been %s since the last queer female, non-binary, or transgender death on television', '<strong>' . human_time_diff( $last_death['died'], current_time( 'timestamp' ) ) . '</strong> ' );
		$return    .= ': <span class="hidden-death"><a href="' . $last_death['url'] . '">' . $last_death['name'] . '</a></span> - ' . gmdate( 'F j, Y', $last_death['died'] ) . '</p>';
	}

	$return = '<div class="lezwatchtv last-death">' . $return . '</div>';

	return $return;
}
