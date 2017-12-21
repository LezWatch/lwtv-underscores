<?php
/**
 * Weird LWTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play 
 * nicely with each other. Including cursing at Jetpack.
 *
 * @package LezWatchTV
 */

/* This Year code: */
require get_template_directory() . '/inc/thisyear.php';

/** THE GENERAL SECTION **/

/**
 * Loved Shows Shuffle
 *
 * This puts the loved show in a random order so it'll be different
 * for reloads.
 */
add_filter( 'the_posts', function( $posts, \WP_Query $query ) {
		if( $pick = $query->get( '_loved_shuffle' ) ) {
			shuffle( $posts );
			$posts = array_slice( $posts, 0, (int) $pick );
		}
		return $posts;
	}, 10, 2 );

/*
 * Filter Comment Status
 *
 * Remove comments from attachment pages. This is for SEO and spam
 * purposes. Why do spammers spam?
 */
function lwtv_yikes_filter_media_comment_status( $open, $post_id ) {
	if ( get_post_type() == 'attachment' ) return false;
	return $open;
}
add_filter( 'comments_open', 'lwtv_yikes_filter_media_comment_status', 10 , 2 );

/**
 * Symbolicons Output
 *
 * Echos the default outputtable symbolicon, based on the SVG and FA icon passed to it.
 * 
 * @access public
 * @param string $svg (default: 'square.svg')
 * @param string $fontawesome (default: 'fa-square')
 * @return icon
 */
function lwtv_yikes_symbolicons( $svg = 'square.svg', $fontawesome = 'fa-square' ) {	

	$icon   = '<i class="fas ' . $fontawesome . ' fa-fw" aria-hidden="true"></i>';
	$square = file_get_contents( get_template_directory() . '/images/square.svg' );

	if ( defined( 'LP_SYMBOLICONS_PATH' ) && file_exists( LP_SYMBOLICONS_PATH . $svg ) ) {
		$icon = file_get_contents( LP_SYMBOLICONS_PATH . $svg );
	} elseif ( !wp_style_is( 'fontawesome', 'enqueued' ) ) {
		$icon = $square;
	} 
	
	// Override for AMP - NO ICONS
	if ( is_amp_endpoint() ) {
		$icon = '';
	}

	return $icon;
}
 
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
 * Remove Jetpack because it's stupid. 
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
function lwtv_yikes_jetpack_post_meta( ) {
	if ( function_exists( 'sharing_display' ) ) sharing_display( '', true );

	if ( class_exists( 'Jetpack_Likes' ) ) {
		$custom_likes = new Jetpack_Likes;
		$post_meta = $custom_likes->post_likes( '' );
	}
}


/** THE ARCHIVE SECTION **/

/*
 * Archive Sort Order
 *
 * Characters, shows, and certain taxonmies will use a
 * special order: ASC by title
 */
function lwtv_yikes_archive_sort_order( $query ) {
	if ( $query->is_main_query() && !is_admin() ) {
		$posttypes  = array( 'post_type_characters', 'post_type_shows', 'post_type_actors' );
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_tropes', 'lez_country', 'lez_stations', 'lez_formats', 'lez_genres', 'lez_stars', 'lez_triggers', 'lez_actor_gender', 'lez_actor_sexuality', );
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
	if ( $query->is_archive() && $query->is_main_query() && !is_admin() ) {
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality' );
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
	if ( !isset( $location ) || !isset( $posttype ) || !isset( $taxonomy ) ) return;

	$title_prefix = $title_suffix = '';
	$term         = get_term_by( 'slug', get_query_var( 'term' ), $taxonomy );
	$termicon     = get_term_meta( $term->term_id, 'lez_termsmeta_icon', true );

	// FA defaults
	switch ( $taxonomy ) {
		case 'lez_cliches':
			$fa  = 'fa-bell';
			$svg = $termicon ? $termicon . '.svg' : 'bell.svg';
			break;
		case 'lez_gender':
			$fa  = 'fa-venus';
			$svg = $termicon ? $termicon . '.svg' : 'venus.svg';
			break;
		case 'lez_sexuality':
			$fa  = 'fa-venus-double';
			$svg = $termicon ? $termicon . '.svg' : 'venus-double.svg';
			break;
		case 'lez_romantic':
			$fa  = 'fa-heart';
			$svg = $termicon ? $termicon . '.svg' : 'heart-download.svg';
			break;
		case 'lez_formats':
			$fa  = 'fa-tv';
			$svg = $termicon ? $termicon . '.svg' : 'tv.svg';
			break;
		case 'lez_actor_gender':
			$fa  = 'fa-bug';
			$svg = 'octopus.svg';
			break;
		case 'lez_actor_sexuality':
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
			$svg = $termicon ? $termicon . '.svg' : 'square.svg';
			break;
	}

	$icon = lwtv_yikes_symbolicons( $svg, $fa );

	switch ( $posttype ) {
		case 'post_type_characters':
			$title_suffix = ' Characters';
			break;
		case 'post_type_actors':
			$title_prefix = 'Actors ';
			switch ( $taxonomy ) {
				case 'lez_actor_gender':
					$title_prefix .= 'Who Identify As A ';
					break;
				case 'lez_actor_sexuality':
					$title_prefix .= 'Who Identify As ';
					break;
			}
			break;
		case 'post_type_shows':
			$title_prefix = 'TV Shows ';
			
			// TV Shows are harder to have titles
			switch ( $taxonomy ) {
				case 'lez_tropes':
					$title_prefix .= 'With The ';
					$title_suffix .= ' Trope';
					break;
				case 'lez_country':
					$title_prefix .= 'That Originate In ';
					break;
				case 'lez_stations':
					$title_prefix .= 'That Air On ';
					break;
				case 'lez_formats':
					$title_prefix .= 'That Air As ';
					break;
				case 'lez_genres':
					$title_prefix = '';
					$title_suffix = ' TV Shows';
					break;
				case 'lez_stars':
					$title_prefix .= 'With A ';
					break;
				case 'lez_triggers':
					$title_prefix .= 'With A ';
					$title_suffix .= ' Trigger Warning';
					break;
			}
			break;
	}
	
	if ( $location == 'prefix' ) return $title_prefix;
	if ( $location == 'suffix' ) return $title_suffix;
	if ( $location == 'icon' )   return $icon;
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

	if ( get_post_meta( $show_id, 'lezshows_stars', true ) || ( !empty( $star_terms ) && !is_wp_error( $star_terms ) ) ) {
		$color = esc_attr( get_post_meta( $show_id, 'lezshows_stars' , true ) );
		if ( !empty( $star_terms ) && !is_wp_error( $star_terms ) ) {
			$color_term = get_the_terms( $show_id, 'lez_stars' );
			$color = $color_term[0]->slug;
		}
		$star = ' <span role="img" aria-label="' . ucfirst( $color ) . ' Star Show" data-toggle="tooltip" title="' . ucfirst( $color ) . ' Star Show" class="show-star ' . $color . '">' . lwtv_yikes_symbolicons( 'star.svg', 'fa-star' ) . '</span>';
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
	if ( is_null( $show_id ) || get_post_type( $show_id ) !== 'post_type_shows' ) return $warning_array;
	
	$trigger_terms = get_the_terms( $show_id, 'lez_triggers' );
	$trigger = ( !empty( $trigger_terms ) && !is_wp_error( $trigger_terms ) )? $trigger_terms[0]->slug : get_post_meta( $show_id, 'lezshows_triggerwarning', true );
	$warning_array['content'] = ( !empty( $trigger_terms ) && !is_wp_error( $trigger_terms ) )? term_description( $trigger_terms[0]->term_id, 'lez_triggers' ) : '<strong>WARNING</strong> This show may be upsetting to watch.';
	
	switch ( $trigger ) {
		case "on":
		case "high":
			$warning_array['card']    = 'danger';
			break;
		case "med":
		case "medium":
			$warning_array['card']    = 'warning';
			break;
		case "low":
			$warning_array['card']    = 'info';
			break;
		default:
			$warning_array['card']    = 'none';
			$warning_array['content'] = 'none';
	}

	return $warning_array;
}

/**
 * Get Characters For Show
 *
 * Get all the characters for a show, based on role type.
 * 
 * @access public
 * @param mixed $show_id: Extracted from page the function is called on
 * @param mixed $role: regular (default), recurring, guest
 * @return array of characters
 */
function lwtv_yikes_get_characters_for_show( $show_id, $havecharcount, $role = 'regular' ) {

	// The Shane Clause & The Clone Club Correlary
	// Calculate the max number of characters to list, based on the
	// previous count. Default/Minimum is 100 characters.
	$count = ( isset( $havecharcount ) && $havecharcount >= '100' )? $havecharcount : '100' ;

	// Valid Roles:	
	$valid_roles = array( 'regular', 'recurring', 'guest' );
	
	// If this isn't a show page, or there are no valid roles, bail.
	if ( !isset( $show_id ) || get_post_type( $show_id ) !== 'post_type_shows' || !in_array( $role, $valid_roles ) ) return;

	// Prepare the ARRAY
	$characters = array();
	
	$charactersloop = new WP_Query( array(
		'post_type'              => 'post_type_characters',
		'post_status'            => array( 'publish' ),
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'posts_per_page'         => $count,
		'no_found_rows'          => true,
		'update_post_term_cache' => true,
		'meta_query'             => array( 
			'relation'    => 'AND',
			array(
				'key'     => 'lezchars_show_group',
				'value'   => $role,
				'compare' => 'LIKE',
			),
			array(
				'key'     => 'lezchars_show_group',
				'value'   => $show_id,
				'compare' => 'LIKE',
			),
		),
	) );

	if ( $charactersloop->have_posts() ) {
		while ( $charactersloop->have_posts() ) {
			$charactersloop->the_post();
			$char_id     = get_the_ID();
			$shows_array = get_post_meta( $char_id, 'lezchars_show_group', true );

			// The Sara Lance Complexity:
			// If the character is in this show, AND a published character,
			// AND has this role ON THIS SHOW we will pass the following 
			// data to the character template to determine what to display.

			if ( get_post_status ( $char_id ) == 'publish' && isset( $shows_array ) && !empty( $shows_array ) ) {
				foreach( $shows_array as $char_show ) {
					if ( $char_show['show'] == $show_id && $char_show['type'] == $role ) {
						$characters[$char_id] = array(
							'id'        => $char_id,
							'title'     => get_the_title( $char_id ),
							'url'       => get_the_permalink( $char_id ),
							'content'   => get_the_content( $char_id ),
							'shows'     => $shows_array,
							'show_from' => $show_id,
							'role_from' => $role,
						);
					}
				}
			}
		}
		wp_reset_query();
	}
	return $characters;
}


/**
 * Character Data
 *
 * Called on character pages to generate certain data bits
 * 
 * @access public
 * @param mixed $the_ID: the character ID
 * @param mixed $data: 
 * @return void
 */
function lwtv_yikes_chardata( $the_ID, $data ) {

	// Early Bail
	$valid_data = array( 'dead', 'shows', 'actors', 'gender', 'sexuality', 'cliches', 'oneshow', 'oneactor' );
	if ( !isset( $the_ID ) || !isset( $data) || !in_array( $data, $valid_data ) ) return;
	
	$output = '';

	switch ( $data ) {
		case 'dead':
			$deadpage = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			// Show nothing on archive pages for dead
			if ( !empty( $term ) && $term->slug == 'dead' ) { 
				return;
			} elseif ( has_term( 'dead', 'lez_cliches' , $the_ID ) ) {
				$output = '<span role="img" aria-label="Grim Reaper" title="Grim Reaper" class="charlist-grave">' . lwtv_yikes_symbolicons( 'grim-reaper.svg', 'fa-times-circle' ) . '</span>';
			}
			break;
		case 'shows':
			$output = get_post_meta( $the_ID, 'lezchars_show_group', true );
			break;
		case 'oneshow':
			$all_shows   = get_post_meta( $the_ID, 'lezchars_show_group', true );
			$shows_value = isset( $all_shows[0] ) ? $all_shows[0] : '';
			$output      = '';
			if ( !empty( $shows_value ) ) {
				$num_shows = count( $all_shows );
				$showsmore = ( $num_shows > 1 )? ' (plus ' . ( $num_shows - 1 ) .' more)' : '';
				$show_post = get_post( $shows_value['show'] );
				$output   .= '<div class="card-meta-item shows">' . lwtv_yikes_symbolicons( 'tv-hd.svg', 'fa-tv' ) . '<em>';
				if ( get_post_status ( $shows_value['show'] ) !== 'publish' ) {
					$output .= '&nbsp;<span class="disabled-show-link">' . $show_post->post_title . '</span>';
				} else {
					$output .= '&nbsp;<a href="' . get_the_permalink( $show_post->ID )  .'">' . $show_post->post_title .'</a>';
				}
				$output .= '</em> (' . $shows_value['type'] . ')' . $showsmore . '</div>';
			}
			break;
		case 'oneactor':
			$actors      = get_post_meta( $the_ID, 'lezchars_actor', true );
			$actor_value = isset( $actors[0] ) ? $actors[0] : '';
			$output      = '';
			if ( !empty( $actor_value ) ) {
				$num_actors = count( $actors );
				$actorsmore = ( $num_actors > 1 )? ' (plus ' . ( $num_actors - 1 ) .' more)' : '';
				$actor_post = get_post( $actor_value );
				$output .= '<div class="card-meta-item actors">' . lwtv_yikes_symbolicons( 'user.svg', 'fa-user' ) . '&nbsp;';
				if ( get_post_status ( $actor_value ) !== 'publish' ) {
					$output .= '&nbsp;<span class="disabled-show-link">' . $actor_post->post_title . '</span>';
				} else {
					$output .= '&nbsp;<a href="' . get_the_permalink( $actor_post->ID )  .'">' . $actor_post->post_title .'</a>';
				}
				$output .= $actorsmore . '</div>';
			}
			break;
		case 'actors':
			$character_actors = get_post_meta( $the_ID, 'lezchars_actor', true );
			if ( !is_array ( $character_actors ) ) {
				$character_actors = array( get_post_meta( $the_ID, 'lezchars_actor', true ) );
			}
			$output = $character_actors;
			break;
		case 'gender':
			$gender_terms = get_the_terms( $the_ID, 'lez_gender', true );
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				foreach( $gender_terms as $gender_term ) {
					$output .= '<a href="' . get_term_link( $gender_term->slug, 'lez_gender') . '" rel="tag" title="' . $gender_term->name . '">' . $gender_term->name . '</a> ';
				}
			}
			break;
		case 'sexuality':
			$sexuality_terms = get_the_terms( $the_ID, 'lez_sexuality', true );
			if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
				foreach( $sexuality_terms as $sexuality_term ) {
					$output .= '<a href="' . get_term_link( $sexuality_term->slug, 'lez_sexuality') . '" rel="tag" title="' . $sexuality_term->name . '">' . $sexuality_term->name . '</a> ';
				}
			}
			break;
		case 'cliches':
			$lez_cliches = get_the_terms( $the_ID, 'lez_cliches' );
			$cliches = '';
			if ( $lez_cliches && ! is_wp_error( $lez_cliches ) ) {
				$cliches = '';
				foreach( $lez_cliches as $the_cliche ) {
					$termicon = get_term_meta( $the_cliche->term_id, 'lez_termsmeta_icon', true );
					$tropicon = $termicon ? $termicon . '.svg' : 'square.svg';
					$icon     = lwtv_yikes_symbolicons( $tropicon, 'fa-square' );
					$cliches .= '<a href="' . get_term_link( $the_cliche->slug, 'lez_cliches') . '" data-toggle="tooltip" data-placement="bottom" rel="tag" title="' . $the_cliche->name . '"><span role="img" aria-label="' . $the_cliche->name . '" class="character-cliche ' . $the_cliche->slug . '">' .$icon . '</span></a>&nbsp;';
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
 * @param mixed $the_ID: the actor post ID
 * @param mixed $data: 
 * @return void
 */
function lwtv_yikes_actordata( $the_ID, $data ) {

	// Early Bail
	$valid_data = array( 'characters', 'gender', 'sexuality', 'age', 'dead' );
	if ( !isset( $the_ID ) || !isset( $data) || !in_array( $data, $valid_data ) ) return;
	
	$output = '';

	switch ( $data ) {
		case 'age':
			$end = new DateTime( );
			if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
				$get_death = new DateTime( get_post_meta( get_the_ID(), 'lezactors_death', true ) );
				$end       = $get_death;
			}
			if ( get_post_meta( get_the_ID(), 'lezactors_birth', true ) ) {
				$start = new DateTime( get_post_meta( get_the_ID(), 'lezactors_birth', true ) );
			}
			if ( isset( $start ) ) {
				$output = $start->diff($end);
			}
			break;
		case 'characters':
			$characters = array();
			$charactersloop = new WP_Query( array(
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
						'value'   => $the_ID,
						'compare' => 'LIKE',
					),
				),
			) );
			if ( $charactersloop->have_posts() ) {
				while ( $charactersloop->have_posts() ) {
					$charactersloop->the_post();
					$char_id      = get_the_ID();
					$actors_array = get_post_meta( $char_id, 'lezchars_actor', true );
					if ( get_post_status ( $char_id ) == 'publish' && isset( $actors_array ) && !empty( $actors_array ) ) {
						foreach( $actors_array as $char_actor ) {
							if ( $char_actor == $the_ID ) {
								$characters[$char_id] = array(
									'id'        => $char_id,
									'title'     => get_the_title( $char_id ),
									'url'       => get_the_permalink( $char_id ),
									'content'   => get_the_content( $char_id ),
									'shows'     => get_post_meta( $char_id, 'lezchars_show_group', true ),
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
			$deadloop = new WP_Query( array(
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
						'value'   => $the_ID,
						'compare' => 'LIKE',
					),
				),
				'tax_query'              => array(
					'taxonomy' => 'lez_cliches',
					'terms'    => 'dead',
					'field'    => 'slug',
					'operator' => 'IN',
				),
			) );
			if ( $deadloop->have_posts() ) {
				while ( $deadloop->have_posts() ) {
					$deadloop->the_post();
					$char_id = get_the_ID();
					$actors  = get_post_meta( $char_id, 'lezchars_actor', true );
		
					if ( get_post_status ( $char_id ) == 'publish' && isset( $actors ) && !empty( $actors ) ) {
						foreach( $actors as $actor ) {
							if ( $actor == $the_ID && has_term( 'dead', 'lez_cliches' , $char_id )) {
								$dead[$char_id] = array(
									'id'        => $char_id,
									'title'     => get_the_title( $char_id ),
									'url'       => get_the_permalink( $char_id ),
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
			$gender_terms = get_the_terms( $the_ID, 'lez_actor_gender', true );
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				foreach( $gender_terms as $gender_term ) {
					$output .= '<a href="' . get_term_link( $gender_term->slug, 'lez_actor_gender') . '" rel="tag" title="' . $gender_term->name . '">' . $gender_term->name . '</a> ';
				}
			}
			break;
		case 'sexuality':
			$sexuality_terms = get_the_terms( $the_ID, 'lez_actor_sexuality', true );
			if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
				foreach( $sexuality_terms as $sexuality_term ) {
					$output .= '<a href="' . get_term_link( $sexuality_term->slug, 'lez_actor_sexuality') . '" rel="tag" title="' . $sexuality_term->name . '">' . $sexuality_term->name . '</a> ';
				}
			}
			break;
	}
	return $output;
}

/**
 * Is the actor queer function
 * 
 * @access public
 * @param mixed $the_ID
 * @return void
 */
function lwtv_yikes_is_queer( $the_ID ) {
	
	// If we're not a valid ID and not an actor, bail.
	if ( !isset( $the_ID ) || !is_singular( 'post_type_actors' ) ) return false;
	
	// Defaults
	$gender = $sexuality = true;
	
	// If the actor is cis, they may not be queer...
	$straight_genders =  array( 'cis-man', 'cis-woman', 'cisgender' );
	$gender_terms     = get_the_terms( $the_ID, 'lez_actor_gender', true );
	if ( !$gender_terms || is_wp_error( $gender_terms ) || has_term( $straight_genders, 'lez_actor_gender', $the_ID ) ) {
		$gender = false;
	}
	
	// If the actor is heterosexual they may not be queer...
	$straight_sexuality =  array( 'heterosexual', 'unknown' );
	$sexuality_terms    = get_the_terms( $the_ID, 'lez_actor_sexuality', true );
	if ( !$sexuality_terms || is_wp_error( $sexuality_terms ) || has_term( $straight_sexuality, 'lez_actor_sexuality', $the_ID ) ) {
		$sexuality = false;
	}
	
	// If either the gender or sexuality is queer, we have a queerio!
	$is_queer = false;
	if ( $sexuality == true || $gender == true ) {
		$is_queer = true;
	}
	
	return $is_queer;
}