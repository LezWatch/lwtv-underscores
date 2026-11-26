<?php
/**
 * Name: Compare Page
 * Description: Throwaway admin screen comparing Meilisearch and SearchWP results.
 *
 * Meilisearch is queried from the browser with the public search key, as the
 * eventual front end would. SearchWP runs through a small REST route using its
 * existing engine of the same name (`characters`, `shows`, `actors`; content
 * has no engine of its own, so it runs `default` and keeps the posts and
 * pages), read-only. Its rows are cut to the index's displayedAttributes, so an
 * actor's other names never reach the browser from either column. The
 * indexing key never leaves PHP.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Index_Settings;

class Compare_Page {

	const SLUG       = 'lwtv_meili_compare';
	const CAPABILITY = 'manage_options';
	const LIMIT      = 20;

	private Config $config;
	private Indexes $indexes;

	/**
	 * @param Config  $config  Connection settings.
	 * @param Indexes $indexes Every index, used to describe SearchWP hits.
	 */
	public function __construct( Config $config, Indexes $indexes ) {
		$this->config  = $config;
		$this->indexes = $indexes;
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
	 * Add the page under the Search menu, labelled Compare.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		add_submenu_page( Search_Menu::PARENT, __( 'Search Compare', 'lwtv-underscores' ), __( 'Compare', 'lwtv-underscores' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );
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
				'types'     => $this->types(),
				'searchKey' => $this->config->search_key(),
				'route'     => '/lwtv/v1/meili-compare/searchwp',
				'limit'     => self::LIMIT,
				'labels'    => array(
					'noResults' => __( 'No results.', 'lwtv-underscores' ),
					'error'     => __( 'Request failed:', 'lwtv-underscores' ),
					'ms'        => __( 'ms round trip', 'lwtv-underscores' ),
					'server'    => __( 'ms on the server', 'lwtv-underscores' ),
					'notSetUp'  => __( 'Index not set up on this environment.', 'lwtv-underscores' ),
					'worthIt'   => __( 'Worth It:', 'lwtv-underscores' ),
					'score'     => __( 'Score:', 'lwtv-underscores' ),
					'queer'     => __( 'Queer', 'lwtv-underscores' ),
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
					/* translators: %s: Meilisearch index names. */
					esc_html__( 'Searches Meilisearch (%s) and SearchWP side by side. Throwaway prototype page.', 'lwtv-underscores' ),
					implode( ', ', array_map( fn( $index ) => '<code>' . esc_html( $index->uid() ) . '</code>', $this->indexes->all() ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each uid is escaped above.
				);
				?>
			</p>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'What to search', 'lwtv-underscores' ); ?></legend>
				<?php foreach ( $this->indexes->all() as $type => $index ) : ?>
					<label style="margin-right:1rem;">
						<input type="radio" name="lwtv-meili-type" value="<?php echo esc_attr( $type ); ?>" <?php checked( 'characters', $type ); ?> />
						<?php echo esc_html( $index->label() ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p>
				<label for="lwtv-meili-q" class="screen-reader-text"><?php esc_html_e( 'Search', 'lwtv-underscores' ); ?></label>
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
	 * Per index type: the Meilisearch uid and a label, for the browser.
	 *
	 * @return array<string, array{index:string, label:string}>
	 */
	private function types(): array {
		$types = array();
		foreach ( $this->indexes->all() as $type => $index ) {
			$types[ $type ] = array(
				'index' => $index->uid(),
				'label' => $index->label(),
			);
		}

		return $types;
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
					'q'    => array(
						'required'          => true,
						'type'              => 'string',
						'minLength'         => 1,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => fn( $value ) => is_string( $value ) && '' !== trim( $value ),
					),
					'type' => array(
						'type'    => 'string',
						'enum'    => $this->indexes->types(),
						'default' => 'characters',
					),
				),
			)
		);
	}

	/**
	 * Run the SearchWP engine named like the index type for a term. Reads only.
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

		$index   = $this->indexes->get( (string) $request->get_param( 'type' ) ) ?? $this->indexes->get( 'characters' );
		$started = microtime( true );
		$query   = new \SearchWP\Query(
			(string) $request->get_param( 'q' ),
			array(
				'engine'   => $index ? $index->searchwp_engine() : 'characters',
				'fields'   => 'ids',
				'per_page' => self::LIMIT,
				'page'     => 1,
			)
		);
		$ids     = array_map( 'intval', (array) $query->get_results() );
		$ms      = (int) round( ( microtime( true ) - $started ) * 1000 );

		return new \WP_REST_Response(
			array(
				'hits' => $index ? $this->displayed( $index, $index->documents( $ids ) ) : array(),
				'ms'   => $ms,
			)
		);
	}

	/**
	 * Cut documents to the fields Meilisearch would return, so the SearchWP
	 * column shows no more than the search key could read.
	 *
	 * @param Index   $index     Index the documents belong to.
	 * @param array[] $documents Documents.
	 * @return array[]
	 */
	private function displayed( Index $index, array $documents ): array {
		try {
			$settings = Index_Settings::load( (string) file_get_contents( $index->settings_path() ) );
		} catch ( \InvalidArgumentException $e ) {
			return array();
		}

		return Index_Settings::displayed( $settings, $documents );
	}
}
