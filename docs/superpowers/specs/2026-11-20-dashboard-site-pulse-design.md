# Dashboard "Site Pulse" widget

Date: 2026-11-20 · Size: small change · Status: draft, awaiting approval
Base: `release/7.5.1` (needs its `last_5xx` API report change)

## Why

The "LezWatch.TV Tools" widget (Data Health, separate work) says what is broken.
Site Pulse says what is happening and what needs a look this morning: messages
waiting, how the database grew this week, and which API routes are failing.

## Goals

- **Inbox:** unread Gravity Forms entries per message form, each linking to that
  form's unread entries.
- **Added this week:** published shows, characters and actors since the start of
  the current week, with last week's total beside each.
- **API (admins only):** the routes with the most 5xx in the API page's range, plus
  any route with 10 or more 404s, each with its last 5xx date and a link to API Usage.
- Rename the "Forms Pending Approval" widget to **"Pending Slack Applications"**.

## Non-goals

- Airing-this-week, score movers, Of-the-Day/Postiz and missed-schedule content
  (discussed, not wanted now).
- New storage, snapshots or cron steps.
- Data Health content.

## Anti-goals

- Editors seeing API internals.
- Widget numbers disagreeing with the page a line links to.
- Slack applications or mailing-list sign-ups counted as "messages".

## Audience (decided)

- `upload_files` (editors): Added this week.
- Inbox only for users with Gravity Forms' `gravityforms_view_entries` (decided after
  review: `upload_files` includes Authors, and the links are useless without it; in
  practice that means admins, which is fine).
- `manage_options` (admins): also the API section.

## Approaches

- **A. Pure pulse builder fed by existing sources (chosen).** Each source is read
  where it lives; a tested builder decides what to show; a template renders.
- B. All logic inside the widget class. Rejected: the error-prone rules (week
  boundaries, which forms count, the 404 threshold) would go untested.
- C. Nightly snapshot the widget reads. Rejected: unread counts up to a day stale;
  live cost is a few counts plus an already-cached report.

## Design

1. **Pure builder** `LWTV\Admin_Menu\Build\Site_Pulse` (`admin-menu/build/class-site-pulse.php`),
   static methods, no WordPress calls:
   - `inbox( forms, excluded_ids )`: input rows `{id, title, unread, is_active}`;
     drops inactive and excluded forms; keeps forms with 0 unread (so the line reads
     "0"), sorted unread desc then title. Returns rows + total.
   - `week_bounds( now, start_of_week )`: returns `{this_start, last_start}` as
     local `Y-m-d 00:00:00` strings. `start_of_week` is 0–6 (Sun–Sat), per the WP option.
   - `added( this_week, last_week )`: shapes per-type `{label, this, last}`, in
     order shows, characters, actors.
   - `api( routes, top = 3, min_404 = 10 )`: from the report's route rows, the top
     `top` with `s5xx > 0` (by s5xx), then any other route with `s404 >= min_404`
     (by s404); each row `{route, s5xx, s404, last_5xx, reason}`, no duplicates.
2. **404 count in the API report.** `Api_Usage_Report` gains an `s404` counter
   (a subset of `s4xx`, which is unchanged). `Api_Usage::CACHE_VERSION` → 3 so a
   cached pre-deploy report is not served without it. The Routes tab gains a
   "404" column.
3. **WP glue** `LWTV\_Components\Site_Pulse` (new component, registered in
   `class-plugin.php`, so it doesn't touch `class-dashboard-widgets.php`, which the
   Data Health work rewrites):
   - Inbox: active forms from `GFAPI::get_forms()`; unread via
     `GFAPI::count_entries( id, [ status => active, field_filters => is_read = 0 ] )`.
     Excluded: forms with an active `lwtv-gf-approvals` or `gravityformsapprovals`
     feed (the Slack form), and forms whose only feed is `gravityformsbrevo`
     (mailing lists). Skipped entirely when Gravity Forms isn't active.
     Link: `admin.php?page=gf_entries&id=<id>&filter=unread`.
   - Added: `WP_Query` counts (`fields => ids`, `no_found_rows => false`,
     `posts_per_page => 1`) for `post_type_shows|characters|actors`, `post_status
     publish`, `date_query` on `post_date` (local) for each week window.
   - API: `Api_Usage::load()` made public and called with the page's defaults
     (30-day range, internal traffic excluded), so it shares the page's daily cache.
     An `error` result shows one line ("API usage data unavailable") instead.
4. **Template** in the component: three headed sections with core admin styles;
   empty API list shows "No failing routes in the last 30 days."
5. **Rename** in `GF_Approvals::dashboard_setup()`.

## My calls (please check)

- Mailing lists and the Slack form are excluded by feed type, not form ID.
- Forms with 0 unread still get a line.
- "Added" = published posts by `post_date`; drafts and scheduled posts don't count.
- Top 3 for 5xx; the 404 rule adds routes beyond those 3 with no cap.
- 404 column added to the API Usage Routes tab (cheap, and it explains the widget).

## Left to the builder

Markup details, i18n with `lwtv-underscores`, test cases (exclusions, Sunday vs
Monday week start and the day-of boundary, top-3 cut, 404 threshold at exactly 10,
de-duplication when a route qualifies on both, `s404` counting in the report).
