<?php
/**
 * Weird LWTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play 
 * nicely with each other. Including cursing at Jetpack.
 *
 * @package LezWatchTV
 */
 
/** THE GENERAL SECTION **/


/**
 * Loved Shows Shuffle
 *
 * This puts the loved show in a random order so it'll be different
 * for reloads. If we have more than three loved shows, it'll make
 * it even moar random.
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

	$icon = '<i class="fa ' . $fontawesome . '" aria-hidden="true"></i>';

	if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
		$response      = wp_remote_get( LP_SYMBOLICONS_PATH );
		$response_code = wp_remote_retrieve_response_code( $response );
		
		if ( $response_code == '200' ) {
			$get_svg      = wp_remote_get( LP_SYMBOLICONS_PATH . $svg );
			$response_svg = wp_remote_retrieve_response_code( $get_svg );
			$icon         = ( $response_svg == '200' )? $get_svg['body'] : 'square.svg';
		}
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
	// Add theme support for Responsive Videos.
	add_theme_support( 'jetpack-responsive-videos' );
}
add_action( 'after_setup_theme', 'lwtv_yikes_jetpack_setup' );

/*
 * Remove Jetpack because it's stupid. 
 *
 * This stops Jetpack from adding sharing
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


/** THE ARCHIVE SORT SECTION **/

/*
 * Archive Sort Order
 *
 * Everything BUT regular posts go alphabetical
 */
function lwtv_yikes_archive_sort_order( $query ) {
    if ( $query->is_main_query() && !is_admin() ) {
	    $posttypes  = array( 'post_type_characters', 'post_type_shows' );
	    $taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_tropes', 'lez_country', 'lez_stations', 'lez_formats', 'lez_genres' );
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
 * Change posts-per-page for custom post type archives
 */
function lwtv_yikes_character_archive_query( $query ) {
	if ( $query->is_archive() && $query->is_main_query() && !is_admin() ) {
		// Character archive Pages get 24 per page vs the regular 10
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality' );
		if ( is_post_type_archive( 'post_type_characters' ) || is_tax( $taxonomies ) ) {
			$query->set( 'posts_per_page', 24 );
		}  
    }
}
add_action( 'pre_get_posts', 'lwtv_yikes_character_archive_query' );

/** THE DISPLAY SECTION **/

/**
 * Show content warning
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
	
	switch ( get_post_meta( $show_id, 'lezshows_triggerwarning', true ) ) {
		case "on":
			$warning_array['card']    = 'danger';
			$warning_array['content'] = '<strong>WARNING!</strong> This show contains scenes of explicit violence, drug use, suicide, sex, and/or abuse.';
			break;
		case "med":
			$warning_array['card']    = 'warning';
			$warning_array['content'] = '<strong>CAUTION!</strong> This show regularly discusses and sometimes depicts "strong content" like violence and abuse.';
			break;
		case "low":
			$warning_array['card']    = 'info';
			$warning_array['content'] = '<strong>NOTICE!</strong> While generally acceptable for the over 14 crowd, this show may hit some sensitive topics now and then.';
			break;
		default:
			$warning_array['card']    = 'none';
			$warning_array['content'] = 'none';
	}

	$warning_array['content'] .= ' If those aren\'t your speed, neither is this show.';

	return $warning_array;
}


/**
 * lwtv_yikes_get_characters_for_show function.
 * 
 * @access public
 * @param mixed $show_id
 * @param mixed $role
 * @return void
 */
function lwtv_yikes_get_characters_for_show( $show_id, $role = 'regular' ) {
	
	$valid_roles = array( 'regular', 'recurring', 'guest' );
	
	// If this isn't a show page, or there are no valid roles, bail.
	if ( get_post_type( $show_id ) !== 'post_type_shows' || !in_array( $role, $valid_roles ) ) return;

	$characters = array();
	
	$charactersloop = new WP_Query( array(
		'post_type'              => 'post_type_characters',
		'post_status'            => array( 'publish' ),
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'posts_per_page'         => '100', // If The L Word ever gets over 100 characters, change this
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
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
			$char_id = get_the_ID();
			$shows_array = get_post_meta( $char_id, 'lezchars_show_group', true );

			// If the character is in this show, AND a published character,
			// AND has this role ON THIS SHOW (you can thank Sara Lance),
			// we will pass the following data to the character template
			// to determine what to display

			if ( $shows_array !== '' && !empty( $shows_array ) && get_post_status ( $char_id ) == 'publish' ) {
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

function lwtv_yikes_chardata( $the_ID, $data ) {
	
	// Early Bail
	if ( !isset( $the_ID ) || !isset( $data) ) return;
	
	$output = '';
	
	switch ( $data ) {
		case 'dead':
			$deadpage = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			// Show nothing on archive pages for dead
			if ( !empty( $term ) && $term->slug == 'dead' ) { 
				return;
			} elseif ( get_post_meta( $the_ID, 'lezchars_death_year', true ) ) {
				$output = '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave">' . lwtv_yikes_symbolicons( 'rip_gravestone.svg', 'fa-times-circle' ) . '</span>';
			}
			break;
		case 'shows':
			$ouput = get_post_meta( $the_ID, 'lezchars_show_group', true );
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
			    $cliches = 'Clichés: ';
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