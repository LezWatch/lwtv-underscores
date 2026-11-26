<?php
/**
 * Name: Search Rules Store
 * Description: The saved search rules, and applying them to the indexes.
 *
 * One option, `lwtv_meili_search_rules` (not autoloaded):
 *
 *     { version: 1,
 *       synonyms: { two_way: [[…]], one_way: [{ from, to: […] }] },
 *       pins: [], redirects: [],            // reserved: kept, never read
 *       applied: { at: <unix>, indexes: { <type>: { status, task } } } }
 *
 * Applying never blocks: each index gets one settings update with a short
 * timeout, and its task uid is stored as "applying". refresh() checks those
 * tasks later (the admin page polls it). Only indexes that exist get an
 * update, because PATCHing the settings of a missing index creates a bare
 * one. Shared by the REST controller and the CLI.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Build\Index_State;
use LWTV\Meilisearch\Build\Search_Rules;

class Search_Rules_Store {

	const OPTION = 'lwtv_meili_search_rules';

	const VERSION = 1;

	/**
	 * Statuses that aren't errors.
	 */
	const APPLYING   = 'applying';
	const OK         = 'ok';
	const NOT_SET_UP = 'not set up';

	private Indexes $indexes;
	private Client $client;

	/**
	 * @param Indexes $indexes Every index.
	 * @param Client  $client  Meilisearch client.
	 */
	public function __construct( Indexes $indexes, Client $client ) {
		$this->indexes = $indexes;
		$this->client  = $client;
	}

	/**
	 * The whole option, with defaults filled in.
	 *
	 * @return array{version: int, synonyms: array{two_way: array, one_way: array}, pins: array, redirects: array, applied: array{at: int, indexes: array}}
	 */
	public function get(): array {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		$synonyms = is_array( $saved['synonyms'] ?? null ) ? $saved['synonyms'] : array();
		$applied  = is_array( $saved['applied'] ?? null ) ? $saved['applied'] : array();

		return array(
			'version'   => self::VERSION,
			'synonyms'  => array(
				'two_way' => is_array( $synonyms['two_way'] ?? null ) ? array_values( $synonyms['two_way'] ) : array(),
				'one_way' => is_array( $synonyms['one_way'] ?? null ) ? array_values( $synonyms['one_way'] ) : array(),
			),
			'pins'      => is_array( $saved['pins'] ?? null ) ? $saved['pins'] : array(),
			'redirects' => is_array( $saved['redirects'] ?? null ) ? $saved['redirects'] : array(),
			'applied'   => array(
				'at'      => (int) ( $applied['at'] ?? 0 ),
				'indexes' => is_array( $applied['indexes'] ?? null ) ? $applied['indexes'] : array(),
			),
		);
	}

	/**
	 * Every index's stop words, from the repo settings files.
	 *
	 * @return string[]
	 */
	public function stop_words(): array {
		$words = array();
		foreach ( $this->indexes->all() as $index ) {
			try {
				$settings = Index_Settings::load( (string) file_get_contents( $index->settings_path() ) );
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}
			foreach ( (array) ( $settings['stopWords'] ?? array() ) as $word ) {
				$words[ (string) $word ] = (string) $word;
			}
		}
		sort( $words, SORT_STRING );

		return $words;
	}

	/**
	 * The saved synonyms in Meilisearch's format.
	 *
	 * @return array<string, string[]>
	 */
	public function synonyms_map(): array {
		return Search_Rules::to_meilisearch( $this->get()['synonyms'] );
	}

	/**
	 * Warnings for the saved list; its rows match the saved rows.
	 *
	 * @return array
	 */
	public function warnings(): array {
		return Search_Rules::normalize( $this->get()['synonyms'], $this->stop_words() )['warnings'];
	}

	/**
	 * Validate and save synonyms, then apply them. Saving doesn't depend on
	 * the search server: an unreachable server still saves the list, and
	 * each index's status says what went wrong.
	 *
	 * @param mixed $synonyms Raw synonyms from the app.
	 * @return array{ok: bool, errors: array, warnings: array, applied: array}
	 */
	public function save( $synonyms ): array {
		$result = Search_Rules::normalize( $synonyms, $this->stop_words() );

		if ( ! empty( $result['errors'] ) ) {
			return array(
				'ok'       => false,
				'errors'   => $result['errors'],
				'warnings' => $result['warnings'],
				'applied'  => $this->get()['applied'],
			);
		}

		$rules             = $this->get();
		$rules['synonyms'] = $result['synonyms'];
		update_option( self::OPTION, $rules, false );

		return array(
			'ok'       => true,
			'errors'   => array(),
			// Re-checked on the saved list, so the rows match what the app reloads.
			'warnings' => $this->warnings(),
			'applied'  => $this->apply(),
		);
	}

	/**
	 * Send the saved synonyms to every index that exists. Never waits.
	 *
	 * @return array{at: int, indexes: array<string, array{status: string, task: int|null}>}
	 */
	public function apply(): array {
		$map     = $this->synonyms_map();
		$results = array();

		foreach ( $this->targets() as $index ) {
			// Check afresh: a cached "exists" for a since-deleted index would
			// let the update create a bare one.
			$index->forget_state();
			$state = $index->state();

			if ( Index_State::MISSING === $state ) {
				$results[ $index->type() ] = self::entry( self::NOT_SET_UP, null );
				continue;
			}
			if ( Index_State::EXISTS !== $state ) {
				$results[ $index->type() ] = self::entry( 'Could not reach the search server to check this index.', null );
				continue;
			}

			// An empty map must go as an object ({}), not a list.
			$response = $this->client->update_settings( $index->uid(), array( 'synonyms' => empty( $map ) ? new \stdClass() : $map ), Client::SHORT_TIMEOUT );

			if ( is_wp_error( $response ) ) {
				$results[ $index->type() ] = self::entry( $response->get_error_message(), null );
			} elseif ( ! isset( $response['taskUid'] ) ) {
				$results[ $index->type() ] = self::entry( 'The search server did not return a task.', null );
			} else {
				$results[ $index->type() ] = self::entry( self::APPLYING, (int) $response['taskUid'] );
			}
		}

		$applied = array(
			'at'      => time(),
			'indexes' => $results,
		);

		$rules            = $this->fresh();
		$rules['applied'] = $applied;
		update_option( self::OPTION, $rules, false );

		return $applied;
	}

	/**
	 * Check the tasks still "applying" and store what they became.
	 *
	 * An entry is only updated when its stored task is still the task that
	 * was polled, so an older save's task can never mark a newer one "ok".
	 *
	 * @return array{at: int, indexes: array}
	 */
	public function refresh(): array {
		$applied = $this->get()['applied'];
		$updates = array();

		foreach ( $applied['indexes'] as $type => $entry ) {
			if ( ! is_array( $entry ) || self::APPLYING !== ( $entry['status'] ?? '' ) || ! is_numeric( $entry['task'] ?? null ) ) {
				continue;
			}

			$task_uid = (int) $entry['task'];
			$task     = $this->client->get_task( $task_uid );
			if ( is_wp_error( $task ) || (int) ( $task['uid'] ?? -1 ) !== $task_uid ) {
				continue;
			}

			$status = (string) ( $task['status'] ?? '' );
			if ( 'succeeded' === $status ) {
				$updates[ $type ] = self::entry( self::OK, $task_uid );
			} elseif ( 'failed' === $status || 'canceled' === $status ) {
				$error            = is_array( $task['error'] ?? null ) ? $task['error'] : array();
				$code             = (string) ( $error['code'] ?? $status );
				$message          = (string) ( $error['message'] ?? '' );
				$updates[ $type ] = self::entry( '' === $message ? $code : $code . ' — ' . $message, $task_uid );
			}
		}

		if ( empty( $updates ) ) {
			return $applied;
		}

		// Re-read: a save may have stored newer tasks while we polled.
		$rules   = $this->fresh();
		$changed = false;
		foreach ( $updates as $type => $entry ) {
			$current = $rules['applied']['indexes'][ $type ] ?? null;
			if ( is_array( $current ) && self::APPLYING === ( $current['status'] ?? '' ) && (int) ( $current['task'] ?? -1 ) === $entry['task'] ) {
				$rules['applied']['indexes'][ $type ] = $entry;
				$changed                              = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION, $rules, false );
		}

		return $rules['applied'];
	}

	/**
	 * The indexes to apply to. The filter is for debugging and live checks.
	 *
	 * @return Index[]
	 */
	public function targets(): array {
		$indexes = apply_filters( 'lwtv_meili_search_rules_indexes', array_values( $this->indexes->all() ) );

		return array_values( array_filter( is_array( $indexes ) ? $indexes : array(), fn( $i ) => $i instanceof Index ) );
	}

	/**
	 * The option as stored now, past this request's cache.
	 *
	 * @return array
	 */
	private function fresh(): array {
		wp_cache_delete( self::OPTION, 'options' );

		return $this->get();
	}

	/**
	 * One index's applied entry.
	 *
	 * @param string   $status Status.
	 * @param int|null $task   Task uid.
	 * @return array{status: string, task: int|null}
	 */
	private static function entry( string $status, ?int $task ): array {
		return array(
			'status' => $status,
			'task'   => $task,
		);
	}
}
