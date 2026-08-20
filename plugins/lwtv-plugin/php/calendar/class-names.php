<?php
/**
 * Name: Calendar Names
 * Description: Sometimes we have weird names for the calendar.
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Names {

	/**
	 * Memoised name lookups, keyed by the TVMaze display name.
	 *
	 * @var array
	 */
	private $resolved = array();

	/**
	 * Check Show Name
	 *
	 * Since TV Maze sometimes uses different names than we do, we have to make
	 * a related array that can handle two names.
	 *
	 * Names can be customized in the admin area using the TVMaze CPT.
	 *
	 * @param  string $showname Display Name of the show
	 * @param  string $source   lwtv or tvmaze
	 * @return string           The display name
	 */
	public function make( $name, $source, $output ) {

		$check_name = $this->resolve( $name );

		// Output depends on source calling.
		switch ( $source ) {
			case 'lwtv':
				// Return only the name
				return ( 'name' === $output ) ? $check_name['name'] : $check_name['id'];
			case 'tvmaze':
				return ( 'name' === $output ) ? $this->get_link( $check_name['name'], (int) $check_name['id'] ) : $check_name['id'];
		}
	}

	/**
	 * Resolve a TVMaze show name to a local show ID and display name.
	 *
	 * Each lookup costs up to two get_page_by_path() calls, so callers that
	 * need both the ID and the name should call this once and read both keys
	 * rather than calling make() twice.
	 *
	 * Results are memoised on the instance because the calendar renders three
	 * weeks at a time and the same show recurs across many days. Callers get
	 * the shared instance from Calendar_Object_Pool, so the cache lasts the
	 * request and is released by Calendar_Object_Pool::clear().
	 *
	 * @param  string $name Display name of the show, as TVMaze gives it to us.
	 * @return array        array( 'id' => int, 'name' => string )
	 */
	public function resolve( string $name ): array {
		if ( isset( $this->resolved[ $name ] ) ) {
			return $this->resolved[ $name ];
		}

		// Check TV Maze
		$check_name = $this->tvmaze( $name );

		// If the ID is 0, try looking for a local show that matches.
		if ( 0 === $check_name['id'] ) {
			$check_name = $this->local( $name );
		}

		$check_name['id'] = (int) $check_name['id'];

		$this->resolved[ $name ] = $check_name;

		return $check_name;
	}

	/**
	 * Build the display markup for a show name.
	 *
	 * Returns a link when we have a local show to point at, and the escaped
	 * name on its own when we do not. Either way the return value is HTML and
	 * is already escaped - callers must NOT run it through esc_html().
	 *
	 * @param  string $name Display name of the show.
	 * @param  int    $id   Local show post ID, or 0 if we have no match.
	 * @return string       Escaped HTML.
	 */
	public function get_link( string $name, int $id ): string {
		if ( 0 === $id ) {
			return esc_html( $name );
		}

		return '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $name ) . '</a>';
	}

	/**
	 * Check TV Maze for a stored name.
	 *
	 * @param  string $name
	 * @return array
	 */
	private function tvmaze( string $name ): array {
		// Set base name and ID.
		$show_name = $name;
		$show_id   = 0;

		$sanitized_slug = sanitize_title( $name );
		$tvmaze_obj     = get_page_by_path( $sanitized_slug, OBJECT, 'post_type_tvmaze' );

		// If there is a TV Maze entry already, we use it.
		if ( isset( $tvmaze_obj->ID ) && 0 !== $tvmaze_obj->ID && 'publish' === get_post_status( $tvmaze_obj->ID ) ) {
			$post_id = $tvmaze_obj->ID;
			$show    = (int) get_field( 'leztvmaze_our_show', $post_id );

			if ( 0 !== $show ) {
				$show_id   = $show;
				$show_name = get_the_title( $show_id );
			}
		}

		return array(
			'id'   => $show_id,
			'name' => $show_name,
		);
	}

	/**
	 * Check local shows for the matching name.
	 *
	 * @param  string $name
	 * @return array
	 */
	private function local( string $name ): array {
		$show_name = $name;
		$show_id   = 0;

		// Find the show based on the LezWatch name
		$show_page_obj = get_page_by_path( sanitize_title( $name ), OBJECT, 'post_type_shows' );

		// If there is a local show with a full match, we can use it.
		if ( isset( $show_page_obj->ID ) && 0 !== $show_page_obj->ID && 'publish' === get_post_status( $show_page_obj->ID ) ) {
			$show_id   = $show_page_obj->ID;
			$show_name = get_the_title( $show_id );
		}

		return array(
			'id'   => $show_id,
			'name' => $show_name,
		);
	}
}
