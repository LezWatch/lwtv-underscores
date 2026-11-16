<?php
/**
 * WP CLI Commands for stored credentials.
 *
 * The only way to write the site's credentials. Values are read from stdin, a
 * file, or a hidden prompt, so they never land in shell history, and no
 * subcommand ever prints one. See docs/operations/secrets.md.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\_Helpers\{ Secret_Registry, Secrets, Uncached_Option };
use LWTV\Plugins\Gravity_Forms\{ Gemini_Client, Sheets_Client, Submission_Triage };

/**
 * Manage LezWatch.TV's API keys and tokens in the WordPress Secrets API.
 *
 * Names: postiz-api-key, tmdb-api-key, gemini-api-key, google-service-account, tvmaze-ical-url.
 */
class WP_CLI_LWTV_Secrets {

	/**
	 * Per-probe HTTP timeout, in seconds.
	 */
	const VERIFY_TIMEOUT = 15;

	/**
	 * Show where each credential is read from. Never shows a value.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp lwtv secrets status
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Flags.
	 */
	public function status( $args, $assoc_args ) {
		$meta = $this->store_metadata();
		$rows = array();

		foreach ( Secret_Registry::names() as $name ) {
			$fallback = Secret_Registry::fallback( $name );
			$stored   = Secrets::from_store( $name );
			$info     = $meta[ Secret_Registry::full_name( $name ) ] ?? null;

			$rows[] = array(
				'name'           => $name,
				'in use'         => Secrets::source( $name ),
				'stored'         => $this->state_label( $stored['state'] ),
				'needs rotation' => $info ? ( $info['needs_rotation'] ? 'yes' : 'no' ) : '-',
				'previous'       => $info ? ( $info['has_previous'] ? 'yes' : 'no' ) : '-',
				'fallback'       => $fallback['type'] . ' ' . $fallback['name'] . ( '' !== Secrets::fallback_value( $name ) ? ' (set)' : ' (empty)' ),
			);
		}

		\WP_CLI\Utils\format_items( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, array_keys( $rows[0] ) );

		if ( ! Secrets::api_available() ) {
			\WP_CLI::warning( 'The Secrets API is not available (WordPress 7.2+). Everything is read from the fallbacks.' );
		}
	}

	/**
	 * Store or replace a credential.
	 *
	 * Reads the value from --from-file, from stdin when it is piped, or from a
	 * hidden prompt. Replacing a value keeps the old one as the previous version
	 * until `retire` is run, so roll back is possible while the new one is tested.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : One of: postiz-api-key, tmdb-api-key, gemini-api-key, google-service-account, tvmaze-ical-url.
	 *
	 * [--from-file=<path>]
	 * : Read the value from this file. For google-service-account, the downloaded JSON key file.
	 *
	 * ## EXAMPLES
	 *
	 *     # Prompt for the value without echoing it
	 *     $ wp lwtv secrets set tmdb-api-key
	 *
	 *     # Store the Google service-account key itself, then delete the file
	 *     $ wp lwtv secrets set google-service-account --from-file=/path/to/key.json
	 *
	 *     # Pipe it from a password manager
	 *     $ op read op://LWTV/Postiz/credential | wp lwtv secrets set postiz-api-key
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Flags.
	 */
	public function set( $args, $assoc_args ) {
		$name = $this->name_arg( $args );
		$file = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'from-file', '' );

		if ( '' !== $file ) {
			if ( ! is_readable( $file ) ) {
				\WP_CLI::error( 'Cannot read ' . $file . '.' );
			}
			$value = (string) file_get_contents( $file );
		} elseif ( function_exists( 'posix_isatty' ) && posix_isatty( STDIN ) ) {
			$value = (string) \cli\prompt( 'Value for ' . $name . ' (hidden)', false, ': ', true );
		} else {
			$value = (string) stream_get_contents( STDIN );
		}

		$result = Secrets::set( $name, $value );
		$value  = '';

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		$check = Secrets::from_store( $name );
		if ( Secret_Registry::STATE_FOUND !== $check['state'] ) {
			\WP_CLI::error( 'Stored, but reading it back failed. Run `wp lwtv secrets status`.' );
		}

		\WP_CLI::success( 'Stored ' . Secret_Registry::full_name( $name ) . '.' );

		// Core skips the write when the value is unchanged, so ask the store rather than assume.
		$info = $this->store_metadata()[ Secret_Registry::full_name( $name ) ] ?? array();
		if ( ! empty( $info['has_previous'] ) ) {
			\WP_CLI::log( 'The old value is kept as the previous version. Once the new one works (`wp lwtv secrets verify ' . $name . '`), run `wp lwtv secrets retire ' . $name . '`.' );
		}

		$this->remind_about_fallback( $name );
	}

	/**
	 * Copy every credential from its old home into the Secrets API.
	 *
	 * For each credential the store doesn't have yet, reads the old constant,
	 * key file, or option, stores it, and reads it back to compare. The Postiz
	 * key is imported with wp_import_option_as_secret(), which flags it
	 * needs_rotation: it sat in plain options and every backup of them.
	 *
	 * Then deletes the plaintext option copies of any credential the store now
	 * holds (the Postiz key's ACF rows and its copy in the old CMB2 array),
	 * unless --keep-legacy is given. wp-config constants and key files can't be
	 * removed from here; it lists them for you. Safe to run again.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Say what would happen, change nothing.
	 *
	 * [--keep-legacy]
	 * : Import, but leave the old plaintext option rows in place.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp lwtv secrets import --dry-run
	 *     $ wp lwtv secrets import
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Flags.
	 */
	public function import( $args, $assoc_args ) {
		$dry_run     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$keep_legacy = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'keep-legacy', false );
		$failed      = 0;

		if ( ! Secrets::api_available() ) {
			\WP_CLI::error( 'The Secrets API is not available. It needs WordPress 7.2 or later.' );
		}

		foreach ( Secret_Registry::names() as $name ) {
			$full     = Secret_Registry::full_name( $name );
			$fallback = Secret_Registry::fallback( $name );
			$stored   = Secrets::from_store( $name );

			if ( Secret_Registry::STATE_ERROR === $stored['state'] ) {
				\WP_CLI::warning( $full . ': stored but unreadable (' . $stored['error']?->get_error_code() . '). Skipped. Fix WP_SECRETS_KEY or `set` it again.' );
				++$failed;
				continue;
			}

			// An empty stored value counts as missing, as in resolve(): never clean up legacy copies on the strength of one.
			if ( Secret_Registry::STATE_FOUND === $stored['state'] && '' !== $stored['value'] ) {
				\WP_CLI::log( $full . ': already stored.' );
			} else {
				$value   = Secrets::fallback_value( $name );
				$checked = Secret_Registry::validate( $name, $value );

				if ( '' === $value ) {
					\WP_CLI::log( $full . ': nothing to import (' . $fallback['type'] . ' ' . $fallback['name'] . ' is empty).' );
					continue;
				}
				if ( '' !== $checked['error'] ) {
					\WP_CLI::warning( $full . ': the ' . $fallback['type'] . ' value is not valid, so it was not imported. ' . $checked['error'] );
					++$failed;
					continue;
				}

				if ( $dry_run ) {
					\WP_CLI::log( $full . ': would import from ' . $fallback['type'] . ' ' . $fallback['name'] . '.' );
				} else {
					// An option import goes through core so the secret is flagged needs_rotation.
					// Core copies the raw row, so only use it when the raw row is already clean.
					$raw    = ( 'option' === $fallback['type'] ) ? get_option( $fallback['name'], null ) : null;
					$result = ( is_string( $raw ) && $checked['value'] === $raw )
						? wp_import_option_as_secret( $fallback['name'], $full )
						: Secrets::set( $name, $checked['value'] );
					Secrets::flush( $name );

					$readback = Secrets::from_store( $name );
					if ( is_wp_error( $result ) || Secret_Registry::STATE_FOUND !== $readback['state'] || ! hash_equals( $checked['value'], $readback['value'] ) ) {
						\WP_CLI::warning( $full . ': import failed' . ( is_wp_error( $result ) ? ' (' . $result->get_error_message() . ')' : ' (read-back did not match)' ) . '. The old value is untouched.' );
						++$failed;
						continue;
					}

					\WP_CLI::log( $full . ': imported from ' . $fallback['type'] . ' ' . $fallback['name'] . '.' );
				}
				$value   = '';
				$checked = array();
			}

			$this->clean_legacy( $name, $dry_run || $keep_legacy, $dry_run );
		}

		if ( $failed > 0 ) {
			\WP_CLI::error( $failed . ' credential(s) were not imported. See the warnings above.' );
		}

		\WP_CLI::success( $dry_run ? 'Dry run complete. Nothing was changed.' : 'Import complete. Next: `wp lwtv secrets verify`, then remove the wp-config fallbacks listed above.' );
	}

	/**
	 * Make one authenticated, read-only call per integration.
	 *
	 * Uses whatever Secrets::get() resolves, so it proves the site's own read
	 * path, fallback included. Prints HTTP status codes, never values or URLs.
	 *
	 * ## OPTIONS
	 *
	 * [<name>...]
	 * : Only these. Default: all.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp lwtv secrets verify
	 *     $ wp lwtv secrets verify tmdb-api-key
	 *
	 * @param array<int,string> $args Positional arguments.
	 */
	public function verify( $args ) {
		$names = empty( $args ) ? Secret_Registry::names() : array_map( array( $this, 'checked_name' ), $args );
		$rows  = array();
		$bad   = 0;

		foreach ( $names as $name ) {
			$value = Secrets::get( $name );

			if ( '' === $value ) {
				$result = array( 'skipped', 'not configured' );
			} else {
				$result    = $this->probe( $name, $value );
				$result[1] = Secret_Registry::redact( $result[1], array( $value, rawurlencode( $value ) ) );
			}
			$value = '';

			if ( 'FAIL' === $result[0] ) {
				++$bad;
			}

			$rows[] = array(
				'name'   => $name,
				'source' => Secrets::source( $name ),
				'result' => $result[0],
				'detail' => $result[1],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'name', 'source', 'result', 'detail' ) );

		if ( $bad > 0 ) {
			\WP_CLI::error( $bad . ' integration(s) did not authenticate.' );
		}
		\WP_CLI::success( 'Every configured integration authenticated.' );
	}

	/**
	 * Delete a credential from the store.
	 *
	 * Whatever fallback is still configured takes over. Rotate the credential at
	 * the provider as well if it leaked: deleting it here doesn't revoke it.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The credential.
	 *
	 * [--yes]
	 * : Don't ask.
	 *
	 * @param array<int,string>   $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Flags.
	 */
	public function delete( $args, $assoc_args ) {
		$name = $this->name_arg( $args );
		$this->require_api();

		\WP_CLI::confirm( 'Delete ' . Secret_Registry::full_name( $name ) . ' (both versions)?', $assoc_args );

		$result = wp_delete_secret( Secret_Registry::full_name( $name ) );
		Secrets::flush( $name );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success( 'Deleted. Now in use: ' . Secrets::source( $name ) . '.' );
	}

	/**
	 * Drop a credential's previous version after a replacement works.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The credential.
	 *
	 * @param array<int,string> $args Positional arguments.
	 */
	public function retire( $args ) {
		$name = $this->name_arg( $args );
		$this->require_api();

		$result = wp_retire_secret_version( Secret_Registry::full_name( $name ) );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success( 'Previous version of ' . Secret_Registry::full_name( $name ) . ' retired.' );
	}

	/**
	 * One read-only authenticated request.
	 *
	 * @param string $name  Short name.
	 * @param string $value The credential.
	 * @return array{0: string, 1: string} OK|FAIL, and a value-free detail.
	 */
	private function probe( string $name, string $value ): array {
		try {
			switch ( $name ) {
				case 'tmdb-api-key':
					return $this->http_result( wp_remote_get( 'https://api.themoviedb.org/3/configuration?api_key=' . rawurlencode( $value ), array( 'timeout' => self::VERIFY_TIMEOUT ) ) );

				case 'gemini-api-key':
					$base = ( defined( 'LWTV_GEMINI_API_BASE' ) && is_string( LWTV_GEMINI_API_BASE ) && '' !== LWTV_GEMINI_API_BASE ) ? LWTV_GEMINI_API_BASE : Gemini_Client::API_BASE;
					return $this->http_result(
						wp_remote_get(
							untrailingslashit( $base ) . '?pageSize=1',
							array(
								'timeout' => self::VERIFY_TIMEOUT,
								'headers' => array( 'x-goog-api-key' => $value ),
							)
						)
					);

				case 'postiz-api-key':
					$url = function_exists( 'get_field' ) ? (string) get_field( 'lwtv_postiz_api_url', 'option' ) : (string) get_option( 'options_lwtv_postiz_api_url', '' );
					if ( '' === $url ) {
						return array( 'skipped', 'no Postiz API URL set' );
					}
					return $this->http_result(
						wp_remote_get(
							trailingslashit( $url ) . 'integrations',
							array(
								'timeout' => self::VERIFY_TIMEOUT,
								'headers' => array( 'Authorization' => $value ),
							)
						)
					);

				case 'tvmaze-ical-url':
					$response = wp_remote_get( $value, array( 'timeout' => self::VERIFY_TIMEOUT ) );
					$result   = $this->http_result( $response );
					if ( 'OK' === $result[0] && ! str_contains( (string) wp_remote_retrieve_body( $response ), 'BEGIN:VCALENDAR' ) ) {
						return array( 'FAIL', 'HTTP 200 but not an iCal feed' );
					}
					return $result;

				case 'google-service-account':
					if ( '' === Submission_Triage::sheet_id() ) {
						return array( 'skipped', 'no LWTV_CONTENT_SHEET_ID to read' );
					}
					$client = new Sheets_Client( Submission_Triage::sheet_id(), $value );
					$client->forget_token(); // Mint a fresh token from the key, not a cached one.
					return array( 'OK', 'token minted, ' . count( $client->tab_ids() ) . ' tabs readable' );
			}
		} catch ( \Throwable $e ) {
			return array( 'FAIL', $e->getMessage() );
		}

		return array( 'skipped', 'no probe for this name' );
	}

	/**
	 * Summarise a response: status code only.
	 *
	 * @param array|\WP_Error $response wp_remote_* response.
	 * @return array{0: string, 1: string}
	 */
	private function http_result( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array( 'FAIL', $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return array( ( $code >= 200 && $code < 300 ) ? 'OK' : 'FAIL', 'HTTP ' . $code );
	}

	/**
	 * Delete the plaintext option copies of a credential the store now holds.
	 *
	 * @param string $name    Short name.
	 * @param bool   $skip    Report only.
	 * @param bool   $dry_run Wording for a dry run.
	 * @return void
	 */
	private function clean_legacy( string $name, bool $skip, bool $dry_run ): void {
		$found = array();

		foreach ( Secret_Registry::legacy_options( $name ) as $option ) {
			if ( null !== Uncached_Option::get( $option, null ) ) {
				$found[] = $option;
			}
		}

		$array = Secret_Registry::legacy_array( $name );
		$held  = $array ? Uncached_Option::get( $array['option'], null ) : null;
		if ( $array && is_array( $held ) && array_key_exists( $array['key'], $held ) ) {
			$found[] = $array['option'] . '[' . $array['key'] . ']';
		}

		if ( empty( $found ) ) {
			$this->remind_about_fallback( $name );
			return;
		}

		if ( $skip ) {
			\WP_CLI::log( '  ' . ( $dry_run ? 'Would delete' : 'Kept (--keep-legacy)' ) . ': ' . implode( ', ', $found ) );
			return;
		}

		foreach ( Secret_Registry::legacy_options( $name ) as $option ) {
			Uncached_Option::forget( $option );
			delete_option( $option );
		}
		if ( $array && is_array( $held ) && array_key_exists( $array['key'], $held ) ) {
			unset( $held[ $array['key'] ] );
			Uncached_Option::forget( $array['option'] );
			update_option( $array['option'], $held );
		}

		\WP_CLI::log( '  Deleted plaintext copies: ' . implode( ', ', $found ) );

		if ( 'postiz-api-key' === $name ) {
			\WP_CLI::log( '  This key sat in plain options and backups: rotate it at Postiz, then `wp lwtv secrets set postiz-api-key`.' );
		}
	}

	/**
	 * Point out a wp-config fallback that can now go.
	 *
	 * @param string $name Short name.
	 * @return void
	 */
	private function remind_about_fallback( string $name ): void {
		$fallback = Secret_Registry::fallback( $name );

		if ( 'option' === $fallback['type'] || '' === Secrets::fallback_value( $name ) ) {
			return;
		}

		$line = '  Once the secret is stored and `wp lwtv secrets verify` passes, remove the ' . $fallback['name'] . ' constant from wp-config.php.';
		if ( 'file' === $fallback['type'] ) {
			$line .= ' Then delete the key file it points to.';
		}
		\WP_CLI::log( $line );
	}

	/**
	 * Name metadata from the store, keyed by full name.
	 *
	 * @return array<string, array>
	 */
	private function store_metadata(): array {
		if ( ! Secrets::api_available() || ! function_exists( 'wp_list_secrets' ) ) {
			return array();
		}

		$list = wp_list_secrets( rtrim( Secret_Registry::PREFIX, '/' ) );
		if ( is_wp_error( $list ) ) {
			\WP_CLI::warning( 'Could not list secrets: ' . $list->get_error_message() );
			return array();
		}

		$by_name = array();
		foreach ( $list as $row ) {
			$by_name[ $row['name'] ] = $row;
		}
		return $by_name;
	}

	/**
	 * Words for a lookup state.
	 *
	 * @param string $state STATE_* constant.
	 * @return string
	 */
	private function state_label( string $state ): string {
		return match ( $state ) {
			Secret_Registry::STATE_FOUND       => 'yes',
			Secret_Registry::STATE_MISSING     => 'no',
			Secret_Registry::STATE_ERROR       => 'UNREADABLE',
			default                            => 'n/a (no API)',
		};
	}

	/**
	 * The first positional argument, checked against the registry.
	 *
	 * @param array<int,string> $args Positional arguments.
	 * @return string
	 */
	private function name_arg( array $args ): string {
		return $this->checked_name( (string) ( $args[0] ?? '' ) );
	}

	/**
	 * Refuse names the registry doesn't know.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	private function checked_name( string $name ): string {
		if ( ! Secret_Registry::is_known( $name ) ) {
			\WP_CLI::error( 'Unknown name "' . $name . '". Known: ' . implode( ', ', Secret_Registry::names() ) . '.' );
		}
		return $name;
	}

	/**
	 * Stop when the Secrets API isn't loaded.
	 *
	 * @return void
	 */
	private function require_api(): void {
		if ( ! Secrets::api_available() ) {
			\WP_CLI::error( 'The Secrets API is not available. It needs WordPress 7.2 or later.' );
		}
	}
}

\WP_CLI::add_command( 'lwtv secrets', 'WP_CLI_LWTV_Secrets' );
