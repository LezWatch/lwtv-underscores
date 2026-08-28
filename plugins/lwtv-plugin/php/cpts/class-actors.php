<?php
/*
 * Custom Post Type for actors on LWTV
 *
 * Updated to use ACF
 *
 * @since 1.0
 */

namespace LWTV\CPTs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\CPTs;
use LWTV\CPTs\Actors\{ Custom_Columns, Privacy };

/**
 * class LWTV_CPT_Actors
 */

class Actors {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const SLUG = 'post_type_actors';

	/**
	 * All Taxonomies
	 *
	 * @var array
	 */
	const ALL_TAXONOMIES = array(
		'lez_actor_gender'    => array( 'name' => 'gender' ),
		'lez_actor_sexuality' => array(
			'name'   => 'sexuality',
			'plural' => 'sexualities',
		),
		'lez_actor_romantic'  => array( 'name' => 'romantic orientation' ),
		'lez_actor_pronouns'  => array( 'name' => 'pronoun' ),
	);

	/**
	 * Taxonomies that use Select2
	 */
	const SELECT2_TAXONOMIES = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		new Custom_Columns();

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Create CPT and Taxes
		add_action( 'init', array( $this, 'create_post_type' ), 0 );
		add_action( 'init', array( $this, 'create_taxonomies' ), 0 );

		// Save Hooks
		add_action( 'save_post_post_type_actors', array( $this, 'save_post_meta' ), 10, 3 );

		// Hide taxonomies from Gutenberg.
		add_filter( 'rest_prepare_taxonomy', array( $this, 'hide_taxonomies_from_gutenberg' ), 10, 2 );
	}

	/**
	 * Admin Init
	 */
	public function admin_init() {
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance_items' ) );
		add_filter( 'enter_title_here', array( $this, 'custom_enter_title' ) );
	}

	/**
	 * Hide Taxonomies from Gutenberg
	 *
	 * https://github.com/WordPress/gutenberg/issues/6912#issuecomment-428403380
	 *
	 * @param  object $response
	 * @param  object $taxonomy
	 * @return object
	 */
	public function hide_taxonomies_from_gutenberg( $response, $taxonomy ) {
		$all_tax_array = array();

		// Build an array of all taxonomies that should be hidden.
		foreach ( self::ALL_TAXONOMIES as $actor_tax => $actor_array ) {
			if ( ! isset( $actor_array['hide'] ) || false !== $actor_array['hide'] ) {
				$all_tax_array[] = $actor_tax;
			}
		}

		// False Positive
		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		if ( in_array( $taxonomy->name, $all_tax_array ) ) {
			$response->data['visibility']['show_ui'] = false;
		}
		return $response;
	}

	/*
	 * Create Actor Post Type
	 *
	 * post_type_actors
	 *
	 */
	public function create_post_type() {

		$actor_taxonomies = array();
		foreach ( self::ALL_TAXONOMIES as $actor_tax => $actor_array ) {
			$actor_taxonomies[] = $actor_tax;
		}

		$labels   = array(
			'name'                     => 'Actors',
			'singular_name'            => 'Actor',
			'menu_name'                => 'Actors',
			'add_new_item'             => 'Add New Actor',
			'edit_item'                => 'Edit Actor',
			'new_item'                 => 'New Actor',
			'view_item'                => 'View Actor',
			'all_items'                => 'All Actors',
			'search_items'             => 'Search Actors',
			'not_found'                => 'No Actors found',
			'not_found_in_trash'       => 'No Actors found in Trash',
			'update_item'              => 'Update Actor',
			'featured_image'           => 'Actor Image',
			'set_featured_image'       => 'Set Actor Image (recommended 350 x 412)',
			'remove_featured_image'    => 'Remove Actor Image',
			'use_featured_image'       => 'Use as Actor Image',
			'archives'                 => 'Actor archives',
			'insert_into_item'         => 'Insert into Actor',
			'uploaded_to_this_item'    => 'Uploaded to this Actor',
			'filter_items_list'        => 'Filter Actor list',
			'items_list_navigation'    => 'Actor list navigation',
			'items_list'               => 'Actor list',
			'item_published'           => 'Actor published.',
			'item_published_privately' => 'Actor published privately.',
			'item_reverted_to_draft'   => 'Actor reverted to draft.',
			'item_scheduled'           => 'Actor scheduled.',
			'item_updated'             => 'Actor updated.',
		);
		$template = array(
			array( 'lez-library/featured-image' ),
			array(
				'core/paragraph',
				array( 'placeholder' => 'Everything we need to know about this actor.' ),
			),
		);
		$args     = array(
			'label'               => self::SLUG,
			'description'         => 'Actors',
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'template'            => $template,
			'rest_base'           => 'actor',
			'menu_position'       => 7,
			'menu_icon'           => 'dashicons-id',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ),
			'has_archive'         => 'actors',
			'rewrite'             => array( 'slug' => 'actor' ),
			'taxonomies'          => $actor_taxonomies,
			'delete_with_user'    => false,
			'exclude_from_search' => false,
			'capability_type'     => array( 'actor', 'actors' ),
			'map_meta_cap'        => true,
		);
		register_post_type( self::SLUG, $args );
	}

	/*
	 * Create Custom Taxonomies
	 */
	public function create_taxonomies() {
		foreach ( self::ALL_TAXONOMIES as $tax_slug => $tax_array ) {
			// Remove lez_ from slug.
			$slug = str_replace( 'lez_', '', $tax_slug );

			// Determine names.
			$name_singular = ucwords( $tax_array['name'] );
			$name_plural   = ( isset( $tax_array['plural'] ) ) ? ucwords( $tax_array['plural'] ) : ucwords( $tax_array['name'] ) . 's';

			// Labels for taxonomy
			$labels = array(
				'name'                       => $name_plural,
				'singular_name'              => $name_singular,
				'search_items'               => 'Search ' . $name_plural,
				'popular_items'              => 'Popular ' . $name_plural,
				'all_items'                  => 'All' . $name_plural,
				'edit_item'                  => 'Edit ' . $name_singular,
				'update_item'                => 'Update ' . $name_singular,
				'add_new_item'               => 'Add New ' . $name_singular,
				'new_item_name'              => 'New' . $name_singular . 'Name',
				'separate_items_with_commas' => 'Separate ' . $name_plural . ' with commas',
				'add_or_remove_items'        => 'Add or remove' . $name_plural,
				'choose_from_most_used'      => 'Choose from the most used ' . $name_plural,
				'not_found'                  => 'No ' . $name_plural . ' found.',
				'menu_name'                  => $name_plural,
			);
			//parameters for the new taxonomy
			$arguments = array(
				'hierarchical'          => false,
				'labels'                => $labels,
				'show_ui'               => true,
				'show_in_rest'          => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'show_in_nav_menus'     => true,
				'rewrite'               => array( 'slug' => rtrim( $slug, 's' ) ),
			);

			// Register taxonomy
			register_taxonomy( $tax_slug, self::SLUG, $arguments );
		}
	}

	/*
	 * Save post meta for actors
	 *
	 * @param int $post_id The post ID.
	 * @param post $post The post object.
	 * @param bool $update Whether this is an existing post being updated or not.
	 */
	public function save_post_meta( $post_id ) {

		// Prevent running on autosave.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// unhook this function so it doesn't loop infinitely
		remove_action( 'save_post_post_type_actors', array( $this, 'save_post_meta' ) );

		// Privacy check
		$this->make_private( $post_id, 'check' );

		// Queue TMDB ID generation for batch processing
		lwtv_plugin()->queue_tmdb_batch( $post_id );

		// Queue an IMDb staleness check. No HTTP here -- this only marks the post
		// as needing verification; Action Scheduler does the asking.
		lwtv_plugin()->queue_imdb_verify( $post_id );

		// Schedule calculations for later processing
		lwtv_plugin()->schedule_task( 'calculation', $post_id );

		// Queue cache invalidation for shutdown processing
		lwtv_plugin()->cache_queue( $post_id );

		// Smart statistics cache invalidation
		lwtv_plugin()->invalidate_statistics_cache( 'post_type_actors', $post_id );

		// re-hook this function
		add_action( 'save_post_post_type_actors', array( $this, 'save_post_meta' ) );
	}

	/*
	 * Add count of actors to 'Right Now'
	 */
	public function dashboard_glance_items() {
		foreach ( array( self::SLUG ) as $post_type ) {
			$num_posts = wp_count_posts( $post_type );
			if ( $num_posts && $num_posts->publish ) {
				if ( self::SLUG === $post_type ) {
					// translators: %s is the number of actors
					$text = _n( '%s Actor', '%s Actors', $num_posts->publish );
				}
				$text = sprintf( $text, number_format_i18n( $num_posts->publish ) );
				printf( '<li class="%1$s-count"><a href="edit.php?post_type=%1$s">%2$s</a></li>', esc_attr( $post_type ), esc_html( $text ) );
			}
		}
	}

	/*
	 * Customize title
	 */
	public function custom_enter_title( $input ) {
		if ( self::SLUG === get_post_type() ) {
			$input = 'Add actor';
		}
		return $input;
	}

	/**
	 * Make actor Private
	 *
	 * @param  int $post_id
	 * @return void
	 */
	public function make_private( $post_id, $set = false ) {
		( new Privacy() )->make( $post_id, $set );
	}

	/**
	 * Hide Actor Data
	 *
	 * @param  int    $post_id
	 * @param  string $type    - Type of post data we're hiding.
	 * @return bool
	 */
	public function hide_data( $post_id, $type ): bool {
		return ( new Privacy() )->hide( $post_id, $type );
	}

	/**
	 * Get the privacy warning
	 *
	 * @param  int $post_id
	 * @param  bool $echo
	 *
	 * @return mixed
	 */
	public function privacy_warning( $post_id, $return_echo = true ) {
		return ( new Privacy() )->get_warning( $post_id, $return_echo );
	}

	/**
	 * Generate the TMDB ID for an actor and save it.
	 *
	 * @param int $post_id
	 *
	 * @return void
	 */
	public function generate_tmdb_id( $post_id ): void {
		$tmdb_id   = get_post_meta( $post_id, 'lezactors_tmdb_id', true );
		$tmdb_data = false;

		// If the TMDB ID is already set, move on.
		if ( isset( $tmdb_id ) && ! empty( $tmdb_id ) ) {
			return;
		}

		// Get the TMDB ID from the data.
		$tmdb_data = ( new CPTs() )->get_tmdb_info( $post_id );

		if ( isset( $tmdb_data['id'] ) ) {
			$tmdb_id = $tmdb_data['id'];
		} elseif ( isset( $tmdb_data['person_results'][0]['id'] ) ) {
			$tmdb_id = $tmdb_data['person_results'][0]['id'];
		} else {
			$tmdb_id = false;
		}

		// If we have a TMDB ID, save it.
		if ( false !== $tmdb_id ) {
			update_post_meta( $post_id, 'lezactors_tmdb_id', $tmdb_id );
		}
	}
}
