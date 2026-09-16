<?php
/**
 * Unit tests for the TMDB response-shape reader.
 *
 * _Components\CPTs::get_tmdb_info() returns two incompatible shapes and says
 * nothing about which one it picked: a /3/{tv,person}/{id} detail object when the
 * post already has a TMDB ID, a /3/find/{imdb_id} envelope when it only has an
 * IMDb one. A post therefore changes shape the moment a backfill writes its ID,
 * silently, with no code change involved.
 *
 * The worked example is Vigil (TMDB 126167). It read as tv_results[0] for as long
 * as we only held its IMDb ID; the day the backfill wrote lezshows_tmdb_id it
 * started arriving as a flat detail object, and the show score -- which read only
 * tv_results[0].vote_average -- went to TBD without anything erroring.
 *
 * These tests pin both shapes and, more importantly, the three-way distinction in
 * imdb_id(): an ID, '' for "TMDB has the record and no IMDb link", and null for
 * "this shape cannot answer". Collapsing the last two is what let a find envelope
 * read as a verdict.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use LWTV\_Helpers\Tmdb_Response;

class TmdbResponseTest extends TestCase {

	/**
	 * Trimmed /3/tv/126167 -- the shape Vigil returns now.
	 *
	 * @return array
	 */
	private function tv_detail(): array {
		return array(
			'id'           => 126167,
			'name'         => 'Vigil',
			'vote_average' => 7.2,
			'vote_count'   => 276,
			'seasons'      => array(
				array(
					'season_number' => 1,
					'vote_average'  => 6.4,
				),
			),
		);
	}

	/**
	 * Trimmed /3/find/tt11846996?external_source=imdb_id -- the shape it used to
	 * return, and still does for any show we have no TMDB ID for.
	 *
	 * @return array
	 */
	private function tv_find(): array {
		return array(
			'movie_results'      => array(),
			'person_results'     => array(),
			'tv_results'         => array(
				array(
					'id'           => 126167,
					'name'         => 'Vigil',
					'vote_average' => 7.2,
				),
			),
			'tv_episode_results' => array(),
			'tv_season_results'  => array(),
		);
	}

	/*
	 * result_key()
	 */

	public function test_result_key_knows_our_two_post_types(): void {
		$this->assertSame( 'tv_results', Tmdb_Response::result_key( 'post_type_shows' ) );
		$this->assertSame( 'person_results', Tmdb_Response::result_key( 'post_type_actors' ) );
	}

	public function test_result_key_is_empty_for_a_type_tmdb_knows_nothing_about(): void {
		// Characters have no TMDB counterpart. get_tmdb_info() refuses them
		// outright, and this must not invent a bucket for them either.
		$this->assertSame( '', Tmdb_Response::result_key( 'post_type_characters' ) );
		$this->assertSame( '', Tmdb_Response::result_key( '' ) );
	}

	/*
	 * id()
	 */

	public function test_id_reads_the_detail_shape(): void {
		$this->assertSame( '126167', Tmdb_Response::id( $this->tv_detail(), 'post_type_shows' ) );
	}

	public function test_id_reads_the_find_envelope(): void {
		$this->assertSame( '126167', Tmdb_Response::id( $this->tv_find(), 'post_type_shows' ) );
	}

	public function test_id_reads_a_person_envelope(): void {
		$data = array(
			'person_results' => array( array( 'id' => 1466234 ) ),
			'tv_results'     => array(),
		);

		$this->assertSame( '1466234', Tmdb_Response::id( $data, 'post_type_actors' ) );
	}

	public function test_id_does_not_cross_the_buckets(): void {
		// A TV movie lands in movie_results, and an actor search never populates
		// tv_results. Reading the wrong bucket would store an ID of the wrong kind
		// against the post -- see CLI\TMDB::look_up(), which reports that case
		// rather than saving it.
		$this->assertSame( '', Tmdb_Response::id( $this->tv_find(), 'post_type_actors' ) );

		$movie_only = array(
			'movie_results' => array( array( 'id' => 99999 ) ),
			'tv_results'    => array(),
		);

		$this->assertSame( '', Tmdb_Response::id( $movie_only, 'post_type_shows' ) );
	}

	public function test_id_handles_an_empty_or_failed_response(): void {
		// get_tmdb_info() returns false on transport failure and API errors, and
		// null when the body was not JSON at all.
		$this->assertSame( '', Tmdb_Response::id( false, 'post_type_shows' ) );
		$this->assertSame( '', Tmdb_Response::id( null, 'post_type_shows' ) );
		$this->assertSame( '', Tmdb_Response::id( array(), 'post_type_shows' ) );
		$this->assertSame( '', Tmdb_Response::id( $this->tv_find(), 'post_type_characters' ) );
	}

	public function test_id_is_returned_as_a_string(): void {
		// JSON decodes these as ints; the meta they feed is compared as a string.
		$this->assertSame( '126167', Tmdb_Response::id( $this->tv_detail(), 'post_type_shows' ) );
	}

	/*
	 * vote_average()
	 */

	public function test_vote_average_reads_both_shapes(): void {
		$this->assertSame( 7.2, Tmdb_Response::vote_average( $this->tv_detail(), 'post_type_shows' ) );
		$this->assertSame( 7.2, Tmdb_Response::vote_average( $this->tv_find(), 'post_type_shows' ) );
	}

	public function test_vote_average_ignores_the_per_season_averages(): void {
		// The detail object carries a vote_average inside every entry of seasons[]
		// as well as at the top level. Series 3 of Vigil sits at 2.2 against the
		// show's 7.2, so picking the wrong one is a visibly wrong score.
		$this->assertSame( 7.2, Tmdb_Response::vote_average( $this->tv_detail(), 'post_type_shows' ) );
	}

	public function test_vote_average_is_null_when_absent(): void {
		// Null, not 0.0: the caller stores 'TBD' for this and retries tomorrow,
		// where a 0 would cache as a real score.
		$this->assertNull( Tmdb_Response::vote_average( array( 'id' => 1 ), 'post_type_shows' ) );
		$this->assertNull( Tmdb_Response::vote_average( false, 'post_type_shows' ) );
		$this->assertNull( Tmdb_Response::vote_average( array(), 'post_type_shows' ) );
	}

	public function test_vote_average_treats_zero_as_unrated(): void {
		// TMDB's user scale runs 0.5 to 10, so it cannot express "rated zero" and
		// sends a bare 0 for anything nobody has rated. Storing that as a score
		// would put a hard 0 on the show and cache it for a day.
		$this->assertNull( Tmdb_Response::vote_average( array( 'vote_average' => 0 ), 'post_type_shows' ) );
		$this->assertNull( Tmdb_Response::vote_average( array( 'vote_average' => 0.0 ), 'post_type_shows' ) );
		$this->assertNull( Tmdb_Response::vote_average( array( 'vote_average' => '0' ), 'post_type_shows' ) );
	}

	public function test_vote_average_trusts_a_zero_vote_count_over_the_average(): void {
		// The unaired-episode case from the live payload: vote_average 0.0 next to
		// vote_count 0. Where TMDB sends a count it settles the question outright,
		// so a stray non-zero average alongside it is still no rating.
		$this->assertNull( Tmdb_Response::vote_average( array( 'vote_average' => 1.0, 'vote_count' => 0 ), 'post_type_shows' ) );
		$this->assertNull(
			Tmdb_Response::vote_average(
				array( 'tv_results' => array( array( 'vote_average' => 1.0, 'vote_count' => 0 ) ) ),
				'post_type_shows'
			)
		);
	}

	public function test_vote_average_keeps_a_real_rating_with_votes_behind_it(): void {
		// Vigil: 7.2 from 276 votes. The floor of the scale, 0.5, has to survive
		// the unrated check too.
		$this->assertSame( 7.2, Tmdb_Response::vote_average( $this->tv_detail(), 'post_type_shows' ) );
		$this->assertSame( 0.5, Tmdb_Response::vote_average( array( 'vote_average' => 0.5, 'vote_count' => 2 ), 'post_type_shows' ) );
	}

	public function test_vote_average_is_null_for_a_type_with_no_bucket(): void {
		$this->assertNull( Tmdb_Response::vote_average( $this->tv_find(), 'post_type_characters' ) );
		$this->assertNull( Tmdb_Response::vote_average( $this->tv_find(), 'post_type_actors' ) );
	}

	/*
	 * imdb_id()
	 */

	public function test_imdb_id_reads_a_person_detail_object(): void {
		$this->assertSame( 'nm0000123', Tmdb_Response::imdb_id( array( 'id' => 5, 'imdb_id' => 'nm0000123' ) ) );
	}

	public function test_imdb_id_is_empty_when_tmdb_holds_the_person_and_no_link(): void {
		// TMDB sends the key with a null value. That is a real answer -- it clears
		// a previously reported stale flag -- so it must be '' and not null.
		$this->assertSame( '', Tmdb_Response::imdb_id( array( 'id' => 5, 'imdb_id' => null ) ) );
		$this->assertSame( '', Tmdb_Response::imdb_id( array( 'id' => 5, 'imdb_id' => '' ) ) );
	}

	public function test_imdb_id_is_null_for_a_find_envelope(): void {
		// The bug this class exists to fix. A find response carries no imdb_id
		// anywhere, and reading that absence as '' told Imdb_Canonical "TMDB has
		// no IMDb link for this actor" -- a verdict invented from the wrong shape.
		$data = array(
			'person_results' => array( array( 'id' => 1466234, 'name' => 'Tom Edge' ) ),
			'tv_results'     => array(),
		);

		$this->assertNull( Tmdb_Response::imdb_id( $data ) );
	}

	public function test_imdb_id_is_null_for_a_tv_detail_object(): void {
		// /tv/{id} carries no imdb_id at all -- it needs external_ids appended.
		// Shows are verified against TVMaze for exactly this reason, and if that
		// ever changes this must report "could not ask" rather than "no link".
		$this->assertNull( Tmdb_Response::imdb_id( $this->tv_detail() ) );
	}

	public function test_imdb_id_reads_an_appended_external_ids_block(): void {
		$data = array(
			'id'           => 126167,
			'external_ids' => array(
				'imdb_id'    => 'tt11846996',
				'tvdb_id'    => 391153,
				'twitter_id' => null,
			),
		);

		$this->assertSame( 'tt11846996', Tmdb_Response::imdb_id( $data ) );
	}

	public function test_imdb_id_prefers_external_ids_over_a_top_level_key(): void {
		// append_to_response=external_ids is the more specific answer, and on a
		// person response the two agree anyway.
		$data = array(
			'imdb_id'      => 'nm0000123',
			'external_ids' => array( 'imdb_id' => 'nm9999999' ),
		);

		$this->assertSame( 'nm9999999', Tmdb_Response::imdb_id( $data ) );
	}

	public function test_imdb_id_is_null_when_there_was_no_response(): void {
		$this->assertNull( Tmdb_Response::imdb_id( false ) );
		$this->assertNull( Tmdb_Response::imdb_id( null ) );
		$this->assertNull( Tmdb_Response::imdb_id( array() ) );
	}

	public function test_imdb_id_trims(): void {
		$this->assertSame( 'nm0000123', Tmdb_Response::imdb_id( array( 'imdb_id' => '  nm0000123 ' ) ) );
	}
}
