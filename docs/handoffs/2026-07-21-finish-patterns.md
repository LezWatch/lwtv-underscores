# Handoff: Finish the Patterns (callouts everywhere, CSV everywhere, view-as-table)

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin on `feat/cliche-stats`.
**Scope:** Roll the three reusable systems from the redesign — the callout kit, the CSV export kit, and (new) an accessibility text-table — across the views that don't yet have them. Almost entirely configuration; near-zero new logic.

## Goal
The redesign added callouts to *some* views, CSV to 11 views, and server-rendered SVG charts to all. Close the gaps so coverage is consistent, and complete the accessibility story the launch post advertises ("friendlier to screen-readers") with an actual text fallback.

---

## Part A — Callouts on the remaining views
Every donut / ranked-bars view can carry a "most / least / share" callout via the existing `$lwtv_callouts` contract + `partials/callouts.php` + `partials/phrases.php`.

**Pattern (already proven in `shows/tropes.php`):**
```php
$lwtv_callouts = array(
    array( 'label' => __( 'Most common', 'lwtv' ), 'icon' => 'chart-bar.svg', 'text' => /* phrase */ ),
);
include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
```

**Views still missing callouts (audit + add):** characters → gender, sexuality, romantic orientation, queer-IRL; shows → stars, triggers, worth-it, formats; actors → gender, sexuality. For each, a "most common X (share %)" + "least common" pair, using `lwtv_stats_fraction_phrase()`.

**⚠️ Verify first:** the exact data shape each view already builds (donut rows vs ranked rows) so the callout reads the right key. Don't add a callout where one already exists (grep each template for `$lwtv_callouts` first).

---

## Part B — CSV on the remaining breakdowns
The CSV whitelist in `class-csv-download.php::resolve()` is one entry per view. Extend it to the taxonomy tallies that lack an export.

**Add views:** shows tropes/genres/formats/stars/triggers; characters cliches/gender/sexuality. Each is a `label_rows()`-style export (term name + count) — the helper already exists.

**Steps per view:**
1. Add the view slug to the `resolve()` whitelist with its headers + filename.
2. Point it at the existing `generate_*_statistics('array', <tax>)` source.
3. Add `$download_csv = array( 'page' => …, 'title' => …, 'count' => count($rows) )` + `include partials/download-csv.php` to the template.

**⚠️ Verify first:** the label key each tally uses (`term_name` vs `name` — the death stations/nations bug was exactly this; `label_rows()` now reads `term_name ?? name`, confirm it covers the new sources). Keep the BOM + formula-injection hardening (already in `Format\CSV`) — no changes there.

---

## Part C — "View as table" accessibility toggle
Each chart partial renders from a PHP data array; expose that array as a real HTML `<table>` behind a `<details>` toggle, and add ARIA to the SVGs.

- **Partials to touch:** `donut.php`, `ranked-bars.php`, `year-bars.php`, `trendline.php`, `leaderboard.php`.
- **Add:** a `<details class="lwtv-chart-table"><summary>View as table</summary><table>…</table></details>` after each SVG, built from the same `$rows` the chart already has. And on each `<svg>`: `role="img"` + `<title>`/`<desc>` from the chart's existing title/sub strings.
- This makes the "works without JS / screen-reader friendly" claim literally true (the data is now in the DOM as a table, not just visual bars).

**⚠️ Verify first:** whether any partial is included in a context where a `<details>` block would break layout (e.g. inside the compact actor-modal donut). Gate the table with an opt-in flag (`$lwtv_show_table = true`) so callers control it, defaulting on for full-page stats and off for modals.

## Global constraints
- All strings i18n-ready (`'lwtv'`).
- CSV: keep UTF-8 BOM + `= + - @` cell hardening (public, no nonce — unchanged).
- Custom auto-escaped funcs (`lwtv_plugin`, `get_symbolicon`, etc.) — don't wrap in `esc_*`.
- No hardcoded hex; use existing tokens.

## Testing checklist
- [ ] Every donut/ranked view now shows a callout; numbers match the chart.
- [ ] Every new CSV downloads with correct headers, BOM (`ef bb bf`), and row count matching its card.
- [ ] Injection guard holds on any term starting with `= + - @`.
- [ ] `<table>` fallback present and populated; SVG has `role="img"` + `<title>`/`<desc>`; actor-modal donut unaffected.
- [ ] Screen-reader spot check (VoiceOver) reads the table, not gibberish.
- [ ] `composer lint` + `npm run lint:css` clean.

## Out of scope
- New chart *types*.
- Changing existing callout wording.
