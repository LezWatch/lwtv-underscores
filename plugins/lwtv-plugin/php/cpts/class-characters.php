<?php
/*
 * Custom Post Type for characters on LWTV
 *
 * @since 1.0
 */

namespace LWTV\CPTs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Characters\Custom_Columns;
use LWTV\Rest_API\BYQ;

/**
 * class LWTV_CPT_Characters
 */
class Characters {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const SLUG = 'post_type_characters';

	/**
	 * All Taxonomies
	 *
	 * @var array
	 */
	const ALL_TAXONOMIES = array(
		'lez_cliches'   => array( 'name' => 'cliché' ),
		'lez_gender'    => array( 'name' => 'gender' ),
		'lez_sexuality' => array( 'name' => 'sexual orientation' ),
		'lez_romantic'  => array( 'name' => 'romantic orientation' ),
	);

	/**
	 * Shadow Taxonomy
	 */
	const SHADOW_TAXONOMY = 'shadow_tax_characters';

	/**
	 * Action Scheduler hook for daily drift check.
	 */
	const AS_DRIFT_HOOK = 'lwtv_shadow_tax_drift_check';

	/**
	 * Action Scheduler group.
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * Taxonomies that use Select2
	 */
	const SELECT2_TAXONOMIES = array(
		'lezchars_cliches'            => 'lez_cliches',
		'lezchars_relationship_chart' => 'shadow_tax_characters',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		new Custom_Columns();

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Create CPT and Taxes
		add_action( 'init', array( $this, 'create_post_type' ), 0 );
		add_action( 'init', array( $this, 'create_taxonomies' ), 0 );
		add_action( 'init', array( $this, 'create_shadow_taxonomies' ), 0 );

		// WP-Admin alert if the shadow taxonomy is empty.
		add_action( 'admin_notices', array( $this, 'admin_notices_shadowtax__error' ) );

		// Daily drift check and auto-repair.
		add_action( self::AS_DRIFT_HOOK, array( $this, 'shadow_tax_drift_check' ) );
		add_action( 'action_scheduler_init', array( $this, 'init_shadow_tax_drift_check' ) );

		// phpcs:disable
		// Hide taxonomies from Gutenberg.
		// While this isn't the official API for this need, it works.
		// https://github.com/WordPress/gutenberg/issues/6912#issuecomment-428403380
		add_filter( 'rest_prepare_taxonomy', function( $response, $taxonomy ) {

			$all_tax_array = array();
			foreach ( self::ALL_TAXONOMIES as $char_tax => $char_array ) {
				if ( ! isset( $char_tax['hide'] ) || false !== $char_array['hide'] ) {
					$all_tax_array[] = $char_tax;
				}
			}

			if ( in_array( $taxonomy->name, $all_tax_array ) ) {
				$response->data['visibility']['show_ui'] = false;
			}
			return $response;
		}, 10, 2 );
		// phpcs:enable
	}

	/**
	 * Admin Init
	 */
	public function admin_init() {
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance_items' ) );
		add_action( 'save_post_post_type_characters', array( $this, 'save_post_meta' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'handle_character_deletion' ) );
		add_action( 'transition_post_status', array( $this, 'maybe_update_new_character_flags' ), 10, 3 );
		add_filter( 'enter_title_here', array( $this, 'custom_enter_title' ) );
	}

	/*
	 * Create Post Type
	 *
	 * post_type_characters
	 */
	public function create_post_type() {

		$char_taxonomies = array();
		foreach ( self::ALL_TAXONOMIES as $slug => $more ) {
			$char_taxonomies[] = $slug;
		}

		$labels   = array(
			'name'                     => 'Characters',
			'singular_name'            => 'Character',
			'menu_name'                => 'Characters',
			'add_new_item'             => 'Add New Character',
			'edit_item'                => 'Edit Character',
			'new_item'                 => 'New Character',
			'view_item'                => 'View Character',
			'all_items'                => 'All Characters',
			'search_items'             => 'Search Characters',
			'not_found'                => 'No Characters found',
			'not_found_in_trash'       => 'No Characters found in Trash',
			'update_item'              => 'Update Character',
			'featured_image'           => 'Character Image',
			'set_featured_image'       => 'Set Character Image (recommended 350 x 412)',
			'remove_featured_image'    => 'Remove Character Image',
			'use_featured_image'       => 'Use as Character Image',
			'archives'                 => 'Character archives',
			'insert_into_item'         => 'Insert into Character',
			'uploaded_to_this_item'    => 'Uploaded to this Character',
			'filter_items_list'        => 'Filter Character list',
			'items_list_navigation'    => 'Character list navigation',
			'items_list'               => 'Character list',
			'item_published'           => 'Character published.',
			'item_published_privately' => 'Character published privately.',
			'item_reverted_to_draft'   => 'Character reverted to draft.',
			'item_scheduled'           => 'Character scheduled.',
			'item_updated'             => 'Character updated.',
		);
		$template = array(
			array( 'lez-library/featured-image' ),
			array(
				'core/paragraph',
				array( 'placeholder' => 'Everything we need to know about this character' ),
			),
		);
		$args     = array(
			'label'               => self::SLUG,
			'description'         => 'Characters',
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'template'            => $template,
			'rest_base'           => 'character',
			'menu_position'       => 7,
			'menu_icon'           => 'dashicons-nametag',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ),
			'has_archive'         => 'characters',
			'rewrite'             => array( 'slug' => 'character' ),
			'taxonomies'          => $char_taxonomies,
			'delete_with_user'    => false,
			'exclude_from_search' => false,
			'capability_type'     => array( 'character', 'characters' ),
			'map_meta_cap'        => true,
		);
		register_post_type( self::SLUG, $args );
	}

	/*
	 * Custom Taxonomies
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
				'parent_item'                => null,
				'parent_item_colon'          => null,
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

			// parameters for the new taxonomy
			$arguments = array(
				'hierarchical'          => false,
				'labels'                => $labels,
				'public'                => true,
				'show_ui'               => true,
				'show_in_rest'          => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'show_in_nav_menus'     => true,
				'rewrite'               => array( 'slug' => rtrim( $slug, 's' ) ),
				'rest_base'             => rtrim( $slug, 's' ),
			);

			// Register taxonomy
			register_taxonomy( $tax_slug, self::SLUG, $arguments );
		}
	}

	/**
	 * Registers shadow taxonomy for being able to relate Characters to TV shows and actors.
	 * Think of it as we're adding the taxonomy for the character to the show and actor CPT.
	 *
	 * See https://packagist.org/packages/spock/shadow-taxonomies for more information.
	 */
	public function create_shadow_taxonomies() {
		$show_ui = ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) ? true : false;

		register_taxonomy(
			self::SHADOW_TAXONOMY,
			array( Actors::SLUG, Shows::SLUG, self::SLUG ),
			array(
				'label'         => 'SHADOW Characters',
				'rewrite'       => false,
				'show_tagcloud' => false,
				'show_ui'       => $show_ui,
				'public'        => false,
				'hierarchical'  => false,
				'show_in_menu'  => $show_ui,
				'meta_box_cb'   => false,
			)
		);

		\Shadow_Taxonomy\Core\create_relationship( self::SLUG, self::SHADOW_TAXONOMY );
	}

	/*
	 * Add to 'Right Now'
	 */
	public function dashboard_glance_items() {
		foreach ( array( self::SLUG ) as $post_type ) {
			$num_posts = wp_count_posts( $post_type );
			if ( $num_posts && $num_posts->publish ) {
				if ( self::SLUG === $post_type ) {
					// translators: %s is the number of characters
					$text = _n( '%s Character', '%s Characters', $num_posts->publish );
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
			$input = 'Add character';
		}
		return $input;
	}

	/*
	 * Save post meta for characters
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_post_meta( $post_id ) {

		// Prevent running on autosave.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// unhook this function so it doesn't loop infinitely
		remove_action( 'save_post_post_type_characters', array( $this, 'save_post_meta' ) );

		// Schedule Fix Shows - you only get one!
		lwtv_plugin()->schedule_task( 'fixcharshows', $post_id );

		// Schedule calculations for later processing
		lwtv_plugin()->schedule_task( 'calculation', $post_id );

		// Queue cache invalidation for shutdown processing
		lwtv_plugin()->cache_queue( $post_id );

		// Smart statistics cache invalidation
		lwtv_plugin()->invalidate_statistics_cache( self::SLUG, $post_id );

		// Check if character has 'dead' term and invalidate BYQ cache if needed
		$this->maybe_invalidate_byq_cache( $post_id );

		// re-hook this function
		add_action( 'save_post_post_type_characters', array( $this, 'save_post_meta' ) );
	}

	/**
	 * Register the daily recurring AS action for drift detection.
	 *
	 * @return void
	 */
	public function init_shadow_tax_drift_check(): void {
		if ( ! as_next_scheduled_action( self::AS_DRIFT_HOOK ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, self::AS_DRIFT_HOOK, array(), self::AS_GROUP );
			lwtv_plugin()->debug_log( 'shadow-taxonomy', 'Scheduled daily shadow taxonomy drift check via Action Scheduler' );
		}
	}

	/**
	 * Compare published character count to shadow term count.
	 * If they differ, queue a taxsync task to repair the drift.
	 *
	 * Fires via Action Scheduler on the lwtv_shadow_tax_drift_check hook.
	 *
	 * @return void
	 */
	public function shadow_tax_drift_check(): void {
		$num_posts = wp_count_posts( self::SLUG );
		$num_terms = wp_count_terms( self::SHADOW_TAXONOMY );

		if ( is_wp_error( $num_terms ) || (int) $num_posts->publish <= (int) $num_terms ) {
			return;
		}

		$drift = (int) $num_posts->publish - (int) $num_terms;
		lwtv_plugin()->debug_log( 'shadow-taxonomy', "Drift detected: {$drift} characters missing shadow terms — manual shadow taxonomy sync needed" );
	}

	/**
	 * Display admin notice if the shadow taxonomy is empty or if there are
	 * fewer terms than posts.
	 *
	 * Of note: There will be more terms than PUBLISHED posts, as drafts etc
	 * are not counted.
	 *
	 * @return void
	 */
	public function admin_notices_shadowtax__error() {
		// Admin only
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$num_posts = wp_count_posts( self::SLUG );
		$num_terms = wp_count_terms( self::SHADOW_TAXONOMY );
		$class     = 'notice notice-error';

		if ( empty( $num_terms ) || is_wp_error( $num_terms ) ) {
			$message = 'The sync for Shadow Characters has not been run. Via CLI you need to run "wp shadow sync characters".';
		} elseif ( $num_posts->publish > $num_terms ) {
			$message = 'The sync for Shadow Characters is not complete. Via CLI you need to run "wp shadow sync characters" until there are no characters left to process.';
		} else {
			return;
		}

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Get the privacy warning
	 *
	 * @param  int $post_id
	 * @param  bool $return_echo
	 * @return void|array
	 */
	public function privacy_warning( $post_id, $return_echo = true ) {
		$return_echo = ! is_user_logged_in() ? false : $return_echo;

		// For each actor, check if they have a privacy warning.
		$actors      = lwtv_plugin()->get_character_data( $post_id, 'actors' );
		$actor_notes = array();

		if ( ! $actors ) {
			return;
		}

		foreach ( $actors as $actor ) {
			if ( 'private' === get_post_status( $actor ) ) {
				$actor_notes[ $actor ] = '<a href="' . get_post_permalink( $actor ) . '"><em>' . get_the_title( $actor ) . '</em></a> has requested that all of their personal information be hidden from public view.';
			}
		}

		// If notes are empty, or we're not returning echo, return.
		if ( empty( $actor_notes ) || ! $return_echo ) {
			return;
		}

		echo '<div class="maybe-private-note alert alert-danger" role="alert">';

		foreach ( $actor_notes as $actor => $note ) {
			echo '<p>&nbsp;' . wp_kses_post( $note ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Update show new character flags when a character is published for the first time
	 *
	 * @param string $new_status The new post status.
	 * @param string $old_status The old post status.
	 * @param WP_Post $post The character post object.
	 */
	public function maybe_update_new_character_flags( $new_status, $old_status, $post ) {
		// Only handle character posts
		if ( self::SLUG !== get_post_type( $post ) ) {
			return;
		}

		// If the old status is a draft or auto-draft, and the new status is publish, update the new character flags.
		if ( ( 'draft' === $old_status || 'auto-draft' === $old_status ) && 'publish' === $new_status ) {

			// Get show relationships
			$show_group  = get_field( 'lezchars_show_group', $post->ID );
			$actor_group = get_field( 'lezchars_actor', $post->ID );

			$show_ids = array();

			// Parse show group rows (ACF returns int; pre-migration CMB2 may return array).
			if ( is_array( $show_group ) ) {
				foreach ( $show_group as $group ) {
					if ( isset( $group['show'] ) ) {
						$show_ids[] = (int) ( is_array( $group['show'] ) ? $group['show'][0] : $group['show'] );
					}
				}
			}

			// Parse actor group meta
			if ( is_array( $actor_group ) ) {
				$show_ids = array_merge( $show_ids, $actor_group );
			}

			// Remove duplicates and update meta for each show
			$show_ids = array_unique( $show_ids );

			foreach ( $show_ids as $show_id ) {
				update_post_meta( $show_id, 'lwtv_has_new_char', true );
				update_post_meta( $show_id, 'lwtv_characters_last_updated', time() );
			}
		}
	}

	/**
	 * Check if character has 'dead' term and invalidate BYQ cache if needed
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function maybe_invalidate_byq_cache( $post_id ) {
		// Only check if character has 'dead' term
		if ( ! has_term( 'dead', 'lez_cliches', $post_id ) ) {
			lwtv_plugin()->debug_log( 'buryqueers', 'Skipping BYQ cache invalidation for character post save (not a dead character): ' . $post_id );
			return;
		}

		// Check if character is already in cached list with same death date
		$is_in_list = ( new BYQ() )->is_character_in_cached_list( $post_id );

		// If character is not in cached list or death date changed, invalidate cache
		if ( ! $is_in_list ) {
			// Schedule cache invalidation for 10 minutes in the future
			as_schedule_single_action( time() + ( 10 * MINUTE_IN_SECONDS ), \LWTV\Schedulers\BYQ_Task::AS_INVALIDATE_HOOK, array(), \LWTV\Schedulers\BYQ_Task::AS_GROUP );
			lwtv_plugin()->debug_log( 'buryqueers', "Scheduled BYQ cache invalidation for character {$post_id} - character not in cached list or death date changed" );
		}
	}

	/**
	 * Handle character deletion - reset show new character flags
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public function handle_character_deletion( $post_id ) {
		// Only handle character posts
		if ( get_post_type( $post_id ) !== self::SLUG ) {
			return;
		}

		// Get show relationships
		$show_group  = get_field( 'lezchars_show_group', $post_id );
		$actor_group = get_field( 'lezchars_actor', $post_id );

		$update_ids = array();

		// Parse show group rows (ACF returns int; pre-migration CMB2 may return array).
		if ( is_array( $show_group ) ) {
			foreach ( $show_group as $group ) {
				if ( isset( $group['show'] ) ) {
					$update_ids[] = (int) ( is_array( $group['show'] ) ? $group['show'][0] : $group['show'] );
				}
			}
		}

		// Parse actor group meta
		if ( is_array( $actor_group ) ) {
			$update_ids = array_merge( $update_ids, $actor_group );
		}

		// Remove duplicates and reset meta for each show
		$update_ids = array_unique( $update_ids );

		foreach ( $update_ids as $one_update_id ) {
			delete_post_meta( $one_update_id, 'lwtv_has_new_char' );
			delete_post_meta( $one_update_id, 'lwtv_characters_last_updated' );
		}
	}
}
