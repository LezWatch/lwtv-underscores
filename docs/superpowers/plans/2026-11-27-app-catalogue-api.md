# App Catalogue API (8.1.0) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the two public routes `lwtv/v2/app/catalogue` and `lwtv/v2/app/availability` that the PWA server's `lwtv:sync` reads, exactly as the agreed contract describes.

**Architecture:** All shaping lives in pure `LWTV\Rest_API\Build` classes, written test-first: the envelope, one show record, the catalogue with its term dictionaries, the provider-to-service map and the availability file. One WordPress class, `LWTV\Rest_API\App_JSON`, reads posts, terms and meta into plain arrays, hands them to the builders, and caches each response in a transient that is cleared by save, term and meta hooks. Providers are grouped into services automatically from their names, and an editor can override that with a new TMDB-provider-ids field on `lez_watch_urls` terms.

**Tech Stack:** PHP 8.1+, WordPress REST API, ACF local JSON, PHPUnit (pure, no WP bootstrap), `intl` `Locale::getDisplayRegion()`.

**Spec:** [LezWatch/pwa `docs/api/app-catalogue-v1.md`](https://github.com/LezWatch/pwa/blob/production/docs/api/app-catalogue-v1.md) (agreed 2026-11-24, tightened in pwa#39), plus [#755](https://github.com/LezWatch/lwtv-underscores/issues/755) and its comments. Samples: pwa `tests/Fixtures/app/catalogue.json` and `availability.json`. Checker: pwa `tests/Support/AppCatalogueContract.php`.

## Global Constraints

- Branch: the work lands on `release/8.1.0` (currently at the 8.0.0 tip, `fd9953ec`). This worktree's branch `claude/meilisearch-release-8-1-0-cbc883` is at the same commit, so work here is fine. **Never commit.** The user commits the whole diff at the end, so the tasks have no commit steps.
- Namespace `LWTV\Rest_API\Build` for pure classes in `plugins/lwtv-plugin/php/rest-api/build/`, and `LWTV\Rest_API` for `rest-api/class-app-json.php`. Files are named `class-*.php`, one class per file.
- Pure classes must not call WordPress functions. Each one gets a `require_once` line in `tests/bootstrap.php`, after the existing `rest-api/build/` lines. Tests go in `tests/unit/Rest_API/`, namespace `LWTV\Tests\Rest_API`.
- `schema_version` is `1`. Every response has `schema_version` (int), `generated_at` (ISO 8601 UTC, `Y-m-d\TH:i:s\Z`) and `data_version` (string).
- Missing values are `null`, never `""`, `0` or `false`, and every field is always present.
- Ids are integers. Object keys are those ids as strings, and an empty dictionary must encode as `{}`, never `[]`.
- Text is plain UTF-8 with entities decoded and no HTML.
- Catalogue `shows` is sorted by `id` ascending.
- `trigger` is one of `"low"`, `"med"`, `"high"` or `null`, using the same lookup as `Theme\Content_Warning`: the `lez_triggers` term slug, else the `lezshows_triggerwarning` meta, through `Trigger_Warning::normalize()`. `'none'` becomes `null`.
- `star` is one of `"gold"`, `"silver"`, `"bronze"`, `"anti"` or `null`, from the `lez_stars` slug.
- `queer_death` is true when the show has the `dead-queers` trope. That trope also stays in `tropes`.
- `lwtv_score` is `lezshows_the_score`, rounded to a whole number, 0–100. `viewer_score` and `viewer_votes` are always `null` in 8.1.0.
- Term dictionaries hold only the terms that at least one returned show uses.
- Availability offer types are `flatrate`, `free`, `ads`, `rent`, `buy`, and empty types, regions and shows are left out. Only `flatrate`, `free` and `ads` count as "streamable" when ranking a region's services.
- Attribution is exactly `{"text": "Streaming data from JustWatch.", "url": "https://www.justwatch.com/"}`.
- The only query parameter is `schema`. Missing or `1` serves v1. Anything else returns a 400 `WP_Error` with code `rest_invalid_param`. Nothing about the request is logged beyond what WordPress already logs.
- No change to scoring, CPT relationships, existing routes, or the show-page strip's behaviour. `composer lint`, `composer analyse` and `vendor/bin/phpunit` must all pass (CI runs PHPStan too).

## Review Focus

1. **Empty dictionaries encoding as `[]`.** If no show has a genre, `terms.genres` must still be `{}`. PHP's `json_encode( array() )` gives `[]`. Tests in Tasks 1 and 3.
2. **Over-eager grouping.** "Apple TV" (a store) and "Apple TV Plus" (a service) must not merge, and "Max" must not swallow "maxdome Store". Only ad-tier names fold into their base service. Test in Task 4.
3. **A meta refresh that doesn't change `data_version`.** The watch-provider task and score recalculation write meta without touching `post_modified`, so the version must come from the payload, not `MAX(post_modified)`. Tests in Task 1; the hook check is in Task 6.
4. **Similar ids that point nowhere.** Drafts, trashed shows, the show's own id, duplicates and string ids in `lezshows_similar_shows` must all be dropped or cast. Test in Task 3.
5. **Stored provider meta that isn't clean.** Old rows may carry no `logo`, an empty type, or a region with nothing in it. Each must be left out or become `null`, never `""`. Test in Task 5.

---

### Task 1: Envelope and plain text

**Files:**
- Create: `plugins/lwtv-plugin/php/rest-api/build/class-app-envelope.php`
- Modify: `tests/bootstrap.php` (add the require)
- Test: `tests/unit/Rest_API/AppEnvelopeTest.php`

**Interfaces:**
- Produces:
  - `App_Envelope::SCHEMA_VERSION = 1`
  - `App_Envelope::wrap( array $body, int $now ): array` returns `schema_version`, `generated_at`, `data_version`, then `$body`'s keys in order
  - `App_Envelope::data_version( array $body ): string` returns the first 12 hex characters of `md5( json_encode( $body ) )`
  - `App_Envelope::plain_text( string $text ): string`
  - `App_Envelope::dict( array $map ): object` returns `(object) $map`, so an empty map encodes as `{}`

- [ ] **Step 1: Write the failing tests**
  - `test_wrap_puts_envelope_first`: `wrap( array( 'shows' => array() ), 1795737600 )` has keys `schema_version, generated_at, data_version, shows`, with `schema_version === 1` and `generated_at === '2026-11-27T00:00:00Z'`.
  - `test_data_version_ignores_generated_at`: wrapping the same body at two different `$now` values gives the same `data_version`.
  - `test_data_version_changes_with_body`: `array( 'a' => 1 )` and `array( 'a' => 2 )` give different versions, and each version matches `/^[0-9a-f]{12}$/`.
  - `test_plain_text_decodes_and_strips`: `plain_text( 'Tom &amp; Jerry&#8217;s <em>Show</em> ' )` is `'Tom & Jerry’s Show'`.
  - `test_empty_dict_encodes_as_object`: `json_encode( App_Envelope::dict( array() ) )` is `'{}'`, and `json_encode( App_Envelope::dict( array( 101 => array( 'slug' => 'x' ) ) ) )` is `'{"101":{"slug":"x"}}'`.

- [ ] **Step 2: Run and confirm they fail**
  Run: `vendor/bin/phpunit --filter AppEnvelopeTest`
  Expected: FAIL, because the class `LWTV\Rest_API\Build\App_Envelope` is not found.

- [ ] **Step 3: Implement `App_Envelope`**
  - `generated_at` is `gmdate( 'Y-m-d\TH:i:s\Z', $now )`.
  - `plain_text` is `trim( html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) )`. Use core `strip_tags`, because `wp_strip_all_tags` is WordPress; add a phpcs ignore with the reason.
  - `json_encode` flags are `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`. The version must never depend on `generated_at`.

- [ ] **Step 4: Run and confirm they pass**
  Run: `vendor/bin/phpunit --filter AppEnvelopeTest`
  Expected: PASS

### Task 2: One show record

**Files:**
- Create: `plugins/lwtv-plugin/php/rest-api/build/class-app-show-record.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Rest_API/AppShowRecordTest.php`

**Interfaces:**
- Consumes: `App_Envelope::plain_text()`, `LWTV\CPTs\Shows\Scoring\Trigger_Warning::normalize()`, `LWTV\CPTs\Shows\Airdates::is_still_airing()` (both are already in the bootstrap).
- Produces:
  - `App_Show_Record::TAXONOMIES`, a map from record field to taxonomy: `formats` → `lez_formats`, `countries` → `lez_country`, `stations` → `lez_stations`, `genres` → `lez_genres`, `tropes` → `lez_tropes`
  - `App_Show_Record::DEATH_TROPE = 'dead-queers'`
  - `App_Show_Record::STARS = array( 'gold', 'silver', 'bronze', 'anti' )`
  - `App_Show_Record::build( array $raw ): array`

  The raw input is gathered by Task 6:
  - `id` int, `slug` string, `title` string (raw, may hold entities), `url` string
  - `start` and `finish` strings from `Airdates::get()`
  - `on_air` raw meta
  - `terms`: `field => array<int, array{slug: string, name: string}>`, one entry per `TAXONOMIES` field, keyed by term id
  - `trigger_term` (`lez_triggers` slug or `''`) and `trigger_meta` (raw meta)
  - `star` (`lez_stars` slug or `''`), `score` (raw meta), `tmdb_id` (raw meta), `similar` (raw meta)

  The output has exactly the contract's 20 show fields, in contract order. `similar` comes back as cast, de-duplicated ints with the show's own id removed; Task 3 drops the ones that aren't in the catalogue.

- [ ] **Step 1: Write the failing tests**
  - `test_full_record_matches_contract_shape`: a raw show modelled on the pwa sample "The Lantern Years" (start `'2019'`, finish `'2023'`, `on_air` `'no'`, trope `102 => law-enforcement`, `trigger_term` `'low'`, star `'gold'`, score `'84.4'`, tmdb `'210001'`, similar `array( '90002' )`) builds exactly the sample record: `year_start` 2019, `year_end` 2023, `on_air` false, `lwtv_score` 84, `tmdb_id` 210001, `similar` `[90002]`, and `viewer_score` and `viewer_votes` null.
  - `test_trigger_term_beats_meta_and_legacy_aliases_normalise`: term `'medium'` gives `'med'`; no term with meta `'on'` gives `'high'`; neither gives `null`; and an unknown value such as `'none'` gives `null`.
  - `test_still_airing_and_unknown_years`: finish `'current'` gives `year_end` null; start `''` gives `year_start` null; `on_air` `'yes'` gives true and anything else gives false.
  - `test_star_outside_known_tiers_is_null`: `'platinum'` and `''` both give `null`, and `'anti'` gives `'anti'`.
  - `test_score_rounds_and_clamps_and_blank_is_null`: `'84.5'` gives 85, `'-3'` gives 0, `'104'` gives 100, and `''` or `'n/a'` give `null`.
  - `test_queer_death_from_trope_slug`: a trope with slug `dead-queers` sets `queer_death` true and its id stays in `tropes`.
  - `test_similar_casts_dedupes_and_drops_self`: `array( '5', 5, 'x', 0, <own id> )` gives `[5]`. A non-array or `''` gives `[]`.
  - `test_title_is_plain_text`: `'Gentleman Jack&#8217;s <b>Return</b>'` gives `'Gentleman Jack’s Return'`.
  - `test_tmdb_id_zero_or_blank_is_null`.

- [ ] **Step 2: Run and confirm they fail**
  Run: `vendor/bin/phpunit --filter AppShowRecordTest`
  Expected: FAIL, because the class is not found.

- [ ] **Step 3: Implement `App_Show_Record::build()`**
  - A year is the leading four digits of the airdate, matched with `/^(\d{4})/`, cast to int, and `null` when there is no match.
  - Term fields are `array_keys()` of each `terms[field]`, as ints sorted ascending.

- [ ] **Step 4: Run and confirm they pass**
  Run: `vendor/bin/phpunit --filter AppShowRecordTest`
  Expected: PASS

### Task 3: Catalogue body and term dictionaries

**Files:**
- Create: `plugins/lwtv-plugin/php/rest-api/build/class-app-catalogue.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Rest_API/AppCatalogueTest.php`

**Interfaces:**
- Consumes: `App_Show_Record::build()`, `App_Show_Record::TAXONOMIES`, `App_Envelope::dict()`, `App_Envelope::plain_text()`.
- Produces: `App_Catalogue::build( array $raws ): array`, which returns `array{ terms: array<string, object>, shows: array<int, array> }`. `terms` keys appear in the contract's order: `tropes`, `genres`, `formats`, `countries`, `stations`. It does not add the envelope; Task 6 wraps it.

- [ ] **Step 1: Write the failing tests**
  - `test_shows_sorted_by_id`: raws given as ids 30, 10, 20 come back as 10, 20, 30.
  - `test_dictionaries_hold_only_used_terms`: shows use tropes 101, 102 and 103 between them, so all three are in `terms.tropes`; trope 104, which exists on the site but on no show in `$raws`, never appears. Each dictionary entry is `{slug, name}`, and `name` is plain text (`'Cops &amp; Robbers'` becomes `'Cops & Robbers'`).
  - `test_empty_taxonomy_dictionary_encodes_as_object`: no show has a genre, and `json_encode( $out['terms']['genres'] )` is `'{}'`.
  - `test_similar_drops_ids_not_in_catalogue`: show 10 lists similar `[20, 99]` with only 10 and 20 present, so its `similar` is `[20]`.
  - `test_build_of_pwa_sample_raws_matches_sample_terms`: raws built from the pwa sample's shows produce `terms` equal to the sample's `terms`. Copy only the needed rows into the test, with a comment linking the pwa fixture; don't vendor the file.

- [ ] **Step 2: Run and confirm they fail**
  Run: `vendor/bin/phpunit --filter AppCatalogueTest`
  Expected: FAIL, because the class is not found.

- [ ] **Step 3: Implement `App_Catalogue::build()`**
  Build every record first. Then collect the set of known ids, filter each record's `similar` against it, and merge the term rows from each raw into the per-field dictionaries, keyed by id and sorted by key.

- [ ] **Step 4: Run and confirm they pass**
  Run: `vendor/bin/phpunit --filter AppCatalogueTest`
  Expected: PASS

### Task 4: Provider-to-service map

**Files:**
- Modify: `plugins/lwtv-plugin/php/_helpers/class-tmdb-providers.php`. Make `spaced_name()` and `service_key()` `public static`, with no behaviour change, and update their docblocks to say the app map uses them too.
- Create: `plugins/lwtv-plugin/php/rest-api/build/class-service-map.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Rest_API/ServiceMapTest.php`

**Interfaces:**
- Consumes: `Tmdb_Providers::spaced_name()`, `Tmdb_Providers::service_key()`, `Tmdb_Providers::RESELLER_PATTERN`.
- Produces:
  - `Service_Map::AD_TIER = '/^(?:[\p{L}\p{N}]+\s+)*with\s+ads$/u'`. This is tested against the *remainder* after a whole-word base-name prefix ("standard with ads", "basic with ads", "with ads").
  - `Service_Map::parse_ids( string $raw ): array<int, int>`. It splits on commas or whitespace and keeps positive ints, unique and sorted.
  - `Service_Map::slug( string $name ): string`. It lowercases, turns `+` into `-plus`, turns runs of other non-alphanumerics into `-`, and trims dashes (`'Prime Video'` → `prime-video`, `'AMC+'` → `amc-plus`).
  - `Service_Map::build( array $providers, array $overrides ): array{ services: array<string, array{name: string}>, providers: array<int, array{name: string, service: ?string, logo: ?string}> }`.
    - `$providers` is `id => array{name, logo}`, every provider seen in any show.
    - `$overrides` is `provider_id => array{slug: string, name: string}`, from `lez_watch_urls` terms (Task 7).

- [ ] **Step 1: Write the failing tests**
  - `test_ad_tier_folds_into_base_service`: providers 8 "Netflix" and 1796 "Netflix Standard with Ads" both map to `netflix`, and `services` has `netflix => {name: 'Netflix'}`.
  - `test_resellers_and_spellings_share_a_service`: "AMC+", "AMC Plus Apple TV channel" and "AMC+ Amazon Channel" all map to `amc-plus`, named `'AMC+'` (the direct provider's name wins).
  - `test_prefix_without_ads_does_not_fold`: "Apple TV" and "Apple TV Plus" get two different services, and "Max" and "maxdome Store" get two different services.
  - `test_override_wins_over_auto`: an override `1899 => {slug: 'max', name: 'Max'}` maps provider 1899 "HBO Max" to `max` even though the automatic choice would be `hbo-max`.
  - `test_logo_blank_is_null`: logo `''` gives `null`.
  - `test_name_with_no_letters_maps_to_null_service`: provider "!!!" gets `service` null and adds no `services` entry.
  - `test_parse_ids`: `' 8, 1796 8 x -2 '` gives `[8, 1796]`.
  - `test_existing_strip_unchanged`: run the existing `TmdbProvidersTest` (no new assertions; the visibility change must not break it).

- [ ] **Step 2: Run and confirm they fail**
  Run: `vendor/bin/phpunit --filter 'ServiceMapTest|TmdbProvidersTest'`
  Expected: `ServiceMapTest` fails because the class is not found, and `TmdbProvidersTest` passes.

- [ ] **Step 3: Implement `Service_Map`**
  Resolve each provider's service as follows:
  1. If it has an override, use that.
  2. If its `spaced_name` is `<another provider's spaced_name> <remainder>` and the remainder matches `AD_TIER`, use that other provider's service. Take the longest matching base.
  3. Otherwise group it by `service_key()`.

  Within a `service_key` group, the service name and slug come from the provider that has no reseller suffix. On a tie, use the lowest provider id. An override's name and slug always replace the automatic ones for its service.

- [ ] **Step 4: Run and confirm they pass**
  Run: `vendor/bin/phpunit --filter 'ServiceMapTest|TmdbProvidersTest'`
  Expected: PASS

### Task 5: Availability body

**Files:**
- Create: `plugins/lwtv-plugin/php/rest-api/build/class-app-availability.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Rest_API/AppAvailabilityTest.php`

**Interfaces:**
- Consumes: `Service_Map::build()`, `App_Envelope::dict()`, `Tmdb_Providers::TYPES`, `Tmdb_Providers::DISPLAY_TYPES`.
- Produces:
  - `App_Availability::ATTRIBUTION`, the constant from Global Constraints.
  - `App_Availability::build( array $stored, array $overrides, array $region_names ): array{ attribution, services, providers, regions, shows }`.
    - `$stored` is `show_id => Tmdb_Providers::normalise()` output, read from `lezshows_watch_providers`: `CODE => {link, flatrate: [{id, name, logo}], …}`.
    - `$region_names` is `CODE => name`; a missing code falls back to the code itself.
    - `services`, `providers`, `regions` and `shows` are all `dict()` objects.
  - `App_Availability::provider_index( array $stored ): array<int, array{name: string, logo: string}>`, the first seen name and logo per provider id.

- [ ] **Step 1: Write the failing tests**
  - `test_sample_show_compacts_to_ids`: a show `90003` with US flatrate Netflix (8) builds `shows['90003']` as `{"US": {"flatrate": [8]}}`. The `link` key is dropped.
  - `test_empty_types_regions_and_shows_are_dropped`: a region with only empty lists, and a show with no regions, are both absent from `shows`.
  - `test_region_services_ranked_by_streamable_show_count`: in US, Netflix is on 3 shows (flatrate), Max on 2 (ads), and Apple TV on 5 (`buy` only). US `services` is `['netflix', 'max']`, so Apple TV is not listed because `buy` doesn't count. Ties sort by slug ascending.
  - `test_region_name_from_map_or_code`: with `array( 'US' => 'United States' )`, US is named "United States" and an unmapped `XK` is named `'XK'`.
  - `test_attribution_exact`.
  - `test_rent_and_buy_kept_in_shows`: a `rent: [2]` entry survives in `shows`.
  - `test_empty_inputs_encode_as_objects`: `json_encode( build( array(), array(), array() ) )` contains `"services":{}`, `"providers":{}`, `"regions":{}` and `"shows":{}`.

- [ ] **Step 2: Run and confirm they fail**
  Run: `vendor/bin/phpunit --filter AppAvailabilityTest`
  Expected: FAIL, because the class is not found.

- [ ] **Step 3: Implement `App_Availability::build()`**
  - Walk the shows in id order, and the types in `TYPES` order within each.
  - A provider's ids within a type keep their stored order (TMDB's priority) and are de-duplicated.
  - A region appears in `regions` when at least one show has an entry there. Its `services` list may be empty.

- [ ] **Step 4: Run and confirm they pass**
  Run: `vendor/bin/phpunit --filter AppAvailabilityTest`
  Expected: PASS

### Task 6: `App_JSON` routes, data gathering and caching

**Files:**
- Create: `plugins/lwtv-plugin/php/rest-api/class-app-json.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-rest-api.php` (add `use LWTV\Rest_API\App_JSON;` and `new App_JSON();` in `init()`, alphabetically first)

**Interfaces:**
- Consumes: Tasks 1–5, `Airdates::get()`, `CPT_Shows::SLUG`, `Watch_Providers_Task::META_DATA`, `lwtv_plugin()->get_transient()`, `set_transient()` and `delete_transient()`, and `App_JSON::service_overrides()` from Task 7.
- Produces:
  - `App_JSON::ROUTE_NAMESPACE = 'lwtv/v2'`
  - `App_JSON::CACHE_CATALOGUE = 'lwtv_app_catalogue_v1'`
  - `App_JSON::CACHE_AVAILABILITY = 'lwtv_app_availability_v1'`
  - `App_JSON::catalogue( WP_REST_Request $request ): WP_REST_Response|WP_Error`
  - `App_JSON::availability( WP_REST_Request $request ): WP_REST_Response|WP_Error`
  - `App_JSON::flush_catalogue(): void` and `App_JSON::flush_availability(): void`

- [ ] **Step 1: Register the routes**
  Register `/app/catalogue` and `/app/availability` on `rest_api_init` as `GET` routes with `permission_callback => '__return_true'`. Each route gets an `args` entry for `schema`: `type: integer`, a `validate_callback` that accepts only `1`, and `required: false`. A failed validation gives core's 400 `rest_invalid_param`.

- [ ] **Step 2: Gather the catalogue raws**
  - Fetch published shows with one `get_posts()` call: `post_type => CPT_Shows::SLUG`, `post_status => publish`, `posts_per_page => -1`, `orderby => ID`, `order => ASC`, `no_found_rows => true`, with the meta and term caches primed.
  - For each show, call `get_the_terms()` for the five `App_Show_Record::TAXONOMIES` plus `lez_triggers` and `lez_stars`, and `get_post_meta()` for `lezshows_on_air`, `lezshows_triggerwarning`, `lezshows_the_score`, `lezshows_tmdb_id` and `lezshows_similar_shows`.
  - `url` is `get_permalink()` and `title` is `get_the_title()`, which App_Show_Record makes plain.

- [ ] **Step 3: Gather the availability input**
  For the same show ids, decode `lezshows_watch_providers` (JSON in meta) into `$stored`, skipping anything that isn't an array. Region names come from `\Locale::getDisplayRegion( '-' . $code, 'en' )` when the class exists, else `''`, which the builder turns into the code.

- [ ] **Step 4: Cache and respond**
  - Each handler reads its transient. On a miss, it builds the body, wraps it with `App_Envelope::wrap( $body, time() )`, and stores it for `DAY_IN_SECONDS`.
  - It returns `rest_ensure_response()` with the header `Cache-Control: public, max-age=3600`.

- [ ] **Step 5: Hook invalidation**
  `flush_*()` deletes its transient, guarded by a static flag so a batch of 2,300 meta writes deletes it only once per request.
  - `save_post_{CPT_Shows::SLUG}`, `trashed_post`, `untrashed_post` and `deleted_post` (shows only) flush both.
  - `edited_term`, `created_term` and `delete_term`, for the seven catalogue taxonomies, flush the catalogue. For `lez_watch_urls`, they flush availability.
  - `added_post_meta`, `updated_post_meta` and `deleted_post_meta` flush the catalogue for `lezshows_the_score`, `lezshows_on_air`, `lezshows_triggerwarning`, `lezshows_similar_shows` and `lezshows_tmdb_id`. They flush availability for `Watch_Providers_Task::META_DATA`.

- [ ] **Step 6: Check it live on lwtv.local**
  Point the lwtv.local theme symlink at this worktree, then run:
  `curl -sk https://lwtv.local/wp-json/lwtv/v2/app/catalogue | jq '{v: .schema_version, n: (.shows|length), g: (.terms.genres|type), first: .shows[0]}'`
  Expected: `v` is 1, `n` equals the published-show count, `g` is `"object"`, and `first` has all 20 fields.
  Then run `curl -sk -o /dev/null -w '%{http_code}' 'https://lwtv.local/wp-json/lwtv/v2/app/catalogue?schema=2'`. Expected: `400`.

- [ ] **Step 7: Check that `data_version` moves on a meta-only change**
  - Record `.data_version`.
  - Run `wp eval 'update_post_meta( <a show id>, "lezshows_the_score", 12.3 );'` (using the socket override from the local wp-cli notes).
  - Re-fetch. Expected: a different `data_version`, and that show's `lwtv_score` is 12.
  - Restore the meta with `wp lwtv calc <id>` and confirm the version changes back.

- [ ] **Step 8: Time the cold build**
  Delete both transients, then run `time curl -sk …/availability >/dev/null` and record the byte size. If either build takes over ~10 s or availability is over ~5 MB, stop and tell the user; the contract's fallback is per-region files.

### Task 7: TMDB-provider-ids override on `lez_watch_urls` terms

**Files:**
- Modify: `plugins/lwtv-plugin/acf-json/group_lwtv_term_watch_urls.json`. Add a text field `field_lwtv_lezwatchurls_tmdb_providers`, name `lezwatchurls_tmdb_providers`, label "TMDB provider IDs", with the instructions "Comma-separated TMDB provider ids that are this service in the app (for example 8, 1796 for Netflix). Leave blank to let the app group providers by name." Place it after `lezwatchurls_setting_confirmed_name` and bump `modified`.
- Modify: `plugins/lwtv-plugin/php/rest-api/class-app-json.php`

**Interfaces:**
- Consumes: `Service_Map::parse_ids()`, `Service_Map::slug()`.
- Produces: `App_JSON::service_overrides(): array<int, array{slug: string, name: string}>`. It runs `get_terms()` on `lez_watch_urls` with `hide_empty => false` and a `meta_query` for a non-empty `lezwatchurls_tmdb_providers`. Each term's slug is `Service_Map::slug( term name )` and its name is the plain-text term name. When two terms claim the same provider id, the lower term id wins.

- [ ] **Step 1: Add the field and confirm it shows**
  Open a Netflix `lez_watch_urls` term in wp-admin on lwtv.local. Expected: the field appears and saves `8, 1796`.

- [ ] **Step 2: Wire `service_overrides()` into `availability()`**
  Pass its result as `$overrides` to `App_Availability::build()`.

- [ ] **Step 3: Check the override live**
  Set the HBO Max term's field to `384, 1899`, then fetch availability.
  Expected: `providers["1899"].service` is the term's slug, and the cache was flushed by the `edited_term` hook without a manual purge.

### Task 8: Contract check, docs and lint

**Files:**
- Create: `docs/architecture/app-api.md`
- Modify: `docs/README.md` (index entry), `docs/architecture/watch-providers.md` (a short "App service grouping" section that names the new field and links to `app-api.md`)

- [ ] **Step 1: Run the PWA contract checker against real output**
  Fetch `tests/Support/AppCatalogueContract.php` from LezWatch/pwa into the scratchpad with `gh api … -H 'Accept: application/vnd.github.raw'`. Read it, and write a scratchpad runner that loads it (stubbing only what its docblock says it needs) and checks both live responses.
  Expected: zero violations. Don't vendor the checker into this repo.

- [ ] **Step 2: Write `docs/architecture/app-api.md`**
  Cover the routes and the link to the contract; the build classes and what each owns; the cache keys and invalidation hooks; why `data_version` hashes the payload and not `post_modified`; how grouping works (override, then ad-tier fold, then `service_key`) and how to fix a wrong grouping with the term field; and the privacy rule that the only parameter is `schema`.

- [ ] **Step 3: Run the full suite and linters**
  Run: `vendor/bin/phpunit && composer lint && composer analyse`
  Expected: everything passes. If PHPStan flags the new class only through baseline drift, regenerate the baseline as the project notes describe, and say so.

- [ ] **Step 4: Hand back for review**
  Leave everything uncommitted on `release/8.1.0`. Summarise the diff and the live checks for the user, along with the open question from #755 that this plan answers by default: the services list is grouped automatically and overridden per term, so the maintainer's job is fixing wrong groupings.

## Deferred (not in 8.1.0)

- The unmapped and suspect providers admin report from the #755 comment. The data it needs (`Service_Map` output) exists after this plan.
- Characters per show (#755 item 4), viewer score (#757), and per-region availability files (only if Task 6 Step 8 trips).
