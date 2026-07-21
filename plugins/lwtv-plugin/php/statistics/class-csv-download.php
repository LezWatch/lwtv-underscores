<?php
/**
 * CSV download for statistics views.
 *
 * Intercepts `?download=csv` on the statistics page and streams the underlying
 * chart data as a CSV for a hard-whitelisted set of views. Reads only through
 * the existing (transient-cached) builders via the `array` format.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Statistics\Format\CSV;

class CSV_Download {

	/**
	 * Construct — hook the interceptor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_download' ) );
	}

	/**
	 * On the statistics page, if `?download=csv` is set and the current view is
	 * supported, emit a CSV and exit. Otherwise return and let the page render.
	 *
	 * @return void
	 */
	public function maybe_download() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only export; no state change.
		if ( ! isset( $_GET['download'] ) || 'csv' !== sanitize_key( wp_unslash( $_GET['download'] ) ) ) {
			return;
		}

		if ( ! is_page( 'statistics' ) ) {
			return;
		}

		$group   = sanitize_key( get_query_var( 'statistics', '' ) );
		$view    = sanitize_key( get_query_var( 'view', '' ) );
		$nation  = sanitize_title( get_query_var( 'nation', '' ) );
		$station = sanitize_title( get_query_var( 'station', '' ) );

		$payload = $this->resolve( $group, $view, $nation, $station );

		// Unsupported view (or invalid context): render the page normally.
		if ( null === $payload ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $payload['filename'] . '"' );
		header( 'X-Robots-Tag: noindex' );

		// The CSV is fully built + injection-hardened by the formatter.
		echo ( new CSV() )->build( $payload['rows'], $payload['headers'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Match the current context to a supported view and return its CSV payload,
	 * or null when nothing matches (or a required slug is missing/invalid).
	 *
	 * @param string $group   The `statistics` query var (characters/shows/nations/stations/death).
	 * @param string $view    The `view` query var (on-air/years/stations/nations).
	 * @param string $nation  The `nation` query var (slug), if any.
	 * @param string $station The `station` query var (slug), if any.
	 * @return array|null { 'rows' => array, 'headers' => array, 'filename' => string } or null.
	 */
	private function resolve( $group, $view, $nation, $station ) {
		$today = gmdate( 'Y-m-d' );

		// Characters on air per year.
		if ( 'characters' === $group && 'on-air' === $view ) {
			$raw = lwtv_plugin()->generate_characters_statistics( 'array', 'on-air' );
			$raw = ( is_array( $raw ) && ! empty( $raw ) ) ? (array) reset( $raw ) : array();
			return array(
				'rows'     => $this->year_rows( $raw, 'name', 'count' ),
				'headers'  => array( __( 'Year', 'lwtv' ), __( 'Characters On Air', 'lwtv' ) ),
				'filename' => "lwtv-characters-on-air-{$today}.csv",
			);
		}

		// Shows on air per year.
		if ( 'shows' === $group && 'on-air' === $view ) {
			$raw = lwtv_plugin()->generate_shows_statistics( 'array', 'on-air' );
			$raw = ( is_array( $raw ) && ! empty( $raw ) ) ? (array) reset( $raw ) : array();
			return array(
				'rows'     => $this->year_rows( $raw, 'name', 'count' ),
				'headers'  => array( __( 'Year', 'lwtv' ), __( 'Shows On Air', 'lwtv' ) ),
				'filename' => "lwtv-shows-on-air-{$today}.csv",
			);
		}

		// Shows on air per year for a single nation.
		if ( 'nations' === $group && 'on-air' === $view && '' !== $nation ) {
			if ( ! term_exists( $nation, 'lez_country' ) ) {
				return null;
			}
			$raw = (array) lwtv_plugin()->generate_nation_statistics( $nation, 'on-air', 'array' );
			return array(
				'rows'     => $this->year_rows( $raw, 'name', 'count' ),
				'headers'  => array( __( 'Year', 'lwtv' ), __( 'Shows On Air', 'lwtv' ) ),
				'filename' => "lwtv-nations-on-air-{$nation}-{$today}.csv",
			);
		}

		// Shows on air per year for a single station.
		if ( 'stations' === $group && 'on-air' === $view && '' !== $station ) {
			if ( ! term_exists( $station, 'lez_stations' ) ) {
				return null;
			}
			$raw = (array) lwtv_plugin()->generate_station_statistics( $station, 'on-air', 'array' );
			return array(
				'rows'     => $this->year_rows( $raw, 'name', 'count' ),
				'headers'  => array( __( 'Year', 'lwtv' ), __( 'Shows On Air', 'lwtv' ) ),
				'filename' => "lwtv-stations-on-air-{$station}-{$today}.csv",
			);
		}

		// Character deaths per year.
		if ( 'death' === $group && 'years' === $view ) {
			$raw = (array) lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'array' );
			return array(
				'rows'     => $this->year_rows( $raw, 'death_year', 'death_count' ),
				'headers'  => array( __( 'Year', 'lwtv' ), __( 'Deaths', 'lwtv' ) ),
				'filename' => "lwtv-death-years-{$today}.csv",
			);
		}

		// Deaths per station / network.
		if ( 'death' === $group && 'stations' === $view ) {
			$raw = (array) lwtv_plugin()->generate_dead_statistics( 'shows', 'stations', 'array' );
			return array(
				'rows'     => $this->label_rows( $raw ),
				'headers'  => array( __( 'Station', 'lwtv' ), __( 'Deaths', 'lwtv' ) ),
				'filename' => "lwtv-death-stations-{$today}.csv",
			);
		}

		// Deaths per country.
		if ( 'death' === $group && 'nations' === $view ) {
			$raw = (array) lwtv_plugin()->generate_dead_statistics( 'shows', 'nations', 'array' );
			return array(
				'rows'     => $this->label_rows( $raw ),
				'headers'  => array( __( 'Nation', 'lwtv' ), __( 'Deaths', 'lwtv' ) ),
				'filename' => "lwtv-death-nations-{$today}.csv",
			);
		}

		// Full actor roster: name, gender, sexuality, characters played.
		if ( 'actors' === $group && ( '' === $view || 'overview' === $view ) ) {
			return array(
				'rows'     => $this->actor_roster_rows(),
				'headers'  => array( __( 'Actor Name', 'lwtv' ), __( 'Gender', 'lwtv' ), __( 'Sexuality', 'lwtv' ), __( 'Characters Played', 'lwtv' ) ),
				'filename' => "lwtv-actors-{$today}.csv",
			);
		}

		// All nations: name, shows, characters, dead.
		if ( 'nations' === $group && '' === $view && '' === $nation ) {
			$raw = (array) lwtv_plugin()->generate_nation_statistics( 'all', 'all', 'array' );
			return array(
				'rows'     => $this->summary_rows( $raw ),
				'headers'  => array( __( 'Nation', 'lwtv' ), __( 'Shows', 'lwtv' ), __( 'Characters', 'lwtv' ), __( 'Dead', 'lwtv' ) ),
				'filename' => "lwtv-nations-{$today}.csv",
			);
		}

		// All stations: name, shows, characters, dead.
		if ( 'stations' === $group && '' === $view && '' === $station ) {
			$raw = (array) lwtv_plugin()->generate_station_statistics( 'all', 'all', 'array' );
			return array(
				'rows'     => $this->summary_rows( $raw ),
				'headers'  => array( __( 'Station', 'lwtv' ), __( 'Shows', 'lwtv' ), __( 'Characters', 'lwtv' ), __( 'Dead', 'lwtv' ) ),
				'filename' => "lwtv-stations-{$today}.csv",
			);
		}

		// Death overview: character deaths per year (same series as the years view).
		if ( 'death' === $group && '' === $view ) {
			$raw = (array) lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'array' );
			return array(
				'rows'     => $this->year_rows( $raw, 'death_year', 'death_count' ),
				'headers'  => array( __( 'Year', 'lwtv' ), __( 'Number of Dead', 'lwtv' ) ),
				'filename' => "lwtv-death-years-{$today}.csv",
			);
		}

		return null;
	}

	/**
	 * Densify a per-year series (filling zero-count years) and flatten to CSV
	 * rows of [ year, count ], matching what the on-screen chart plots.
	 *
	 * @param array  $raw       Raw builder rows.
	 * @param string $year_key  Row key holding the year (e.g. 'name' or 'death_year').
	 * @param string $count_key Row key holding the count (e.g. 'count' or 'death_count').
	 * @return array List of [ (string) year, (int) count ].
	 */
	private function year_rows( array $raw, $year_key, $count_key ) {
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		require_once plugin_dir_path( __DIR__ ) . 'statistics/templates/partials/phrases.php';

		$series = lwtv_stats_year_series( $raw, $year_key, $count_key );
		$rows   = array();
		foreach ( $series['rows'] as $row ) {
			$rows[] = array( (string) (int) $row['year'], (int) $row['count'] );
		}
		return $rows;
	}

	/**
	 * Flatten a ranked term list to CSV rows of [ label, count ]. The death
	 * station/nation builders key the label as 'term_name' (with 'name' as a
	 * fallback for other shapes).
	 *
	 * @param array $raw Raw builder rows.
	 * @return array List of [ (string) label, (int) count ].
	 */
	private function label_rows( array $raw ) {
		$rows = array();
		foreach ( $raw as $row ) {
			$label = (string) ( $row['term_name'] ?? $row['name'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$rows[] = array( $label, (int) ( $row['count'] ?? 0 ) );
		}
		return $rows;
	}

	/**
	 * Flatten a nation/station summary list to CSV rows of
	 * [ name, shows, characters, dead ]. Summary rows key counts as
	 * show_count / character_count / dead_count (SUM()s come back as strings).
	 *
	 * @param array $raw Raw summary rows.
	 * @return array List of [ (string) name, (int) shows, (int) characters, (int) dead ].
	 */
	private function summary_rows( array $raw ) {
		$rows = array();
		foreach ( $raw as $row ) {
			$name = (string) ( $row['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$rows[] = array(
				$name,
				(int) ( $row['show_count'] ?? 0 ),
				(int) ( $row['character_count'] ?? 0 ),
				(int) ( $row['dead_count'] ?? 0 ),
			);
		}
		return $rows;
	}

	/**
	 * Full published-actor roster as CSV rows of [ name, gender, sexuality,
	 * characters played ]. Character count is the pre-computed
	 * `lezactors_char_count` meta (no live counting). Cached for a day.
	 *
	 * @return array List of [ (string) name, (string) gender, (string) sexuality, (int) chars ].
	 */
	private function actor_roster_rows() {
		$cache_key = 'lwtv_actor_roster_csv';
		$cached    = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'post_type_actors',
				'post_status'            => 'publish',
				'posts_per_page'         => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Intentional full export; result is transient-cached.
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
				'update_post_meta_cache' => true,
			)
		);

		$rows = array();
		foreach ( $query->posts as $actor ) {
			$rows[] = array(
				$actor->post_title,
				$this->term_names( $actor->ID, 'lez_actor_gender' ),
				$this->term_names( $actor->ID, 'lez_actor_sexuality' ),
				(int) get_post_meta( $actor->ID, 'lezactors_char_count', true ),
			);
		}

		lwtv_plugin()->set_transient( $cache_key, $rows, DAY_IN_SECONDS );

		return $rows;
	}

	/**
	 * Comma-joined term names for a post in a taxonomy (usually one), or ''.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private function term_names( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}
}
