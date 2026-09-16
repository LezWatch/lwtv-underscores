<?php
/**
 * What counts as an editorial override, and what to say about one.
 *
 * The Exclusion Checker exists because automation only goes so far and we
 * override it when it's wrong.
 *
 * Everything here is a decision, and every decision is made from an array of
 * meta values passed in. Nothing reads post meta, runs a query, or echoes. That
 * is what lets the awkward parts be tested rather than discovered on the
 * live admin screen. Admin_Menu\Exclusions keeps the WordPress half: the menu,
 * one query per check, the meta reads, and the markup.
 *
 * The thing worth knowing before adding a check: our booleans are not stored
 * the same way.
 *
 *   - Plain ACF true_false writes "1" when ticked and *keeps a "0" row* when
 *     not. A query for EXISTS on one of those matches every post that has ever
 *     been saved, so these must match on the value '1'.
 *   - lezshows_byq_override and lezshows_worthit_show_we_love are special:
 *     ACF::SHOW_LEGACY_ON_FIELDS makes save_show_legacy_meta() rewrite them to
 *     'on' when ticked and *delete* the row when not, because SQL elsewhere
 *     hardcodes = 'on'. Those match on 'on'.
 *   - lezactors_queer_override is a select whose default is the literal string
 *     'undefined', so for that one any *other* defined value is the signal.
 *
 * Getting this wrong does not error. It quietly counts the whole catalogue as
 * overridden, which is why `match` is a required part of every definition
 * rather than something a caller may leave off.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Wikidata\Build\Qid_Trust;

class Exclusion_Registry {

	/**
	 * Which CPT a check runs against.
	 *
	 * Tokens rather than the real slugs: this class is loaded by the unit
	 * bootstrap without WordPress, and CPTs\Actors / CPTs\Shows cannot be. The
	 * caller maps these to CPT_Actors::SLUG and CPT_Shows::SLUG, so the slugs
	 * themselves are never duplicated.
	 */
	const CPT_ACTORS = 'actors';
	const CPT_SHOWS  = 'shows';

	/**
	 * Any defined value counts -- for selects, not booleans.
	 */
	const MATCH_ANY = '';

	/**
	 * Stored values that mean "nobody overrode anything".
	 *
	 * A select's "no selection" default, an empty string, and an unticked ACF
	 * boolean. Public because the caller builds its SQL from this too: a
	 * MATCH_ANY check must be queried as NOT IN these values, not as EXISTS.
	 * EXISTS matches every post that has ever been saved -- ACF writes the
	 * 'undefined' default for all of them -- so the database would hand back the
	 * whole catalogue for qualifies() to throw away in PHP, one WP_Post and its
	 * meta at a time.
	 *
	 * @var array<string>
	 */
	const UNSET_VALUES = array( '', '0', 'undefined' );

	/**
	 * The overrides we track.
	 *
	 * 'context' maps a short alias to the meta key holding it. The caller reads
	 * those keys and hands back an array keyed by alias, so meta key names stay
	 * declared in exactly one place while the reading stays out of this class.
	 *
	 * A method rather than a const, only so the labels an editor reads -- 'name',
	 * 'desc', 'column', 'empty' -- can go through __(). A const cannot call a
	 * function, and these are tab names and headings on a wp-admin screen, so
	 * they are as user-facing as anything describe() returns.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function checks(): array {
		return array(
			'queer_checker'   => array(
				'name'    => __( 'Queer Checker', 'lwtv' ),
				'desc'    => __( 'Actors whose queerness has been set by hand.', 'lwtv' ),
				'cpt'     => self::CPT_ACTORS,
				'meta'    => 'lezactors_queer_override',
				'match'   => self::MATCH_ANY,
				'column'  => __( 'Actor', 'lwtv' ),
				'empty'   => __( 'No actors have their queerness overridden at this time.', 'lwtv' ),
				'context' => array(
					// The stored flag the admin column, the ACF relationship labels,
					// both REST endpoints and the statistics all read. It is written
					// on save, so it lags an override until the actor is recalculated.
					'stored_queer' => 'lezactors_queer',
				),
			),
			'dead_checker'    => array(
				'name'    => __( 'Dead Checker', 'lwtv' ),
				'desc'    => __( 'Shows with the death-score deduction overridden.', 'lwtv' ),
				'cpt'     => self::CPT_SHOWS,
				'meta'    => 'lezshows_byq_override',
				// 'on', not '1'. See the note in the class docblock.
				'match'   => 'on',
				'column'  => __( 'Show', 'lwtv' ),
				'empty'   => __( 'No shows have their scores for death overridden at this time.', 'lwtv' ),
				'context' => array(),
			),
			'wikidata_ignore' => array(
				'name'    => __( 'WikiData Locked', 'lwtv' ),
				'desc'    => __( 'Actors whose WikiData QID is write-locked against the backfill.', 'lwtv' ),
				'cpt'     => self::CPT_ACTORS,
				'meta'    => 'lezactors_wikidata_ignore',
				'match'   => '1',
				'column'  => __( 'Actor', 'lwtv' ),
				'empty'   => __( 'No actors have their WikiData QID write-locked at this time.', 'lwtv' ),
				'context' => array(
					'stored_qid' => 'lezactors_wikidata_qid',
					'qid_source' => 'lezactors_wikidata_qid_source',
				),
			),
			'tvmaze_ignore'   => array(
				'name'    => __( 'TVMaze Ignored', 'lwtv' ),
				'desc'    => __( 'Shows whose TVMaze match an editor has overridden or ruled out.', 'lwtv' ),
				'cpt'     => self::CPT_SHOWS,
				'meta'    => 'lezshows_tvmaze_ignore',
				'match'   => '1',
				'column'  => __( 'Show', 'lwtv' ),
				'empty'   => __( 'No shows have their TVMaze match overridden at this time.', 'lwtv' ),
				'context' => array(
					'manual_id' => 'lezshows_tvmaze_id_manual',
					'stored_id' => 'lezshows_tvmaze_id',
				),
			),
			'no_known_chars'  => array(
				'name'    => __( 'No Known Characters', 'lwtv' ),
				'desc'    => __( 'Shows an editor has flagged as having no characters to list.', 'lwtv' ),
				'cpt'     => self::CPT_SHOWS,
				'meta'    => 'lezshows_no_chars',
				'match'   => '1',
				'column'  => __( 'Show', 'lwtv' ),
				'empty'   => __( 'No shows are flagged as having no known characters at this time.', 'lwtv' ),
				'context' => array(
					'char_count' => 'lezshows_char_count',
				),
			),
		);
	}

	/**
	 * Every check, keyed by its tab slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return self::checks();
	}

	/**
	 * The tab slugs, in display order.
	 *
	 * @return array<int, string>
	 */
	public static function keys(): array {
		return array_keys( self::checks() );
	}

	/**
	 * Is this a check we know about?
	 *
	 * @param  string $key Tab slug.
	 * @return bool
	 */
	public static function exists( string $key ): bool {
		return isset( self::checks()[ $key ] );
	}

	/**
	 * One check definition.
	 *
	 * @param  string $key Tab slug.
	 * @return array<string, mixed> Empty when the key is unknown.
	 */
	public static function get( string $key ): array {
		return self::checks()[ $key ] ?? array();
	}

	/**
	 * Does this meta value mean the override is actually set?
	 *
	 * @param  string $meta_value  The stored value.
	 * @param  string $match_value The definition's 'match', or MATCH_ANY.
	 * @return bool
	 */
	public static function qualifies( string $meta_value, string $match_value ): bool {
		$meta_value = trim( $meta_value );

		if ( self::MATCH_ANY !== $match_value ) {
			return $meta_value === $match_value;
		}

		// Still worth checking in PHP even though the caller now filters these in
		// SQL: trim() above means a value of '   ' fails here but would pass a
		// SQL NOT IN, so the two are complementary rather than redundant.
		return ! in_array( $meta_value, self::UNSET_VALUES, true );
	}

	/**
	 * What to put in the "Setting" column.
	 *
	 * For a boolean this is where the row stops being useless. "Yes" tells an
	 * editor nothing they cannot see from the tab they are on; what they want to
	 * know is which of the two things the toggle means in this case "there is
	 * nothing to match" or "the match is this instead".
	 *
	 * @param  string $key     Tab slug.
	 * @param  array  $context Meta values keyed by the definition's aliases,
	 *                         plus 'value' for the override itself.
	 * @return string
	 */
	public static function describe( string $key, array $context ): string {
		$value = trim( (string) ( $context['value'] ?? '' ) );

		switch ( $key ) {
			case 'queer_checker':
				return self::queer_label( $value );

			case 'wikidata_ignore':
				// The two things a lock can mean, and the only way to tell them
				// apart is whether the field holds anything: a pinned identity,
				// or "this person has no WikiData item".
				$stored = trim( (string) ( $context['stored_qid'] ?? '' ) );
				$source = trim( (string) ( $context['qid_source'] ?? '' ) );

				if ( '' === $stored ) {
					return __( 'No WikiData item', 'lwtv' );
				}

				if ( '' !== $source ) {
					/* translators: 1: a WikiData QID, 2: how it was resolved (manual, imdb, name, legacy). */
					return sprintf( __( 'Locked to %1$s (%2$s)', 'lwtv' ), $stored, $source );
				}

				/* translators: %s: a WikiData QID. */
				return sprintf( __( 'Locked to %s', 'lwtv' ), $stored );

			case 'tvmaze_ignore':
				$manual = trim( (string) ( $context['manual_id'] ?? '' ) );

				if ( '' !== $manual ) {
					/* translators: %s: a TVMaze show ID. */
					return sprintf( __( 'Using %s', 'lwtv' ), $manual );
				}

				return __( 'No TVMaze match', 'lwtv' );

			case 'no_known_chars':
			case 'dead_checker':
				return __( 'Yes', 'lwtv' );
		}

		return ( '' === $value ) ? __( 'Yes', 'lwtv' ) : ucfirst( $value );
	}

	/**
	 * Has this override quietly stopped being true?
	 *
	 * An override is a statement about the data at the moment someone ticked it,
	 * and the data keeps moving afterwards. A show flagged "no known characters"
	 * that now has six is the clearest case: the flag is suppressing a panel the
	 * readers should be seeing, and nothing anywhere would ever mention it.
	 *
	 * @param  string $key     Tab slug.
	 * @param  array  $context Meta values keyed by the definition's aliases.
	 * @return string
	 */
	public static function staleness( string $key, array $context ): string {
		switch ( $key ) {
			case 'queer_checker':
				// The override is read live by is_actor_queer(); the stored flag is
				// only rewritten when the actor is saved.
				$override = trim( (string) ( $context['value'] ?? '' ) );
				$stored   = trim( (string) ( $context['stored_queer'] ?? '' ) );

				// Absent counts as not queer, matching how every PHP consumer
				// tests it.
				$stored_says_queer = ! in_array( $stored, array( '', '0' ), true );

				// The WP-CLI command is not translated -- it is a literal someone
				// has to type -- so it sits outside the placeholder.
				if ( 'is_queer' === $override && ! $stored_says_queer ) {
					/* translators: %s: a WP-CLI command to run, not translatable. */
					return sprintf( __( 'Stored as not queer -- run: %s', 'lwtv' ), 'wp lwtv calc actors' );
				}

				if ( 'not_queer' === $override && $stored_says_queer ) {
					/* translators: %s: a WP-CLI command to run, not translatable. */
					return sprintf( __( 'Stored as queer -- run: %s', 'lwtv' ), 'wp lwtv calc actors' );
				}

				return '';

			case 'no_known_chars':
				$count = (int) ( $context['char_count'] ?? 0 );

				if ( $count > 0 ) {
					return sprintf(
						/* translators: %s: number of characters now listed on the show. */
						_n(
							'%s character is now listed -- untick this?',
							'%s characters are now listed -- untick this?',
							$count,
							'lwtv'
						),
						number_format_i18n( $count )
					);
				}

				return '';

			case 'wikidata_ignore':
				$stored = trim( (string) ( $context['stored_qid'] ?? '' ) );
				$source = trim( (string) ( $context['qid_source'] ?? '' ) );

				if ( '' !== $stored && ! Qid_Trust::is_trusted( $source ) ) {
					/* translators: %s: how the QID was resolved (name, legacy). */
					return sprintf( __( 'Locked to an unverified QID (%s) -- retype it to confirm.', 'lwtv' ), Qid_Trust::normalise_source( $source ) );
				}

				return '';

			case 'tvmaze_ignore':
				$manual = trim( (string) ( $context['manual_id'] ?? '' ) );
				$stored = trim( (string) ( $context['stored_id'] ?? '' ) );

				if ( '' === $manual && '' !== $stored ) {
					/* translators: %s: a TVMaze show ID. */
					return sprintf( __( 'Still holds %s, which is now unused.', 'lwtv' ), $stored );
				}

				return '';
		}

		return '';
	}

	/**
	 * The Queer Override select's value, as an editor chose it.
	 *
	 * Mirrors the choices in group_lwtv_actors_details.json. The raw values are
	 * snake_case keys and were being printed as-is.
	 *
	 * @param  string $value Stored value.
	 * @return string
	 */
	private static function queer_label( string $value ): string {
		switch ( $value ) {
			case 'is_queer':
				return __( 'Is Queer', 'lwtv' );
			case 'not_queer':
				return __( 'Is NOT Queer', 'lwtv' );
		}

		// An unrecognised value is echoed back rather than translated: it is a raw
		// stored slug, so there is no string to have translated in the first place.
		return ( '' === $value ) ? '' : ucfirst( str_replace( '_', ' ', $value ) );
	}
}
