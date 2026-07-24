# Security Review Remediation — Design

**Date:** 2026-07-23
**Branch:** `chore/security-review`
**Source:** Claude Security scan of `plugins/lwtv-plugin` @ `c68ea19f` — 30 verified findings (8 HIGH, 18 MEDIUM, 4 LOW)

---

## 1. Goal

Close all 30 verified findings without regressing the public REST API contract (documented at `docs.lezwatchtv.com`, consumed by WikiData mix-n-match and the block editor) or the show-score / CPT-relationship integrity called out in `CLAUDE.md`.

The findings collapse into **six root causes**. Fixing by root cause — not one finding at a time — is what keeps this small and low-risk:

| Root cause | Findings | Core fix |
|---|---|---|
| A. Public read endpoints leak non-published / privacy-hidden records by numeric ID or slug | F1, F3, F4, F5, F6, F7, F11 | Require `'publish' === get_post_status()`; for actors also honor `hide_actor_data()` |
| B. Unauthenticated wikidata route writes post meta + fans out live outbound HTTP per matched actor (DoS + tamper) | F8, F10, F15 | Split read from write: public GET is read-only from stored meta; refresh/write moves behind capability + WP-CLI/cron; reject empty slug |
| C. Output not escaped (stored XSS) | F2, F9, F18, F19, F20, F21, F22, F24, F26 | `esc_url()` / `esc_attr()` / `esc_html()` at every sink |
| D. Author-box references arbitrary user with no ownership/capability check | F17, F25 | Restrict `users` to the post author unless `edit_others_posts` |
| E. Admin-gated fields enforced only in the UI, not at save | F13, F14 | Re-check the capability on the save/update path |
| F. Outbound API calls over cleartext HTTP + unvalidated response reuse | F12, F23, F28, F29, F30 | Force HTTPS; validate/encode values before use |

## 2. Cross-cutting decisions

1. **No new abstraction for the status check.** The codebase idiom is inline `'publish' === get_post_status( $id )`. We match it rather than introduce a helper (YAGNI). `export_actor()` in `class-export-json.php` is the existing model for the actor-privacy pattern (`hide_actor_data($id, 'all'|'dob'|'socials')`) — every actor-facing fix mirrors it.
2. **Severity-led phases, but co-located findings travel together.** The user chose severity phasing (Phase 1 HIGH → Phase 2 MEDIUM → Phase 3 LOW). However, revisiting the same file across phases is wasteful and risk-prone. Where a lower-severity finding lives in a file a HIGH fix already touches (or shares B/C/F's single fix), it rides along in the earlier phase. Each such pull-forward is flagged explicitly below so the reviewer can veto it.
3. **Wikidata read/write split resolves three findings at once.** `check_actors_wikidata()` (`debugger/class-actors.php:488`) both writes `lezactors_saved_wikidata` meta and makes live `wp_remote_get()` calls. The public GET path will read *only* the stored `lezactors_saved_wikidata` meta; the comparison-and-write stays available to authenticated editors and the existing WP-CLI/cron refresh. This kills F8, F10, and F15 together, so all three land in Phase 1 alongside F8 (HIGH).
4. **Verification is per finding.** Each fix gets a written before/after reproduction note (the exact request or role + expected old vs. new behavior). Run `composer lint` after each phase. No PHPUnit harness exists for these paths, so verification is manual/`wp shell` + lint.
5. **Low-confidence finding F30** is real but already mitigated at render by `esc_url()`; its fix is folded into the F12/F28 HTTPS change (Phase 3) — no separate work.

## 3. Phase 1 — HIGH (8 findings + 2 pulled-forward MEDIUM)

### Group A1 — Publish/privacy guards on public read endpoints
- **F3** `rest-api/class-stats-json.php` `format_id()` (~:557): change guard from truthy `! $post_status` to `'publish' !== $post_status`. In the `actor` branch, short-circuit sexuality/gender/queer when `hide_actor_data($id,'all')`.
- **F7** `queeries/class-is-actor-queer.php` `make()` (:42): reject any status other than `publish` (not just `private`) *before* the `update_post_meta()` on :59 — this also removes the anonymous-write side effect. Apply the same to `Is_Actor_Trans::make()`.
- **F4** `rest-api/class-export-json.php` `export_show()` (:474): add `'publish' === get_post_status( $page->ID )` to the type guard.
- **F5** same file `export_character()` (:578): same publish guard.
- **F1** `rest-api/class-wikidata.php` `get_by_post_id()` (:101): require `publish` + honor `hide_actor_data()` for dob/socials before returning comparison data.
- **F6** same file `get_by_actor_slug()` (:167): add `AND post_status = 'publish'` to the SQL, and honor `hide_actor_data()` downstream.

### Group B1 — Wikidata write lockdown + DoS (F8, +F10, +F15)
- **F8** `class-wikidata.php` `get_by_actor_slug()` (:167): reject empty/blank `$slug` before building the `LIKE`; add a sane `LIMIT`.
- **F10 / F15** (pulled forward — same call chain): public GET returns stored `lezactors_saved_wikidata` meta only; move the live-fetch + `update_post_meta` in `check_actors_wikidata()` behind an editor capability / existing WP-CLI path. Read-only public route = no anonymous writes, no outbound amplification.

### Group C1 — Author-box stored XSS (F2, +F9 pulled forward)
- **F2** `features/class-author-box.php` `get_author_details()` (:123): `esc_url()` the URL, `esc_attr()` the display name/label before building `href`/`aria-label`.
- **F9 + F26** (pulled forward — same `social()` method, lines :54 and :57) `theme/class-data-author.php`: `esc_url()` every contact-method URL / `esc_html()` displayed text. Fixing them separately would mean editing one method twice, so both land here.

## 4. Phase 2 — MEDIUM

- **F11** `rest-api/class-whats-on-json.php` `whats_on_show()` (:239): add publish + post-type guard after ID resolution (mirror the calendar class's existing check).
- **F16** `theme/class-actor-birthday.php` `get()` (:53): gate the birthday banner/age on `hide_actor_data($id,'dob')` / `'all'`.
- **F13** `plugins/class-acf.php` (:112): add an `acf/update_value` (or `acf/save_post`) guard re-checking `manage_options` for the admin-only field keys.
- **F14** `features/class-user-profiles.php` `save_extra_profile_fields()` (:124): gate the `jobrole` write behind the same capability the display uses, not the generic `edit_user`.
- **F17 / F25** `features/class-author-box.php` `make()` (:89/:95): restrict the `users` attribute to the post's actual author unless `current_user_can('edit_others_posts')`.
- **F18–F22** calendar display (`class-display-list.php` :92/:110, `class-display-grid.php` :122/:163, `class-display-calendar.php` :155): `esc_html()` all TVMaze-derived show/episode names/titles before output.
- **F12** `calendar/class-tvmaze.php` `get_tvmaze_info_show()` (:98): switch the three TVMaze calls to `https://` (grouped with Phase 3 F28/F29/F30).
- **F23** `_components/class-cpts.php` `get_tmdb_info()` (:113): `rawurlencode()` (or numeric-validate) the TMDB/IMDB ID before splicing into the URL, and add a digits-only `sanitize_callback` to the meta registration.
- **F24** `cpts/class-related-posts.php` `related_archive_header()` (:176): `esc_html( get_the_title() )` + `esc_url()` the permalink.

## 5. Phase 3 — LOW

- **F27** `rest-api/class-list-json.php` `list()` (:81): make the dead `in_array()` allowlist actually gate — return the error and stop when the type isn't allowed.
- **F28** `grading/class-tvmaze.php` `update_scores()` (:82): HTTPS.
- **F29** `rest-api/class-whats-on-json.php` `make_show_array()` (:317): HTTPS for TVMaze + validate fetched `href` is on `api.tvmaze.com` before the second `wp_remote_get()`.
- **F30** `grading/class-tvmaze.php` (:90): validate the response `url` is well-formed + tvmaze.com host before persisting (HTTPS from F28 removes the MITM vector).

*Efficiency note:* F12 (Phase 2), F28/F29/F30 (Phase 3) are all TVMaze-HTTPS. If preferred, do all four TVMaze findings in a single "TVMaze HTTPS + response validation" commit rather than splitting across phases.

## 6. Testing / verification

- Per finding: a documented reproduction (exact `curl` / role + expected old→new behavior).
- Publish-guard findings: verify a draft/private ID now returns the error path; verify a published ID still returns identical data (no regression for legit consumers).
- Privacy findings: set a test actor's `hide_dob`/`hide_socials`, confirm suppression on the fixed path.
- Wikidata: confirm public GET no longer writes meta (check `lezactors_saved_wikidata` timestamp unchanged) and makes no outbound call; confirm the authenticated refresh path still works.
- `composer lint` clean after every phase; no re-added excluded rules.

## 7. Risks & API-consumer impact

- Publish guards **tighten** the public API — any consumer relying on reading unpublished data was relying on a bug. Documented endpoints only ever intended to serve published records, so this is contract-preserving.
- Wikidata read-only split: confirm the block editor panel and any front-end wikidata preview read from stored meta (or are authenticated) before shipping. This is the one change needing a pre-flight check.
- No scoring weights or CPT relationships are touched.

## 8. Out of scope

Theme root, SCSS/JS, build scripts, and the rest of the repository (this was a scoped scan of `plugins/lwtv-plugin` only). Third-party vendored libs (ICal, tablesorter, facetwp-pagination) untouched.
