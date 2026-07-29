<?php
/**
 * Characters On Air view transforms for This Year.
 *
 * Pure array-in / array-out helpers that shape the flat character list and the
 * by-show cast for the Characters On Air template. No WordPress runtime
 * dependency — all i18n and WP calls stay in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes character data for the Characters On Air view.
 */
class Characters_On_Air {

	/**
	 * Role types in precedence order, strongest first. Mirrors
	 * Breakdowns::ROLE_TYPES; a character on two shows displays the strongest.
	 *
	 * @var string[]
	 */
	public const ROLE_PRECEDENCE = array( 'regular', 'recurring', 'guest' );

	/**
	 * Every tracked role a character holds across their shows, strongest first.
	 *
	 * @param array $shows List of { name, url, type }.
	 * @return array List of { type, show } for tracked roles only, strongest first.
	 */
	public static function roles_by_strength( array $shows ): array {
		$roles = array();
		foreach ( $shows as $show ) {
			$type = $show['type'] ?? '';
			if ( in_array( $type, self::ROLE_PRECEDENCE, true ) ) {
				$roles[] = array(
					'type' => $type,
					'show' => (string) ( $show['name'] ?? '' ),
				);
			}
		}

		usort(
			$roles,
			static fn( $a, $b ) =>
				array_search( $a['type'], self::ROLE_PRECEDENCE, true )
				<=> array_search( $b['type'], self::ROLE_PRECEDENCE, true )
		);

		return $roles;
	}

	/**
	 * The graph/directory bucket for a name: its uppercase Latin initial, or #.
	 *
	 * @param string $name Character name.
	 * @return string One of A–Z, or '#'.
	 */
	public static function bucket_for( string $name ): string {
		$first = mb_strtoupper( mb_substr( trim( $name ), 0, 1 ) );
		return ( 1 === preg_match( '/^[A-Z]$/', $first ) ) ? $first : '#';
	}

	/**
	 * The A–Z (+ #) graph model. Bars sum to the character count because the #
	 * bucket captures every non-Latin initial the A–Z tally would drop.
	 *
	 * @param array $characters List of characters, each with a 'name'.
	 * @return array See the method's @return contract in the plan.
	 */
	public static function alphabet( array $characters ): array {
		$counts = array_fill_keys( range( 'A', 'Z' ), 0 );
		$hash   = 0;

		foreach ( $characters as $char ) {
			$bucket = self::bucket_for( (string) ( $char['name'] ?? '' ) );
			if ( '#' === $bucket ) {
				++$hash;
			} else {
				++$counts[ $bucket ];
			}
		}

		$nonzero = array_filter( $counts );
		$max     = $nonzero ? max( $nonzero ) : 0;
		$top     = $nonzero ? array_keys( $counts, $max, true ) : array();
		$unused  = array_values( array_keys( $counts, 0, true ) );
		$in_use  = 26 - count( $unused );

		$columns = array();
		foreach ( range( 'A', 'Z' ) as $letter ) {
			$columns[] = array(
				'letter' => $letter,
				'count'  => $counts[ $letter ],
				'empty'  => 0 === $counts[ $letter ],
				'peak'   => $max > 0 && in_array( $letter, $top, true ),
			);
		}
		$columns[] = array(
			'letter' => '#',
			'count'  => $hash,
			'empty'  => 0 === $hash,
			'peak'   => false,
		);

		return array(
			'columns' => $columns,
			'max'     => $max,
			'top'     => $top,
			'unused'  => $unused,
			'in_use'  => $in_use,
			'hash'    => $hash,
		);
	}

	/**
	 * Group the flat character list into A–Z (+ #) buckets for the directory,
	 * alphabetized within each bucket, strongest role attached per row.
	 *
	 * @param array $characters List of { slug, name, dead, shows:[{name,url,type}] }.
	 * @return array Ordered list of { letter, count, rows:[ { slug, name, dead, shows, role, roles } ] }.
	 */
	public static function directory( array $characters ): array {
		$buckets = array();
		foreach ( $characters as $char ) {
			$name   = (string) ( $char['name'] ?? '' );
			$bucket = self::bucket_for( $name );
			$roles  = self::roles_by_strength( $char['shows'] ?? array() );

			$buckets[ $bucket ][] = array(
				'slug'  => (string) ( $char['slug'] ?? '' ),
				'name'  => $name,
				'dead'  => (bool) ( $char['dead'] ?? false ),
				'shows' => array_values( $char['shows'] ?? array() ),
				'role'  => $roles[0]['type'] ?? '',
				'roles' => $roles,
			);
		}

		$ordered = array();
		foreach ( array_merge( range( 'A', 'Z' ), array( '#' ) ) as $letter ) {
			if ( empty( $buckets[ $letter ] ) ) {
				continue;
			}
			$rows = $buckets[ $letter ];
			usort( $rows, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );

			$ordered[] = array(
				'letter' => $letter,
				'count'  => count( $rows ),
				'rows'   => $rows,
			);
		}

		return $ordered;
	}

	/**
	 * A show's cast for the By Show tab: nameless entries filtered out
	 * (defensive guard for dangling show-group references), sorted by name.
	 *
	 * @param array $characters A show's characters, each with a 'name'.
	 * @return array Named characters, alphabetized, reindexed.
	 */
	public static function cast_for_show( array $characters ): array {
		$named = array_values(
			array_filter(
				$characters,
				static fn( $c ) => '' !== trim( (string) ( $c['name'] ?? '' ) )
			)
		);

		usort( $named, static fn( $a, $b ) => strnatcasecmp( (string) $a['name'], (string) $b['name'] ) );

		return $named;
	}
}
