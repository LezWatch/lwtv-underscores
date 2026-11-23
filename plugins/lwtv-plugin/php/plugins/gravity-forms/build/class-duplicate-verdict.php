<?php
/**
 * Name: Duplicate Verdict
 * Description: Pure submit-time duplicate check for contact-form suggestions.
 *
 * Decides whether every character a submitter names is already live on LWTV
 * (ADDED) or already waiting on the Content to Add sheet (TODO). Anything
 * else, including a single unknown character, is NONE and the submission is
 * handled as normal. No WordPress calls, so it is unit-testable.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Duplicate_Verdict {

	/**
	 * Verdicts.
	 */
	const NONE  = '';
	const ADDED = 'added';
	const TODO  = 'todo';

	/**
	 * Triage_Decision reasons that mean "already queued on the sheet".
	 *
	 * Completed and Questionable rows are not here: those were already
	 * reviewed, so a fresh mention of them is worth a human look.
	 */
	const QUEUED_REASONS = array( 'to_add', 'ambiguous', 'legacy_new', 'legacy_existing' );

	/**
	 * IDs of shows where any stored name (title or alternate title) matches.
	 *
	 * @param string                    $title       Show title from the submission.
	 * @param array<int, array<string>> $names_by_id Show ID => every name it goes by.
	 * @return int[]
	 */
	public static function matching_show_ids( string $title, array $names_by_id ): array {
		$ids = array();

		foreach ( $names_by_id as $id => $names ) {
			foreach ( (array) $names as $name ) {
				if ( Name_Match::titles_match( $title, (string) $name ) ) {
					$ids[] = (int) $id;
					break;
				}
			}
		}

		return $ids;
	}

	/**
	 * Is every named character already handled?
	 *
	 * @param string[] $names       Character names the submission mentions.
	 * @param string   $show        Show title from the submission.
	 * @param string[] $lwtv_titles Titles of characters on matching published shows.
	 * @param array    $tabs        Sheet_Table::rows() results keyed as for Triage_Decision.
	 * @return array{verdict:string, lines:string[]} Lines are empty for NONE.
	 */
	public static function decide( array $names, string $show, array $lwtv_titles, array $tabs ): array {
		$none = array(
			'verdict' => self::NONE,
			'lines'   => array(),
		);

		if ( empty( $names ) ) {
			return $none;
		}

		$all_live = true;
		$lines    = array();

		foreach ( $names as $name ) {
			$name = (string) $name;
			if ( Name_Match::is_placeholder( $name ) ) {
				return $none;
			}

			// Entry ID 0: a brand-new entry has never written to the sheet.
			$decision = Triage_Decision::decide( $name, $show, 0, $lwtv_titles, $tabs );
			$reason   = (string) ( $decision['reason'] ?? '' );
			$label    = $name . ' (' . $show . '): ';

			if ( 'lwtv' === $reason ) {
				$lines[] = $label . 'already on LWTV.';
				continue;
			}

			if ( ! in_array( $reason, self::QUEUED_REASONS, true ) ) {
				return $none;
			}

			$all_live = false;
			$lines[]  = $label . self::queued_line( $reason, $decision );
		}

		return array(
			'verdict' => $all_live ? self::ADDED : self::TODO,
			'lines'   => $lines,
		);
	}

	/**
	 * Where on the sheet a queued character was found.
	 *
	 * @param string $reason   Triage_Decision reason.
	 * @param array  $decision Triage_Decision result.
	 * @return string
	 */
	private static function queued_line( string $reason, array $decision ): string {
		switch ( $reason ) {
			case 'to_add':
				return 'already on To Add row ' . (int) ( $decision['row'] ?? 0 ) . '.';
			case 'ambiguous':
				return 'already on To Add rows ' . implode( ', ', array_map( 'intval', (array) ( $decision['rows'] ?? array() ) ) ) . '.';
			case 'legacy_new':
				return 'mentioned on the legacy New tab, row ' . (int) ( $decision['row'] ?? 0 ) . '.';
			default:
				return 'mentioned on the legacy Existing tab, row ' . (int) ( $decision['row'] ?? 0 ) . '.';
		}
	}
}
