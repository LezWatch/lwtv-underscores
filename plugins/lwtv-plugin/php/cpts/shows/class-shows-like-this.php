<?php
/**
 * Name: Shows Like This
 * Description: Calculate other shows you'd like if you like this.
 *
 * This requires https://wordpress.org/plugins/related-posts-by-taxonomy/
 * See https://wordpress.org/support/topic/adding-meta-to-where-join-currently-it-replaces/
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shows_Like_This {

	/**
	 * Transient key prefix for a show's cached reciprocity list.
	 *
	 * @var string
	 */
	const RECIPROCITY_PREFIX = 'lwtv_similar_reciprocity_';

	/**
	 * Whether the filters and actions have already been registered.
	 *
	 * The class is instantiated once at boot (class-shows.php) AND once per call
	 * to the `get_shows_like_this_show()` template facade. Object-method callbacks
	 * hash per instance, so add_filter() does not dedupe them: without this guard
	 * every filter is attached twice and the reciprocity query runs twice per
	 * request.
	 *
	 * @var bool
	 */
	private static bool $hooked = false;

	/**
	 * Pre-save 'lezshows_similar_shows' ID lists, keyed by show ID.
	 *
	 * ACF writes the new values at priority 10, so the old list has to be read
	 * (and kept) at priority 5 to know which OTHER shows' reciprocity caches the
	 * edit invalidated.
	 *
	 * @var array
	 */
	private static array $pre_save_similar = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;

		add_filter( 'related_posts_by_taxonomy_posts_meta_query', array( $this, 'meta_query' ), 10, 4 );
		add_filter( 'related_posts_by_taxonomy', array( $this, 'alter_results' ), 10, 4 );
		add_filter( 'related_posts_by_taxonomy_cache', '__return_true' );
		add_filter( 'related_posts_by_taxonomy_wp_rest_api', '__return_true' );

		// Reciprocity is cached per show, so a Similar Shows edit has to evict the
		// caches of every show it added or removed, as well as its own.
		add_action( 'acf/save_post', array( $this, 'stash_similar_shows' ), 5 );
		add_action( 'acf/save_post', array( $this, 'flush_reciprocity_cache' ), 20 );
	}

	/**
	 * Shows like this
	 *
	 * @param  int   $show_id
	 * @return mixed (string|bool)
	 */
	public function make( $show_id ): mixed {
		$return = '';

		if ( ! empty( $show_id ) && has_filter( 'related_posts_by_taxonomy_posts_meta_query' ) ) {

			// Get the tags and add them to include if they exist. Default if so.
			$tagged     = get_the_terms( $show_id, 'lez_showtagged' );
			$exclude    = '';
			$include    = '';
			$tags_array = array();
			if ( ! empty( $tagged ) ) {
				foreach ( $tagged as $tag ) {
					$tags_array[] = $tag->term_id;
				}
				$include    = implode( ', ', $tags_array );
				$taxonomies = 'lez_showtagged';
			} else {
				// Not tagged? Terms!
				// Get the genre terms, we're going to include them
				$terms = get_the_terms( $show_id, 'lez_genres' );
				if ( isset( $terms ) && is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$terms_array[] = $term->term_id;
					}
				} else {
					$terms_array[] = '';
				}

				// Now. Get the primary
				$primary    = get_post_meta( $show_id, 'lezshows_tvgenre_primary', true ) ?: false;
				$taxonomies = 'lez_genres';

				// If we have a primary, then we default to JUST that.
				if ( false !== $primary ) {
					// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
					$primary_key = array_search( $primary, $terms_array );
					if ( false !== $primary_key ) {
						unset( $terms_array[ $primary_key ] );
					}
					$exclude = implode( ', ', $terms_array );
					$include = $primary;
				} else {
					$include = implode( ', ', $terms_array );
				}
			}

			// Include the terms list
			$rpbt_include = 'include_terms="' . $include . '" exclude_terms="' . $exclude . '"';

			$return = do_shortcode( '[related_posts_by_tax post_id="' . $show_id . '" fields="ids" order="RAND" title="" format="lwtv_cards" image_size="postloop-img" link_caption="true" posts_per_page="6" columns="0" post_class="similar-shows" taxonomies="' . $taxonomies . '" ' . $rpbt_include . ' related="false"]' );
		}

		if ( empty( $return ) ) {
			return false;
		}

		return $return;
	}

	/**
	 * Custom Meta Query for related posts
	 *
	 * @TODO: Move this to a QUEERY looper.
	 *
	 * @param  array  $meta_query
	 * @param  int    $post_id
	 * @param  array  $taxonomies
	 * @param  array  $args
	 * @return array
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function meta_query( $meta_query, $post_id, $taxonomies, $args ) {

		if ( 'post_type_shows' === get_post_type( $post_id ) ) {
			/*
			 * The $meta_query variable is an array.
			 *
			 * If not empty it could be the meta query for post_thumbnails ( key '_thumbnail_id' )
			 * or some other meta query (from the shortcode or widget).
			 */
			$worthit = get_post_meta( $post_id, 'lezshows_worthit_rating', true ) ?: false;

			// We should match up the worth-it value as well as the score.
			// After all, some low scores have a thumbs up.
			if ( false !== $worthit ) {
				$meta_query[] = array(
					'key'     => 'lezshows_worthit_rating',
					'value'   => $worthit,
					'compare' => '=',
				);
			}
		}

		return $meta_query;
	}

	/**
	 * Handpicked Reciprocity
	 *
	 * "Pick me, chose me, love me!" Meredith Grey wants McDreamy to love her. If another
	 * show has picked THIS show as a 'similar show', we want to pick it back.
	 *
	 * The answer is cached per show for a week, because RPBT's own cache does not
	 * cover this filter: it re-applies `related_posts_by_taxonomy` to its cached
	 * IDs, so without a cache of our own this query ran on every show view and
	 * every RPBT REST call. Save-time invalidation lives in
	 * flush_reciprocity_cache(); the TTL backstops programmatic writes.
	 *
	 * @param  int   $post_id Post ID of the show we're checking
	 * @return array          Array of posts that match
	 */
	public function reciprocity( $post_id ) {
		// If this isn't a show page, bail.
		if ( isset( $post_id ) && 'post_type_shows' !== get_post_type( $post_id ) ) {
			return;
		}

		$transient = self::RECIPROCITY_PREFIX . $post_id;
		$cached    = lwtv_plugin()->get_transient( $transient );

		// Only a hard false is a miss -- an empty array is a real, cacheable answer.
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$reciprocity      = array();
		$reciprocity_loop = new \WP_Query(
			array(
				'post_type'              => 'post_type_shows',
				'post_status'            => array( 'publish' ),
				'fields'                 => 'ids',
				'orderby'                => 'none',
				'posts_per_page'         => '100',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => 'lezshows_similar_shows',
						'value'   => $post_id,
						'compare' => 'LIKE',
					),
				),
			)
		);

		$matched_ids = $reciprocity_loop->posts;

		if ( ! empty( $matched_ids ) ) {
			// One query to prime every match's meta, instead of one per show.
			update_meta_cache( 'post', $matched_ids );

			foreach ( $matched_ids as $this_show_id ) {
				$shows_array = get_post_meta( $this_show_id, 'lezshows_similar_shows', true ) ?: array();

				if ( ! is_array( $shows_array ) || empty( $shows_array ) ) {
					continue;
				}

				/*
				 * The LIKE above matches substrings, so each candidate's list has to
				 * be checked for an exact hit on this show's ID.
				 */
				foreach ( $shows_array as $related_show ) {
					// IDs are stored as ints by the CLI migration and as strings by the
					// ACF UI, so this comparison has to stay loose.
					// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
					if ( $related_show == $post_id ) {
						$reciprocity[] = $this_show_id;
					}
				}
			}

			$reciprocity = wp_parse_id_list( $reciprocity );
		}

		lwtv_plugin()->set_transient( $transient, $reciprocity, WEEK_IN_SECONDS );

		return $reciprocity;
	}

	/**
	 * Stash a show's Similar Shows list as it stands BEFORE this save.
	 *
	 * Runs at priority 5, ahead of ACF's own write at priority 10, so
	 * flush_reciprocity_cache() can compare old and new lists.
	 *
	 * @param  int|string $post_id Post ID being saved.
	 * @return void
	 */
	public function stash_similar_shows( int|string $post_id ): void {
		if ( ! is_numeric( $post_id ) || $post_id < 1 ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( 'post_type_shows' !== get_post_type( $post_id ) ) {
			return;
		}

		self::$pre_save_similar[ $post_id ] = self::similar_shows_ids( $post_id );
	}

	/**
	 * Evict the reciprocity caches an edit to a show's Similar Shows list made stale.
	 *
	 * reciprocity( X ) answers "which shows picked X?", so adding or removing a
	 * show from this show's list invalidates THAT show's cache, not (only) this
	 * one's. Runs at priority 20, after ACF has written the new values.
	 *
	 * @param  int|string $post_id Post ID being saved.
	 * @return void
	 */
	public function flush_reciprocity_cache( int|string $post_id ): void {
		if ( ! is_numeric( $post_id ) || $post_id < 1 ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( 'post_type_shows' !== get_post_type( $post_id ) ) {
			return;
		}

		$old = self::$pre_save_similar[ $post_id ] ?? array();
		unset( self::$pre_save_similar[ $post_id ] );
		$new = self::similar_shows_ids( $post_id );

		// Everything that was picked, is now picked, plus the show itself.
		$affected = array_unique( array_merge( $old, $new, array( $post_id ) ) );

		foreach ( $affected as $show_id ) {
			lwtv_plugin()->delete_transient( self::RECIPROCITY_PREFIX . $show_id );
		}
	}

	/**
	 * A show's Similar Shows list, as a clean array of IDs.
	 *
	 * Reads raw post meta rather than get_field() -- the stored value is already
	 * an ID array and ACF's formatting layer is not wanted here.
	 *
	 * @param  int $post_id Show ID.
	 * @return array        Array of show IDs.
	 */
	private static function similar_shows_ids( int $post_id ): array {
		$similar = get_post_meta( $post_id, 'lezshows_similar_shows', true );

		return is_array( $similar ) ? wp_parse_id_list( $similar ) : array();
	}

	/**
	 * Alter Results
	 *
	 * Since we added in a custom value for similar shows, we have to check that list here
	 * and make sure they're included.
	 *
	 * @param  array $results    The current results
	 * @param  int   $post_id    Post ID of the show
	 * @param  array $taxonomies All taxonomies,
	 * @param  array $args       Arguments passed through.
	 * @return array             The corrected results
	 */
	public function alter_results( $results, $post_id, $taxonomies, $args ) {

		if ( 'post_type_shows' === get_post_type( $post_id ) ) {
			// Set our base array
			$add_results = array();

			// The shortcode only allows post ids or post objects from the query.
			if ( ! empty( $results ) && empty( $args['fields'] ) ) {
				$results = wp_list_pluck( $results, 'ID' );
			}

			$results = array_unique( $results );

			// What MIGHT we be adding:
			$handpicked  = wp_parse_id_list( get_field( 'lezshows_similar_shows', $post_id ) ?: array() );
			$reciprocity = self::reciprocity( $post_id );
			$combo_list  = array_merge( $handpicked, $reciprocity );

			// If we have a combo list, we need to figure out how many shows to add
			if ( ! empty( $combo_list ) ) {
				foreach ( $combo_list as $a_show ) {

					// Only go forward if the show is published
					// (you CAN add drafts, but they won't show up -- this helps us to pre-load)
					if ( 'publish' === get_post_status( $a_show ) ) {
						$add_results[] = $a_show;
					}
				}
			}

			// Make it unique
			$add_results = array_unique( $add_results );

			// Merge arrays, make them unique, and keep it to 6.
			$new_results = array_slice( array_unique( array_merge( $add_results, $results ) ), 0, 6 );
		}

		$return = ( isset( $new_results ) ) ? $new_results : $results;

		return $return;
	}
}
