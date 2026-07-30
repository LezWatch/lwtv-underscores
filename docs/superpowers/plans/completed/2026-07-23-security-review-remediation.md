# Security Review Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close all 30 verified findings from the Claude Security scan of `plugins/lwtv-plugin` without regressing the public REST API contract or show-score/CPT integrity.

**Architecture:** Fix by root cause across six clusters — publish/privacy guards on public read endpoints, a read/write split on the wikidata route, output escaping, author-box authorization, save-time capability checks, and forcing HTTPS on outbound API calls. Findings that share a file or a single fix are done together even when severities differ, so no file is edited twice.

**Tech Stack:** PHP 8.1+, WordPress 6.5+, WordPress-Extra coding standard (`phpcs.xml.dist`), namespace `LWTV\`.

## Global Constraints

- PHP 8.1+ minimum; do not use syntax unavailable in 8.1.
- WordPress-Extra standard. Run `composer lint` after each task; must stay clean. Do NOT re-add any excluded rule from `phpcs.xml.dist`.
- Do NOT wrap the custom auto-escaped functions (`lwtv_plugin`, `get_symbolicon`, `lwtv_symbolicons`, `LWTV_Features`, `LWTV_Statistics`, pagination helpers) in `esc_*`.
- All user-facing strings use the `'lwtv'` text domain.
- Meta key prefixes: shows `lezshows_`, characters `lezchars_`, actors `lezactors_`.
- Do NOT alter scoring weights in `class-calculations.php`. No task here touches scoring.
- The actor-privacy idiom is `lwtv_plugin()->hide_actor_data( $id, 'all'|'dob'|'socials' )`, returning `true` when the data must be hidden. `export_actor()` in `rest-api/class-export-json.php` is the reference implementation.
- **Testing note:** there is no PHPUnit harness for these paths. Each task's verification is (a) `composer lint` clean and (b) a documented manual reproduction via `wp shell` / `curl` / role switch, with expected old→new behavior stated. This is a deliberate deviation from the TDD default because no runnable test rig exists for this plugin.
- Commit after every task. Branch: `chore/security-review`. Do NOT push or open a PR unless the user asks.

---

## PHASE 1 — HIGH severity

### Task 1: Publish/privacy guard on stats `format_id` + queer/trans status queries (F3, F7)

**Files:**
- Modify: `plugins/lwtv-plugin/php/rest-api/class-stats-json.php` (`format_id`, ~:557-565)
- Modify: `plugins/lwtv-plugin/php/queeries/class-is-actor-queer.php` (`make`, :42)
- Modify: `plugins/lwtv-plugin/php/queeries/class-is-actor-trans.php` (`make`, :44)

**Interfaces:**
- Consumes: `lwtv_plugin()->hide_actor_data( $id, 'all' )`.
- Produces: `Is_Actor_Queer::make()` and `Is_Actor_Trans::make()` return `false` for any post whose status is not `publish`; `format_id()` returns the invalid-ID error array for non-published IDs.

- [ ] **Step 1: Tighten the `format_id` status gate.** In `class-stats-json.php`, replace the guard:

```php
		$post_status = get_post_status( $id );
		$post_type   = get_post_type( $id );

		if ( 'publish' !== $post_status || 'post_type_' . $cpt . 's' !== $post_type ) {
			$stats_array = array( 'Error: Invalid ' . ucfirst( $cpt ) . ' ID provided.' );
			return $stats_array;
		}
```

- [ ] **Step 2: Respect actor privacy in the actor branch.** In the same method, at the top of `case 'actor':`, before building `$stats_array`:

```php
			case 'actor':
				if ( lwtv_plugin()->hide_actor_data( $id, 'all' ) ) {
					return array( 'Error: Invalid Actor ID provided.' );
				}
				$stats_array = array(
```

- [ ] **Step 3: Reject non-published in `Is_Actor_Queer::make()`.** Replace the `private`-only check (:40-44) with a publish-only allow, placed *before* the `update_post_meta()` on line 59:

```php
		// Only compute/store queer status for published actors. Any other
		// status (private/draft/pending/future/trash) is treated as not-queer
		// to protect unpublished and privacy-hidden identities, and to avoid
		// writing post meta for unpublished records.
		if ( 'publish' !== get_post_status( $the_id ) ) {
			return false;
		}
```

- [ ] **Step 4: Reject non-published in `Is_Actor_Trans::make()`.** Replace the `private`-only block (:43-47) with:

```php
		// Only published actors are evaluated; anything else is auto-false.
		if ( 'publish' !== get_post_status( $the_id ) ) {
			lwtv_plugin()->set_transient( $cache_key, false, HOUR_IN_SECONDS );
			return false;
		}
```

- [ ] **Step 5: Lint.**

Run: `composer lint`
Expected: no new errors in the three modified files.

- [ ] **Step 6: Manual verification.**

Run: `wp shell` then:
```php
// Pick a draft actor ID $d and a published actor ID $p.
( new LWTV\Rest_API\Stats_JSON() )->format_id( 'actor', $d ); // expect: Error array
( new LWTV\Rest_API\Stats_JSON() )->format_id( 'actor', $p ); // expect: full data
( new LWTV\Queeries\Is_Actor_Queer() )->make( $d );           // expect: false, and no lezactors_queer_status meta written on $d
```
Confirm `GET /wp-json/lwtv/v1/stats/actors/id/{$d}` now returns the error, `{$p}` unchanged.

- [ ] **Step 7: Commit.**

```bash
git add plugins/lwtv-plugin/php/rest-api/class-stats-json.php plugins/lwtv-plugin/php/queeries/class-is-actor-queer.php plugins/lwtv-plugin/php/queeries/class-is-actor-trans.php
git commit -m "fix(security): require published status on stats id + queer/trans queries (F3, F7)"
```

---

### Task 2: Publish guard on export show + character (F4, F5)

**Files:**
- Modify: `plugins/lwtv-plugin/php/rest-api/class-export-json.php` (`export_show` :474, `export_character` :578)

**Interfaces:**
- Consumes: nothing new.
- Produces: `export_show()` / `export_character()` return `array()` (→ 400 error upstream) for non-published posts.

- [ ] **Step 1: Guard `export_show`.** Change the `if ( isset( $page ) && CPT_Shows::SLUG === get_post_type( $page->ID ) ) {` line to also require publish:

```php
		// Let's make sure it exists, is a show, and is published.
		if ( isset( $page ) && CPT_Shows::SLUG === get_post_type( $page->ID ) && 'publish' === get_post_status( $page->ID ) ) {
```

- [ ] **Step 2: Guard `export_character`.** Change the equivalent line to:

```php
		// Let's make sure it exists, is a character, and is published.
		if ( isset( $page ) && CPT_Characters::SLUG === get_post_type( $page->ID ) && 'publish' === get_post_status( $page->ID ) ) {
```

- [ ] **Step 3: Lint.** Run: `composer lint` — clean.

- [ ] **Step 4: Manual verification.**

`GET /wp-json/lwtv/v1/export/show/{draft_id}` → `no_type` 400 error.
`GET /wp-json/lwtv/v1/export/show/{published_id}` → unchanged data.
Repeat for `/export/character/{id}`.

- [ ] **Step 5: Commit.**

```bash
git add plugins/lwtv-plugin/php/rest-api/class-export-json.php
git commit -m "fix(security): require published status on show/character export (F4, F5)"
```

---

### Task 3: Wikidata slug/id guards — publish, privacy, empty-slug, LIMIT (F1, F6, F8)

**Files:**
- Modify: `plugins/lwtv-plugin/php/rest-api/class-wikidata.php` (`get_by_post_id` :101, `get_by_actor_slug` :167)

**Interfaces:**
- Consumes: `lwtv_plugin()->hide_actor_data( $id, 'all' )`.
- Produces: both helpers return an error array for non-published, privacy-hidden, or empty-slug input; the slug query is publish-only and capped at 50 rows.

- [ ] **Step 1: Guard `get_by_post_id`.** Replace the body's opening check:

```php
	private function get_by_post_id( $post_id ): array {
		if ( get_post_type( $post_id ) !== CPT_Actors::SLUG || 'publish' !== get_post_status( $post_id ) ) {
			return array(
				'error' => 'Invalid post ID',
			);
		}

		// Respect the actor's own privacy request.
		if ( lwtv_plugin()->hide_actor_data( $post_id, 'all' ) ) {
			return array(
				'error' => 'Invalid post ID',
			);
		}

		$wikidata = ( new Debug_Actors() )->check_actors_wikidata( $post_id );

		return array( $wikidata );
	}
```

(This keeps the existing `check_actors_wikidata` call; Task 4 swaps all four call sites to the read-only-aware helper.)

- [ ] **Step 2: Guard `get_by_actor_slug` — reject empty slug, add publish filter + LIMIT.** Replace the method body up to the loop:

```php
	private function get_by_actor_slug( $slug ): array {
		global $wpdb;

		$actors = array();

		// Reject empty/blank slugs so the LIKE never degrades to a match-all '%'.
		$slug = trim( (string) $slug );
		if ( '' === $slug ) {
			return array(
				'error' => 'No such actor found.',
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$possible_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post_type_actors' AND post_status = 'publish' AND post_name LIKE %s LIMIT 50",
				$wpdb->esc_like( $slug ) . '%'
			)
		);

		if ( ! $possible_ids ) {
			return array(
				'error' => 'No such actor found.',
			);
		}

		foreach ( $possible_ids as $actor ) {
			if ( lwtv_plugin()->hide_actor_data( $actor, 'all' ) ) {
				continue;
			}
			$actors[] = ( new Debug_Actors() )->check_actors_wikidata( $actor );
		}

		return $actors;
	}
```

(Same as Step 1 — Task 4 swaps the `check_actors_wikidata` call to the read-only-aware helper.)

- [ ] **Step 3: Lint.** Run: `composer lint` — clean.

- [ ] **Step 4: Manual verification.**

`GET /wp-json/lwtv/v1/wikidata/` (empty) → `{"error":"No data found for "}` (no full-table scan; confirm via query log that no `LIKE '%'` ran).
`GET /wp-json/lwtv/v1/wikidata/{draft_actor_slug}` → no such actor.
`GET /wp-json/lwtv/v1/wikidata/{published_actor_id}` → data (subject to Task 4 read-only behavior).

- [ ] **Step 5: Commit.**

```bash
git add plugins/lwtv-plugin/php/rest-api/class-wikidata.php
git commit -m "fix(security): guard wikidata slug/id lookups — publish, privacy, empty-slug, LIMIT (F1, F6, F8)"
```

---

### Task 4: Wikidata read/write split — public route is read-only (F10, F15)

**Files:**
- Modify: `plugins/lwtv-plugin/php/rest-api/class-wikidata.php` (add `get_actor_wikidata`; update `get_by_post_id`, `get_by_imdb`, `get_by_wikidata`, `get_by_actor_slug`)

**Interfaces:**
- Consumes: `Debug_Actors::check_actors_wikidata( $id )` (live fetch + meta write — authenticated/CLI only); `get_post_meta( $id, 'lezactors_saved_wikidata', true )` (stored comparison).
- Produces: `private function get_actor_wikidata( $actor_id ): array` — returns fresh comparison via `check_actors_wikidata()` when `current_user_can( 'edit_posts' )`, otherwise the stored `lezactors_saved_wikidata` meta with no outbound call and no write.

**Context:** `check_actors_wikidata()` writes `lezactors_saved_wikidata` meta and makes live `wp_remote_get()` calls. Its legitimate callers (WP-CLI `cli-check.php`, `validator/class-actor-wiki.php`, the authenticated `admin_post_lwtv_data_check_wikidata_actors` action) are unaffected — only the four public REST helpers stop calling it directly for anonymous users. Block-editor requests carry the logged-in editor's cookie/nonce, so `current_user_can( 'edit_posts' )` is true there and the panel still gets fresh data.

- [ ] **Step 1: Add the read-only-aware helper.** Add this private method to the class:

```php
	/**
	 * Get an actor's WikiData comparison for the REST response.
	 *
	 * Unauthenticated callers get the stored comparison meta only — no live
	 * WikiData fetch and no post-meta write. Users who can edit posts (e.g. the
	 * block editor panel) get a fresh comparison, which also refreshes the meta.
	 *
	 * @param int $actor_id Actor post ID.
	 * @return array
	 */
	private function get_actor_wikidata( $actor_id ): array {
		if ( current_user_can( 'edit_posts' ) ) {
			return ( new Debug_Actors() )->check_actors_wikidata( $actor_id );
		}

		$stored = get_post_meta( $actor_id, 'lezactors_saved_wikidata', true );
		return is_array( $stored ) ? $stored : array();
	}
```

- [ ] **Step 2: Route all four helpers through it.** Replace every `( new Debug_Actors() )->check_actors_wikidata( $x )` call in `get_by_post_id`, `get_by_imdb`, `get_by_wikidata`, and `get_by_actor_slug` with `$this->get_actor_wikidata( $x )`. (Four call sites total.)

- [ ] **Step 3: Lint.** Run: `composer lint` — clean.

- [ ] **Step 4: Manual verification.**

As an anonymous client: `curl https://lwtv.local/wp-json/lwtv/v1/wikidata/{published_actor_id}` → returns stored comparison; confirm `lezactors_saved_wikidata` `post_modified`/value is unchanged afterward and NO outbound request to wikidata.org occurred (check debug log / network).
As a logged-in editor (cookie + `X-WP-Nonce`): same URL → fresh comparison, meta updated. Confirm the wikidata-actor block panel in wp-admin still renders.

- [ ] **Step 5: Commit.**

```bash
git add plugins/lwtv-plugin/php/rest-api/class-wikidata.php
git commit -m "fix(security): make public wikidata route read-only; gate live fetch/write behind edit_posts (F10, F15)"
```

---

### Task 5: Escape author-box + author-social output (F2, F9, F26)

**Files:**
- Modify: `plugins/lwtv-plugin/php/features/class-author-box.php` (`get_author_details`, :117-143)
- Modify: `plugins/lwtv-plugin/php/theme/class-data-author.php` (`social`, :54-60)

**Interfaces:**
- Consumes: `esc_url()`, `esc_attr()`, `esc_html()`.
- Produces: all user-controlled contact-method URLs and display names are escaped at the point of HTML interpolation.

- [ ] **Step 1: Escape in `Author_Box::get_author_details`.** Replace the social-link build (:121-124):

```php
		foreach ( $content['social'] as $social => $url ) {
			$data           = self::$social_array[ $social ];
			$social_array[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow" aria-label="' . esc_attr( 'Follow ' . $content['name'] . ' on ' . $data['name'] ) . '">' . lwtv_plugin()->get_symbolicon( $data['icon'], 'fa-' . $social ) . '</a>';
		}
```

- [ ] **Step 2: Escape the name/title/permalink in the format branches.** Replace the `view_articles` line and the three `switch` cases so every echo of `$content['name']`, `$author_title`, and `$content['url']` is escaped:

```php
		$social_array  = array_filter( $social_array );
		$view_articles = ( $content['postcount'] > 0 ) ? '<div class="author-archives">' . lwtv_plugin()->get_symbolicon( svg: 'newspaper.svg', icon: 'svg-newspaper-o' ) . '&nbsp;<a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID', $content['id'] ) ) ) . '">' . esc_html( 'View all articles by ' . $content['name'] ) . '</a></div>' : '';
		$author_title  = ( '' !== $content['title'] ) ? ' (' . esc_html( $content['title'] ) . ')' : '';

		switch ( $format ) {
			case 'thumbnail':
				$author_details = '<div>' . $content['avatar'] . '<br>' . esc_html( $content['name'] ) . ' ' . $author_title . '</div>';
				break;
			case 'compact':
				$author_details = '<div class="col-sm-2">' . $content['avatar'] . '</div><div class="col-sm"><span class="author_name author_box_compact"><a href="' . esc_url( $content['url'] ) . '">' . esc_html( $content['name'] ) . '</a>' . $author_title . ' <span class="author_box_social">' . implode( ' ', $social_array ) . '</span></span><hr><div class="author-details">' . $view_articles . '</div></div>';
				break;
			case 'large':
				$author_details = '<div class="col-sm-2">' . $content['avatar'] . '</div><div class="col-sm"><span class="author_name author_box_large">' . esc_html( $content['name'] ) . $author_title . ' <span class="author_box_social">' . implode( ' ', $social_array ) . '</span></span><hr><div class="author-bio">' . nl2br( esc_html( $content['bio'] ) ) . '</div><div class="author-details">' . $view_articles . $content['fav_shows'] . '</div>';
				break;
		}
```

Note: `$author_title` is already escaped where built, so it is interpolated raw in the cases (do not double-escape). `$content['bio']` is now `esc_html()` + `nl2br()`.

- [ ] **Step 3: Escape in `Data_Author::social`.** Replace the six social-link lines (:54-60) so every `href` uses `esc_url()`. For the username-based ones the full URL is escaped; for the raw-value ones (`bluesky`, `tumblr`, `website`, `mastodon`) the stored value is escaped:

```php
		$bluesky   = ( ! empty( $user_socials['bluesky'] ) ) ? '<a href="' . esc_url( $user_socials['bluesky'] ) . '" target="_blank" rel="nofollow">' . lwtv_plugin()->get_symbolicon( svg: 'bluesky.svg', icon: 'svg-bluesky', max_size: '20' ) . '</a>' : false;
		$instagram = ( ! empty( $user_socials['instagram'] ) ) ? '<a href="' . esc_url( 'https://instagram.com/' . $user_socials['instagram'] ) . '" target="_blank" rel="nofollow">' . lwtv_plugin()->get_symbolicon( svg: 'instagram.svg', icon: 'svg-instagram', max_size: '20' ) . '</a>' : false;
		$threads   = ( ! empty( $user_socials['threads'] ) ) ? '<a href="' . esc_url( 'https://threads.net/' . $user_socials['threads'] ) . '" target="_blank" rel="nofollow">' . lwtv_plugin()->get_symbolicon( svg: 'threads.svg', icon: 'svg-threads', max_size: '20' ) . '</a>' : false;
		$twitter   = ( ! empty( $user_socials['twitter'] ) ) ? '<a href="' . esc_url( 'https://twitter.com/' . $user_socials['twitter'] ) . '" target="_blank" rel="nofollow">' . lwtv_plugin()->get_symbolicon( svg: 'x-twitter.svg', icon: 'svg-x-twitter', max_size: '20' ) . '</a>' : false;
		$tumblr    = ( ! empty( $user_socials['tumblr'] ) ) ? '<a href="' . esc_url( $user_socials['tumblr'] ) . '" target="_blank" rel="nofollow">' . lwtv_plugin()->get_symbolicon( svg: 'tumblr.svg', icon: 'svg-tumblr', max_size: '20' ) . '</a>' : false;
		$website   = ( ! empty( $user_socials['website'] ) ) ? '<a href="' . esc_url( $user_socials['website'] ) . '" target="_blank" rel="nofollow">' . lwtv_plugin()->get_symbolicon( svg: 'home.svg', icon: 'svg-home', max_size: '20' ) . '</a>' : false;
		$mastodon  = ( ! empty( $user_socials['mastodon'] ) ) ? '<a href="' . esc_url( $user_socials['mastodon'] ) . '" target="_blank" rel="nofollow">' . lwtv_plugin()->get_symbolicon( svg: 'mastodon.svg', icon: 'svg-mastodon', max_size: '20' ) . '</a>' : false;
```

- [ ] **Step 4: Lint.** Run: `composer lint` — clean.

- [ ] **Step 5: Manual verification.**

On a test user, set the Website contact field to `https://x.com" onmouseover="alert(1)` and the display name to `<script>alert(2)</script>`. Render `[author-box users=<login>]` on a draft preview and view the user's author archive. Confirm: the injected `onmouseover` does not survive in the `href`, and the script tag renders as inert text.

- [ ] **Step 6: Commit.**

```bash
git add plugins/lwtv-plugin/php/features/class-author-box.php plugins/lwtv-plugin/php/theme/class-data-author.php
git commit -m "fix(security): escape author-box + author-social URLs and names (F2, F9, F26)"
```

---

## PHASE 2 — MEDIUM severity

### Task 6: Publish + post-type guard on whats-on show lookup (F11)

**Files:**
- Modify: `plugins/lwtv-plugin/php/rest-api/class-whats-on-json.php` (`whats_on_show`, ~:225-242)

**Interfaces:**
- Produces: the on-air branch runs only for a published `post_type_shows` post.

- [ ] **Step 1: Add the guard after `$show_id` is resolved and before the `$on_air` read.** Insert:

```php
			// Only published shows are exposed via this endpoint.
			if ( empty( $show_id ) || 'post_type_shows' !== get_post_type( $show_id ) || 'publish' !== get_post_status( $show_id ) ) {
				return $return;
			}

			// Get the on-air status.
			$on_air = get_post_meta( $show_id, 'lezshows_on_air', true );
```

- [ ] **Step 2: Lint.** Run: `composer lint` — clean.

- [ ] **Step 3: Manual verification.**

`GET /wp-json/lwtv/v1/whats-on/show/{draft_show_slug_or_id}` → the default "no upcoming airing" reply, with no title/episode leak.
`GET /wp-json/lwtv/v1/whats-on/show/{published_on_air_show}` → unchanged.

- [ ] **Step 4: Commit.**

```bash
git add plugins/lwtv-plugin/php/rest-api/class-whats-on-json.php
git commit -m "fix(security): require published show on whats-on endpoint (F11)"
```

---

### Task 7: Actor birthday banner respects hide-DOB privacy (F16)

**Files:**
- Modify: `plugins/lwtv-plugin/php/theme/class-actor-birthday.php` (`get`, :36)

**Interfaces:**
- Consumes: `lwtv_plugin()->hide_actor_data( $id, 'dob' )` and `'all'`.
- Produces: no banner/age output when DOB is hidden.

- [ ] **Step 1: Gate the banner.** Add at the top of `get()`:

```php
	public function get( $the_id ) {
		// Honor the actor's DOB/all privacy request — the birthday banner
		// reveals birth month/day and exact age.
		if ( lwtv_plugin()->hide_actor_data( $the_id, 'dob' ) || lwtv_plugin()->hide_actor_data( $the_id, 'all' ) ) {
			return;
		}

		if ( $this->make( $the_id ) && ! get_post_meta( $the_id, 'lezactors_death', true ) ) {
```

- [ ] **Step 2: Lint.** Run: `composer lint` — clean.

- [ ] **Step 3: Manual verification.**

Set a test actor's DOB to today and `hide_dob`. Load their single page → no birthday banner. Clear the flag → banner returns.

- [ ] **Step 4: Commit.**

```bash
git add plugins/lwtv-plugin/php/theme/class-actor-birthday.php
git commit -m "fix(security): respect hide-DOB privacy on birthday banner (F16)"
```

---

### Task 8: Enforce ADMIN_ONLY_FIELDS at save time (F13)

**Files:**
- Modify: `plugins/lwtv-plugin/php/plugins/class-acf.php` (`__construct` :110-113; add `filter_admin_only_value`)

**Interfaces:**
- Consumes: `current_user_can( 'manage_options' )`; ACF `acf/update_value/name=<field>` filter (args: `$value, $post_id, $field`).
- Produces: `filter_admin_only_value( $value, $post_id, $field )` — returns the existing stored value (discarding the submitted one) for non-admins, so admin-only fields cannot be overwritten via a crafted POST.

- [ ] **Step 1: Register the save-time guard alongside the existing prepare_field hook.** In the `foreach ( self::ADMIN_ONLY_FIELDS ... )` loop (:111-113):

```php
		// Restrict specific fields to administrators only — hide in the UI AND
		// reject writes from non-admins (prepare_field alone does not gate saves).
		foreach ( self::ADMIN_ONLY_FIELDS as $field_name ) {
			add_filter( 'acf/prepare_field/name=' . $field_name, array( $this, 'restrict_to_admin' ) );
			add_filter( 'acf/update_value/name=' . $field_name, array( $this, 'filter_admin_only_value' ), 10, 3 );
		}
```

- [ ] **Step 2: Add the filter method** next to `restrict_to_admin`:

```php
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
		return get_field( $field['name'], $post_id );
	}
```

- [ ] **Step 3: Lint.** Run: `composer lint` — clean.

- [ ] **Step 4: Manual verification.**

As a non-admin editor with a valid nonce, POST an update to a show adding `acf[field_...worthit_show_we_love]=1`. Confirm the stored value is unchanged. As an admin, the same change persists.

- [ ] **Step 5: Commit.**

```bash
git add plugins/lwtv-plugin/php/plugins/class-acf.php
git commit -m "fix(security): enforce admin-only ACF fields at save time (F13)"
```

---

### Task 9: Gate `jobrole` profile save behind its display capability (F14)

**Files:**
- Modify: `plugins/lwtv-plugin/php/features/class-user-profiles.php` (`save_extra_profile_fields`, :119-129)

**Interfaces:**
- Produces: `jobrole` is written only when `current_user_can( 'update_core' )` (the same capability the field's display is gated on at :81); other fields keep saving with the existing `edit_user` check. All `$_POST` reads are isset-guarded.

- [ ] **Step 1: Rewrite the save handler** with the capability gate and isset guards:

```php
	public function save_extra_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		// jobrole is an admin-only field (see extra_profile_fields) — gate the
		// write on the same capability, not the generic edit_user.
		if ( current_user_can( 'update_core' ) && isset( $_POST['jobrole'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_user_meta( $user_id, 'jobrole', sanitize_text_field( wp_unslash( $_POST['jobrole'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['gender'] ) ) {
			update_user_meta( $user_id, 'gender', sanitize_text_field( wp_unslash( $_POST['gender'] ) ) );
		}
		if ( isset( $_POST['sexuality'] ) ) {
			update_user_meta( $user_id, 'sexuality', sanitize_text_field( wp_unslash( $_POST['sexuality'] ) ) );
		}
		if ( isset( $_POST['pronouns'] ) ) {
			update_user_meta( $user_id, 'pronouns', sanitize_text_field( wp_unslash( $_POST['pronouns'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
```

- [ ] **Step 2: Lint.** Run: `composer lint` — clean.

- [ ] **Step 3: Manual verification.**

As a Subscriber editing their own profile, POST an added `jobrole=Editor` field → confirm `jobrole` user meta is NOT set. As an admin, `jobrole` saves normally.

- [ ] **Step 4: Commit.**

```bash
git add plugins/lwtv-plugin/php/features/class-user-profiles.php
git commit -m "fix(security): gate jobrole profile save behind update_core (F14)"
```

---

### Task 10: Restrict author-box `users` attribute to the post author (F17, F25)

**Files:**
- Modify: `plugins/lwtv-plugin/php/features/class-author-box.php` (`make`, :81-101)

**Interfaces:**
- Consumes: `get_post_field( 'post_author', get_the_ID() )`, `current_user_can( 'edit_others_posts' )`.
- Produces: the resolved `$author_id` is forced to the current post's author unless the current user can `edit_others_posts`; the default falls back to the current post's author when no valid target is allowed.

- [ ] **Step 1: Add the ownership gate** immediately after `$author_id` is resolved (after the `if/else` block at :88-93, before `$user = get_userdata( $author_id );`):

```php
		// A content author may only render the author box for the post's own
		// author. Users who can edit others' posts may target any user ID.
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$post_author = (int) get_post_field( 'post_author', get_the_ID() );
			if ( $post_author && $author_id !== $post_author ) {
				$author_id = $post_author;
			}
		}
```

- [ ] **Step 2: Lint.** Run: `composer lint` — clean.

- [ ] **Step 3: Manual verification.**

As an Author, publish a post containing `[author-box users=<another_user_id>]`. Confirm the rendered box shows the *post author's* profile, not the targeted user's. As an Editor with `edit_others_posts`, the targeted user still renders (intended for editorial use).

- [ ] **Step 4: Commit.**

```bash
git add plugins/lwtv-plugin/php/features/class-author-box.php
git commit -m "fix(security): restrict author-box users attr to post author (F17, F25)"
```

---

### Task 11: Escape TVMaze-derived names/titles in calendar output (F18–F22)

**Files:**
- Modify: `plugins/lwtv-plugin/php/calendar/class-display-list.php` (:92, :110)
- Modify: `plugins/lwtv-plugin/php/calendar/class-display-grid.php` (:122-123, :163)
- Modify: `plugins/lwtv-plugin/php/calendar/class-display-calendar.php` (:152-154)

**Interfaces:**
- Consumes: `esc_html()`.
- Produces: every `$show['show_name']` and `$show['title']` string is `esc_html()`-escaped before HTML interpolation. `$show['episode_badge']` is plugin-generated markup and stays raw.

- [ ] **Step 1: `class-display-list.php` — multiple + single.** Replace :92 and :95-96, and :110:

```php
		$show_content  = '<em>' . esc_html( $show['show_name'] ) . $show['episode_badge'] . '</em>';
		$show_content .= '<ul>';

		foreach ( $show['title'] as $one_show ) {
			$show_content .= '<li>' . esc_html( $one_show ) . '</li>';
		}
```

```php
	private function display_single_episode_list( array $show ): string {
		return '<em>' . esc_html( $show['show_name'] ) . '</em> - ' . esc_html( $show['title'] );
	}
```

- [ ] **Step 2: `class-display-grid.php` — single card (:122-123).**

```php
					<p class="card-title">' . esc_html( $show['show_name'] ) . '</p>
					<p class="card-text"><small>' . esc_html( $show['title'] ) . '</small></p>
```

- [ ] **Step 3: `class-display-grid.php` — multiple card (:163).**

```php
					<p class="card-title">' . esc_html( $show['show_name'] ) . '</p>
```

- [ ] **Step 4: `class-display-calendar.php` — day cell (:152-154).**

```php
				$show_name    = esc_html( $show['show_name'] );
				$lwtv_date    = $show['time_data']['formatted_time'];
				$show_content = ( is_array( $show['title'] ) ) ? $show_name . $show['episode_badge'] : $show_name;
```

(Here `$show_name` is escaped once and reused; `$show['title']` is only used as an array-count discriminator, not printed, so no extra escape needed.)

- [ ] **Step 5: Lint.** Run: `composer lint` — clean.

- [ ] **Step 6: Manual verification.**

In `wp shell`, build a show array with `show_name` = `<script>alert(1)</script>` and pass it through each display method; confirm the returned HTML contains the escaped entity, not a live tag.

- [ ] **Step 7: Commit.**

```bash
git add plugins/lwtv-plugin/php/calendar/class-display-list.php plugins/lwtv-plugin/php/calendar/class-display-grid.php plugins/lwtv-plugin/php/calendar/class-display-calendar.php
git commit -m "fix(security): escape TVMaze-derived names/titles in calendar output (F18-F22)"
```

---

### Task 12: Encode + validate TMDB/IMDB IDs in outbound URL (F23)

**Files:**
- Modify: `plugins/lwtv-plugin/php/_components/class-cpts.php` (`get_tmdb_info`, :112-117)
- Modify: `plugins/lwtv-plugin/php/cpts/class-post-meta.php` (`lezactors_tmdb_id` :52, `lezshows_tmdb_id` :133, registration loop :259-285)

**Interfaces:**
- Consumes: `rawurlencode()`.
- Produces: the ID path segment is percent-encoded, so `../` traversal cannot reach other TMDB endpoints; the `*_tmdb_id` meta gains a digits-only `sanitize_callback` for defense-in-depth (the registration loop is extended to pass a per-key `sanitize_callback`, defaulting to `null`).

- [ ] **Step 1: Encode the IDs in the request URL.** Replace :112-117:

```php
			// If we have a TMDB ID, use it.
			if ( $tmdb_id ) {
				$response_url .= $post_type . '/' . rawurlencode( (string) $tmdb_id ) . '?api_key=' . TMDB_API;
			} else {
				// If we have an IMDB ID, use it.
				$response_url .= 'find/' . rawurlencode( (string) $imdb_id ) . '?api_key=' . TMDB_API . '&external_source=imdb_id';
			}
```

- [ ] **Step 2: Lint.** Run: `composer lint` — clean.

- [ ] **Step 3: Add a digits-only `sanitize_callback` to the two `*_tmdb_id` meta entries.** In `class-post-meta.php`, extend the `ALL_POST_META` entries at :52 and :133:

```php
			'lezactors_tmdb_id'             => array(
				'post_type'         => CPT_Actors::SLUG,
				'sanitize_callback' => array( self::class, 'sanitize_numeric_id' ),
			),
```
```php
			'lezshows_tmdb_id'              => array(
				'post_type'         => CPT_Shows::SLUG,
				'sanitize_callback' => array( self::class, 'sanitize_numeric_id' ),
			),
```

- [ ] **Step 4: Pass the callback through the registration loop and add the sanitizer.** In the inner `foreach` (before `register_post_meta` at :285), add — the `?? null` is required because `$arguments` is reused across iterations and must not leak a previous key's callback:

```php
					$arguments['sanitize_callback'] = $meta_data['sanitize_callback'] ?? null;

					register_post_meta( $one_post_type, $meta_name, $arguments );
```

Add the sanitizer method to the class:

```php
	/**
	 * Strip a stored ID meta value to digits only (TMDB IDs are numeric).
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_numeric_id( $value ): string {
		return preg_replace( '/[^0-9]/', '', (string) $value );
	}
```

Do NOT add a callback for IMDB IDs — they are `tt`/`nm` + digits and are consumed elsewhere; the `rawurlencode()` in Step 1 already neutralizes the injection vector for the IMDB path.

- [ ] **Step 5: Lint + verify.** Run: `composer lint` — clean. In `wp shell`, set a show's TMDB meta to `../../account`, call `get_tmdb_info` for it, and confirm the built URL encodes the slashes as `%2F` (the value becomes `..%2F..%2Faccount` — `rawurlencode()` leaves the `.` characters as-is since they are RFC 3986 unreserved, but encoding the `/` is what defeats traversal, because the server does not treat `%2F` as a path separator). Also confirm a REST write of `../../account` to `lezshows_tmdb_id` now stores digits only (`account` → stripped).

- [ ] **Step 6: Commit.**

```bash
git add plugins/lwtv-plugin/php/_components/class-cpts.php plugins/lwtv-plugin/php/cpts/class-post-meta.php
git commit -m "fix(security): rawurlencode + sanitize TMDB/IMDB IDs in outbound URL (F23)"
```

---

### Task 13: Escape linked post title/permalink in related-archive header (F24)

**Files:**
- Modify: `plugins/lwtv-plugin/php/cpts/class-related-posts.php` (`related_archive_header`, :176)

**Interfaces:**
- Consumes: `esc_url()`, `esc_html()`.
- Produces: the linked-post title and permalink are escaped before output.

- [ ] **Step 1: Escape the linked-post line (:176).**

```php
			$related = '<p><a href="' . esc_url( get_permalink( $linked_post ) ) . '">' . esc_html( get_the_title( $linked_post ) ) . '</a></p>';
```

- [ ] **Step 2: Lint.** Run: `composer lint` — clean.

- [ ] **Step 3: Manual verification.**

Set a Show title to `<script>alert(1)</script>`, link it to a tag via `lez_termsmeta_linked_post`, and load that tag archive. Confirm the title renders as inert text.

- [ ] **Step 4: Commit.**

```bash
git add plugins/lwtv-plugin/php/cpts/class-related-posts.php
git commit -m "fix(security): escape linked post title/url in related archive header (F24)"
```

---

## PHASE 3 — LOW severity

### Task 14: Make the list-endpoint allowlist actually gate (F27)

**Files:**
- Modify: `plugins/lwtv-plugin/php/rest-api/class-list-json.php` (`rest_api_callback`, :52-66)

**Interfaces:**
- Produces: a non-allowlisted `type` returns the error and stops, never reaching `list()`.

- [ ] **Step 1: Return on the failed allowlist check.** Replace :56-63:

```php
		if ( ! in_array( $type, array( 'shows', 'characters', 'actors' ), true ) ) {
			return new \WP_Error( 'invalid', 'An unexpected error has occurred.', array( 'status' => 400 ) );
		}

		$return = $this->list( $type );
		if ( false === $return ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method' );
		}
```

- [ ] **Step 2: Lint.** Run: `composer lint` — clean.

- [ ] **Step 3: Manual verification.**

`GET /wp-json/lwtv/v1/list/tvmaze` → `invalid` 400 error (no titles/permalinks from the internal CPT).
`GET /wp-json/lwtv/v1/list/shows` → unchanged.

- [ ] **Step 4: Commit.**

```bash
git add plugins/lwtv-plugin/php/rest-api/class-list-json.php
git commit -m "fix(security): enforce list endpoint type allowlist (F27)"
```

---

### Task 15: Force HTTPS + validate responses on all TVMaze calls (F12, F28, F29, F30)

**Files:**
- Modify: `plugins/lwtv-plugin/php/calendar/class-tvmaze.php` (:98, :101, :108)
- Modify: `plugins/lwtv-plugin/php/grading/class-tvmaze.php` (`update_scores`, :82, :90)
- Modify: `plugins/lwtv-plugin/php/rest-api/class-whats-on-json.php` (`make_show_array`, :317, :324)

**Interfaces:**
- Consumes: `wp_parse_url()`.
- Produces: every `api.tvmaze.com` request uses `https://`; episode-link hrefs from the response are host-validated against `api.tvmaze.com` before the follow-up fetch; the persisted score `url` is validated as a well-formed tvmaze.com URL.

- [ ] **Step 1: `calendar/class-tvmaze.php` — HTTPS on all three lookups (:98, :101, :108).** Change each `'http://api.tvmaze.com/...'` to `'https://api.tvmaze.com/...'`.

- [ ] **Step 2: `grading/class-tvmaze.php` — HTTPS (:82).**

```php
				$response = wp_remote_get( 'https://api.tvmaze.com/lookup/shows?imdb=' . rawurlencode( $imdb_id ) );
```

- [ ] **Step 3: `grading/class-tvmaze.php` — validate the persisted URL (:90).** Replace the `$scores['url'] = $body['url'];` assignment:

```php
						$maybe_url = $body['url'] ?? '';
						$host      = $maybe_url ? strtolower( (string) wp_parse_url( $maybe_url, PHP_URL_HOST ) ) : '';
						// Exact host or a real subdomain — NOT "eviltvmaze.com".
						if ( 'tvmaze.com' === $host || str_ends_with( $host, '.tvmaze.com' ) ) {
							$scores['url'] = $maybe_url;
						}
```

- [ ] **Step 4: `class-whats-on-json.php` — validate episode-link hosts before fetch.** Add a private helper to the class:

```php
	/**
	 * Fetch a TVMaze episode link only if it points at the TVMaze API host.
	 *
	 * The href comes from a TVMaze API response; validate it before following
	 * to prevent SSRF via a tampered response.
	 *
	 * @param string|false $href The _links href from the show response.
	 * @return array|false wp_remote_get result, or false.
	 */
	private function fetch_tvmaze_link( $href ) {
		if ( empty( $href ) ) {
			return false;
		}
		$parts = wp_parse_url( $href );
		if ( empty( $parts['host'] ) || 'https' !== ( $parts['scheme'] ?? '' ) || 'api.tvmaze.com' !== strtolower( $parts['host'] ) ) {
			return false;
		}
		return wp_remote_get( $href );
	}
```

Then replace the two inline fetches (:317 and :324):

```php
		$previous_episode = ( isset( $show_array['_links']['previousepisode']['href'] ) ) ? $this->fetch_tvmaze_link( $show_array['_links']['previousepisode']['href'] ) : false;
```
```php
			$next_episode = ( isset( $show_array['_links']['nextepisode']['href'] ) ) ? $this->fetch_tvmaze_link( $show_array['_links']['nextepisode']['href'] ) : false;
```

- [ ] **Step 5: Lint.** Run: `composer lint` — clean.

- [ ] **Step 6: Manual verification.**

`grep -rn "http://api.tvmaze.com" plugins/lwtv-plugin/php` → no results.
In `wp shell`, force a score refresh for an on-air show and confirm `lezshows_3rd_scores` `url` is a `tvmaze.com` URL. Confirm `fetch_tvmaze_link( 'https://169.254.169.254/' )` returns `false`.

- [ ] **Step 7: Commit.**

```bash
git add plugins/lwtv-plugin/php/calendar/class-tvmaze.php plugins/lwtv-plugin/php/grading/class-tvmaze.php plugins/lwtv-plugin/php/rest-api/class-whats-on-json.php
git commit -m "fix(security): HTTPS + response validation for TVMaze calls (F12, F28, F29, F30)"
```

---

## Coverage map

| Finding | Task | Finding | Task |
|---|---|---|---|
| F1 | 3 | F16 | 7 |
| F2 | 5 | F17 | 10 |
| F3 | 1 | F18 | 11 |
| F4 | 2 | F19 | 11 |
| F5 | 2 | F20 | 11 |
| F6 | 3 | F21 | 11 |
| F7 | 1 | F22 | 11 |
| F8 | 3 | F23 | 12 |
| F9 | 5 | F24 | 13 |
| F10 | 4 | F25 | 10 |
| F11 | 6 | F26 | 5 |
| F12 | 15 | F27 | 14 |
| F13 | 8 | F28 | 15 |
| F14 | 9 | F29 | 15 |
| F15 | 4 | F30 | 15 |

All 30 findings are covered.
