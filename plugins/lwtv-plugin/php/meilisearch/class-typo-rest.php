<?php
/**
 * Name: Typo Rest
 * Description: REST API for Search → Settings → Typo tolerance: read, save and reset per-index typo settings.
 *
 * Admins only (manage_options), with the REST nonce.
 * - GET  lwtv/v1/meili-settings/typo        every index's block, defaults, attributes and latest job
 * - POST lwtv/v1/meili-settings/typo        save { type, settings }, then apply
 * - POST lwtv/v1/meili-settings/typo/reset  back to the defaults { type }, then apply
 *
 * A save is stored first, then applied through the Apply job. When it can't
 * be applied now, `applied` says why (job_running, unreachable, not_set_up,
 * or not_applied with a reason) and the block stays saved; the Status tab's Apply settings catches it up.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

use LWTV\Meilisearch\Build\Index_Job;
use LWTV\Meilisearch\Build\Index_State;
use LWTV\Meilisearch\Build\Typo_Tolerance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Typo_Rest {

	const NAMESPACE  = 'lwtv/v1';
	const ROUTE      = '/meili-settings/typo';
	const CAPABILITY = 'manage_options';

	const STARTED     = 'started';
	const JOB_RUNNING = 'job_running';
	const UNREACHABLE = 'unreachable';
	const NOT_SET_UP  = 'not_set_up';
	const NOT_APPLIED = 'not_applied';

	private Indexes $indexes;
	private Typo_Store $typo;
	private Index_Jobs $jobs;

	/**
	 * @param Indexes    $indexes Every index.
	 * @param Typo_Store $typo    Saved typo tolerance.
	 * @param Index_Jobs $jobs    Background jobs.
	 */
	public function __construct( Indexes $indexes, Typo_Store $typo, Index_Jobs $jobs ) {
		$this->indexes = $indexes;
		$this->typo    = $typo;
		$this->jobs    = $jobs;
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Add the routes.
	 *
	 * @return void
	 */
	public function routes(): void {
		$allowed = array( $this, 'allowed' );

		register_rest_route(
			self::NAMESPACE,
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
			self::NAMESPACE,
			self::ROUTE . '/reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset' ),
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
	 * GET: every index's block.
	 *
	 * @return \WP_REST_Response
	 */
	public function read(): \WP_REST_Response {
		$rows = array();
		foreach ( array_keys( $this->indexes->all() ) as $type ) {
			$index = $this->jobs->index( (string) $type );
			if ( null !== $index ) {
				$rows[] = $this->row( $index );
			}
		}

		return rest_ensure_response(
			array(
				'indexes' => $rows,
				'limits'  => array(
					'one_min'   => Typo_Tolerance::ONE_MIN,
					'one_max'   => Typo_Tolerance::ONE_MAX,
					'two_max'   => Typo_Tolerance::TWO_MAX,
					'words_max' => Typo_Tolerance::WORDS_MAX,
					'word_max'  => Typo_Tolerance::WORD_MAX,
				),
			)
		);
	}

	/**
	 * POST: validate, save, then apply.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$body  = is_array( $body ) ? $body : array();
		$index = $this->index_from( $body );
		if ( $index instanceof \WP_Error ) {
			return $index;
		}

		$result = $this->typo->save( $index, $body['settings'] ?? null );
		if ( ! empty( $result['errors'] ) ) {
			return new \WP_Error(
				'lwtv_meili_typo_invalid',
				__( 'Nothing was saved: check the highlighted settings.', 'lwtv-underscores' ),
				array(
					'status' => 400,
					'errors' => $this->translate( $result['errors'] ),
				)
			);
		}

		return $this->applied( $index );
	}

	/**
	 * POST /reset: back to the defaults, then apply.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reset( \WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$index = $this->index_from( is_array( $body ) ? $body : array() );
		if ( $index instanceof \WP_Error ) {
			return $index;
		}

		$this->typo->reset( $index->type() );

		return $this->applied( $index );
	}

	/**
	 * Typo_Tolerance's English messages, by field, as translatable text.
	 *
	 * @param array<string, string> $errors Errors by field.
	 * @return array<string, string>
	 */
	private function translate( array $errors ): array {
		$out = array();
		foreach ( $errors as $field => $message ) {
			switch ( $field ) {
				case 'enabled':
				case 'disableOnNumbers':
					$out[ $field ] = __( 'Choose on or off.', 'lwtv-underscores' );
					break;
				case 'oneTypo':
					$out[ $field ] = sprintf(
						/* translators: 1: fewest letters, 2: most letters. */
						__( 'One typo needs a whole number of letters from %1$d to %2$d.', 'lwtv-underscores' ),
						Typo_Tolerance::ONE_MIN,
						Typo_Tolerance::ONE_MAX
					);
					break;
				case 'twoTypos':
					$out[ $field ] = sprintf(
						/* translators: %d: most letters. */
						__( 'Two typos needs a whole number of letters above the one-typo length, up to %d.', 'lwtv-underscores' ),
						Typo_Tolerance::TWO_MAX
					);
					break;
				case 'disableOnWords':
					$out[ $field ] = sprintf(
						/* translators: 1: most words, 2: longest word in characters. */
						__( 'Use up to %1$d words, each up to %2$d characters.', 'lwtv-underscores' ),
						Typo_Tolerance::WORDS_MAX,
						Typo_Tolerance::WORD_MAX
					);
					break;
				case 'disableOnAttributes':
					$out[ $field ] = __( 'Choose only fields this index searches.', 'lwtv-underscores' );
					break;
				case 'settings':
					$out[ $field ] = __( 'Send the typo settings as an object.', 'lwtv-underscores' );
					break;
				default:
					$out[ $field ] = $message;
			}
		}

		return $out;
	}

	/**
	 * The index named by `type`, or a 400.
	 *
	 * @param array $body Request body.
	 * @return Index|\WP_Error
	 */
	private function index_from( array $body ) {
		$type  = is_string( $body['type'] ?? null ) ? $body['type'] : '';
		$index = ( '' !== $type && array_key_exists( $type, $this->indexes->all() ) ) ? $this->jobs->index( $type ) : null;

		if ( null === $index ) {
			return new \WP_Error(
				'lwtv_meili_typo_invalid',
				__( 'Unknown index.', 'lwtv-underscores' ),
				array(
					'status' => 400,
					'errors' => array( 'type' => __( 'Unknown index.', 'lwtv-underscores' ) ),
				)
			);
		}

		return $index;
	}

	/**
	 * Apply the saved block now if it can be, and say what happened.
	 *
	 * @param Index $index Index.
	 * @return \WP_REST_Response
	 */
	private function applied( Index $index ): \WP_REST_Response {
		$type   = $index->type();
		$reason = '';

		if ( null !== $this->jobs->running( $type ) ) {
			$applied = self::JOB_RUNNING;
		} else {
			$index->forget_state();
			$state = $index->state();

			if ( Index_State::UNKNOWN === $state ) {
				$applied = self::UNREACHABLE;
			} elseif ( Index_State::MISSING === $state ) {
				$applied = self::NOT_SET_UP;
			} else {
				$started = $this->jobs->start( $type, Index_Job::APPLY );
				if ( ! is_wp_error( $started ) ) {
					$applied = self::STARTED;
				} elseif ( Index_Job::is_running( $this->jobs->get( $type ) ) ) {
					// Read again: a job may have started since the check above.
					$applied = self::JOB_RUNNING;
				} else {
					$applied = self::NOT_APPLIED;
					$reason  = $started->get_error_message();
				}
			}
		}

		return rest_ensure_response(
			array(
				'index'   => $this->row( $index ),
				'applied' => $applied,
				'reason'  => $reason,
			)
		);
	}

	/**
	 * One index's row.
	 *
	 * @param Index $index Index.
	 * @return array
	 */
	private function row( Index $index ): array {
		$type   = $index->type();
		$values = $this->typo->values( $index );

		return array(
			'type'       => $type,
			'label'      => $index->label(),
			'settings'   => $values['values'],
			'removed'    => $values['removed'],
			'defaults'   => Typo_Tolerance::defaults( $type ),
			'is_default' => Typo_Tolerance::is_default( $type, $values['values'] ),
			'attributes' => $this->typo->searchable( $index ),
			'job'        => $this->jobs->refresh( $type ),
		);
	}
}
