<?php
/**
 * Name: Search Settings REST
 * Description: The REST API behind Search → Settings.
 *
 * Admins only (manage_options), with the standard REST nonce. WordPress talks
 * to Meilisearch; responses carry booleans and log-safe messages, never a key.
 *
 * - GET    lwtv/v1/meili-settings              the switch, timeout, connection, constant and fallback
 * - POST   lwtv/v1/meili-settings              save { switch?, timeout_ms?, url?, prefix? }; adds health { ok, message, access, prefix } when the connection changed
 * - GET    lwtv/v1/meili-settings/status       server, keys and per-index health, with jobs
 * - POST   lwtv/v1/meili-settings/jobs         start { type, kind }
 * - DELETE lwtv/v1/meili-settings/jobs/<type>  cancel
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Index_Job;
use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Build\Index_State;
use LWTV\Meilisearch\Build\Search_Rules;
use LWTV\Meilisearch\Build\Search_Settings;
use LWTV\Meilisearch\Build\Settings_Drift;

class Search_Settings_Rest {

	const APISPACE   = 'lwtv/v1';
	const ROUTE      = '/meili-settings';
	const CAPABILITY = 'manage_options';

	private Config $config;
	private Client $client;
	private Indexes $indexes;
	private Search_Settings_Store $settings;
	private Search_Rules_Store $rules;
	private Index_Jobs $jobs;
	private Typo_Store $typo;

	/**
	 * @param Config                $config   Connection settings.
	 * @param Client                $client   Meilisearch client.
	 * @param Indexes               $indexes  Every index.
	 * @param Search_Settings_Store $settings The switch and timeout.
	 * @param Search_Rules_Store    $rules    Saved synonyms.
	 * @param Index_Jobs            $jobs     Background jobs.
	 * @param Typo_Store            $typo     Saved typo tolerance.
	 */
	public function __construct( Config $config, Client $client, Indexes $indexes, Search_Settings_Store $settings, Search_Rules_Store $rules, Index_Jobs $jobs, Typo_Store $typo ) {
		$this->config   = $config;
		$this->client   = $client;
		$this->indexes  = $indexes;
		$this->settings = $settings;
		$this->rules    = $rules;
		$this->jobs     = $jobs;
		$this->typo     = $typo;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function routes(): void {
		$allowed = array( $this, 'allowed' );

		register_rest_route(
			self::APISPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'read' ),
					'permission_callback' => $allowed,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $allowed,
				),
			)
		);

		register_rest_route(
			self::APISPACE,
			self::ROUTE . '/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $allowed,
			)
		);

		register_rest_route(
			self::APISPACE,
			self::ROUTE . '/jobs',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_job' ),
				'permission_callback' => $allowed,
				'args'                => array(
					'type' => array(
						'required' => true,
						'type'     => 'string',
					),
					'kind' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			self::APISPACE,
			self::ROUTE . '/jobs/(?P<type>[a-z]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'cancel_job' ),
				'permission_callback' => $allowed,
			)
		);
	}

	/**
	 * Admins only.
	 *
	 * @return bool
	 */
	public function allowed(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * GET: the settings and where the switch comes from. Seeds the option
	 * from a valid constant first, so removing the define changes nothing.
	 *
	 * @return \WP_REST_Response
	 */
	public function read(): \WP_REST_Response {
		$seeded = $this->settings->seed();

		return rest_ensure_response( $this->shape( $seeded ) );
	}

	/**
	 * POST: validate and save.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $request ) {
		$body    = $request->get_json_params();
		$body    = is_array( $body ) ? $body : array();
		$changed = $this->settings->connection_changes( $body );

		// A reindex mustn't switch servers or prefixes halfway through. The
		// connection waits; anything else in the same save still goes through.
		$busy = null;
		if ( $changed && $this->job_running() ) {
			$busy = __( 'The connection wasn’t changed: wait for the running job to finish, then save it again.', 'lwtv-underscores' );
			unset( $body['url'], $body['prefix'] );
			$changed = false;
			if ( empty( $body ) ) {
				return new \WP_Error(
					'lwtv_meili_settings_busy',
					$busy,
					array(
						'status' => 409,
						'errors' => array(),
					)
				);
			}
		}

		$result = $this->settings->save( $body );

		if ( 409 === $result['status'] ) {
			return new \WP_Error(
				'lwtv_meili_settings_locked',
				(string) ( reset( $result['errors'] ) ?: '' ),
				array(
					'status' => 409,
					'errors' => $this->translate( $result['errors'], true ),
				)
			);
		}
		if ( ! $result['ok'] ) {
			return new \WP_Error(
				'lwtv_meili_settings_invalid',
				__( 'Nothing was saved: check the highlighted settings.', 'lwtv-underscores' ),
				array(
					'status' => 400,
					'errors' => $this->translate( $result['errors'], false ),
				)
			);
		}

		$shape = $this->shape( false );
		if ( null !== $busy ) {
			$shape['connection_busy'] = $busy;
		}
		if ( $changed ) {
			// Check the server the new settings point at; an unreachable one is still saved.
			$fresh  = Config::load();
			$client = new Client( $fresh );
			$health = $client->health();
			$ok     = ! is_wp_error( $health ) && 'available' === ( $health['status'] ?? '' );

			// Then whether the keys reach this prefix's indexes: 401/403 means they're scoped to another one.
			$access = null;
			if ( $ok ) {
				$stats  = $client->index_stats( $fresh->index( 'shows' ) );
				$status = is_wp_error( $stats ) ? (int) ( $stats->get_error_data()['status'] ?? 0 ) : 200;
				$access = ! in_array( $status, array( 401, 403 ), true );
			}

			$shape['health'] = array(
				'ok'      => $ok,
				'message' => is_wp_error( $health ) ? $health->get_error_message() : '',
				'access'  => $access,
				'prefix'  => Config::connection()['prefix']['value'],
			);
		}

		return rest_ensure_response( $shape );
	}

	/**
	 * The pure classes' English messages, by field, as translatable text.
	 *
	 * @param array<string, string> $errors Errors by field.
	 * @param bool                  $locked Whether these are "set in wp-config.php" refusals.
	 * @return array<string, string>
	 */
	private function translate( array $errors, bool $locked ): array {
		$defines = array(
			'switch' => 'LWTV_MEILI_MODAL',
			'url'    => 'LWTV_MEILI_URL',
			'prefix' => 'LWTV_MEILI_INDEX_PREFIX',
		);

		$out = array();
		foreach ( $errors as $field => $message ) {
			if ( $locked && isset( $defines[ $field ] ) ) {
				$out[ $field ] = sprintf(
					/* translators: %s: the constant's name. */
					__( 'This is set in wp-config.php (%s). Remove that line to manage it here.', 'lwtv-underscores' ),
					$defines[ $field ]
				);
				continue;
			}

			switch ( $field ) {
				case 'switch':
					$out[ $field ] = __( 'Choose off, editors or everyone.', 'lwtv-underscores' );
					break;
				case 'timeout_ms':
					$out[ $field ] = sprintf(
						/* translators: 1: minimum, 2: maximum, 3: step, all in milliseconds. */
						__( 'The timeout must be a whole number of milliseconds from %1$d to %2$d, in steps of %3$d.', 'lwtv-underscores' ),
						Search_Settings::TIMEOUT_MIN,
						Search_Settings::TIMEOUT_MAX,
						Search_Settings::TIMEOUT_STEP
					);
					break;
				case 'url':
					$out[ $field ] = __( 'The URL must start with https:// (http:// only for a local host), have a host, and have no spaces, user name, password, ? or # part.', 'lwtv-underscores' );
					break;
				case 'prefix':
					$out[ $field ] = __( 'The prefix can use only letters, digits, - and _, up to 40 characters.', 'lwtv-underscores' );
					break;
				default:
					$out[ $field ] = $message;
			}
		}

		return $out;
	}

	/**
	 * Whether any index has a job running.
	 *
	 * @return bool
	 */
	private function job_running(): bool {
		foreach ( array_keys( $this->indexes->all() ) as $type ) {
			if ( null !== $this->jobs->running( (string) $type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * GET /status: the dashboard. With the server down it makes one call.
	 *
	 * @return \WP_REST_Response
	 */
	public function status(): \WP_REST_Response {
		// Keep a running job moving without waiting for WP-Cron.
		$this->jobs->nudge();

		$health = $this->client->health();
		$up     = ! is_wp_error( $health ) && 'available' === ( $health['status'] ?? '' );
		$map    = $this->rules->synonyms_map();

		$indexes = array();
		foreach ( $this->indexes->all() as $type => $registered ) {
			// The index a job on this row would act on (same debug filter).
			$index = $this->jobs->index( $type ) ?? $registered;
			$job   = $this->jobs->refresh( $type );
			$row   = array(
				'type'           => $type,
				'uid'            => $index->uid(),
				'label'          => $index->label(),
				'state'          => Index_State::UNKNOWN,
				'documents'      => null,
				'indexing'       => false,
				'settings_drift' => array(),
				'synonyms_match' => null,
				'job'            => $job,
			);

			if ( $up ) {
				$stats        = $this->client->index_stats( $index->uid() );
				$state        = Index_State::classify( is_wp_error( $stats ) ? $stats->get_error_message() : null );
				$row['state'] = $state;

				if ( Index_State::EXISTS === $state && is_array( $stats ) ) {
					$row['documents'] = (int) ( $stats['numberOfDocuments'] ?? 0 );
					$row['indexing']  = ! empty( $stats['isIndexing'] );

					$live = $this->client->get_settings( $index->uid() );
					if ( is_array( $live ) ) {
						try {
							$repo                  = Index_Settings::load( (string) file_get_contents( $index->settings_path() ) );
							$row['settings_drift'] = Settings_Drift::differs( $repo, $live, $this->typo->payload( $index ) );
						} catch ( \InvalidArgumentException $e ) {
							$row['settings_drift'] = array( 'repo file' );
						}
						$row['synonyms_match'] = Search_Rules::same( is_array( $live['synonyms'] ?? null ) ? $live['synonyms'] : array(), $map );
					}
				}
			}

			$indexes[] = $row;
		}

		$connection = Config::connection();

		return rest_ensure_response(
			array(
				'server'           => array(
					'ok'      => $up,
					'message' => is_wp_error( $health ) ? $health->get_error_message() : '',
				),
				'host'             => $this->config->host(),
				'host_source'      => $connection['url']['source'],
				'prefix'           => $this->prefix(),
				'prefix_source'    => $connection['prefix']['source'],
				'keys'             => array(
					'url'                => '' !== $this->config->url(),
					'index_key'          => '' !== $this->config->index_key(),
					'search_key'         => '' !== $this->config->search_key(),
					'search_key_differs' => $this->config->search_key_is_public(),
				),
				'action_scheduler' => $this->jobs->can_schedule(),
				'switch'           => $this->settings->effective(),
				'timeout_ms'       => $this->settings->timeout_ms(),
				'indexes'          => $indexes,
			)
		);
	}

	/**
	 * POST /jobs: start one.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start_job( \WP_REST_Request $request ) {
		$type = (string) $request->get_param( 'type' );
		$kind = (string) $request->get_param( 'kind' );

		if ( ! in_array( $type, $this->indexes->types(), true ) || ! in_array( $kind, Index_Job::KINDS, true ) ) {
			return new \WP_Error( 'lwtv_meili_job_invalid', __( 'Unknown index or job.', 'lwtv-underscores' ), array( 'status' => 400 ) );
		}

		$job = $this->jobs->start( $type, $kind );
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$response = rest_ensure_response( array( 'job' => $job ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * DELETE /jobs/<type>: cancel.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel_job( \WP_REST_Request $request ) {
		$type = (string) $request->get_param( 'type' );
		if ( ! in_array( $type, $this->indexes->types(), true ) ) {
			return new \WP_Error( 'lwtv_meili_job_invalid', __( 'Unknown index or job.', 'lwtv-underscores' ), array( 'status' => 400 ) );
		}

		$job = $this->jobs->cancel( $type );

		return is_wp_error( $job ) ? $job : rest_ensure_response( array( 'job' => $job ) );
	}

	/**
	 * The GET/POST response.
	 *
	 * @param bool $seeded Whether this request copied the constant into the option.
	 * @return array
	 */
	private function shape( bool $seeded ): array {
		$settings = $this->settings->get();

		$blocked = null;
		if ( ! $this->config->is_configured() ) {
			$blocked = 'unconfigured';
		} elseif ( ! $this->config->search_key_is_public() ) {
			$blocked = 'search_key_is_index_key';
		}

		return array(
			'settings'   => array(
				'switch'     => $settings['switch'],
				'timeout_ms' => $settings['timeout_ms'],
				'url'        => $settings['url'],
				'prefix'     => $settings['prefix'],
			),
			'connection' => Config::connection(),
			'effective'  => $this->settings->effective(),
			'constant'   => $this->settings->constant(),
			'fallback'   => class_exists( '\SearchWP' ) ? 'searchwp' : 'wordpress',
			'limits'     => array(
				'timeout_min'  => Search_Settings::TIMEOUT_MIN,
				'timeout_max'  => Search_Settings::TIMEOUT_MAX,
				'timeout_step' => Search_Settings::TIMEOUT_STEP,
			),
			'seeded'     => $seeded,
			'blocked'    => $blocked,
		);
	}

	/**
	 * This environment's index prefix, from a uid (never the keys).
	 *
	 * @return string
	 */
	private function prefix(): string {
		$uid  = $this->config->index( 'characters' );
		$tail = '_characters';

		return str_ends_with( $uid, $tail ) ? substr( $uid, 0, -strlen( $tail ) ) : '';
	}
}
