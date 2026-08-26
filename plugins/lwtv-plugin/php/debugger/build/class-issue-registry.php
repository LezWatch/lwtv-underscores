<?php
/**
 * The debugger's issue vocabulary: one entry per kind of problem we can find.
 *
 * Findings used to be an HTML string blob -- several unrelated problems joined
 * with `</br>` into one `problem` key -- which meant a finding could not be
 * addressed, counted, or repaired individually. This constant is the single
 * source of truth that replaces that: the human copy, and whether a repair
 * exists, both live here rather than being duplicated across scanners, CLI
 * output and admin views.
 *
 * PURE. No WordPress calls, no state. Fix callables are stored as
 * array( 'Fully\\Qualified\\Class', 'method' ) *strings* on purpose: the
 * registry must stay loadable (and unit-testable) without pulling a scanner --
 * and therefore WordPress -- in behind it. The caller resolves them.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Issue_Registry {

	/**
	 * Class holding the show repairs. String, not ::class, to keep this file
	 * free of scanner imports.
	 */
	private const SHOWS = '\LWTV\Debugger\Shows';

	/**
	 * Class holding the character repairs.
	 */
	private const CHARACTERS = '\LWTV\Debugger\Characters';

	/**
	 * Class holding the actor repairs.
	 */
	private const ACTORS = '\LWTV\Debugger\Actors';

	/**
	 * Every issue we know how to report.
	 *
	 * - level:     'show' | 'character' | 'actor'. Which CPT the finding is about.
	 * - message:   default human copy. A finding may override it when the detail
	 *              is per-post (a title, a bad value, a URL).
	 * - fix:       optional array( class, method ) taking one post ID and
	 *              returning bool. Presence of this key is what makes an issue
	 *              fixable -- nothing else declares it.
	 * - fix_label: what the repair will actually do, shown before running it.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	const ISSUES = array(

		/*
		 * Shows.
		 */
		'show-no-score'               => array(
			'level'   => 'show',
			'message' => 'Score is 0 or not set - needs characters and/or ratings.',
		),
		'show-no-characters'          => array(
			'level'   => 'show',
			'message' => 'No queer characters recorded. Either the data is missing, or the show only had background/unnamed characters - worth confirming which.',
		),
		'show-no-worthit-details'     => array(
			'level'   => 'show',
			'message' => 'No worthit details.',
		),
		'show-missing-thumb'          => array(
			'level'     => 'show',
			'message'   => 'No Thumb score.',
			'fix'       => array( self::SHOWS, 'set_thumb_tbd' ),
			'fix_label' => 'sets it to TBD',
		),
		'show-no-realness'            => array(
			'level'   => 'show',
			'message' => 'No realness rating.',
		),
		'show-no-quality'             => array(
			'level'   => 'show',
			'message' => 'No quality rating.',
		),
		'show-no-screentime'          => array(
			'level'   => 'show',
			'message' => 'No screentime rating.',
		),
		'show-no-imdb'                => array(
			'level'   => 'show',
			'message' => 'No IMDb ID.',
		),
		'show-no-stations'            => array(
			'level'   => 'show',
			'message' => 'No stations.',
		),
		'show-no-country'             => array(
			'level'   => 'show',
			'message' => 'No country.',
		),
		'show-no-format'              => array(
			'level'   => 'show',
			'message' => 'No format.',
		),
		'show-no-genres'              => array(
			'level'   => 'show',
			'message' => 'No genres.',
		),
		'show-missing-trope'          => array(
			'level'     => 'show',
			'message'   => 'No tropes set.',
			'fix'       => array( self::SHOWS, 'add_none_trope' ),
			'fix_label' => 'adds the "none" trope',
		),
		'show-airdate'                => array(
			'level'   => 'show',
			'message' => 'Airdate problem.',
		),
		'show-duplicate'              => array(
			'level'   => 'show',
			'message' => 'Possible duplicate show.',
		),
		'show-intersection'           => array(
			'level'   => 'show',
			'message' => 'Intersectionality does not match the characters.',
		),

		/*
		 * Characters.
		 */
		'char-missing-cliche'         => array(
			'level'     => 'character',
			'message'   => 'No cliché set.',
			'fix'       => array( self::CHARACTERS, 'add_none_cliche' ),
			'fix_label' => 'adds the "none" cliché',
		),
		'char-dead-no-date'           => array(
			'level'   => 'character',
			'message' => 'Dead but missing date.',
		),
		'char-no-shows'               => array(
			'level'   => 'character',
			'message' => 'No shows listed.',
		),
		'char-no-years'               => array(
			'level'   => 'character',
			'message' => 'No years on air set.',
		),
		'char-no-role'                => array(
			'level'   => 'character',
			'message' => 'No role set.',
		),
		'char-no-show-name'           => array(
			'level'   => 'character',
			'message' => 'No show name set.',
		),
		'char-no-actors'              => array(
			'level'   => 'character',
			'message' => 'No actors listed.',
		),

		/*
		 * Actors.
		 */
		'actor-no-characters'         => array(
			'level'   => 'actor',
			'message' => 'No characters listed.',
		),
		'actor-wikipedia-invalid'     => array(
			'level'   => 'actor',
			'message' => 'Wikipedia URL does not point to Wikipedia.',
		),
		'actor-instagram-invalid'     => array(
			'level'   => 'actor',
			'message' => 'Instagram ID is invalid.',
		),
		'actor-instagram-is-imdb'     => array(
			'level'     => 'actor',
			'message'   => 'Instagram ID is an IMDb ID.',
			'fix'       => array( self::ACTORS, 'remove_imdb_from_instagram' ),
			'fix_label' => 'removes it',
		),
		'actor-twitter-invalid'       => array(
			'level'   => 'actor',
			'message' => 'Twitter ID is invalid.',
		),
		'actor-twitter-is-imdb'       => array(
			'level'     => 'actor',
			'message'   => 'Twitter ID is an IMDb ID.',
			'fix'       => array( self::ACTORS, 'remove_imdb_from_twitter' ),
			'fix_label' => 'removes it',
		),
		'actor-homepage-is-wikipedia' => array(
			'level'     => 'actor',
			'message'   => 'Homepage points to Wikipedia and no Wikipedia URL is set.',
			'fix'       => array( self::ACTORS, 'fix_homepage_wikipedia' ),
			'fix_label' => 'moves it to the Wikipedia field',
		),
		'actor-homepage-dupe-wiki'    => array(
			'level'     => 'actor',
			'message'   => 'Homepage duplicates the Wikipedia URL.',
			'fix'       => array( self::ACTORS, 'fix_homepage_wikipedia' ),
			'fix_label' => 'removes the homepage',
		),
		'actor-homepage-wikipedia'    => array(
			'level'   => 'actor',
			'message' => 'Homepage points to a different Wikipedia page than the Wikipedia field.',
		),
		'actor-shadow-sync-failed'    => array(
			'level'   => 'actor',
			'message' => 'Shadow taxonomy sync failed repeatedly.',
		),
	);

	/**
	 * Is this a registered issue type?
	 *
	 * @param  string $issue_type Issue type key.
	 * @return bool
	 */
	public static function exists( string $issue_type ): bool {
		return isset( self::ISSUES[ $issue_type ] );
	}

	/**
	 * One registry entry.
	 *
	 * @param  string $issue_type Issue type key.
	 * @return array  Empty when unregistered.
	 */
	public static function get( string $issue_type ): array {
		return self::ISSUES[ $issue_type ] ?? array();
	}

	/**
	 * Default human copy for an issue.
	 *
	 * Falls back to the key itself rather than an empty string: an unregistered
	 * type showing up as 'show-whatever' in a report is a bug we want to see,
	 * not a blank table cell.
	 *
	 * @param  string $issue_type Issue type key.
	 * @return string
	 */
	public static function message( string $issue_type ): string {
		return self::ISSUES[ $issue_type ]['message'] ?? $issue_type;
	}

	/**
	 * Which CPT an issue is about.
	 *
	 * @param  string $issue_type Issue type key.
	 * @return string 'show' | 'character' | 'actor', or '' when unregistered.
	 */
	public static function level( string $issue_type ): string {
		return self::ISSUES[ $issue_type ]['level'] ?? '';
	}

	/**
	 * Does a repair exist for this issue?
	 *
	 * @param  string $issue_type Issue type key.
	 * @return bool
	 */
	public static function is_fixable( string $issue_type ): bool {
		return ! empty( self::ISSUES[ $issue_type ]['fix'] );
	}

	/**
	 * What the repair will do, phrased to follow "fixable, ...".
	 *
	 * @param  string $issue_type Issue type key.
	 * @return string Empty when there is no repair.
	 */
	public static function fix_label( string $issue_type ): string {
		if ( ! self::is_fixable( $issue_type ) ) {
			return '';
		}

		return self::ISSUES[ $issue_type ]['fix_label'] ?? 'repairs it';
	}

	/**
	 * The repair callable, as array( class, method ).
	 *
	 * @param  string $issue_type Issue type key.
	 * @return array  Empty when there is no repair.
	 */
	public static function fix_callable( string $issue_type ): array {
		if ( ! self::is_fixable( $issue_type ) ) {
			return array();
		}

		return self::ISSUES[ $issue_type ]['fix'];
	}

	/**
	 * Every issue type that has a repair.
	 *
	 * @return array<string>
	 */
	public static function fixable_types(): array {
		$fixable = array();

		foreach ( self::ISSUES as $issue_type => $issue ) {
			if ( ! empty( $issue['fix'] ) ) {
				$fixable[] = $issue_type;
			}
		}

		return $fixable;
	}

	/**
	 * Every issue type for one CPT level.
	 *
	 * @param  string $level 'show' | 'character' | 'actor'.
	 * @return array<string>
	 */
	public static function for_level( string $level ): array {
		$types = array();

		foreach ( self::ISSUES as $issue_type => $issue ) {
			if ( ( $issue['level'] ?? '' ) === $level ) {
				$types[] = $issue_type;
			}
		}

		return $types;
	}
}
