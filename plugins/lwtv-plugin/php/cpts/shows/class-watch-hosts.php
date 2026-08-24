<?php
/**
 * Name: Watch Hosts
 * Description: Which hosts the Ways to Watch fields actually point at.
 *
 * Shared by `wp lwtv waystowatch` and the Watch URLs validation tab so the two
 * can't disagree about what's registered.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The Shows CPT class lives in LWTV\CPTs, not LWTV\CPTs\Shows, so a bare
// `Shows` here would resolve to LWTV\CPTs\Shows\Shows and fatal. Aliased,
// matching what class-calculations.php does in this same namespace.
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\Theme\Ways_To_Watch as Theme_Ways_To_Watch;

class Watch_Hosts {

	/**
	 * ACF field keys for the term's URL repeater, as written by
	 * `wp lwtv migrate watchtermurls`. Needed so terms created here look
	 * identical to ones created through the ACF UI.
	 */
	const FIELD_REPEATER = 'field_lwtv_lezwatchurls_all';
	const FIELD_URL      = 'field_lwtv_lezwatchurls_all_url';

	/**
	 * Meta key prefix for the repeater.
	 */
	const META_REPEATER = 'lezwatchurls_all';

	/**
	 * Request timeout in seconds for CLI runs, where nothing is waiting on us.
	 */
	const TIMEOUT = 6;

	/**
	 * Request timeout in seconds for admin-request runs. Tighter, because a
	 * person is watching a spinner.
	 */
	const UI_TIMEOUT = 3;

	/**
	 * Cap on bytes read. Site metadata lives in <head>; no need for the body.
	 */
	const MAX_BYTES = 120000;

	/**
	 * Most hosts one admin-request lookup will fetch.
	 *
	 * Secondary to UI_TIME_BUDGET. A count on its own bounds nothing useful --
	 * ten slow hosts is a minute either way -- so the budget is what actually
	 * protects the request and this just stops it doing needless work when
	 * every host answers instantly.
	 */
	const UI_BATCH = 5;

	/**
	 * Wall-clock budget in seconds for an admin-request lookup.
	 *
	 * Deliberately well under a typical 30s max_execution_time and a 60s
	 * gateway timeout, with room for the rest of the page load. The loop stops
	 * before *starting* a request that could exceed this, so overshoot is
	 * bounded rather than hoped for.
	 */
	const UI_TIME_BUDGET = 15;

	/**
	 * Request-level memo of host => show count.
	 *
	 * @var array<string, int>|null
	 */
	private static $in_use = null;

	/**
	 * Request-level memo of host => WP_Term|null.
	 *
	 * @var array<string, \WP_Term|null>
	 */
	private static $terms = array();

	/**
	 * Request-level memo of every term URL.
	 *
	 * A three-way join over terms, term_taxonomy and termmeta, and the watch-URL
	 * scan asks for it twice in one run -- once to find terms with no URLs, once
	 * to build the probe list.
	 *
	 * @var array<int, array{term_id: int, name: string, url: string}>|null
	 */
	private static $term_urls = null;

	/**
	 * Every host referenced by a published show, with a distinct-show count.
	 *
	 * Sorted by count descending: the hosts most readers actually reach first.
	 *
	 * @return array<string, int> host => number of shows
	 */
	public static function in_use(): array {
		if ( null !== self::$in_use ) {
			return self::$in_use;
		}

		global $wpdb;

		// Two predicates on meta_key, deliberately. REGEXP is the exact test --
		// anchored, digits only -- but it is not sargable, so alone it makes
		// MySQL reach every candidate row in wp_postmeta before filtering. The
		// LIKE is redundant for correctness and exists only to give the
		// meta_key index a constant prefix to range-scan first.
		//
		// esc_like() is not optional: these keys are full of literal
		// underscores, and an unescaped '_' is a single-character LIKE wildcard
		// -- it would both loosen the match and truncate the usable index prefix
		// at the first one.
		$like = $wpdb->esc_like( 'lezshows_waystowatch_' ) . '%' . $wpdb->esc_like( '_url' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value AS url
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s
				   AND p.post_status = 'publish'
				   AND pm.meta_key LIKE %s
				   AND pm.meta_key REGEXP '^lezshows_waystowatch_[0-9]+_url$'
				   AND pm.meta_value != ''",
				CPT_Shows::SLUG,
				$like
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Count distinct shows, not rows: one show can list a provider twice.
		$seen = array();
		foreach ( (array) $rows as $row ) {
			$parsed = wp_parse_url( $row->url );

			if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
				continue;
			}

			$host = Host_Name::normalise( $parsed['host'] );

			if ( '' === $host ) {
				continue;
			}

			$seen[ $host ][ (int) $row->post_id ] = true;
		}

		$hosts = array();
		foreach ( $seen as $host => $post_ids ) {
			$hosts[ $host ] = count( $post_ids );
		}

		arsort( $hosts );
		self::$in_use = $hosts;

		return self::$in_use;
	}

	/**
	 * The lez_watch_urls term matching a host, if any.
	 *
	 * Delegates to the theme's matcher so this reports exactly what the front
	 * end would resolve, including its www and scheme handling.
	 *
	 * @param string $host Hostname.
	 * @return \WP_Term|null
	 */
	public static function term_for( string $host ): ?\WP_Term {
		$host = Host_Name::normalise( $host );

		if ( array_key_exists( $host, self::$terms ) ) {
			return self::$terms[ $host ];
		}

		$candidates = array();
		foreach ( Host_Name::host_candidates( $host ) as $one_host ) {
			$candidates[] = 'https://' . $one_host;
			$candidates[] = 'http://' . $one_host;
		}

		$terms                = Theme_Ways_To_Watch::get_term_by_url( $candidates );
		self::$terms[ $host ] = $terms[0] ?? null;

		return self::$terms[ $host ];
	}

	/**
	 * Every URL registered against a lez_watch_urls term.
	 *
	 * The mirror image of in_use(): that asks what the shows point at, this asks
	 * what we have claimed to know about. `wp lwtv debug watchurls` walks this
	 * list, which is a few hundred rows, rather than every Ways to Watch field on
	 * every show, which is thousands of rows pointing at the same few hundred
	 * hosts. Same coverage, an order of magnitude fewer requests.
	 *
	 * Read from the repeater's subfield rows rather than ACF's row-count
	 * bookkeeping, so a deleted row can't leave a phantom URL behind.
	 *
	 * @return array<int, array{term_id: int, name: string, url: string}> One row per URL.
	 */
	public static function term_urls(): array {
		if ( null !== self::$term_urls ) {
			return self::$term_urls;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, tm.meta_value AS url
				 FROM {$wpdb->terms} t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				 INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
				 WHERE tt.taxonomy = %s
				   AND tm.meta_key REGEXP '^lezwatchurls_all_[0-9]+_url$'
				   AND tm.meta_value != ''
				 ORDER BY t.name ASC, tm.meta_key ASC",
				Theme_Ways_To_Watch::TAXONOMY
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$urls = array();
		foreach ( (array) $rows as $row ) {
			$urls[] = array(
				'term_id' => (int) $row->term_id,
				'name'    => (string) $row->name,
				'url'     => (string) $row->url,
			);
		}

		self::$term_urls = $urls;

		return self::$term_urls;
	}

	/**
	 * How many published shows each provider term actually serves.
	 *
	 * Used to sort findings by consequence: a broken URL on the term 500 shows
	 * point at matters more than one on a term nothing reaches.
	 *
	 * Not cheap -- term_for() is a query per host, memoised only per request --
	 * so this is for CLI and cron, and its results are stored alongside the
	 * findings rather than recomputed when the admin tab renders.
	 *
	 * @return array<int, int> term_id => number of shows
	 */
	public static function shows_per_term(): array {
		$totals = array();

		foreach ( self::in_use() as $host => $count ) {
			$term = self::term_for( $host );

			if ( ! $term ) {
				continue;
			}

			$totals[ $term->term_id ] = ( $totals[ $term->term_id ] ?? 0 ) + $count;
		}

		return $totals;
	}

	/**
	 * Fetch a URL and return the facts needed to judge it.
	 *
	 * Deliberately dumb: it gathers, it does not decide. Every judgement lives in
	 * Watch_Url_Health, which is pure and unit-tested, so the untestable part of
	 * this feature is only ever "did the request happen".
	 *
	 * Redirects are followed rather than reported, because a provider moving its
	 * own URLs around is normal and only the destination is interesting. Where we
	 * *landed* is captured so a redirect off the domain entirely can be spotted.
	 *
	 * @param string   $url     Full URL to fetch.
	 * @param int|null $timeout Seconds to wait. Defaults to TIMEOUT; callers in
	 *                          an admin request should pass UI_TIMEOUT.
	 * @return array{error: string, code: int, final_url: string, site_name: string, body: string}
	 */
	public static function probe( string $url, ?int $timeout = null ): array {
		$probe = array(
			'error'     => '',
			'code'      => 0,
			'final_url' => '',
			'site_name' => '',
			'body'      => '',
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'            => $timeout ?? self::TIMEOUT,
				'redirection'        => 5,
				'reject_unsafe_urls' => true,
				'user-agent'         => 'LezWatch.TV watch-URL check (+https://lezwatchtv.com)',
				'headers'            => array( 'Accept' => 'text/html' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$probe['error'] = $response->get_error_message();
			lwtv_plugin()->debug_log( 'shows', sprintf( 'Watch URL check: %s failed - %s', $url, $probe['error'] ) );

			return $probe;
		}

		$probe['code']      = (int) wp_remote_retrieve_response_code( $response );
		$probe['final_url'] = self::final_url( $response );
		$probe['body']      = substr( (string) wp_remote_retrieve_body( $response ), 0, self::MAX_BYTES );
		$probe['site_name'] = self::published_name( $probe['body'] );

		return $probe;
	}

	/**
	 * Where the request actually ended up after following redirects.
	 *
	 * The WP HTTP API exposes no first-class accessor for this, so it comes out
	 * of the underlying Requests response. Guarded at every step: the transport
	 * is swappable and a filter can replace the response array wholesale, in
	 * which case "we don't know" is the correct answer and Watch_Url_Health
	 * treats an empty value as no evidence of a move.
	 *
	 * @param array $response Response array from wp_remote_get().
	 * @return string Final URL, or '' when the transport didn't say.
	 */
	private static function final_url( array $response ): string {
		$http_response = $response['http_response'] ?? null;

		if ( ! $http_response instanceof \WP_HTTP_Requests_Response ) {
			return '';
		}

		$requests_response = $http_response->get_response_object();

		if ( ! is_object( $requests_response ) || empty( $requests_response->url ) ) {
			return '';
		}

		return (string) $requests_response->url;
	}

	/**
	 * The name a page publishes for itself, raw.
	 *
	 * Unlike discover_name(), this does *not* filter on plausibility. That filter
	 * exists to keep taglines off buttons, but here a long implausible name is
	 * exactly the signal we want -- "Lucky Star Casino | Best Online Slots" tells
	 * us quibi.com changed hands, and rejecting it would hide that.
	 *
	 * @param string $html Response body.
	 * @return string Sanitised name, or '' when the page published none.
	 */
	private static function published_name( string $html ): string {
		foreach ( array(
			Watch_Host_Names::SOURCE_OG_SITE_NAME => 'property',
			Watch_Host_Names::SOURCE_APP_NAME     => 'name',
		) as $key => $attribute ) {
			$value = self::meta_content( $html, $attribute, $key );

			if ( '' !== $value ) {
				return substr( Watch_Host_Names::sanitize_name( $value ), 0, 200 );
			}
		}

		return '';
	}

	/**
	 * Hosts in use with no term, most-used first.
	 *
	 * @param int $min_shows Ignore hosts used by fewer shows than this.
	 * @return array<string, int> host => number of shows
	 */
	public static function unregistered( int $min_shows = 1 ): array {
		$out = array();

		foreach ( self::in_use() as $host => $count ) {
			if ( $count < max( 1, $min_shows ) ) {
				continue;
			}

			if ( self::term_for( $host ) ) {
				continue;
			}

			$out[ $host ] = $count;
		}

		return $out;
	}

	/**
	 * The name we would render for a host right now.
	 *
	 * Mirrors the theme's three tiers: term, then discovered name, then guess.
	 *
	 * @param string $host Hostname.
	 * @return string
	 */
	public static function rendered_name( string $host ): string {
		$term = self::term_for( $host );

		if ( $term ) {
			return $term->name;
		}

		return Watch_Host_Names::get( $host ) ?? Host_Name::guess( $host );
	}

	/**
	 * The name to pre-fill when proposing a term for a host.
	 *
	 * @param string $host Hostname.
	 * @return string
	 */
	public static function proposed_name( string $host ): string {
		return Watch_Host_Names::get( $host ) ?? Host_Name::guess( $host );
	}

	/**
	 * Create a lez_watch_urls term for a host.
	 *
	 * Writes the URL into the ACF repeater in the same shape ACF itself uses,
	 * so the term is editable in wp-admin afterwards. Only the https form is
	 * stored -- the matcher already tries http and www variants.
	 *
	 * @param string $host Hostname.
	 * @param string $name Display name for the term.
	 * @return int|\WP_Error Term ID on success.
	 */
	public static function create_term( string $host, string $name ) {
		$host = Host_Name::normalise( $host );
		$name = Watch_Host_Names::sanitize_name( $name );

		if ( '' === $host ) {
			return new \WP_Error( 'lwtv_no_host', __( 'No host was supplied.', 'lwtv' ) );
		}

		if ( '' === $name ) {
			return new \WP_Error( 'lwtv_no_name', __( 'A provider name is required.', 'lwtv' ) );
		}

		if ( self::term_for( $host ) ) {
			return new \WP_Error(
				'lwtv_host_registered',
				/* translators: %s: hostname. */
				sprintf( __( '%s already resolves to a provider term.', 'lwtv' ), $host )
			);
		}

		$created = wp_insert_term( $name, Theme_Ways_To_Watch::TAXONOMY );

		// An existing term of the same name is fine -- attach the host to it
		// rather than refusing. Two hosts, one provider, is normal.
		if ( is_wp_error( $created ) ) {
			$existing = $created->get_error_data( 'term_exists' );

			if ( ! $existing ) {
				return $created;
			}

			$term_id = (int) $existing;
		} else {
			$term_id = (int) $created['term_id'];
		}

		self::add_url_to_term( $term_id, 'https://' . $host );

		// Our memo is now stale for this host.
		unset( self::$terms[ $host ] );

		return $term_id;
	}

	/**
	 * Append a URL row to a term's ACF repeater.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $url     Full 'scheme://host'.
	 * @return void
	 */
	private static function add_url_to_term( int $term_id, string $url ): void {
		$existing = (int) get_term_meta( $term_id, self::META_REPEATER, true );
		$index    = max( 0, $existing );

		// Don't add the same URL twice.
		for ( $i = 0; $i < $index; $i++ ) {
			if ( get_term_meta( $term_id, self::META_REPEATER . "_{$i}_url", true ) === $url ) {
				return;
			}
		}

		update_term_meta( $term_id, self::META_REPEATER . "_{$index}_url", esc_url_raw( $url ) );
		update_term_meta( $term_id, '_' . self::META_REPEATER . "_{$index}_url", self::FIELD_URL );
		update_term_meta( $term_id, self::META_REPEATER, $index + 1 );
		update_term_meta( $term_id, '_' . self::META_REPEATER, self::FIELD_REPEATER );
	}
	/**
	 * Fetch a host and pull a name out of its <head>.
	 *
	 * @param string   $host    Hostname.
	 * @param int|null $timeout Seconds to wait. Defaults to TIMEOUT; callers in
	 *                          an admin request should pass UI_TIMEOUT.
	 * @return array{status: string, name: string, source: string}
	 */
	public static function discover_name( string $host, ?int $timeout = null ): array {
		$response = wp_remote_get(
			'https://' . $host . '/',
			array(
				'timeout'            => $timeout ?? self::TIMEOUT,
				'redirection'        => 3,
				'reject_unsafe_urls' => true,
				'user-agent'         => 'LezWatch.TV provider-name lookup (+https://lezwatchtv.com)',
				'headers'            => array( 'Accept' => 'text/html' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			lwtv_plugin()->debug_log( 'shows', sprintf( 'Ways to Watch enrich: %s failed - %s', $host, $response->get_error_message() ) );
			return array(
				'status' => 'error',
				'name'   => '',
				'source' => '',
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			lwtv_plugin()->debug_log( 'shows', sprintf( 'Ways to Watch enrich: %s returned HTTP %d', $host, $code ) );
			return array(
				'status' => 'error',
				'name'   => '',
				'source' => '',
			);
		}

		$html = substr( (string) wp_remote_retrieve_body( $response ), 0, self::MAX_BYTES );

		foreach ( array(
			Watch_Host_Names::SOURCE_OG_SITE_NAME => 'property',
			Watch_Host_Names::SOURCE_APP_NAME     => 'name',
		) as $source => $attribute ) {
			$value = self::meta_content( $html, $attribute, $source );

			if ( '' !== $value && Watch_Host_Names::is_plausible_name( $value ) ) {
				return array(
					'status' => 'ok',
					'name'   => Watch_Host_Names::sanitize_name( $value ),
					'source' => $source,
				);
			}
		}

		// Deliberately no <title> fallback. Titles are "Watch Full Episodes |
		// ABC" and similar, which is worse than guessing from the hostname.
		return array(
			'status' => 'ok',
			'name'   => '',
			'source' => Watch_Host_Names::SOURCE_NONE,
		);
	}

	/**
	 * Pull one meta tag's content out of raw HTML.
	 *
	 * Attribute order varies, so both orderings are tried.
	 *
	 * @param string $html      Raw HTML.
	 * @param string $attribute 'property' or 'name'.
	 * @param string $key       The attribute value to match.
	 * @return string
	 */
	private static function meta_content( string $html, string $attribute, string $key ): string {
		$quoted = preg_quote( $key, '#' );

		$patterns = array(
			'#<meta[^>]+' . $attribute . '=["\']' . $quoted . '["\'][^>]+content=["\']([^"\']*)["\']#i',
			'#<meta[^>]+content=["\']([^"\']*)["\'][^>]+' . $attribute . '=["\']' . $quoted . '["\']#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $matches ) ) {
				return $matches[1];
			}
		}

		return '';
	}
}
