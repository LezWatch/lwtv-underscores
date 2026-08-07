# Handoff: Auto-Generated Editorial (annual post, share cards, notable feed)

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin.
**Scope:** Turn the stats engine into *content*. Three phases, decreasing certainty. Phase 1 is the high-value, low-risk core; ship it alone if the others stall.

## Goal
The *This Year* lead card ("64 characters on air, 16 premieres, and 9 we lost") already assembles dynamic editorial prose from data via `partials/phrases.php`. Push that output into publishable artifacts so the site owner stops hand-writing the annual recap (they literally just wrote one by hand for the launch).

---

## Phase 1 — Annual "State of Queer TV {year}" draft post  ⭐ do this first
A WP-CLI command that assembles the year's headline numbers, deltas, and callouts into a **draft** post for a human editor to polish and publish.

- **Mechanism:** new WP-CLI subcommand under the plugin's existing `wp lwtv …` command group (see `cron/` scripts + the plugin's CLI class for the registration pattern). It calls `wp_insert_post(['post_status' => 'draft', 'post_type' => 'post', ...])` — **never `publish`** (editorial review is mandatory; publishing is a human action).
- **Content source:** reuse the *This Year* builders/formatters (`LWTV\This_Year\Build\*`, `Format\*`) and `phrases.php` helpers. The prose is: lead sentence + highlights (biggest premiere, most-populated show) + callouts (deadliest month/show, best/worst year deltas).
- **Idempotency:** guard against creating duplicate drafts for the same year — check for an existing draft tagged/meta-marked with the year before inserting; update-or-skip, don't blindly re-create.
- **Output:** command prints the draft's edit URL. Optionally wire to cron to auto-draft each January for the prior year (owner opt-in).

**⚠️ Verify first:** the exact CLI command-group registration; whether *This Year* builders accept an arbitrary `$year` argument (the redesign parameterised them — confirm the int/string year gotcha from the This Year memory doesn't bite when called outside the page context).

---

## Phase 2 — Shareable stat cards (OG / social images)
Server-render a shareable image of a single stat (the trope gap, deaths-per-year, biggest premiere) for social/OG tags.

- **Why now feasible:** charts already render as server-side SVG. Generating an OG-sized (1200×630) SVG per stat reuses the same drawing code.
- **The risk / verify first:** OG consumers (Twitter/X, Facebook, Slack) want **raster** (PNG/JPG), not SVG. So this phase needs an SVG→PNG step. **Confirm which is available on the host before building:** Imagick (preferred), GD, or a headless renderer. If none, options are (a) hand-draw the card with GD primitives instead of converting SVG, or (b) ship SVG and accept degraded OG support. Pick with the owner — do not assume Imagick exists.
- **Serving:** an endpoint or pre-generated attachment per stat/year; set `og:image` on the relevant stats pages. Cache aggressively (these change at most daily).

---

## Phase 3 — "Notable this month" rolling feed  (stretch)
A small block/shortcode surfacing recently notable events (new premiere of note, a death this month, a milestone) derived from the year data. Lowest priority; only after 1–2 land.

## Files (indicative)
- Phase 1: new method in the plugin's WP-CLI class; optionally a `cron/` wrapper.
- Phase 2: a new `Format\Share_Card` renderer + an image endpoint/attachment pipeline; `og:image` wiring in the stats page `<head>` (theme header or a stats-page filter).
- Phase 3: a block or shortcode + a small query wrapper.

## Global constraints
- Drafts only — auto-**publishing** is out of scope and is a human-gated action.
- All strings i18n-ready (`'lwtv'` text domain).
- No new remote `file_get_contents`; use `wp_remote_get` if any fetch is needed.
- Reuse `phrases.php` — do not fork the phrasing logic.

## Testing checklist
- [ ] Phase 1: command produces a draft whose numbers match the live `/this-year/{year}/` page; re-running does not duplicate; post stays `draft`.
- [ ] Phase 1: i18n — prose uses `_n()`/`__()` correctly for singular/plural counts.
- [ ] Phase 2: image renders at 1200×630; `og:image` resolves; confirmed raster on at least one real scraper (or documented SVG limitation).
- [ ] `composer lint` clean.

## Out of scope
- Auto-publishing anything.
- Rich WYSIWYG layout of the draft (plain block content is fine; the editor styles it).
