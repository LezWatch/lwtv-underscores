<?php
/*
 * Library: ACF Pro Add Ons
 * Description: Configuration and JSON sync for Advanced Custom Fields Pro.
 * Version: 1.0.0
 */

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\CPTs\Shows;
use LWTV\Features\Languages;
use LWTV\Queeries\Get_Post_By_Imdb;
use LWTV\_Helpers\Imdb_Canonical;
use LWTV\Wikidata\Build\Qid_Trust;
use LWTV\Wikidata\Identity;

class ACF {

	/**
	 * IMDb ID fields that must be unique, and what they belong to.
	 *
	 * An IMDb ID is an identity claim, not a resemblance: two actors holding one
	 * nm ID are one person. Unlike the name check -- which warns, because people
	 * genuinely share names -- a collision here is refused outright.
	 *
	 * @var array<string, string>
	 */
	const UNIQUE_IMDB_FIELDS = array(
		'lezactors_imdb' => Actors::SLUG,
		'lezshows_imdb'  => Shows::SLUG,
	);

	/**
	 * Fields visible to administrators only.
	 * Add field names here as more CPTs are migrated.
	 */
	const ADMIN_ONLY_FIELDS = array(
		'lezactors_queer_override',
		'lezshows_worthit_show_we_love',
		'lezshows_byq_override',
	);

	/**
	 * Show boolean fields that must stay stored as 'on' for backward compat.
	 * SQL queries in class-we-love-it.php and class-get-loved.php hardcode = 'on'.
	 */
	const SHOW_LEGACY_ON_FIELDS = array(
		'lezshows_worthit_show_we_love',
		'lezshows_byq_override',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		// Only allow fields to be edited on development, unless an admin has explicitly enabled it.
		add_filter( 'acf/settings/show_admin', array( $this, 'show_admin' ) );

		// Set up JSON sync for field groups defined in this plugin.
		add_filter( 'acf/settings/save_json', array( $this, 'save_json_path' ) );
		add_filter( 'acf/settings/load_json', array( $this, 'load_json_paths' ) );
		add_action( 'acf/update_field_group', array( $this, 'prevent_json_sync_loop' ), 1 );

		// Bridge the `excerpt` ACF textarea to post_excerpt for actors and shows.
		add_filter( 'acf/load_value/name=excerpt', array( $this, 'load_excerpt_from_post' ), 10, 3 );
		add_action( 'acf/save_post', array( $this, 'save_excerpt_to_post' ), 20 );

		// Shows: load airdates sub-values from the legacy lezshows_airdates array.
		add_filter( 'acf/load_value/name=lezshows_airdates_start', array( $this, 'load_airdate_start' ), 10, 3 );
		add_filter( 'acf/load_value/name=lezshows_airdates_finish', array( $this, 'load_airdate_finish' ), 10, 3 );

		// Shows: convert legacy 'on' checkbox value so true_false fields render as checked.
		foreach ( self::SHOW_LEGACY_ON_FIELDS as $field_name ) {
			add_filter( 'acf/load_value/name=' . $field_name, array( $this, 'load_legacy_on_as_bool' ), 10, 3 );
		}

		// Shows: populate year dropdowns for air dates.
		add_filter( 'acf/load_field/name=lezshows_airdates_start', array( $this, 'load_airdates_start_choices' ) );
		add_filter( 'acf/load_field/name=lezshows_airdates_finish', array( $this, 'load_airdates_finish_choices' ) );
		add_filter( 'acf/validate_value/name=lezshows_airdates_finish', array( $this, 'validate_airdate_finish' ), 10, 4 );

		// Refuse to store an IMDb ID another post of the same type already holds.
		foreach ( array_keys( self::UNIQUE_IMDB_FIELDS ) as $imdb_field ) {
			add_filter( 'acf/validate_value/name=' . $imdb_field, array( $this, 'validate_unique_imdb' ), 10, 4 );
		}

		// Shows: populate Primary Genre choices from the show's assigned genres.
		add_filter( 'acf/load_field/name=lezshows_tvgenre_primary', array( $this, 'load_genre_primary_choices' ) );

		// Shows: populate language choices for the show_names repeater sub-field.
		add_filter( 'acf/load_field/key=field_lwtv_lezshows_show_name_type', array( $this, 'load_language_choices' ) );

		// Characters: populate year choices for the show_group appears sub-field.
		add_filter( 'acf/load_field/key=field_lwtv_lezchars_show_group_appears', array( $this, 'load_appears_choices' ) );

		// Strip dynamically-populated choices before any field group is written to JSON,
		// so the JSON file never accumulates a stale year list.
		add_filter( 'acf/prepare_field_group_for_export', array( $this, 'strip_dynamic_choices_for_export' ) );

		// Shows: improve search behaviour for the Similar Shows and Favorite Shows relationship fields.
		add_filter( 'acf/fields/relationship/query/name=lezshows_similar_shows', array( $this, 'similar_shows_query' ) );
		add_filter( 'acf/fields/relationship/query/name=lez_user_favourite_shows', array( $this, 'similar_shows_query' ) );

		// Actors: default Gender to cisgender and Sexuality to unknown on new posts.
		add_filter( 'acf/load_value/name=lezactors_gender', array( $this, 'load_actor_gender_default' ), 10, 3 );
		add_filter( 'acf/load_value/name=lezactors_sexuality', array( $this, 'load_actor_sexuality_default' ), 10, 3 );

		// Actors: a hand-edited WikiData QID records itself as 'manual'.
		add_filter( 'acf/update_value/name=lezactors_wikidata_qid', array( $this, 'record_manual_wikidata_qid' ), 10, 3 );

		// Actors: the QID field is read-only until its write-lock is on.
		add_filter( 'acf/prepare_field/name=lezactors_wikidata_qid', array( $this, 'lock_wikidata_qid_field' ) );

		// Characters: improve search for the Show post_object field.
		add_filter( 'acf/fields/post_object/query/key=field_lwtv_lezchars_show_group_show', array( $this, 'show_post_object_query' ) );

		// Characters: improve search for the Actor relationship field.
		add_filter( 'acf/fields/relationship/query/name=lezchars_actor', array( $this, 'actor_query' ) );

		// Characters: annotate actor picker results with queer status and draft flag.
		add_filter( 'acf/fields/relationship/result/name=lezchars_actor', array( $this, 'actor_relationship_label' ), 10, 4 );

		// Terms: populate Symbolicon icon select choices dynamically.
		add_filter( 'acf/load_field/name=lez_termsmeta_icon', array( $this, 'load_symbolicon_choices' ) );

		// Debug logging: populate log_topics checkbox choices dynamically.
		add_filter( 'acf/load_field/name=log_topics', array( $this, 'load_log_topics_choices' ) );

		// Shows: write legacy meta keys on save for backward compat with consuming code.
		add_action( 'acf/save_post', array( $this, 'save_show_legacy_meta' ), 20 );

		// Restrict specific fields to administrators only — hide in the UI AND
		// reject writes from non-admins (prepare_field alone does not gate saves).
		foreach ( self::ADMIN_ONLY_FIELDS as $field_name ) {
			add_filter( 'acf/prepare_field/name=' . $field_name, array( $this, 'restrict_to_admin' ) );
			add_filter( 'acf/update_value/name=' . $field_name, array( $this, 'filter_admin_only_value' ), 10, 3 );
		}

		add_action( 'acf/input/admin_head', array( $this, 'admin_head_styles' ) );

		// Register the number_slider custom field type (used for show ratings).
		add_action( 'acf/init', array( $this, 'register_number_slider' ) );
	}

	/**
	 * Set the directory ACF saves field group JSON files to.
	 *
	 * @return string
	 */
	public function save_json_path(): string {
		return LWTV_PLUGIN_PATH . '/acf-json';
	}

	/**
	 * Add our acf-json directory to ACF's load paths.
	 *
	 * @param array $paths Existing load paths.
	 * @return array
	 */
	public function load_json_paths( array $paths ): array {
		$paths[] = LWTV_PLUGIN_PATH . '/acf-json';
		return $paths;
	}

	/**
	 * Prevent the infinite sync loop when syncing from local JSON.
	 *
	 * When ACF syncs a JSON-local field group to the DB, it immediately re-triggers
	 * save_json and writes a new file with modified = time(), which is always greater
	 * than the original JSON's modified timestamp, creating a perpetual sync notice.
	 * Suppress the save_json path for this request when the group originated from JSON.
	 *
	 * @param array $group The field group being updated.
	 */
	public function prevent_json_sync_loop( array $group ): void {
		if ( isset( $group['local'] ) && 'json' === $group['local'] ) {
			remove_filter( 'acf/settings/save_json', array( $this, 'save_json_path' ) );
		}
	}

	/**
	 * Populate the `excerpt` ACF field from post_excerpt when the meta row is empty.
	 *
	 * CMB2 stored this field directly in post_excerpt, not in post_meta.
	 *
	 * @param mixed $value   Current field value (from post_meta).
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return mixed
	 */
	public function load_excerpt_from_post( $value, int $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $value ) ) {
			$value = get_post_field( 'post_excerpt', $post_id );
		}
		return $value;
	}

	/**
	 * Mirror the `excerpt` ACF field value back to post_excerpt on save.
	 *
	 * Keeps post_excerpt in sync so any code reading it directly stays correct.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_excerpt_to_post( int|string $post_id ): void {
		if ( ! is_numeric( $post_id ) || $post_id < 1 ) {
			return;
		}
		$post_id = (int) $post_id;
		$cpts    = array( 'post_type_actors', 'post_type_shows' );
		if ( ! in_array( get_post_type( $post_id ), $cpts, true ) ) {
			return;
		}

		$excerpt = get_field( 'excerpt', $post_id );
		if ( false === $excerpt || empty( $excerpt ) ) {
			return;
		}

		// Use a direct DB write to avoid re-triggering save_post hooks.
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->posts,
			array( 'post_excerpt' => $excerpt ?? '' ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
	}

	/**
	 * Load lezshows_airdates_start from the legacy lezshows_airdates array.
	 *
	 * Old data lives in lezshows_airdates['start']; new data uses the separate key.
	 *
	 * @param mixed $value   Current field value.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return mixed
	 */
	public function load_airdate_start( $value, int $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $value ) ) {
			$airdates = get_post_meta( $post_id, 'lezshows_airdates', true );
			if ( is_array( $airdates ) && ! empty( $airdates['start'] ) ) {
				$value = $airdates['start'];
			}
		}
		return $value;
	}

	/**
	 * Load lezshows_airdates_finish from the legacy lezshows_airdates array.
	 *
	 * @param mixed $value   Current field value.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return mixed
	 */
	public function load_airdate_finish( $value, int $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $value ) ) {
			$airdates = get_post_meta( $post_id, 'lezshows_airdates', true );
			if ( is_array( $airdates ) && isset( $airdates['finish'] ) ) {
				$value = $airdates['finish'];
			}
		}
		return $value;
	}

	/**
	 * Convert the legacy CMB2 'on' checkbox value to 1 for ACF true_false display.
	 *
	 * CMB2 stored checked checkboxes as the string 'on'. ACF true_false expects 1.
	 * Without this, old shows with 'on' in meta would appear unchecked in the form.
	 *
	 * @param mixed $value   Current field value.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return mixed
	 */
	public function load_legacy_on_as_bool( $value, int $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'on' === $value ) {
			return 1;
		}
		return $value;
	}

	/**
	 * Validate that the finish year is not earlier than the start year.
	 *
	 * 'current' is always valid. Reads the start year from the submitted ACF form data
	 * so both fields are checked together at save time.
	 *
	 * @param bool|string $valid      True if valid, or an error message string.
	 * @param mixed       $value      The finish year value being saved.
	 * @param array       $field      ACF field definition.
	 * @param string      $input_name HTML input name.
	 * @return bool|string
	 */
	public function validate_airdate_finish( $valid, $value, array $field, string $input_name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $valid || 'current' === $value || empty( $value ) ) {
			return $valid;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- ACF handles nonce verification before this hook fires
		$start = isset( $_POST['acf']['field_lwtv_lezshows_airdates_start'] )
			? (int) sanitize_text_field( wp_unslash( $_POST['acf']['field_lwtv_lezshows_airdates_start'] ) )
			: 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $start && (int) $value < $start ) {
			return __( 'The end year cannot be earlier than the start year.', 'lwtv' );
		}

		return $valid;
	}

	/**
	 * Refuse an IMDb ID that another post of the same type already holds.
	 *
	 * This is the one hard stop in the duplicate-detection work. The name check
	 * warns and can be waved past, because two people really do share a name and
	 * a token-sorted key cannot tell them apart. An IMDb ID is different: it is
	 * an identity claim, so a collision is not a resemblance to judge but a
	 * contradiction to fix.
	 *
	 * An unchanged value is allowed through only for the older of the colliding
	 * posts. The job here is to stop a new collision being created, not to make an
	 * existing duplicate pair unsavable -- an editor opening one of those to fix
	 * it must be able to save their work. `wp lwtv dupes` is what reports the
	 * ones already in there.
	 *
	 * "Unchanged" cannot mean "already in the database", which is what it used to.
	 * Publishing in the block editor writes ACF meta before ACF validation runs,
	 * so a brand-new post's colliding ID was already stored by the time this saw
	 * it, read as unchanged, and waved through -- permanently, on that post and
	 * every save after. The one case this exists to refuse was the one case it
	 * structurally could not see.
	 *
	 * So the collision check runs first, and an unchanged value only survives it
	 * when this post is the lower ID of the two. That is the same
	 * lowest-ID-is-the-original convention Debugger\Collect\Duplicate_Collector
	 * pairs on, and it leaves the original of a legacy pair editable while asking
	 * the newer post to fix or clear its ID -- which is the resolution anyway. An
	 * emptied field returns above, so there is always a way out.
	 *
	 * @param bool|string $valid      True if valid, or an error message string.
	 * @param mixed       $value      The IMDb value being saved.
	 * @param array       $field      ACF field definition.
	 * @param string      $input_name HTML input name.
	 * @return bool|string
	 */
	public function validate_unique_imdb( $valid, $value, array $field, string $input_name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $valid || empty( $value ) ) {
			return $valid;
		}

		$field_name = (string) ( $field['name'] ?? '' );
		$post_type  = self::UNIQUE_IMDB_FIELDS[ $field_name ] ?? '';

		if ( '' === $post_type ) {
			return $valid;
		}

		$wanted = Imdb_Canonical::normalise( $value );

		// Nothing usable to compare. Malformed IDs are the IMDb debugger check's
		// business, and reporting them here too would put two errors on one fault.
		if ( '' === $wanted ) {
			return $valid;
		}

		/*
		 * Zero means none of the sources knew, which is not the same as "new
		 * post". The check still runs: a new actor pasting an ID that a published
		 * actor already holds is the exact thing this exists to refuse, and
		 * skipping it there would let duplicates in silently. An unresolved ID on
		 * an *existing* post can still produce a false collision against itself,
		 * which is the lesser of the two and is what the sources below are for.
		 */
		$post_id = self::editing_post_id();

		// The lowest-numbered other post holding this ID, if any. Asked before the
		// unchanged-value question, because the answer to that one depends on it.
		$owner_id = ( new Get_Post_By_Imdb() )->make( $wanted, $post_type, $field_name, $post_id );

		// Nothing else holds it, or the only holder is this post. The second case
		// is unreachable when the ID resolved, since the query excluded it, and is
		// the backstop for when it did not -- the alternative being to tell
		// someone their post duplicates itself.
		if ( ! $owner_id || $owner_id === $post_id ) {
			return $valid;
		}

		/*
		 * Another post holds it, and this one is the older claimant with the value
		 * already stored: a legacy pair being edited from the original's side.
		 * Allowed, so that work on the post that was there first can still be
		 * saved. get_post_meta() on an unresolved zero returns nothing, so an
		 * unidentifiable post never reaches this.
		 */
		$is_older = $post_id > 0 && $post_id < $owner_id;

		if ( $is_older && Imdb_Canonical::normalise( get_post_meta( $post_id, $field_name, true ) ) === $wanted ) {
			return $valid;
		}

		return sprintf(
			/* translators: 1: IMDb ID, 2: title of the post already using it, 3: that post's ID. */
			__( 'IMDb ID %1$s is already used by "%2$s" (post %3$d). If this is a different person or show, check the ID; if it is the same one, edit that post instead of making a second.', 'lwtv' ),
			$wanted,
			get_the_title( $owner_id ),
			$owner_id
		);
	}

	/**
	 * The post being edited, as seen from inside an ACF validation filter.
	 *
	 * acf/validate_value does not always run with a global post. In the block
	 * editor the validation happens in ACF's own AJAX request, where get_the_ID()
	 * has nothing to return and the ID arrives only in the payload -- which is
	 * how the unique-IMDb check came to compare a post against itself and report
	 * post 33236 as already holding post 33236's ID.
	 *
	 * Each source is tried in turn rather than trusting one, because which of
	 * them is populated depends on the editor and on ACF's own version.
	 *
	 * @return int Post ID, or 0 when none of the sources knows.
	 */
	private static function editing_post_id(): int {
		$sources = array( acf_get_form_data( 'post_id' ) );

		// ACF has verified its own nonce before any validate_value filter runs;
		// this only reads an ID it already acted on. _acf_post_id is the hidden
		// field ACF's own form data is built from, so it survives contexts where
		// the parsed copy is empty.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		foreach ( array( '_acf_post_id', 'post_id', 'post_ID' ) as $key ) {
			if ( isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ) {
				$sources[] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$sources[] = get_the_ID();

		foreach ( $sources as $source ) {
			// acf_get_form_data() also answers with things like 'options' or
			// 'term_12', which are not posts and must not become post 0.
			if ( ! is_numeric( $source ) || (int) $source < 1 ) {
				continue;
			}

			$post_id = (int) $source;

			// An autosave or revision stands in for the post it belongs to; its
			// own meta is empty, which would defeat the unchanged-value check.
			$parent_id = (int) wp_is_post_revision( $post_id );

			return $parent_id ? $parent_id : $post_id;
		}

		return 0;
	}

	/**
	 * Clear dynamic choices before a field group is written to local JSON.
	 *
	 * Fields whose choices are built entirely by acf/load_field filters at runtime
	 * must not accumulate a stale year list in the JSON file. Choices are listed here
	 * by field key so this is a targeted, opt-in list rather than a blanket wipe.
	 *
	 * @param array $field_group Field group definition about to be exported/written.
	 * @return array
	 */
	public function strip_dynamic_choices_for_export( array $field_group ): array {
		static $dynamic_keys = array(
			'field_lwtv_lezshows_airdates_start',
			'field_lwtv_lezshows_airdates_finish',
			'field_lwtv_lezchars_show_group_appears',
			'field_lwtv_lez_termsmeta_icon',
			'field_lwtv_log_topics',
		);

		if ( empty( $field_group['fields'] ) ) {
			return $field_group;
		}

		foreach ( $field_group['fields'] as &$field ) {
			if ( in_array( $field['key'], $dynamic_keys, true ) ) {
				$field['choices'] = array();
			}
			if ( ! empty( $field['sub_fields'] ) ) {
				foreach ( $field['sub_fields'] as &$sub_field ) {
					if ( in_array( $sub_field['key'], $dynamic_keys, true ) ) {
						$sub_field['choices'] = array();
					}
				}
				unset( $sub_field );
			}
		}
		unset( $field );

		return $field_group;
	}

	/**
	 * Populate the Air Start Year select with years from LWTV_FIRST_YEAR-10 to present.
	 *
	 * Replicates CMB2 date_year_range start dropdown (reverse sorted, no Current option).
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public function load_airdates_start_choices( array $field ): array {
		$earliest         = (int) LWTV_FIRST_YEAR - 10;
		$current          = (int) gmdate( 'Y' ) + 1;
		$field['choices'] = array();
		for ( $year = $current; $year >= $earliest; $year-- ) {
			$field['choices'][ (string) $year ] = (string) $year;
		}
		return $field;
	}

	/**
	 * Populate the Air Finish Year select with 'Current' then years from present to LWTV_FIRST_YEAR-10.
	 *
	 * Replicates CMB2 date_year_range finish dropdown (Current at top, reverse sorted).
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public function load_airdates_finish_choices( array $field ): array {
		$earliest         = (int) LWTV_FIRST_YEAR - 10;
		$current          = (int) gmdate( 'Y' ) + 1;
		$field['choices'] = array( 'current' => 'Current' );
		for ( $year = $current; $year >= $earliest; $year-- ) {
			$field['choices'][ (string) $year ] = (string) $year;
		}
		return $field;
	}

	/**
	 * Populate the Primary Genre select choices from the show's assigned genres.
	 *
	 * CMB2 used options_cb to build a dynamic list of term IDs from lez_genres.
	 * This replicates that behaviour for ACF.
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public function load_genre_primary_choices( array $field ): array {
		$post_id          = get_the_ID();
		$field['choices'] = array();

		if ( $post_id ) {
			$terms = get_the_terms( $post_id, 'lez_genres' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				// Sort alphabetically to match CMB2 ksort behaviour.
				usort( $terms, fn( $a, $b ) => strcmp( $a->name, $b->name ) );
				foreach ( $terms as $term ) {
					$field['choices'][ $term->term_id ] = $term->name;
				}
			}
		}

		return $field;
	}

	/**
	 * Populate the show_names language select from the full languages list.
	 *
	 * Scoped to field_lwtv_lezshows_show_name_type to avoid firing on any other
	 * field named 'type'.
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public function load_language_choices( array $field ): array {
		$field['choices'] = ( new Languages() )->all_languages();
		return $field;
	}

	/**
	 * Populate the 'Years Appears' multi-select with years from LWTV_FIRST_YEAR to next year.
	 *
	 * Replicates CMB2 pw_multiselect years_array (reverse sorted, includes upcoming year).
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public function load_appears_choices( array $field ): array {
		$earliest         = (int) LWTV_FIRST_YEAR;
		$latest           = (int) gmdate( 'Y' ) + 1;
		$field['choices'] = array();
		for ( $year = $latest; $year >= $earliest; $year-- ) {
			$field['choices'][ (string) $year ] = (string) $year;
		}
		return $field;
	}

	/**
	 * Tune the WP_Query for the Characters → Show post_object field search.
	 *
	 * Default: newest first. On search: relevance ordering.
	 * For short terms (≤4 chars, e.g. "ER") also constrain to exact title matches
	 * so common letters don't flood the results.
	 *
	 * @param array $args WP_Query args built by ACF for the post_object search.
	 * @return array
	 */
	public function show_post_object_query( array $args ): array {
		if ( ! empty( $args['s'] ) ) {
			$args['orderby'] = 'relevance';
			unset( $args['order'] );
			if ( 4 > strlen( $args['s'] ) ) {
				$args['title'] = $args['s'];
			}
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}
		return $args;
	}

	/**
	 * Tune the WP_Query for the Actor relationship field search.
	 *
	 * Default: newest first. On search: relevance ordering.
	 *
	 * @param array $args WP_Query args built by ACF for the relationship search.
	 * @return array
	 */
	public function actor_query( array $args ): array {
		if ( ! empty( $args['s'] ) ) {
			$args['orderby'] = 'relevance';
			unset( $args['order'] );
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}
		return $args;
	}

	/**
	 * Tune the WP_Query for the Similar Shows relationship field search.
	 *
	 * Default: newest first. On search: relevance ordering so the best match
	 * rises to the top. For short terms (≤4 chars, e.g. "ER") also constrain
	 * to exact title matches so common letters don't flood the results.
	 *
	 * @param array $args WP_Query args built by ACF for the relationship search.
	 * @return array
	 */
	public function similar_shows_query( array $args ): array {
		if ( ! empty( $args['s'] ) ) {
			$args['orderby'] = 'relevance';
			unset( $args['order'] );
			if ( 4 > strlen( $args['s'] ) ) {
				$args['title'] = $args['s'];
			}
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}
		return $args;
	}

	/**
	 * Write legacy meta keys on show save for backward compatibility.
	 *
	 * Two issues this solves:
	 *
	 * 1. lezshows_airdates — 10+ files read get_post_meta( $id, 'lezshows_airdates', true )
	 *    expecting array( 'start' => year, 'finish' => year|'current' ).
	 *    ACF now stores the values in separate keys; this hook keeps the legacy key in sync.
	 *
	 * 2. lezshows_worthit_show_we_love / lezshows_byq_override — SQL in
	 *    class-we-love-it.php and class-get-loved.php hardcode pm.meta_value = 'on'.
	 *    ACF true_false writes 1; this hook normalises back to 'on'.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_show_legacy_meta( int|string $post_id ): void {
		if ( ! is_numeric( $post_id ) || $post_id < 1 ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( 'post_type_shows' !== get_post_type( $post_id ) ) {
			return;
		}

		// Normalize boolean flags to legacy 'on' storage format.
		foreach ( self::SHOW_LEGACY_ON_FIELDS as $field_name ) {
			if ( get_field( $field_name, $post_id ) ) {
				update_post_meta( $post_id, $field_name, 'on' );
			} else {
				delete_post_meta( $post_id, $field_name );
			}
		}

		// Auto-manage the 'none' trope: assign it when no tropes are selected,
		// remove it when at least one real trope is present.
		$none_trope = get_term_by( 'slug', 'none', 'lez_tropes' );
		if ( $none_trope ) {
			$none_id = (int) $none_trope->term_id;
			$tropes  = get_field( 'lezshows_tropes', $post_id );
			$tropes  = is_array( $tropes ) ? array_map( 'intval', $tropes ) : array();

			if ( empty( $tropes ) ) {
				wp_set_object_terms( $post_id, $none_id, 'lez_tropes', false );
			} elseif ( in_array( $none_id, $tropes, true ) ) {
				$real = array_values( array_filter( $tropes, fn( $id ) => $id !== $none_id ) );
				if ( ! empty( $real ) ) {
					// "None!" was checked alongside real tropes — drop it and keep the real ones.
					wp_set_object_terms( $post_id, $real, 'lez_tropes', false );
				}
				// else: only "None!" was selected — ACF already set it correctly, leave it.
			}
		}

		// Write the legacy lezshows_airdates array.
		$start  = get_field( 'lezshows_airdates_start', $post_id );
		$finish = get_field( 'lezshows_airdates_finish', $post_id );
		if ( $start || $finish ) {
			update_post_meta(
				$post_id,
				'lezshows_airdates',
				array(
					'start'  => (string) ( $start ?? '' ),
					'finish' => (string) ( $finish ?? '' ),
				)
			);
		}
	}

	/**
	 * Output admin-only styles for ACF field layout tweaks.
	 *
	 * @return void
	 */
	public function admin_head_styles(): void {
		?>
		<style>
		.lwtv-acf-col-2 .acf-checkbox-list {
			column-count: 2;
			column-gap: 1.5em;
		}
		.lwtv-acf-col-3 .acf-checkbox-list {
			column-count: 3;
			column-gap: 1em;
		}
		.lwtv-acf-col-2 .acf-checkbox-list li,
		.lwtv-acf-col-3 .acf-checkbox-list li {
			break-inside: avoid;
		}
		</style>
		<?php
	}

	/**
	 * Hide a field from non-administrators.
	 *
	 * Returning false from acf/prepare_field removes the field from the form
	 * without touching the stored value — existing data is preserved.
	 *
	 * @param array|false $field ACF field definition.
	 * @return array|false
	 */
	public function restrict_to_admin( $field ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $field;
		}
		return false;
	}

	/**
	 * Reject writes to admin-only fields from non-administrators.
	 *
	 * acf/prepare_field only hides the control in the form; the value is still
	 * writable via a crafted submit. This preserves the stored value for anyone
	 * without manage_options.
	 *
	 * @param mixed      $value   The value about to be saved.
	 * @param int|string $post_id The post ID (ACF may pass a string form).
	 * @param array      $field   ACF field definition.
	 * @return mixed
	 */
	public function filter_admin_only_value( $value, $post_id, $field ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $value;
		}
		// Keep whatever is already stored; ignore the submitted value.
		// Returns the formatted stored value, which is correct for the current
		// select/true_false ADMIN_ONLY_FIELDS; a future non-select field added
		// here should use raw get_post_meta() instead.
		return get_field( $field['name'], $post_id );
	}

	/**
	 * Make the WikiData QID read-only until an editor takes the lock.
	 *
	 * Unlocked, the field belongs to the automated check: a value typed here
	 * would sit there looking accepted until the next backfill quietly replaced
	 * it. Showing it as read-only says so before anyone spends the effort.
	 *
	 * Only sets readonly -- it deliberately does not touch the instructions. The
	 * field's own copy already tells an editor to flip the toggle, and appending
	 * a second sentence saying the same thing just made the hint stutter.
	 *
	 * acf/prepare_field, not acf/load_field: load_field runs once per field
	 * definition with no post in sight, which is why the usual recipe for this
	 * reaches for $_GET['post'] -- absent on Gutenberg's metabox request and on
	 * post-new.php. prepare_field runs per render, inside the metabox, where WP
	 * has already set up the post.
	 *
	 * Read-only is an affordance, not a control: the browser still submits the
	 * value and the attribute can be removed. What actually protects the data is
	 * Identity::store_qid() refusing to write when locked, and the source meta
	 * deciding what the death audit will trust.
	 *
	 * @param  array $field ACF field definition, as prepared for this render.
	 * @return array
	 */
	public function lock_wikidata_qid_field( $field ) {
		$post_id = $this->current_admin_post_id();

		// No post yet (post-new.php): nothing has been resolved and there is no
		// lock to read, so leave the field alone rather than shipping a new actor
		// screen with an un-editable field and no way to unlock it.
		if ( ! $post_id ) {
			return $field;
		}

		$locked = get_post_meta( $post_id, Identity::META_IGNORE, true );

		// ACF true_false stores "1"/"0" as strings, so "0" must not read as set.
		if ( ! in_array( (string) $locked, array( '', '0' ), true ) ) {
			return $field;
		}

		$field['readonly'] = 1;

		return $field;
	}

	/**
	 * The post being edited, for filters that run without one passed in.
	 *
	 * get_the_ID() covers the metabox render on both the classic screen and
	 * Gutenberg's meta-box request, since WP sets up the post in both. The
	 * superglobals are the fallback for filters that fire before that.
	 *
	 * @return int Post ID, or 0 when there is no post in context.
	 */
	private function current_admin_post_id(): int {
		$post_id = get_the_ID();

		if ( $post_id ) {
			return (int) $post_id;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which post is on screen, not acting on input.
		if ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_GET['post'];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above; the save itself is nonce-checked by core.
		if ( isset( $_POST['post_ID'] ) && is_numeric( $_POST['post_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			return (int) $_POST['post_ID'];
		}

		return 0;
	}

	/**
	 * Record a hand-edited WikiData QID as coming from a human.
	 *
	 * There is one QID field and the machine may overwrite it, so what separates
	 * "an editor checked this" from "a name search guessed it" is the source meta
	 * beside it. Machine writes go through Identity::store_qid(), which uses
	 * update_post_meta() and therefore never fires this filter -- so reaching
	 * here means a person saved the field.
	 *
	 * Only stamps 'manual' when the value actually CHANGED. ACF re-saves every
	 * field on every post save, including untouched ones, so stamping
	 * unconditionally would relabel a fuzzy 'name' match as trusted the first
	 * time anyone opened an actor and hit Update -- laundering a guess into an
	 * identity, which is the one failure Qid_Trust exists to prevent.
	 *
	 * Also normalises a pasted wikidata.org URL down to the bare QID.
	 *
	 * @param  mixed $value   The value being saved.
	 * @param  mixed $post_id ACF post ID (int for posts, string for options).
	 * @param  array $field   Field definition.
	 * @return mixed
	 */
	public function record_manual_wikidata_qid( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_numeric( $post_id ) ) {
			return $value;
		}

		$post_id = (int) $post_id;
		$value   = Identity::normalise_qid( (string) $value );
		$stored  = trim( (string) get_post_meta( $post_id, Identity::META_QID, true ) );

		if ( $value === $stored ) {
			return $value;
		}

		if ( '' === $value ) {
			// Cleared by hand: we no longer hold an identity, so drop the source
			// rather than leave one describing a value that is gone. The checked
			// marker goes too, so the backfill treats this as never asked instead
			// of "asked, no match" and will look again.
			delete_post_meta( $post_id, Identity::META_SOURCE );
			delete_post_meta( $post_id, Identity::META_CHECKED );

			return $value;
		}

		update_post_meta( $post_id, Identity::META_SOURCE, Qid_Trust::SOURCE_MANUAL );
		update_post_meta( $post_id, Identity::META_CHECKED, time() );

		return $value;
	}

	/**
	 * Annotate actor relationship results with queer status and draft flag.
	 *
	 * Replicates the CMB2 cmb2_attached_posts_title_filter behaviour for the
	 * lezchars_actor ACF relationship field so editors can quickly identify
	 * queer actors and unpublished drafts in the picker.
	 *
	 * @param string   $title   The post title shown in the relationship picker.
	 * @param \WP_Post $post    The post object for the result.
	 * @param array    $field   ACF field definition.
	 * @param int      $post_id The post being edited.
	 * @return string
	 */
	public function actor_relationship_label( string $title, \WP_Post $post, array $field, int $post_id ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$additional = array();

		if ( 'publish' !== $post->post_status ) {
			$additional[] = 'Draft';
		}

		$is_queer = get_post_meta( $post->ID, 'lezactors_queer', true );
		if ( ! empty( $is_queer ) && ! str_contains( $title, 'Queer' ) ) {
			$additional[] = 'Queer';
		}

		if ( ! empty( $additional ) ) {
			$title .= ' (' . implode( ', ', array_unique( $additional ) ) . ')';
		}

		return $title;
	}

	/**
	 * Populate the Symbolicon icon select with choices from symbolicons.json.
	 *
	 * Choices are built at runtime so the JSON file never accumulates a stale
	 * icon list. The field_lwtv_lez_termsmeta_icon key is included in
	 * strip_dynamic_choices_for_export so the JSON stays clean on save.
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public function load_symbolicon_choices( array $field ): array {
		$field['choices'] = array();

		if ( ! defined( 'LWTV_SYMBOLICONS_PATH' ) || ! file_exists( LWTV_SYMBOLICONS_SPRITE_PATH . 'symbolicons.json' ) ) {
			return $field;
		}

		$icon_json = json_decode( file_get_contents( LWTV_SYMBOLICONS_SPRITE_PATH . 'symbolicons.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_array( $icon_json ) ) {
			foreach ( $icon_json as $icon ) {
				if ( isset( $icon['cleanname'] ) ) {
					$field['choices'][ $icon['cleanname'] ] = $icon['cleanname'];
				}
			}
		}

		return $field;
	}

	/**
	 * Populate the log_topics checkbox with choices from Debugging::VALID_LOG_TOPICS.
	 *
	 * Choices are built at runtime so adding a topic to the constant is reflected
	 * immediately without touching the JSON file.
	 *
	 * @param array $field ACF field definition.
	 * @return array
	 */
	public function load_log_topics_choices( array $field ): array {
		$field['choices'] = array();
		foreach ( \LWTV\Admin_Menu\Debugging::VALID_LOG_TOPICS as $topic ) {
			$field['choices'][ $topic ] = ucwords( str_replace( '-', ' ', $topic ) );
		}
		return $field;
	}

	/**
	 * Default the Gender field to cisgender for new actor posts.
	 *
	 * @param mixed $value   Current field value.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return mixed
	 */
	public function load_actor_gender_default( $value, int $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $value ) ) {
			$term = get_term_by( 'slug', 'cisgender', 'lez_actor_gender' );
			if ( $term ) {
				$value = $term->term_id;
			}
		}
		return $value;
	}

	/**
	 * Default the Sexuality field to unknown for new actor posts.
	 *
	 * @param mixed $value   Current field value.
	 * @param int   $post_id Post ID.
	 * @param array $field   ACF field definition.
	 * @return mixed
	 */
	public function load_actor_sexuality_default( $value, int $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $value ) ) {
			$term = get_term_by( 'slug', 'unknown', 'lez_actor_sexuality' );
			if ( $term ) {
				$value = $term->term_id;
			}
		}
		return $value;
	}

	/**
	 * Register the number_slider ACF field type.
	 *
	 * Uses acf/init + acf_register_field_type() (ACF 5.8.9+/6.x).
	 * The legacy acf/include_field_types hook was dropped in ACF 6.x.
	 */
	public function register_number_slider(): void {
		if ( ! function_exists( 'acf_register_field_type' ) ) {
			return;
		}
		require_once __DIR__ . '/acf/class-number-slider.php';
		acf_register_field_type( new \acf_field_number_slider() );
	}

	/**
	 * Show Admin UX
	 *
	 * If the toggle is on, and you're in WP-Admin, show the Admin UX to
	 * admins only.
	 */
	public function show_admin() {
		$acf_ux_enabled = function_exists( 'get_field' ) && get_field( 'enable_acf_ux', 'option' );

		if ( wp_get_environment_type() !== 'production' || ( is_admin() && $acf_ux_enabled ) ) {
			return current_user_can( 'manage_options' );
		}

		return false;
	}
}
