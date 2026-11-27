<?php
/**
 * Name: Character Document
 * Description: Builds a character's Meilisearch document.
 *
 * Pure: the caller gathers titles and visibility; this decides what goes in.
 * Only visible (published, not privacy-hidden) shows and actors are named,
 * because the document is readable through the public search key.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Document {

	/**
	 * Role tie-breaker: higher ranks first.
	 */
	const ROLE_RANKS = array(
		'regular'   => 3,
		'recurring' => 2,
		'guest'     => 1,
	);

	/**
	 * Build the document.
	 *
	 * @param int    $id         Character post ID.
	 * @param string $title      Raw character title.
	 * @param mixed  $show_group Rows of array( 'show' => id, 'type' => role ); anything else for none.
	 * @param mixed  $actor_ids  Actor IDs; anything non-array for none.
	 * @param array  $lookup     id => array( 'title' => string, 'visible' => bool ) for linked posts that exist.
	 * @param string $url        Permalink.
	 * @param string $thumb      Thumbnail URL, or ''.
	 * @return array{id:int, name:string, shows:string[], actors:string[], role:string, role_rank:int, url:string, thumb:string}
	 */
	public static function build( int $id, string $title, $show_group, $actor_ids, array $lookup, string $url, string $thumb ): array {
		$shows = array();
		$role  = '';
		$rank  = 0;

		foreach ( is_array( $show_group ) ? $show_group : array() as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['show'] ) ) {
				continue;
			}

			$show_id = (int) $row['show'];
			if ( ! self::visible( $lookup, $show_id ) ) {
				continue;
			}

			$shows[ $show_id ] = $shows[ $show_id ] ?? self::decode( $lookup[ $show_id ]['title'] );

			$type      = is_string( $row['type'] ?? null ) ? $row['type'] : '';
			$type_rank = self::ROLE_RANKS[ $type ] ?? 0;
			if ( $type_rank > $rank ) {
				$rank = $type_rank;
				$role = $type;
			}
		}

		$actors = array();
		foreach ( is_array( $actor_ids ) ? $actor_ids : array() as $actor_id ) {
			$actor_id = (int) $actor_id;
			if ( self::visible( $lookup, $actor_id ) ) {
				$actors[ $actor_id ] = self::decode( $lookup[ $actor_id ]['title'] );
			}
		}

		return array(
			'id'        => $id,
			'name'      => self::decode( $title ),
			'shows'     => array_values( $shows ),
			'actors'    => array_values( $actors ),
			'role'      => $role,
			'role_rank' => $rank,
			'url'       => $url,
			'thumb'     => $thumb,
		);
	}

	/**
	 * Whether a linked post exists and is publicly visible.
	 *
	 * @param array $lookup Lookup map.
	 * @param int   $id     Post ID.
	 * @return bool
	 */
	private static function visible( array $lookup, int $id ): bool {
		return isset( $lookup[ $id ]['title'] ) && ! empty( $lookup[ $id ]['visible'] );
	}

	/**
	 * Plain text from a stored title.
	 *
	 * @param mixed $text Title.
	 * @return string
	 */
	private static function decode( $text ): string {
		return trim( html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}
}
