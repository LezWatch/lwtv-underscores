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
	 * Class holding the on-air repair.
	 */
	private const ONAIR = '\LWTV\Debugger\OnAir';

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
	 * - manual:    the repair is a judgement call. Offered per finding in
	 *              wp-admin, never applied by a bulk --fix-it run.
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
		/*
		 * The repair here does not fill the gap -- it records that there is
		 * nothing to fill it with, by setting the show's "No Known Characters"
		 * flag. That is a judgement about a particular show, so it is `manual`:
		 * offered as a button next to the finding, never applied by a bulk
		 * --fix-it run. Bulk-flagging every characterless show would erase the
		 * exact distinction this check exists to surface.
		 */
		'show-no-characters'          => array(
			'level'     => 'show',
			'message'   => 'No queer characters recorded.',
			'fix'       => array( self::SHOWS, 'flag_no_characters' ),
			'fix_label' => 'flags it as having no known characters',
			'manual'    => true,
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
		'show-no-airdates'            => array(
			'level'   => 'show',
			'message' => 'No airdates.',
		),
		'show-no-start-date'          => array(
			'level'   => 'show',
			'message' => 'No start date.',
		),
		'show-no-end-date'            => array(
			'level'   => 'show',
			'message' => 'No end-date. If the show is on-air, set to CURRENT. TV movies end in the same year.',
		),
		'show-airdate-inverted'       => array(
			'level'   => 'show',
			'message' => 'Start date is AFTER end date.',
		),
		/*
		 * Retired, kept registered on purpose. The four types above replaced this
		 * one catch-all, and a stored baseline written before that split still
		 * holds `<id>:show-airdate` keys. Keeping the entry means those resolve to
		 * readable copy when the next run reports them as resolved, instead of
		 * falling back to the raw key.
		 */
		'show-airdate'                => array(
			'level'   => 'show',
			'message' => 'Airdate problem.',
		),
		'show-duplicate'              => array(
			'level'   => 'show',
			'message' => 'Likely Dupe - Another Show has this name AND the same IMDb data.',
		),
		'show-intersection'           => array(
			'level'   => 'show',
			'message' => 'No character on this show is tagged as disabled. Please review.',
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

		/*
		 * Bury Your Queers (the `byq` check).
		 */
		'char-no-death-year'          => array(
			'level'   => 'character',
			'message' => 'Character marked as dead but missing lezchars_death_year meta data.',
		),
		'char-show-no-byq-trope'      => array(
			'level'   => 'character',
			'message' => 'A show this character is on has no BYQ trope.',
		),

		/*
		 * Queer consistency (the `queers` check).
		 */
		'char-missing-queer-irl'      => array(
			'level'   => 'character',
			'message' => 'Missing Queer IRL tag.',
		),
		'char-no-queer-actor'         => array(
			'level'   => 'character',
			'message' => 'Tagged Queer IRL, but no actor is queer.',
		),
		'char-no-actors-listed'       => array(
			'level'   => 'character',
			'message' => 'No actors listed for this character.',
		),

		/*
		 * Duplicates (the `dupes` check). Two types rather than one, because a
		 * finding's level decides which cache an admin repair prunes and which
		 * tab it returns to -- and this check spans two post types.
		 *
		 * `acknowledged_by` is the pre-existing editor override, which predates
		 * the mechanism: somebody has already confirmed this is not a duplicate.
		 */
		'show-is-duplicate'           => array(
			'level'   => 'show',
			'message' => 'This show is a duplicate of another.',
		),
		'actor-is-duplicate'          => array(
			'level'   => 'actor',
			'message' => 'This actor is a duplicate of another.',
		),

		/*
		 * On air (the `on_air` check). Both repairable, by the same method that
		 * has always backed `--fix-it` for this check.
		 */
		'show-onair-no-data'          => array(
			'level'     => 'show',
			'message'   => 'Show has no on-air meta data and/or airdates.',
			'fix'       => array( self::ONAIR, 'fix_on_air_status' ),
			'fix_label' => 'recalculates the on-air status from the airdates',
		),
		'show-onair-mismatch'         => array(
			'level'     => 'show',
			'message'   => 'On-air meta does not match the actual on-air status.',
			'fix'       => array( self::ONAIR, 'fix_on_air_status' ),
			'fix_label' => 'recalculates the on-air status from the airdates',
		),

		/*
		 * IMDb (the `show_imdb` and `actor_imdb` checks).
		 */
		'show-imdb-not-set'           => array(
			'level'   => 'show',
			'message' => 'IMDb ID is not set.',
		),
		'show-imdb-invalid'           => array(
			'level'   => 'show',
			'message' => 'IMDb ID is invalid (ex: tt12345).',
		),
		/*
		 * Repairable, and safe in bulk, because the correct value is inside the
		 * wrong one: someone pasted the IMDb page URL instead of the ID, and the
		 * ID is in a known position in it. Contrast the IMDb-in-a-social-field
		 * repair, which deletes rather than moves, because there the intent is a
		 * guess. Here it is not.
		 */
		'show-imdb-url-pasted'        => array(
			'level'     => 'show',
			'message'   => 'The IMDb field holds a URL, not an ID.',
			'fix'       => array( self::SHOWS, 'extract_imdb_from_url' ),
			'fix_label' => 'replaces it with the ID from the URL',
		),
		'show-imdb-stale'             => array(
			'level'   => 'show',
			'message' => 'IMDb ID disagrees with TVMaze.',
		),
		'actor-imdb-not-set'          => array(
			'level'   => 'actor',
			'message' => 'IMDb ID is not set.',
		),
		'actor-imdb-invalid'          => array(
			'level'   => 'actor',
			'message' => 'IMDb ID is invalid (ex: nm12345).',
		),
		'actor-imdb-url-pasted'       => array(
			'level'     => 'actor',
			'message'   => 'The IMDb field holds a URL, not an ID.',
			'fix'       => array( self::ACTORS, 'extract_imdb_from_url' ),
			'fix_label' => 'replaces it with the ID from the URL',
		),
		'actor-imdb-stale'            => array(
			'level'   => 'actor',
			'message' => 'IMDb ID disagrees with TMDB.',
		),

		/*
		 * Watch provider URLs (the `watchurls` check).
		 *
		 * Level 'watch_term' rather than a CPT: these findings are about
		 * `lez_watch_urls` terms. Nothing maps that level to a repair cache, so
		 * Repair::is_supported() refuses them and no admin buttons appear --
		 * which is right, since none of these can be fixed without a human
		 * deciding what the URL should be.
		 *
		 * The health of the URL (broken / needs review / blocked) is a separate
		 * axis, carried on the row for the report's own column. Two of these
		 * types share a health of "needs review" while being quite different
		 * problems, which is why the type is not just the health.
		 */
		'watch-url-broken'            => array(
			'level'   => 'watch_term',
			'message' => 'The URL does not answer.',
		),
		'watch-url-suspect'           => array(
			'level'   => 'watch_term',
			'message' => 'The URL answers, but may not be this provider any more.',
		),
		'watch-url-blocked'           => array(
			'level'   => 'watch_term',
			'message' => 'The host blocked us, so this could not be checked.',
		),
		/*
		 * Retired 2026-08-27. A term with no URLs is legitimate -- it is how a
		 * provider gets prepped before a network launches -- so reporting it and
		 * advising "add a URL or delete the term" was telling editors to throw
		 * away deliberate work. The scanner that emitted it is gone.
		 *
		 * The type stays declared because findings are stored for ten days:
		 * a cached row still typed this way must render with a message rather
		 * than an empty string until the next sweep replaces it.
		 */
		'watch-term-no-urls'          => array(
			'level'   => 'watch_term',
			'message' => 'This term has no URLs. No longer reported — a term without URLs is a valid placeholder.',
		),
		'watch-url-deferred'          => array(
			'level'   => 'watch_term',
			'message' => 'Not re-checked yet — the page ran out of time. Press the button again.',
		),
		'watch-host-collision'        => array(
			'level'   => 'watch_term',
			'message' => 'Another provider term claims this host too.',
		),

		/*
		 * Incomplete actors (the `actor_empty` check).
		 */
		'actor-no-image'              => array(
			'level'   => 'actor',
			'message' => 'No image found.',
		),
		'actor-no-bio'                => array(
			'level'   => 'actor',
			'message' => 'No biography found.',
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
	 * Is the repair a judgement call, for a human to make one at a time?
	 *
	 * Fixable, but not in bulk. A `--fix-it` run must skip these: applying them
	 * across a whole report would be making the judgement on everyone's behalf.
	 *
	 * @param  string $issue_type Issue type key.
	 * @return bool
	 */
	public static function is_manual( string $issue_type ): bool {
		return self::is_fixable( $issue_type ) && ! empty( self::ISSUES[ $issue_type ]['manual'] );
	}

	/**
	 * Every issue type a bulk run may repair.
	 *
	 * @return array<string>
	 */
	public static function bulk_fixable_types(): array {
		return array_values(
			array_filter(
				self::fixable_types(),
				static fn ( $issue_type ) => ! self::is_manual( $issue_type )
			)
		);
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
