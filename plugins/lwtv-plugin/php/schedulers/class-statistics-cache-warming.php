<?php
/**
 * Statistics Cache Warming Handler
 *
 * Handles background cache warming for statistics after content changes
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Characters as CPT_Characters;

use LWTV\This_Year\Build\Characters_Builder;
use LWTV\This_Year\Build\Shows_Builder;
use LWTV\_Components\Statistics_Optimized;
use LWTV\Statistics\Build\Dead as Dead_Stats;
use LWTV\Statistics\Build\Stations as Stations_Stats;
use LWTV\Statistics\Build\Nations as Nations_Stats;
use LWTV\Statistics\Build\On_Air_Optimized as On_Air_Stats;
use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;
use LWTV\Statistics\Build\Queer_IRL as Queer_IRL_Stats;
use LWTV\Statistics\Build\Formats as Build_Formats;
use LWTV\Statistics\Build\We_Love_It as Build_Loved;
use LWTV\Statistics\Build\Worth_It as Build_Worth_It;

/**
 * Class Statistics_Cache_Warming
 */
class Statistics_Cache_Warming {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'lwtv_warm_statistics_cache', array( $this, 'warm_cache_tier' ) );
	}

	/**
	 * Warm a specific cache tier
	 *
	 * @param string $tier    The cache tier to warm
	 * @param int    $post_id The post ID that triggered the warming
	 * @return void
	 */
	public function warm_cache_tier( string $tier, int $post_id = 0 ): void {
		lwtv_plugin()->error_log( 'cache-warming', "Starting cache warming for tier: {$tier}, post ID: {$post_id}" );

		switch ( $tier ) {
			case 'counts':
				$this->warm_count_caches( $post_id );
				break;
			case 'derived':
				$this->warm_derived_caches( $post_id );
				break;
			case 'stable':
				$this->warm_stable_caches();
				break;
		}

		lwtv_plugin()->error_log( 'cache-warming', "Completed cache warming for tier: {$tier}" );
	}

	/**
	 * Warm count-related caches
	 *
	 * @param int $post_id
	 * @return void
	 */
	private function warm_count_caches(): void {
		$this->warm_character_counts();
		$this->warm_shows_counts();
		$this->warm_death_counts();
		$this->warm_actor_counts();
	}

	/**
	 * Warm derived statistics caches
	 *
	 * @param int $post_id
	 * @return void
	 */
	private function warm_derived_caches(): void {
		$this->warm_death_statistics();
	}

	/**
	 * Warm stable data caches
	 *
	 * @return void
	 */
	private function warm_stable_caches(): void {
		$this->warm_station_statistics();
		$this->warm_nation_statistics();
		$this->warm_taxonomy_statistics();
		$this->warm_on_air_statistics();
		$this->warm_queer_irl_statistics();
		$this->warm_formats_statistics();
		$this->warm_loved_statistics();
		$this->warm_worth_it_statistics();
	}

	/**
	 * Warm actor character count caches
	 *
	 * @return void
	 */
	private function warm_character_counts(): void {
		$this_year_characters = ( new Characters_Builder() )->get_characters_for_year( gmdate( 'Y' ) );
		$all_characters       = ( new Statistics_Optimized() )->generate_total_counts( 'characters' );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming actor character count caches. This year: ' . count( $this_year_characters ) . ', All: ' . $all_characters );
	}

	/**
	 * Warm shows count caches
	 *
	 * @return void
	 */
	private function warm_shows_counts(): void {
		$this_year_shows = ( new Shows_Builder() )->get_shows_for_year( gmdate( 'Y' ) );
		$all_shows       = ( new Statistics_Optimized() )->generate_total_counts( 'shows' );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming shows count caches. This year: ' . count( $this_year_shows ) . ', All: ' . $all_shows );
	}

	/**
	 * Warm death count caches
	 *
	 * @return void
	 */
	private function warm_death_counts(): void {
		$this_year_deaths = ( new Characters_Builder() )->get_dead_characters_for_year( gmdate( 'Y' ) );
		$all_deaths       = ( new Statistics_Optimized() )->generate_total_counts( 'characters', true );
		$total_dead_shows = ( new Dead_Stats() )->total_dead_shows();

		lwtv_plugin()->error_log( 'cache-warming', 'Warming death count caches. This year: ' . count( $this_year_deaths ) . ', Characters: ' . $all_deaths . ', Shows: ' . $total_dead_shows );
	}

	/**
	 * Warm actor count caches
	 *
	 * @return void
	 */
	private function warm_actor_counts(): void {
		$all_actors = ( new Statistics_Optimized() )->generate_total_counts( 'actors' );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming actor count caches. All: ' . $all_actors );
	}

	/**
	 * Warm death statistics caches
	 *
	 * @return void
	 */
	private function warm_death_statistics(): void {
		( new Dead_Stats() )->get_dead_characters_data();
		( new Dead_Stats() )->generate_years( 'array' );
		( new Dead_Stats() )->generate_years_data();
		( new Dead_Stats() )->generate_list( 'array' );
		( new Dead_Stats() )->generate_characters_by_roles( 'array' );

		$taxonomies = array( 'gender', 'sexuality' );
		foreach ( $taxonomies as $taxonomy ) {
			( new Dead_Stats() )->generate_characters_taxonomy( 'array', $taxonomy );
		}

		// This would trigger regeneration of death-related statistics
		lwtv_plugin()->error_log( 'cache-warming', 'Warming death statistics caches...' );
	}

	/**
	 * Warm station statistics caches
	 *
	 * @return void
	 */
	private function warm_station_statistics(): void {
		( new Stations_Stats() )->get_station_summaries();
		( new Stations_Stats() )->get_top_stations( 10 );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming station statistics caches...' );
	}

	/**
	 * Warm nation statistics caches
	 *
	 * @return void
	 */
	private function warm_nation_statistics(): void {
		( new Nations_Stats() )->get_nation_summaries();
		( new Nations_Stats() )->get_top_nations( 10 );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming nation statistics caches...' );
	}

	/**
	 * Warm on-air statistics caches
	 *
	 * @return void
	 */
	private function warm_on_air_statistics(): void {
		( new On_Air_Stats() )->generate( 'shows' );
		( new On_Air_Stats() )->generate( 'characters' );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming on-air statistics caches...' );
	}

	/**
	 * Warm taxonomy statistics caches
	 *
	 * @return void
	 */
	private function warm_taxonomy_statistics(): void {
		$character_taxonomies = array( 'lez_gender', 'lez_sexuality' );
		foreach ( $character_taxonomies as $taxonomy ) {
			( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Characters::SLUG, $taxonomy, true );
		}

		$show_taxonomies = array( 'lez_tropes', 'lez_genres' );
		foreach ( $show_taxonomies as $taxonomy ) {
			( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Shows::SLUG, $taxonomy, true );
		}

		$actor_taxonomies = array( 'lez_actor_gender', 'lez_actor_sexuality' );
		foreach ( $actor_taxonomies as $taxonomy ) {
			( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Actors::SLUG, $taxonomy, true );
		}

		lwtv_plugin()->error_log( 'cache-warming', 'Warming taxonomy statistics caches...' );
	}

	/**
	 * Warm queer IRL statistics caches
	 *
	 * @return void
	 */
	private function warm_queer_irl_statistics(): void {
		( new Queer_IRL_Stats() )->generate_all_data();

		lwtv_plugin()->error_log( 'cache-warming', 'Warming queer IRL statistics caches...' );
	}

	/**
	 * Warm formats statistics caches
	 *
	 * @return void
	 */
	private function warm_formats_statistics(): void {
		( new Build_Formats() )->generate( 'array' );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming formats statistics caches...' );
	}

	/**
	 * Warm loved statistics caches
	 *
	 * @return void
	 */
	private function warm_loved_statistics(): void {
		( new Build_Loved() )->generate( 'array' );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming loved statistics caches...' );
	}

	/**
	 * Warm worth it statistics caches
	 *
	 * @return void
	 */
	private function warm_worth_it_statistics(): void {
		( new Build_Worth_It() )->generate( 'array' );

		lwtv_plugin()->error_log( 'cache-warming', 'Warming worth it statistics caches...' );
	}
}
