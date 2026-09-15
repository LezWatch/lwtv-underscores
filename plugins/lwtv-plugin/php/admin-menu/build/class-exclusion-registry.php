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
	 * @var array<string, array<string, mixed>>
	 */
	private const CHECKS = array(
		'queer_checker'   => array(
			'name'    => 'Queer Checker',
			'desc'    => 'Actors whose queerness has been set by hand.',
			'cpt'     => self::CPT_ACTORS,
			'meta'    => 'lezactors_queer_override',
			'match'   => self::MATCH_ANY,
			'column'  => 'Actor',
			'empty'   => 'No actors have their queerness overridden at this time.',
			'context' => array(
				// The stored flag the admin column, the ACF relationship labels,
				// both REST endpoints and the statistics all read. It is written
				// on save, so it lags an override until the actor is recalculated.
				'stored_queer' => 'lezactors_queer',
			),
		),
		'dead_checker'    => array(
			'name'    => 'Dead Checker',
			'desc'    => 'Shows with the death-score deduction overridden.',
			'cpt'     => self::CPT_SHOWS,
			'meta'    => 'lezshows_byq_override',
			// 'on', not '1'. See the note in the class docblock.
			'match'   => 'on',
			'column'  => 'Show',
			'empty'   => 'No shows have their scores for death overridden at this time.',
			'context' => array(),
		),
		'wikidata_ignore' => array(
			'name'    => 'WikiData Ignored',
			'desc'    => 'Actors whose WikiData match an editor has overridden or ruled out.',
			'cpt'     => self::CPT_ACTORS,
			'meta'    => 'lezactors_wikidata_ignore',
			'match'   => '1',
			'column'  => 'Actor',
			'empty'   => 'No actors have their WikiData match overridden at this time.',
			'context' => array(
				'manual_qid' => 'lezactors_wikidata_qid_manual',
				'stored_qid' => 'lezactors_wikidata_qid',
			),
		),
		'tvmaze_ignore'   => array(
			'name'    => 'TVMaze Ignored',
			'desc'    => 'Shows whose TVMaze match an editor has overridden or ruled out.',
			'cpt'     => self::CPT_SHOWS,
			'meta'    => 'lezshows_tvmaze_ignore',
			'match'   => '1',
			'column'  => 'Show',
			'empty'   => 'No shows have their TVMaze match overridden at this time.',
			'context' => array(
				'manual_id' => 'lezshows_tvmaze_id_manual',
				'stored_id' => 'lezshows_tvmaze_id',
			),
		),
		'no_known_chars'  => array(
			'name'    => 'No Known Characters',
			'desc'    => 'Shows an editor has flagged as having no characters to list.',
			'cpt'     => self::CPT_SHOWS,
			'meta'    => 'lezshows_no_chars',
			'match'   => '1',
			'column'  => 'Show',
			'empty'   => 'No shows are flagged as having no known characters at this time.',
			'context' => array(
				'char_count' => 'lezshows_char_count',
			),
		),
	);

	/**
	 * Every check, keyed by its tab slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return self::CHECKS;
	}

	/**
	 * The tab slugs, in display order.
	 *
	 * @return array<int, string>
	 */
	public static function keys(): array {
		return array_keys( self::CHECKS );
	}

	/**
	 * Is this a check we know about?
	 *
	 * @param  string $key Tab slug.
	 * @return bool
	 */
	public static function exists( string $key ): bool {
		return isset( self::CHECKS[ $key ] );
	}

	/**
	 * One check definition.
	 *
	 * @param  string $key Tab slug.
	 * @return array<string, mixed> Empty when the key is unknown.
	 */
	public static function get( string $key ): array {
		return self::CHECKS[ $key ] ?? array();
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
				$manual = trim( (string) ( $context['manual_qid'] ?? '' ) );

				return ( '' !== $manual )
					? 'Using ' . $manual
					: 'No WikiData item';

			case 'tvmaze_ignore':
				$manual = trim( (string) ( $context['manual_id'] ?? '' ) );

				return ( '' !== $manual )
					? 'Using ' . $manual
					: 'No TVMaze match';

			case 'no_known_chars':
			case 'dead_checker':
				return 'Yes';
		}

		return ( '' === $value ) ? 'Yes' : ucfirst( $value );
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
				// only rewritten when the actor is saved. So between setting an
				// override and the next recalculation, the actor page says one
				// thing while the admin column, the ACF labels and the REST
				// endpoints say the other. That window is invisible everywhere
				// else, which is exactly why it belongs on this page.
				$override = trim( (string) ( $context['value'] ?? '' ) );
				$stored   = trim( (string) ( $context['stored_queer'] ?? '' ) );

				// Absent counts as not queer, matching how every PHP consumer
				// tests it.
				$stored_says_queer = ! in_array( $stored, array( '', '0' ), true );

				if ( 'is_queer' === $override && ! $stored_says_queer ) {
					return 'Stored as not queer -- run: wp lwtv calc actors';
				}

				if ( 'not_queer' === $override && $stored_says_queer ) {
					return 'Stored as queer -- run: wp lwtv calc actors';
				}

				return '';

			case 'no_known_chars':
				$count = (int) ( $context['char_count'] ?? 0 );

				if ( $count > 0 ) {
					return $count . ' character(s) are now listed -- untick this?';
				}

				return '';

			case 'wikidata_ignore':
				$manual = trim( (string) ( $context['manual_qid'] ?? '' ) );
				$stored = trim( (string) ( $context['stored_qid'] ?? '' ) );

				// Ignored, nothing put in its place, but a machine-resolved Q-ID
				// is still sitting in the field.
				if ( '' === $manual && '' !== $stored ) {
					return 'Still holds ' . $stored . ', which is now unused.';
				}

				return '';

			case 'tvmaze_ignore':
				$manual = trim( (string) ( $context['manual_id'] ?? '' ) );
				$stored = trim( (string) ( $context['stored_id'] ?? '' ) );

				if ( '' === $manual && '' !== $stored ) {
					return 'Still holds ' . $stored . ', which is now unused.';
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
				return 'Is Queer';
			case 'not_queer':
				return 'Is NOT Queer';
		}

		return ( '' === $value ) ? '' : ucfirst( str_replace( '_', ' ', $value ) );
	}
}
