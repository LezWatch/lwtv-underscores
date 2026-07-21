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

use LWTV\Features\Languages;

class ACF {

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

		// Restrict specific fields to administrators only.
		foreach ( self::ADMIN_ONLY_FIELDS as $field_name ) {
			add_filter( 'acf/prepare_field/name=' . $field_name, array( $this, 'restrict_to_admin' ) );
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
