<?php
/**
 * Name: Triage Runner
 * Description: Puts one contact-form entry on the "LWTV: Content to Add" sheet.
 *
 * Reads the sheet once, extracts characters with Gemini, checks each against
 * live LWTV and every sheet tab, then appends the submission to existing To
 * Add rows and adds new rows past the end of the tab, in that order (so a new
 * row can never shift a row we are about to update). Safe to re-run: the To
 * Add "Form Entry" column records what this entry already wrote. One run per
 * entry at a time (an options-table lock).
 *
 * Throws Transient_Error when a retry may succeed and \RuntimeException when
 * it will not; Sheet_Triage_Task decides what to do with either.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are logged and stored as entry notes, never printed raw.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\Gravity_Forms\Build\Extraction;
use LWTV\Plugins\Gravity_Forms\Build\Name_Match;
use LWTV\Plugins\Gravity_Forms\Build\Row_Builder;
use LWTV\Plugins\Gravity_Forms\Build\Sheet_Table;
use LWTV\Plugins\Gravity_Forms\Build\Triage_Plan;

class Triage_Runner {

	/**
	 * The tab rows are written to.
	 */
	const TO_ADD_TAB = 'To Add';

	/**
	 * Ranges read in one call, by internal key. Columns are found by header
	 * text, so the ranges are wide enough not to cap them.
	 */
	const RANGES = array(
		'to_add'          => "'To Add'!A:Z",
		'completed'       => 'Completed!A:Z',
		'questionable'    => 'Questionable!A:Z',
		'legacy_new'      => "'To Add (NEW)'!A:Z",
		'legacy_existing' => "'To Add (existing)'!A:Z",
		'lists'           => 'Lists!A:Z',
	);

	/**
	 * Headers To Add must have.
	 */
	const TO_ADD_LABELS = array( 'Status', 'Queerness', 'Name', 'Actor', 'Show', 'Cliches', 'Sexuality', 'Gender', 'Description', 'Image', 'Form Entry' );

	/**
	 * Headers Completed and Questionable must have for matching.
	 */
	const MATCH_LABELS = array( 'Status', 'Queerness', 'Name', 'Show' );

	/**
	 * Allowed-values field => Lists header.
	 */
	const LIST_LABELS = array(
		'gender'    => 'Char Genders',
		'sexuality' => 'Char Sexuality',
		'cliches'   => 'Character Cliches',
	);

	/**
	 * Note lines for the fallback cases.
	 */
	const NOTE_NO_SHOW     = 'No show title given; added the raw submission for review.';
	const NOTE_NO_INFO     = 'No character info given; added the raw submission for review.';
	const NOTE_GEMINI      = 'Gemini unavailable/unclear; added the raw submission for review.';
	const NOTE_SHOW_EXISTS = 'Show already exists on LWTV.';

	/**
	 * Per-entry run lock: option name prefix, and the age in seconds after which a lock is stale.
	 */
	const LOCK_PREFIX = 'lwtv_triage_lock_';
	const LOCK_TTL    = 300;

	/**
	 * Sheets client.
	 *
	 * @var Sheets_Client|null
	 */
	private $sheets;

	/**
	 * Gemini client.
	 *
	 * @var Gemini_Client|null
	 */
	private $gemini;

	/**
	 * LWTV lookup.
	 *
	 * @var Lwtv_Lookup|null
	 */
	private $lookup;

	/**
	 * Constructor. Clients default to ones built from the wp-config constants.
	 *
	 * @param Sheets_Client|null $sheets Sheets client.
	 * @param Gemini_Client|null $gemini Gemini client.
	 * @param Lwtv_Lookup|null   $lookup LWTV lookup.
	 */
	public function __construct( ?Sheets_Client $sheets = null, ?Gemini_Client $gemini = null, ?Lwtv_Lookup $lookup = null ) {
		$this->sheets = $sheets;
		$this->gemini = $gemini;
		$this->lookup = $lookup;
	}

	/**
	 * Triage one entry onto the sheet.
	 *
	 * @param int  $entry_id      Entry ID.
	 * @param bool $final_attempt True on the last retry: Gemini failures fall back instead of retrying.
	 * @return string[] The note lines written (empty when the entry was already done).
	 * @throws Transient_Error When a retry may succeed (including "another run holds the lock").
	 * @throws \RuntimeException When it will not.
	 */
	public function run( int $entry_id, bool $final_attempt ): array {
		if ( gform_get_meta( $entry_id, Submission_Triage::META_DONE ) ) {
			return array();
		}

		$this->lock( $entry_id );
		try {
			return $this->run_locked( $entry_id, $final_attempt );
		} finally {
			delete_option( self::LOCK_PREFIX . $entry_id );
		}
	}

	/**
	 * The run itself, under the entry's lock.
	 *
	 * @param int  $entry_id      Entry ID.
	 * @param bool $final_attempt True on the last retry.
	 * @return string[]
	 * @throws Transient_Error When a retry may succeed.
	 * @throws \RuntimeException When it will not.
	 */
	private function run_locked( int $entry_id, bool $final_attempt ): array {
		// 1. Entry (done is checked again: another run may have finished just before we took the lock).
		if ( gform_get_meta( $entry_id, Submission_Triage::META_DONE ) ) {
			return array();
		}
		if ( ! Submission_Triage::is_configured() ) {
			throw new \RuntimeException( 'Sheet triage is not configured.' );
		}

		$entry = \GFAPI::get_entry( $entry_id );
		if ( ! is_array( $entry ) ) {
			throw new \RuntimeException( 'Entry not found.' );
		}

		$config = Submission_Triage::config();
		if ( (int) ( $entry['form_id'] ?? 0 ) !== $config['form_id'] ) {
			throw new \RuntimeException( 'Entry is not from the contact form.' );
		}

		$held = (string) gform_get_meta( $entry_id, Submission_Triage::META_HELD );
		if ( '' !== $held ) {
			throw new \RuntimeException( 'Entry is held (' . $held . '); release it first.' );
		}

		$show = trim( (string) ( $entry[ (string) $config['field_show'] ] ?? '' ) );
		$info = (string) ( $entry[ (string) $config['field_info'] ] ?? '' );

		// 2. Sheet.
		$values = $this->sheets()->get_ranges( array_values( self::RANGES ) );
		$tab    = static function ( string $key ) use ( $values ): array {
			$rows = $values[ self::RANGES[ $key ] ] ?? array();
			return is_array( $rows ) ? array_values( $rows ) : array();
		};

		$to_add_map = $this->header_map( 'To Add', $tab( 'to_add' ), self::TO_ADD_LABELS );
		$maps       = array(
			'to_add'          => $to_add_map,
			'completed'       => $this->header_map( 'Completed', $tab( 'completed' ), self::MATCH_LABELS ),
			'questionable'    => $this->header_map( 'Questionable', $tab( 'questionable' ), self::MATCH_LABELS ),
			// Legacy tabs have a blank column A header; column A is always the show.
			'legacy_new'      => array( 'Show' => 0 ) + $this->header_map( 'To Add (NEW)', $tab( 'legacy_new' ), array( 'Info' ) ),
			'legacy_existing' => array( 'Show' => 0 ) + $this->header_map( 'To Add (existing)', $tab( 'legacy_existing' ), array( 'Info' ) ),
		);

		$tabs = array();
		foreach ( $maps as $key => $map ) {
			$tabs[ $key ] = Sheet_Table::rows( $tab( $key ), $map );
		}

		// 3. Allowed values.
		$allowed = $this->allowed_values( $tab( 'lists' ), array_merge( $tabs['to_add'], $tabs['completed'] ) );

		// 4. Characters.
		$characters    = array();
		$fallback_note = '';
		if ( '' === $show ) {
			$fallback_note = self::NOTE_NO_SHOW;
		} elseif ( '' === trim( $info ) ) {
			$fallback_note = self::NOTE_NO_INFO;
		} else {
			$gemini_error = '';
			$characters   = $this->extract( $show, $info, $allowed, $final_attempt, $gemini_error );
			if ( empty( $characters ) ) {
				$fallback_note = '' === $gemini_error ? self::NOTE_GEMINI : 'Gemini error (' . $gemini_error . '); added the raw submission for review.';
			}
		}
		if ( '' !== $fallback_note ) {
			$characters = array( Row_Builder::fallback_character() );
		}

		// 5. Live LWTV.
		$show_ids    = '' === $show ? array() : $this->lookup()->show_ids( $show );
		$lwtv_titles = empty( $show_ids ) ? array() : $this->lookup()->character_titles( $show_ids );

		// 6. Decide every character, then write: updates to existing rows first, new rows last.
		// The values array starts at row 1 (the header) and keeps blank rows, so its length is the last row.
		$last_row = count( $tab( 'to_add' ) );
		$plan     = Triage_Plan::build( $characters, $show, $info, $entry_id, (string) wp_date( 'Y-m-d' ), $lwtv_titles, $tabs, $to_add_map, $last_row );
		$row_map  = array();
		$landed   = array();

		try {
			foreach ( $plan['updates'] as $update ) {
				$this->sheets()->update_cells( self::TO_ADD_TAB, $update['row'], $update['letters'] );
				$landed[] = $update['item'];
			}
			foreach ( $plan['appends'] as $append ) {
				// The planned row is the last row read + 1 + rows already appended this run.
				$row_map[ $append['row'] ] = $this->sheets()->append_row( self::TO_ADD_TAB, $append['values'], $append['row'] );
				$landed[]                  = $append['item'];
			}
		} catch ( \RuntimeException $e ) {
			// Record what already landed, unless a retry will redo (and report) it.
			if ( $landed && ( $final_attempt || ! $e instanceof Transient_Error ) ) {
				Submission_Triage::note( $entry_id, "Partial sheet triage before an error:\n" . implode( "\n", Triage_Plan::note_lines( $plan, $show, $row_map, $landed ) ), 'warning' );
			}
			throw $e;
		}

		$lines = Triage_Plan::note_lines( $plan, $show, $row_map );

		// 7. Fallback context.
		if ( '' !== $fallback_note ) {
			$lines[] = $fallback_note;
			if ( ! empty( $show_ids ) ) {
				$lines[] = self::NOTE_SHOW_EXISTS;
			}
		}

		// 8. Note and finish.
		\GFAPI::add_note( $entry_id, 0, 'LWTV Robot', implode( "\n", $lines ), 'success', Submission_Triage::NOTE_SUB_TYPE );
		gform_update_meta( $entry_id, Submission_Triage::META_DONE, time() );

		Submission_Triage::debug( 'Sheet triage done for entry ' . $entry_id . ': ' . implode( ' | ', $lines ) );

		return $lines;
	}

	/**
	 * Characters Gemini found, keeping only names (and actors) the submission actually mentions.
	 *
	 * @param string $show          Show title.
	 * @param string $info          Submission text.
	 * @param array  $allowed       Allowed controlled values.
	 * @param bool   $final_attempt Whether a transient failure should fall back.
	 * @param string $error         Set to a short reason when a non-transient Gemini error caused the fallback.
	 * @return array<int, array<string,string>> Empty when Gemini failed or found nobody.
	 * @throws Transient_Error When Gemini may work on a retry and this is not the last one.
	 */
	private function extract( string $show, string $info, array $allowed, bool $final_attempt, string &$error ): array {
		try {
			$characters = Extraction::parse( $this->gemini()->extract( $show, $info, $allowed ), $show, $allowed );
		} catch ( Transient_Error $e ) {
			if ( ! $final_attempt ) {
				throw $e;
			}
			Submission_Triage::debug( 'Gemini failed on the final attempt, using the fallback row: ' . $e->getMessage() );
			return array();
		} catch ( \RuntimeException $e ) {
			// Includes \UnexpectedValueException from parse().
			Submission_Triage::debug( 'Gemini response unusable, using the fallback row: ' . $e->getMessage() );
			$error = $this->short_error( $e->getMessage() );
			return array();
		}

		$kept = array();
		foreach ( $characters as $character ) {
			// Never infer a name: drop anyone the text does not mention.
			$name  = trim( (string) ( $character['name'] ?? '' ) );
			$first = (string) ( preg_split( '/\s+/u', $name )[0] ?? '' );
			if ( ! Name_Match::text_mentions( $info, $name ) && ! Name_Match::text_mentions( $info, $first ) ) {
				continue;
			}

			// Never infer an actor either: keep one only when the text names them.
			$actor = (string) ( $character['actor'] ?? '' );
			if ( '' !== $actor && ( Name_Match::is_placeholder( $actor ) || ! Name_Match::text_mentions( $info, $actor ) ) ) {
				$character['actor'] = '';
			}

			$kept[] = $character;
		}

		return $kept;
	}

	/**
	 * A short, single-line Gemini error for the entry note.
	 *
	 * Gemini_Client never puts the API key in a message; it is stripped here
	 * as well, in case an upstream error ever echoes it.
	 *
	 * @param string $message Exception message.
	 * @return string
	 */
	private function short_error( string $message ): string {
		$message = (string) preg_replace( '/^Gemini (returned )?/', '', trim( $message ) );
		$message = (string) preg_replace( '/\s+/u', ' ', $message );

		$key = defined( 'LWTV_GEMINI_API_KEY' ) ? (string) LWTV_GEMINI_API_KEY : '';
		if ( '' !== $key ) {
			$message = str_replace( $key, '[redacted]', $message );
		}

		$message = rtrim( $message, '.' );
		return mb_strlen( $message ) > 160 ? mb_substr( $message, 0, 157 ) . '...' : $message;
	}

	/**
	 * Allowed controlled values from the Lists tab and existing Queerness values.
	 *
	 * A missing Lists column leaves that field's list empty, so the field is
	 * always written blank rather than guessed.
	 *
	 * @param array $lists_values Lists tab values.
	 * @param array $queer_rows   To Add + Completed rows.
	 * @return array{sexuality:string[], gender:string[], queerness:string[], cliches:string[]}
	 */
	private function allowed_values( array $lists_values, array $queer_rows ): array {
		$allowed = array(
			'sexuality' => array(),
			'gender'    => array(),
			'queerness' => Sheet_Table::column_values( $queer_rows, 'Queerness' ),
			'cliches'   => array(),
		);

		$header = isset( $lists_values[0] ) && is_array( $lists_values[0] ) ? $lists_values[0] : array();
		foreach ( self::LIST_LABELS as $field => $label ) {
			try {
				$map = Sheet_Table::header_map( $header, array( $label ) );
			} catch ( \UnexpectedValueException $e ) {
				Submission_Triage::debug( 'Lists tab: ' . $e->getMessage() . '; ' . $field . ' will be left blank.' );
				continue;
			}
			$allowed[ $field ] = Sheet_Table::column_values( Sheet_Table::rows( $lists_values, $map ), $label );
		}

		return $allowed;
	}

	/**
	 * Header map for one tab, naming the tab when a column is missing.
	 *
	 * @param string   $name     Tab name for the message.
	 * @param array    $values   Tab values.
	 * @param string[] $required Required labels.
	 * @return array<string,int>
	 * @throws \RuntimeException When a required header is missing.
	 */
	private function header_map( string $name, array $values, array $required ): array {
		$header = isset( $values[0] ) && is_array( $values[0] ) ? $values[0] : array();
		try {
			return Sheet_Table::header_map( $header, $required );
		} catch ( \UnexpectedValueException $e ) {
			throw new \RuntimeException( $name . ' tab: ' . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Take the entry's run lock, overriding a stale one.
	 *
	 * INSERT IGNORE on the options table is atomic, unlike add_option(), which
	 * reads first and then upserts. A stale lock is removed only if it still
	 * holds the timestamp we read, so two runs cannot both take it over.
	 *
	 * @param int $entry_id Entry ID.
	 * @return void
	 * @throws Transient_Error When another run holds the lock.
	 */
	private function lock( int $entry_id ): void {
		global $wpdb;

		$name = self::LOCK_PREFIX . $entry_id;
		$now  = time();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic lock has to bypass the options cache.
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", $name, (string) $now ) );
		if ( 1 !== $taken ) {
			$held_since = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( '' !== $held_since && $now - (int) $held_since > self::LOCK_TTL ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $held_since ) );
				$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", $name, (string) $now ) );
			}
		}
		// phpcs:enable

		if ( 1 !== $taken ) {
			throw new Transient_Error( 'Another run for this entry is in progress.' );
		}
	}

	/**
	 * Sheets client.
	 *
	 * @return Sheets_Client
	 */
	private function sheets(): Sheets_Client {
		if ( null === $this->sheets ) {
			$this->sheets = new Sheets_Client( Submission_Triage::sheet_id(), defined( 'LWTV_GOOGLE_SERVICE_ACCOUNT' ) ? (string) LWTV_GOOGLE_SERVICE_ACCOUNT : '' );
		}
		return $this->sheets;
	}

	/**
	 * Gemini client.
	 *
	 * @return Gemini_Client
	 */
	private function gemini(): Gemini_Client {
		if ( null === $this->gemini ) {
			$this->gemini = new Gemini_Client( defined( 'LWTV_GEMINI_API_KEY' ) ? (string) LWTV_GEMINI_API_KEY : '' );
		}
		return $this->gemini;
	}

	/**
	 * LWTV lookup.
	 *
	 * @return Lwtv_Lookup
	 */
	private function lookup(): Lwtv_Lookup {
		if ( null === $this->lookup ) {
			$this->lookup = new Lwtv_Lookup();
		}
		return $this->lookup;
	}
}
