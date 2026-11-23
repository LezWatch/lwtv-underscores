<?php
/**
 * Name: Duplicate Check
 * Description: Skips the notification email for contact-form suggestions LWTV already has.
 *
 * Runs once per "new show/actor/character" submission, after the entry is
 * saved and before Gravity Forms sends notifications. Gravity Forms runs
 * gform_entry_post_save, then notifications, then the confirmation, then
 * gform_after_submission, so the verdict made here drives all of them:
 *
 * - ADDED: every named character is already on a published show. No email,
 *   "Already Added" confirmation, entry marked read, nothing queued.
 * - TODO: every named character is on LWTV or waiting on the Content to Add
 *   sheet. No email, "Already to-do" confirmation, entry marked read, nothing
 *   queued.
 * - Anything else, or any error: normal behaviour.
 *
 * This is the one place the triage does slow work during the request (one
 * sheet read and one Gemini call, each with a short timeout). The Gemini
 * answer is saved on the entry so the background triage never asks twice.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\Gravity_Forms\Build\Duplicate_Verdict;

class Duplicate_Check {

	/**
	 * Entry meta holding the verdict ('added' or 'todo'); missing means not a duplicate.
	 */
	const META_VERDICT = 'lwtv_duplicate_verdict';

	/**
	 * Timeout in seconds for each Google call made during the form submission.
	 */
	const SUBMIT_TIMEOUT = 12;

	/**
	 * Verdicts made this request, by entry ID.
	 *
	 * @var array<int,string>
	 */
	private static $verdicts = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'gform_entry_post_save', array( $this, 'check' ), 10, 2 );
		add_filter( 'gform_disable_notification', array( $this, 'disable_notification' ), 10, 4 );
		add_filter( 'gform_confirmation', array( $this, 'confirmation' ), 10, 4 );
	}

	/**
	 * What the submitter sees for a duplicate.
	 *
	 * @param string $verdict Duplicate_Verdict::ADDED or ::TODO.
	 * @return string Plain text ('' for anything else).
	 */
	public static function message( string $verdict ): string {
		switch ( $verdict ) {
			case Duplicate_Verdict::ADDED:
				// This is for when a submission is already live on the site.
				$message = __( 'Thank you for submitting this information. Our robots are pretty sure we actually already added the data to the site. Don\'t worry, we know the site is huge.', 'lwtv-underscores' );
				break;
			case Duplicate_Verdict::TODO:
				// This is for when the submission is on the to-do list.
				$message = __( 'Thank you for submitting this information. We already have this on our to-do list, and our lovely volunteers are furiously working through the backlog.', 'lwtv-underscores' );
				break;
			default:
				return '';
		}

		/**
		 * Filters the confirmation shown for a duplicate suggestion.
		 *
		 * @param string $message Plain text; it is escaped on output.
		 * @param string $verdict 'added' or 'todo'.
		 */
		return (string) apply_filters( 'lwtv_duplicate_check_message', $message, $verdict );
	}

	/**
	 * The entry's duplicate verdict, or '' when it is not a duplicate.
	 *
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function verdict( int $entry_id ): string {
		if ( $entry_id <= 0 ) {
			return Duplicate_Verdict::NONE;
		}
		if ( ! isset( self::$verdicts[ $entry_id ] ) ) {
			self::$verdicts[ $entry_id ] = function_exists( 'gform_get_meta' ) ? (string) gform_get_meta( $entry_id, self::META_VERDICT ) : '';
		}
		return self::$verdicts[ $entry_id ];
	}

	/**
	 * Decide, record and act on the verdict for a freshly saved entry. Never throws.
	 *
	 * @param array $entry Entry.
	 * @param array $form  Form.
	 * @return array The entry, unchanged.
	 */
	public function check( $entry, $form ) {
		unset( $form );

		if ( ! is_array( $entry ) ) {
			return $entry;
		}

		$entry_id = (int) ( $entry['id'] ?? 0 );

		try {
			if ( $entry_id <= 0
				|| ! Submission_Triage::is_triage_entry( $entry )
				|| 'spam' === ( $entry['status'] ?? '' )
				|| Submission_Triage::is_moderated( $entry )
				|| ! Submission_Triage::is_configured() ) {
				return $entry;
			}

			$result = $this->evaluate( $entry );

			self::$verdicts[ $entry_id ] = $result['verdict'];
			if ( Duplicate_Verdict::NONE === $result['verdict'] ) {
				return $entry;
			}

			gform_update_meta( $entry_id, self::META_VERDICT, $result['verdict'] );
			\GFAPI::update_entry_property( $entry_id, 'is_read', 1 );

			$where = Duplicate_Verdict::ADDED === $result['verdict'] ? 'already on LWTV' : 'already on LWTV or the Content to Add sheet';
			Submission_Triage::note( $entry_id, 'Duplicate: every character named is ' . $where . '. Notification email skipped, not added to the sheet, marked read.' . "\n" . implode( "\n", $result['lines'] ) );
			Submission_Triage::debug( 'Duplicate check: entry ' . $entry_id . ' is ' . $result['verdict'] . '.' );
		} catch ( \Throwable $e ) {
			// Any failure means normal behaviour: the email goes out.
			self::$verdicts[ $entry_id ] = Duplicate_Verdict::NONE;
			Submission_Triage::error( 'Duplicate check failed for entry ' . $entry_id . ', handled as normal: ' . $e->getMessage() );
		}

		return $entry;
	}

	/**
	 * Skip form-submission notifications for duplicates.
	 *
	 * @param bool  $is_disabled  Whether the notification is already disabled.
	 * @param array $notification Notification.
	 * @param array $form         Form.
	 * @param array $entry        Entry.
	 * @return bool
	 */
	public function disable_notification( $is_disabled, $notification, $form, $entry ) {
		unset( $form );

		if ( $is_disabled || ! is_array( $entry ) || ! is_array( $notification ) || 'form_submission' !== ( $notification['event'] ?? '' ) ) {
			return $is_disabled;
		}

		return Duplicate_Verdict::NONE !== self::verdict( (int) ( $entry['id'] ?? 0 ) );
	}

	/**
	 * Swap the confirmation for the duplicate message, keeping Gravity Forms' wrapper markup.
	 *
	 * @param string|array $confirmation Message, or a redirect array.
	 * @param array        $form         Form.
	 * @param array        $entry        Entry.
	 * @param bool         $ajax         Whether the form uses AJAX.
	 * @return string|array
	 */
	public function confirmation( $confirmation, $form, $entry, $ajax ) {
		unset( $ajax );

		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return $confirmation;
		}

		$message = self::message( self::verdict( (int) ( $entry['id'] ?? 0 ) ) );
		if ( '' === $message ) {
			return $confirmation;
		}

		$settings = array_merge(
			is_array( $form['confirmation'] ?? null ) ? $form['confirmation'] : array(),
			array(
				'type'              => 'message',
				'message'           => esc_html( $message ),
				'disableAutoformat' => true,
			)
		);

		// GFFormDisplay is what fires this filter, so it is always loaded here.
		return \GFFormDisplay::get_confirmation_message( $settings, $form, $entry );
	}

	/**
	 * Read the sheet, ask Gemini who is named, and check them against LWTV and the sheet.
	 *
	 * @param array $entry Entry.
	 * @return array{verdict:string, lines:string[]}
	 * @throws \RuntimeException When the sheet or Gemini cannot be reached.
	 */
	private function evaluate( array $entry ): array {
		$none = array(
			'verdict' => Duplicate_Verdict::NONE,
			'lines'   => array(),
		);

		$config   = Submission_Triage::config();
		$entry_id = (int) $entry['id'];
		$show     = trim( (string) ( $entry[ (string) $config['field_show'] ] ?? '' ) );
		$info     = (string) ( $entry[ (string) $config['field_info'] ] ?? '' );

		if ( '' === $show || '' === trim( $info ) ) {
			return $none;
		}

		/**
		 * Filters the timeout, in seconds, for each Google call made while the submitter waits.
		 *
		 * @param int $timeout Seconds. Default SUBMIT_TIMEOUT.
		 */
		$timeout = max( 1, (int) apply_filters( 'lwtv_duplicate_check_timeout', self::SUBMIT_TIMEOUT ) );

		$runner = new Triage_Runner(
			new Sheets_Client( Submission_Triage::sheet_id(), defined( 'LWTV_GOOGLE_SERVICE_ACCOUNT' ) ? (string) LWTV_GOOGLE_SERVICE_ACCOUNT : '', $timeout ),
			new Gemini_Client( defined( 'LWTV_GEMINI_API_KEY' ) ? (string) LWTV_GEMINI_API_KEY : '', '', '', $timeout )
		);

		$sheet = $runner->load_sheet();

		// Final attempt: a Gemini failure returns no characters instead of throwing.
		$error      = '';
		$raw        = '';
		$characters = $runner->characters( $show, $info, $sheet['allowed'], true, $error, 0, $raw );

		if ( '' !== $raw ) {
			// The background triage reuses this answer instead of asking Gemini again.
			gform_update_meta( $entry_id, Submission_Triage::META_GEMINI, $raw );
		}
		if ( empty( $characters ) ) {
			if ( '' !== $error ) {
				Submission_Triage::debug( 'Duplicate check: no Gemini answer for entry ' . $entry_id . ' (' . $error . '); handled as normal.' );
			}
			return $none;
		}

		$lookup = new Lwtv_Lookup();
		$titles = $lookup->live_character_titles( $lookup->live_show_ids( $show ) );
		$names  = array_map(
			static function ( array $character ): string {
				return (string) ( $character['name'] ?? '' );
			},
			$characters
		);

		return Duplicate_Verdict::decide( $names, $show, $titles, $sheet['tabs'] );
	}
}
