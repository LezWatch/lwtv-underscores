<?php
/**
 * Taxonomy Death Leaders Query Class
 *
 * Sums each published show's canonical lezshows_char_count /
 * lezshows_dead_count postmeta — the same two fields Show_Death_Leaders
 * already reads for the Deaths → Shows highlights — per term on a given
 * taxonomy (lez_country / lez_stations), to answer "which network/nation is
 * disproportionately deadly" rather than "which one just has the most
 * shows." Death → Nations and Death → Stations already rank networks/
 * countries by a raw show-count ("shows tagged dead-queers"), and that
 * page's own copy already admits the honest problem: more shows on a
 * network just means more deaths. This is the rate-based fix for that.
 *
 * A show tagged with more than one term on the taxonomy (e.g. a
 * co-production airing on two networks) contributes its full character/
 * death counts to each term it carries — the same multi-term attribution
 * Dead::generate_shows_by_taxonomy() already uses for the existing raw list.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Taxonomy_Death_Leaders {

	/**
	 * A death rate only counts toward "deadliest by rate" once a term's
	 * combined cast is at least this large — otherwise a network with one
	 * small show can look disproportionately lethal.
	 *
	 * @var int
	 */
	const MIN_CHARS_FOR_RATE = 5;

	/**
	 * Taxonomy slug this instance summarizes.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * @param string $taxonomy 'lez_country' or 'lez_stations' ('lez_nations' is
	 *                          normalized to 'lez_country', matching
	 *                          Dead::generate_shows_by_taxonomy()'s own alias).
	 */
	public function __construct( string $taxonomy ) {
		$this->taxonomy = ( 'lez_nations' === $taxonomy ) ? 'lez_country' : $taxonomy;
	}

	/**
	 * Generate the taxonomy-wide death summary.
	 *
	 * @return array {
	 *     @type int        $total_terms      Terms with at least one published show — the
	 *                                        correct denominator for $terms_with_death, since
	 *                                        both are drawn from this same published-shows
	 *                                        query. wp_count_terms() is the wrong denominator
	 *                                        here: with its default hide_empty=false it counts
	 *                                        every term row regardless of post status, so a
	 *                                        term sitting on drafts only (or nothing at all)
	 *                                        would inflate that total without ever being able
	 *                                        to show up in $terms_with_death.
	 *     @type int        $terms_with_death Terms with at least one recorded death.
	 *     @type array|null $deadliest        { 'slug', 'name', 'char_count', 'dead_count', 'pct' } or null.
	 * }
	 */
	public function generate(): array {
		$transient = 'taxonomy_death_leaders_' . sanitize_key( $this->taxonomy );
		$data      = lwtv_plugin()->get_transient( $transient );

		if ( false === $data || ! is_array( $data ) ) {
			$data = $this->build();

			if ( ! empty( $data ) ) {
				lwtv_plugin()->set_transient( $transient, $data, DAY_IN_SECONDS );
			}
		}

		return $data;
	}

	/**
	 * Query every published show's char/dead counts joined to this
	 * taxonomy's terms, fold multi-term shows into per-term totals, and
	 * summarize.
	 *
	 * @return array See generate()'s return shape.
	 */
	private function build(): array {
		global $wpdb;

		try {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						t.slug as term_slug,
						t.name as term_name,
						COALESCE(cc.meta_value, 0) as char_count,
						COALESCE(dc.meta_value, 0) as dead_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
					INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
					LEFT JOIN {$wpdb->postmeta} cc ON cc.post_id = p.ID AND cc.meta_key = 'lezshows_char_count'
					LEFT JOIN {$wpdb->postmeta} dc ON dc.post_id = p.ID AND dc.meta_key = 'lezshows_dead_count'
					WHERE p.post_type = 'post_type_shows'
					AND p.post_status = 'publish'",
					$this->taxonomy
				),
				ARRAY_A
			);

			if ( ! is_array( $results ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Taxonomy death leaders query failed (' . $this->taxonomy . '): ' . $wpdb->last_error );
				return array();
			}

			$terms = array();
			foreach ( $results as $row ) {
				$slug = (string) $row['term_slug'];

				if ( ! isset( $terms[ $slug ] ) ) {
					$terms[ $slug ] = array(
						'name'       => (string) $row['term_name'],
						'char_count' => 0,
						'dead_count' => 0,
					);
				}

				$terms[ $slug ]['char_count'] += (int) $row['char_count'];
				$terms[ $slug ]['dead_count'] += (int) $row['dead_count'];
			}

			$terms_with_death = 0;
			$deadliest        = null;

			foreach ( $terms as $slug => $term ) {
				if ( $term['dead_count'] > 0 ) {
					++$terms_with_death;
				}

				if ( $term['char_count'] < self::MIN_CHARS_FOR_RATE || $term['dead_count'] <= 0 ) {
					continue;
				}

				$pct = round( ( $term['dead_count'] / $term['char_count'] ) * 100, 1 );

				if ( null === $deadliest
					|| $pct > $deadliest['pct']
					|| ( $pct === $deadliest['pct'] && $term['char_count'] > $deadliest['char_count'] )
				) {
					$deadliest = array(
						'slug'       => $slug,
						'name'       => $term['name'],
						'char_count' => $term['char_count'],
						'dead_count' => $term['dead_count'],
						'pct'        => $pct,
					);
				}
			}

			return array(
				'total_terms'      => count( $terms ),
				'terms_with_death' => $terms_with_death,
				'deadliest'        => $deadliest,
			);
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error generating taxonomy death leaders (' . $this->taxonomy . '): ' . $e->getMessage() );
			return array();
		}
	}
}
