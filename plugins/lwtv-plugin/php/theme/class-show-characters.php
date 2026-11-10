<?php

/**
 * Use character link data to:
 *  - get a show's characters (via LWTV\Relationships\Character_Links)
 *  - take a list of characters and break them into their myriad roles.
 *
 * There's a long and storied history of this file. It's been through a lot.
 * Keeping the docblock here for posterity and amusement.
 *
 * Used to be:
 *  - Calculate the max number of characters to list, based on the
 *    previous count. Default/Minimum is the number of characters divided by 10
 *
 * We got there by trying to deal with the following issues:
 *
 *   - The Sara Lance Complexity -- Because someone is on a lot of shows,
 *                                  we have to make sure the IDs are right
 *                                  and the show isn't a partial match.
 *                                  Sara hasn't been on EVERY show yet.
 *   - The Shane Clause          -- Thanks to Shane sleeping with everyone,
 *                                  we had to limit this loop to 100 minimum
 *   - The Clone Club Corollary  -- Sarah Manning took the place of every
 *                                  single other character played by Tatiana
 *                                  Maslany.
 *   - The Vanishing Xenaphobia  -- When set to under 200, Xena doesn't show
 *                                  on the Xena:WP show page
 *   - Just a Phase Samantha     -- By the time we hit 6000 characters, the math
 *                                  stopped working to show all the characters.
 *                                  Now it's set to 1/10th the number of chars.
 *   - The Shadow Tax            -- In order to prevent this from being an ongoing
 *                                  issue, we use shadow taxonomies instead.
 */

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows;

use LWTV\Queeries\Is_Actor_Queer;
use LWTV\Queeries\Is_Actor_Trans;
use LWTV\Relationships\Character_Links;

class Show_Characters {

	/**
	 * Per-request memo of resolved character lists, keyed "post_id:format:role".
	 *
	 * Static because callers construct a fresh Show_Characters every time
	 * (see _Components\Theme::get_characters_list()), so instance state would
	 * never survive to be reused.
	 *
	 * @var array<string, mixed>
	 */
	private static array $memo = array();

	/**
	 * Per-request memo of the resolved per-character traversal, keyed by show ID.
	 *
	 * Separate from $memo, and keyed by show alone, because the traversal is
	 * format-agnostic: 'count', 'dead', 'query' and the rest all read different
	 * fields out of the same array. Keying this by format would defeat the point.
	 *
	 * @var array<string, array>
	 */
	private static array $resolved = array();

	/**
	 * Generate character lists
	 *
	 * @access public
	 *
	 * @param  string $show_id
	 * @param  string $format
	 *
	 * @return mixed
	 */
	public function make( $post_id, $format, $role = '' ) {

		$post_status = get_post_status( $post_id );

		if ( 'publish' !== $post_status ) {
			return array();
		}

		// Per-request memo. Shows\Calculations::do_the_math() resolves the same
		// character list three times for one show -- once in
		// prime_character_caches(), once in show_character_data(), once via
		// count_queers_all_types() -- and each pass would otherwise re-run the
		// character-link lookup and a get_field() per character.
		//
		// Keyed on all three arguments because the format and role change what is
		// returned. Callers that need a guaranteed-fresh read call flush_cache()
		// first; do_the_math() does that per show, which also keeps this from
		// growing across a full recalculation.
		$cache_key = $post_id . ':' . $format . ':' . $role;

		if ( isset( self::$memo[ $cache_key ] ) ) {
			return self::$memo[ $cache_key ];
		}

		$characters = ( new Character_Links() )->characters_for_show( (int) $post_id );

		if ( empty( $characters ) ) {
			// Not memoised: an empty result is cheap to recompute, and caching it
			// would make a show look permanently characterless for the rest of the
			// request if the link index were still being populated.
			return array();
		}

		// Both consumers re-check membership against each character's own
		// lezchars_show_group: count_characters() only counts inside
		// `$char_show['show'] === $show_id`, and build_character_data()
		// `continue`s otherwise. Reading a show never writes links; the
		// save-time sync in Characters\Calculations is the source of truth.
		if ( ! empty( $role ) ) {
			$build_data = $this->build_character_data( $characters, $post_id, $role );
		} else {
			$build_data = $this->build_character_list( $characters, $post_id, $format );
		}

		self::$memo[ $cache_key ] = $build_data;

		return $build_data;
	}

	/**
	 * Drop memoised character lists.
	 *
	 * @param int|null $post_id Clear just this show, or null for everything.
	 */
	public static function flush_cache( $post_id = null ): void {
		if ( null === $post_id ) {
			self::$memo     = array();
			self::$resolved = array();
			return;
		}

		unset( self::$resolved[ (string) $post_id ] );

		$prefix = $post_id . ':';
		foreach ( array_keys( self::$memo ) as $key ) {
			if ( 0 === strpos( (string) $key, $prefix ) ) {
				unset( self::$memo[ $key ] );
			}
		}
	}

	/**
	 * Build Character Data
	 *
	 * Get all the characters for a show, based on role type and output in
	 * a customized format for the show page.
	 *
	 * @param array  $characters Array of character IDs
	 * @param int    $show_id    ID of the show
	 * @param string $role       Role of the characters to look for
	 *
	 * @return array of characters with custom data to output
	 */
	public function build_character_data( $characters, $show_id, $role = 'regular' ): mixed {
		// Valid Roles:
		$valid_roles = array( 'regular', 'recurring', 'guest', 'all' );

		// If this isn't a show page, or there are no valid roles, bail.
		if ( Shows::SLUG !== get_post_type( $show_id ) || ! in_array( $role, $valid_roles, true ) ) {
			return array();
		}

		// Empty array to display later
		$display = array();

		foreach ( $characters as $char_id ) {
			if ( 'publish' !== get_post_status( $char_id ) ) {
				continue;
			}

			$shows_array = get_field( 'lezchars_show_group', $char_id );

			// If the character is in this show, AND a published character,
			// AND has this role ON THIS SHOW we will pass the following
			// data to the character template to determine what to display.
			if ( isset( $shows_array ) && ! empty( $shows_array ) ) {
				$shows_roles = array();

				foreach ( $shows_array as $char_show ) {
					// Remove the Array if it's there.
					if ( is_array( $char_show['show'] ) ) {
						$char_show['show'] = $char_show['show'][0];
					}

					if ( (int) $char_show['show'] !== (int) $show_id ) {
						continue;
					}

					$shows_roles[ $char_show['show'] ] = $char_show['type'];
				}

				if ( ! isset( $shows_roles[ $show_id ] ) ) {
					continue;
				}

				if ( 'all' === $role ) {
					foreach ( array( 'regular', 'recurring', 'guest' ) as $all_role ) {
						if ( $all_role === $shows_roles[ $show_id ] ) {
							$display[ $all_role ][] = $this->build_role_data( $char_id, $show_id, $shows_array, $all_role );
						}
					}
				} else {
					$display[ $char_id ] = $this->build_role_data( $char_id, $show_id, $shows_array, $shows_roles[ $show_id ] );
				}
			}
		}

		return $display;
	}

	/**
	 * Build Role Data
	 *
	 * Get all the characters for a show, based on role type and output in
	 * a customized format for the show page.
	 *
	 * @param int    $char_id           Character ID
	 * @param int    $show_id           ID of the show
	 * @param array  $shows_array       Array of show IDs
	 * @param string $role              Role of the characters to look for
	 *
	 * @return array of characters with custom data to output
	 */
	public function build_role_data( $char_id, $show_id, $shows_array, $role ) {
		$display = array(
			'id'        => $char_id,
			'title'     => get_the_title( $char_id ),
			'url'       => get_the_permalink( $char_id ),
			'shows'     => $shows_array,
			'show_from' => $show_id,
			'role_from' => $role,
		);

		return $display;
	}

	/**
	 * Generate list of characters for shows.
	 *
	 * Public entry point; the work happens in resolve_character_counts()
	 * (memoised per show) and the requested value is picked out by
	 * format_character_list().
	 *
	 * @param array   $characters  Array of character IDs
	 * @param string  $show_id     ID of the show
	 * @param string  $output      Type of Output
	 *
	 * @return mixed  Depends on $output.
	 */
	public function build_character_list( $characters, $show_id, $output ) {
		return $this->format_character_list(
			$this->resolve_character_counts( $characters, $show_id ),
			$output
		);
	}

	/**
	 * Count and classify every character on a show, in one pass.
	 *
	 * Memoised by show ID, so all seven output formats and any number of callers
	 * within a request share a single traversal. Writes the three show meta keys
	 * as it goes, which is why the memo is flushed per show at the top of
	 * Shows\Calculations::do_the_math().
	 *
	 * @param array  $characters Array of character IDs.
	 * @param string $show_id    ID of the show.
	 *
	 * @return array Counts, plus 'characters' and 'dead_list'.
	 */
	private function resolve_character_counts( $characters, $show_id ): array {
		$cache_key = (string) $show_id;

		if ( isset( self::$resolved[ $cache_key ] ) ) {
			return self::$resolved[ $cache_key ];
		}

		self::$resolved[ $cache_key ] = $this->count_characters( $characters, $show_id );

		return self::$resolved[ $cache_key ];
	}

	/**
	 * The per-character traversal itself.
	 *
	 * @param array  $characters Array of character IDs.
	 * @param string $show_id    ID of the show.
	 *
	 * @return array
	 */
	private function count_characters( $characters, $show_id ): array {
		$new_characters  = array();
		$dead_characters = array();
		$char_counts     = array(
			'total' => 0,
			'dead'  => 0,
			'none'  => 0,
			'quirl' => 0,
			'trans' => 0,
			'txirl' => 0,
		);

		if ( ! empty( $characters ) ) {

			foreach ( $characters as $char_id ) {
				// Get the list of shows.
				$shows_array = get_field( 'lezchars_show_group', $char_id );

				// If the character is in this show, AND a published character
				// we will pass the following data to the character template
				// to determine what to display.
				if ( ! empty( $shows_array ) && is_array( $shows_array ) && 'publish' === get_post_status( $char_id ) ) {
					foreach ( $shows_array as $char_show ) {
						// De-array the show (pre-migration CMB2 data).
						if ( is_array( $char_show['show'] ) ) {
							$char_show['show'] = $char_show['show'][0];
						}

						if ( (int) $char_show['show'] === (int) $show_id ) {
							// Get a list of actors (we need this twice later)
							$actor_field = get_field( 'lezchars_actor', $char_id );
							$actors_ids  = ( $actor_field && is_array( $actor_field ) ) ? $actor_field : array();

							// The Queer Clone Calculations: The post query gets too many IDs
							// So we don't **REALLY** count then via this method unless the show
							// is there for the character.
							// Increase the count of characters
							++$char_counts['total'];
							$new_characters[] = $char_id;

							// Dead?
							if ( has_term( 'dead', 'lez_cliches', $char_id ) ) {
								++$char_counts['dead'];
								$dead_characters[] = $char_id;
							}
							// No cliches?
							if ( has_term( 'none', 'lez_cliches', $char_id ) ) {
								++$char_counts['none'];
							}
							// The Tambor Takedown: Checking Queer IRL
							// We don't award shows that have cast a cis/het actor in a queer
							// role. To solve this, we grab the actor listed as PRIMARY ACTOR
							// (i.e. the one listed first). If THEY are QIRL, the show gets points.
							if ( has_term( 'queer-irl', 'lez_cliches', $char_id ) ) {
								$top_actor = reset( $actors_ids );
								if ( ( new Is_Actor_Queer() )->make( $top_actor ) ) {
									++$char_counts['quirl'];
								}
							}

							// Is the character is not Cisgender ...
							$valid_trans_char = array( 'cisgender', 'intersex', 'unknown' );
							if ( ! has_term( $valid_trans_char, 'lez_gender', $char_id ) ) {
								++$char_counts['trans'];
							}

							// If an actor is transgender, we get an extra bonus.
							foreach ( $actors_ids as $actor ) {
								if ( ( new Is_Actor_Trans() )->make( $actor ) ) {
									++$char_counts['txirl'];
								}
							}
						}
					}
				}
			}
		}

		update_post_meta( $show_id, 'lezshows_dead_count', $char_counts['dead'] );
		update_post_meta( $show_id, 'lezshows_char_count', $char_counts['total'] );
		update_post_meta( $show_id, 'lezshows_char_list', $new_characters );

		$char_counts['characters'] = $new_characters;
		$char_counts['dead_list']  = $dead_characters;

		return $char_counts;
	}

	/**
	 * Pick one value out of resolved character data.
	 *
	 * Split out from the resolving loop so every output format shares one pass:
	 * asking for 'count' and then 'dead' on the same show reads the same
	 * resolved array rather than re-running the per-character loop.
	 *
	 * @param array  $resolved From resolve_character_counts().
	 * @param string $output   Requested format.
	 *
	 * @return mixed
	 */
	private function format_character_list( array $resolved, $output ) {
		switch ( $output ) {
			case 'dead':
				// Count of dead characters
				return $resolved['dead'];
			case 'none':
				// count of characters with NO clichés
				return $resolved['none'];
			case 'queer-irl':
				// count of characters who are queer IRL
				return $resolved['quirl'];
			case 'trans':
				// Count of trans characters
				return $resolved['trans'];
			case 'trans-irl':
				// count of characters who are trans IRL
				return $resolved['txirl'];
			case 'query':
				// Array of all characters by ID
				return $resolved['characters'];
			case 'count':
				// Count of all characters on the show
				return count( $resolved['characters'] );
		}

		return null;
	}
}
