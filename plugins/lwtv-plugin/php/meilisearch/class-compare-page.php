<?php
/**
 * Name: Compare Page
 * Description: Throwaway admin screen comparing Meilisearch and SearchWP results.
 *
 * Meilisearch is queried from the browser with the public search key, as the
 * eventual front end would. SearchWP runs through a small REST route using its
 * existing `characters` engine, read-only. The indexing key never leaves PHP.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Compare_Page {

	const SLUG       = 'lwtv_meili_compare';
	const CAPABILITY = 'manage_options';
	const LIMIT      = 20;

	private Config $config;
	private Indexer $indexer;

	/**
	 * @param Config  $config  Connection settings.
	 * @param Indexer $indexer Shared indexer, used to describe SearchWP hits.
	 */
	public function __construct( Config $config, Indexer $indexer ) {
		$this->config  = $config;
		$this->indexer = $indexer;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Add the page under the LezWatch.TV menu.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		$title = __( 'Search Compare', 'lwtv-underscores' );
		add_submenu_page( 'lwtv', $title, $title, self::CAPABILITY, self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Load the script on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		if ( false === strpos( (string) $hook_suffix, self::SLUG ) ) {
			return;
		}

		$path = __DIR__ . '/assets/compare.js';
		wp_enqueue_script( 'lwtv-meili-compare', LWTV_PLUGIN_URL . '/php/meilisearch/assets/compare.js', array( 'wp-api-fetch' ), (string) filemtime( $path ), true );
		wp_localize_script(
			'lwtv-meili-compare',
			'lwtvMeiliCompare',
			array(
				'url'       => $this->config->url(),
				'index'     => $this->indexer->uid(),
				'searchKey' => $this->config->search_key(),
				'route'     => '/lwtv/v1/meili-compare/searchwp',
				'limit'     => self::LIMIT,
				'labels'    => array(
					'noResults' => __( 'No results.', 'lwtv-underscores' ),
					'error'     => __( 'Request failed:', 'lwtv-underscores' ),
					'ms'        => __( 'ms round trip', 'lwtv-underscores' ),
					'server'    => __( 'ms on the server', 'lwtv-underscores' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Search Compare', 'lwtv-underscores' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: Meilisearch index name. */
					esc_html__( 'Searches characters in Meilisearch (index %s) and SearchWP side by side. Throwaway prototype page.', 'lwtv-underscores' ),
					'<code>' . esc_html( $this->indexer->uid() ) . '</code>'
				);
				?>
			</p>
			<p>
				<label for="lwtv-meili-q" class="screen-reader-text"><?php esc_html_e( 'Search characters', 'lwtv-underscores' ); ?></label>
				<input type="search" id="lwtv-meili-q" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. Mel from Slo-Pitch', 'lwtv-underscores' ); ?>" />
			</p>
			<div style="display:flex;gap:2rem;flex-wrap:wrap;">
				<section style="flex:1 1 24rem;" aria-live="polite">
					<h2><?php esc_html_e( 'Meilisearch', 'lwtv-underscores' ); ?> <small id="lwtv-meili-time"></small></h2>
					<ol id="lwtv-meili-results"></ol>
				</section>
				<section style="flex:1 1 24rem;" aria-live="polite">
					<h2><?php esc_html_e( 'SearchWP', 'lwtv-underscores' ); ?> <small id="lwtv-swp-time"></small></h2>
					<ol id="lwtv-swp-results"></ol>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Register the SearchWP route.
	 *
	 * @return void
	 */
	public function rest_api_init(): void {
		register_rest_route(
			'lwtv/v1',
			'/meili-compare/searchwp',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'searchwp_results' ),
				'permission_callback' => fn() => current_user_can( self::CAPABILITY ),
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'minLength'         => 1,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => fn( $value ) => is_string( $value ) && '' !== trim( $value ),
					),
				),
			)
		);
	}

	/**
	 * Run SearchWP's characters engine for a term. Reads only.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function searchwp_results( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! class_exists( '\SearchWP\Query' ) ) {
			return new \WP_REST_Response(
				array(
					'hits' => array(),
					'ms'   => 0,
					'note' => __( 'SearchWP is not active.', 'lwtv-underscores' ),
				)
			);
		}

		$started = microtime( true );
		$query   = new \SearchWP\Query(
			(string) $request->get_param( 'q' ),
			array(
				'engine'   => 'characters',
				'fields'   => 'ids',
				'per_page' => self::LIMIT,
				'page'     => 1,
			)
		);
		$ids     = array_map( 'intval', (array) $query->get_results() );
		$ms      = (int) round( ( microtime( true ) - $started ) * 1000 );

		return new \WP_REST_Response(
			array(
				'hits' => $this->indexer->documents( $ids ),
				'ms'   => $ms,
			)
		);
	}
}
