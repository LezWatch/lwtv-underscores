<?php
/**
 * Taxonomy Profile Build Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared "Most Prolific"-style queries for a single Nation or Station page
 * — the geo-taxonomy analog to Build_Actors' generate_prolific_by_*()
 * methods, parameterized by taxonomy (`lez_country` for Nations,
 * `lez_stations` for Stations) plus a single term slug, so one class serves
 * both single.php templates instead of two near-duplicate ones.
 *
 * These three facets don't share one shape the way Actors' orientation/
 * gender did, because the underlying data isn't actor-identity data:
 *
 * - Sexuality/Gender: a show can have several characters of the same
 *   term, so "which show has the most" is meaningful — same shape as
 *   Build_Actors' prolific-by-orientation, just "show" instead of "actor".
 * - Formats: a format is one-per-show, so "most" is meaningless; the
 *   analog used here is "best representative" — the highest-scored show
 *   of that format, via the site's own show score.
 * - Tropes: a trope is a boolean tag per (show, trope) pair — a show
 *   either carries it or doesn't, so there's no "most of trope X" either.
 *   The analog is a single callout: the one show that collects the most
 *   *distinct* tropes overall, which only exists because shows can and do
 *   carry several different tropes at once.
 */
class Taxonomy_Profile {

	/**
	 * Show taxonomy this profile is scoped to.
	 *
	 * @var string 'lez_country' or 'lez_stations'.
	 */
	private string $taxonomy;

	/**
	 * The nation or station term slug this profile is scoped to.
	 *
	 * @var string
	 */
	private string $term_slug;

	/**
	 * @param string $taxonomy  'lez_country' or 'lez_stations'.
	 * @param string $term_slug Nation or station term slug.
	 */
	public function __construct( string $taxonomy, string $term_slug ) {
		$this->taxonomy  = $taxonomy;
		$this->term_slug = $term_slug;
	}

	/**
	 * Per gender/sexuality term, the show (within this nation/station) with
	 * the most characters carrying that term.
	 *
	 * Reads each show's own lezshows_char_gender/lezshows_char_sexuality
	 * rollup meta (a serialized [term_slug => count] map, written by
	 * class-calculations.php at show-save time) — the same meta
	 * Build\Nations::parse_meta_breakdown() / Build\Stations::
	 * parse_meta_breakdown() sum across every show in the nation/station;
	 * this keeps the counts per-show instead of summing, to find a leader
	 * rather than a total. No new data source, just a different fold of
	 * the same already-trusted numbers.
	 *
	 * @param string $facet 'gender' or 'sexuality'.
	 * @return array [ term_slug => { 'show_id', 'name', 'url', 'count', 'term_name' } ]
	 */
	public function generate_prolific_show( string $facet ): array {
		$meta_key = ( 'gender' === $facet ) ? 'lezshows_char_gender' : 'lezshows_char_sexuality';

		$transient = $this->taxonomy . '_' . $this->term_slug . '_prolific_show_' . $facet;
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as id, p.post_title as name, pm.meta_value as breakdown
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND t.slug = %s
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
					WHERE p.post_type = 'post_type_shows'
					AND p.post_status = 'publish'
					AND pm.meta_value IS NOT NULL
					AND pm.meta_value != ''
					ORDER BY p.ID ASC",
				$this->taxonomy,
				$this->term_slug,
				$meta_key
			),
			ARRAY_A
		);

		$leaders = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$breakdown = maybe_unserialize( $row['breakdown'] );
				if ( ! is_array( $breakdown ) ) {
					continue;
				}
				foreach ( $breakdown as $term_slug => $count ) {
					$count = (int) $count;
					if ( $count <= 0 ) {
						continue;
					}
					if ( ! isset( $leaders[ $term_slug ] ) || $count > $leaders[ $term_slug ]['count'] ) {
						$leaders[ $term_slug ] = array(
							'show_id'   => (int) $row['id'],
							'name'      => $row['name'],
							'url'       => get_permalink( (int) $row['id'] ),
							'count'     => $count,
							'term_name' => ucwords( str_replace( '-', ' ', (string) $term_slug ) ),
						);
					}
				}
			}
		}

		lwtv_plugin()->set_transient( $transient, $leaders, DAY_IN_SECONDS );

		return $leaders;
	}

	/**
	 * Per format term, the highest-scored show (within this nation/station)
	 * carrying that format — see class docblock for why "highest-scored"
	 * stands in for "most" here.
	 *
	 * @return array [ format_slug => { 'show_id', 'name', 'url', 'score', 'term_name' } ]
	 */
	public function generate_top_rated_by_format(): array {
		$transient = $this->taxonomy . '_' . $this->term_slug . '_top_rated_by_format';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as id, p.post_title as name, ft.slug as format_slug, ft.name as format_name, pm.meta_value as score
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND t.slug = %s
					INNER JOIN {$wpdb->term_relationships} ftr ON ftr.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} ftt ON ftr.term_taxonomy_id = ftt.term_taxonomy_id AND ftt.taxonomy = 'lez_formats'
					INNER JOIN {$wpdb->terms} ft ON ftt.term_id = ft.term_id
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'lezshows_the_score'
					WHERE p.post_type = 'post_type_shows'
					AND p.post_status = 'publish'
					AND pm.meta_value IS NOT NULL
					AND pm.meta_value != ''
					ORDER BY p.ID ASC",
				$this->taxonomy,
				$this->term_slug
			),
			ARRAY_A
		);

		$leaders = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$score = (float) $row['score'];
				$slug  = $row['format_slug'];
				if ( ! isset( $leaders[ $slug ] ) || $score > $leaders[ $slug ]['score'] ) {
					$leaders[ $slug ] = array(
						'show_id'   => (int) $row['id'],
						'name'      => $row['name'],
						'url'       => get_permalink( (int) $row['id'] ),
						// Store the raw score, NOT a rounded one: the next row's raw
						// score is compared against this value, and rounding first
						// would let a rounded-up leader beat a genuinely higher
						// score (8.36 stored as 8.4 wrongly rejects 8.39). The
						// template does the 1-decimal formatting for display.
						'score'     => $score,
						'term_name' => $row['format_name'],
					);
				}
			}
		}

		lwtv_plugin()->set_transient( $transient, $leaders, DAY_IN_SECONDS );

		return $leaders;
	}

	/**
	 * The single show (within this nation/station) tagged with the most
	 * distinct lez_tropes terms — see class docblock for why this replaces
	 * a per-trope leader.
	 *
	 * @return array { 'show_id', 'name', 'url', 'count' } or empty if none qualify.
	 */
	public function generate_most_trope_heavy_show(): array {
		$transient = $this->taxonomy . '_' . $this->term_slug . '_most_trope_heavy_show';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as id, p.post_title as name, COUNT(DISTINCT tr2.term_taxonomy_id) as trope_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND t.slug = %s
					INNER JOIN {$wpdb->term_relationships} tr2 ON tr2.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.taxonomy = 'lez_tropes'
					WHERE p.post_type = 'post_type_shows'
					AND p.post_status = 'publish'
					GROUP BY p.ID
					ORDER BY trope_count DESC, p.ID ASC
					LIMIT 1",
				$this->taxonomy,
				$this->term_slug
			),
			ARRAY_A
		);

		$leader = array();
		if ( is_array( $results ) && ! empty( $results ) ) {
			$row = $results[0];
			if ( (int) $row['trope_count'] > 0 ) {
				$leader = array(
					'show_id' => (int) $row['id'],
					'name'    => $row['name'],
					'url'     => get_permalink( (int) $row['id'] ),
					'count'   => (int) $row['trope_count'],
				);
			}
		}

		lwtv_plugin()->set_transient( $transient, $leader, DAY_IN_SECONDS );

		return $leader;
	}
}
