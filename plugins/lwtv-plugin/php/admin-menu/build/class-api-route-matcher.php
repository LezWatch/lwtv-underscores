<?php
/**
 * Map a logged request path onto the registered REST route that serves it.
 *
 * The rollup CSV keeps raw paths (`/wp-json/lwtv/v1/stats/shows/name/x`), so
 * one route shows up as hundreds of rows. We group them using the route keys
 * WordPress itself registered, matched the same way WP_REST_Server does:
 * `@^route$@i` against the decoded path.
 *
 * One deliberate difference from core: when more than one route matches, the
 * one with the most fixed (non-parameter) text wins, so a generic `stats/(type)`
 * never swallows a literal `stats/shows`. Ties keep registration order.
 *
 * Pure: give it the route keys, ask it about paths.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Route_Matcher {

	/**
	 * Group name for paths no registered route matches. Kept visible, never dropped.
	 */
	const UNMATCHED = '(unmatched)';

	/**
	 * Route keys in registration order.
	 *
	 * @var list<string>
	 */
	private array $routes;

	/**
	 * Route keys, most specific first.
	 *
	 * @var list<string>
	 */
	private array $ordered;

	/**
	 * The REST prefix, without slashes (rest_get_url_prefix()).
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Results already worked out, keyed by raw endpoint.
	 *
	 * @var array<string, string>
	 */
	private array $memo = array();

	/**
	 * @param array<int, string> $routes Route keys from rest_get_server()->get_routes().
	 * @param string             $prefix rest_get_url_prefix().
	 */
	public function __construct( array $routes, string $prefix = 'wp-json' ) {
		$this->routes = array_values( array_map( 'strval', $routes ) );
		$this->prefix = trim( $prefix, '/' );

		$weighted = array();
		foreach ( $this->routes as $i => $route ) {
			$weighted[] = array( $route, self::specificity( $route ), $i );
		}
		usort(
			$weighted,
			static fn( $a, $b ) => ( $b[1] <=> $a[1] ) ?: ( $a[2] <=> $b[2] )
		);
		$this->ordered = array_column( $weighted, 0 );
	}

	/**
	 * Route keys in registration order.
	 *
	 * @return list<string>
	 */
	public function routes(): array {
		return $this->routes;
	}

	/**
	 * The route key that serves this logged path, or UNMATCHED.
	 *
	 * @param string $endpoint Path as logged, e.g. /wp-json/lwtv/v1/last-death.
	 * @return string
	 */
	public function match( string $endpoint ): string {
		if ( isset( $this->memo[ $endpoint ] ) ) {
			return $this->memo[ $endpoint ];
		}

		$path = rawurldecode( $endpoint );
		if ( '' !== $this->prefix && str_starts_with( $path, '/' . $this->prefix . '/' ) ) {
			$path = substr( $path, strlen( $this->prefix ) + 1 );
		}
		$path = '/' . trim( $path, '/' );

		$found = self::UNMATCHED;
		foreach ( $this->ordered as $route ) {
			if ( 1 === @preg_match( '@^' . $route . '$@i', $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a malformed route regex must not break the page; it just never matches.
				$found = $route;
				break;
			}
		}

		$this->memo[ $endpoint ] = $found;
		return $found;
	}

	/**
	 * A readable version of a route key: `(?P<type>[a-z]+)` becomes `{type}`.
	 *
	 * @param string $route Route key.
	 * @return string
	 */
	public static function label( string $route ): string {
		return (string) preg_replace( '/\(\?P<(\w+)>[^)]*\)/', '{$1}', $route );
	}

	/**
	 * How much of a route is fixed text rather than a parameter.
	 *
	 * @param string $route Route key.
	 * @return int
	 */
	private static function specificity( string $route ): int {
		return strlen( (string) preg_replace( '/\(\?P<\w+>[^)]*\)/', '', $route ) );
	}
}
