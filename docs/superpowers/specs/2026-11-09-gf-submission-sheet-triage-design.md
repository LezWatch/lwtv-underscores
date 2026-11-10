# Gravity Forms submission → "Content to Add" sheet triage

**Status:** design, not yet approved · **Date:** 2026-11-09

## Why

The contact form lets people tell us about "a new show/actor/character". They rarely check whether we already have it, so today a human reads every one, searches LWTV, searches the **LWTV: Content to Add** Google Sheet, and hand-types one sheet row per character. The lookups are tedious and mechanical; the typing is repetitive. We want rows to land on the sheet with no human touching them, then be reviewed later.

Submitters dump free text ("Satya is the girlfriend of Lara, Blue's sister…"). We are **not** changing the form to structured fields — free text is easier for humans to submit. Understanding the text moves to the server.

## Goals

- Every qualifying submission is checked against live LWTV and the sheet, per character, and results in one of: skipped, appended to an existing row, or a new row.
- New/changed rows are clearly marked as machine-made: **Status = `Needs Verification`**.
- Every entry gets a Gravity Forms note saying what happened to each character, so it's auditable from the GF entry screen.
- Nothing is ever lost: if anything downstream fails, the submission still ends up on the sheet in some form (or the entry note says why not).
- Zero or near-zero running cost.

## Non-goals

- Changing the Gravity Forms form fields.
- Creating anything in WordPress (no draft shows/characters). Output is sheet rows only.
- Showing the result to the submitter on the confirmation screen.
- Migrating or cleaning the legacy `To Add (NEW)` / `To Add (existing)` tabs.
- Handling the `Law & Order` tab.

## Anti-goals (would be a failure even if it "works")

- **Inferring** data. The tool fills a column only when the text explicitly states it. "Satya is the girlfriend of Lara" states neither character's sexuality nor gender → both blank. A human makes those calls.
- Re-adding something a human already rejected (Questionable tab, or Completed → Not Imported).
- Overwriting a value a human already entered on the sheet.
- Making the submitter wait on Gemini/Sheets.
- Sending the submitter's name or email to Gemini.
- Creating rows for non-character words ("Blue", "She's") — the reason for using a model rather than a capitalized-word regex.

## The sheet (as of 2026-11-09)

Spreadsheet ID `1W596yJz11ALCjfq2HMkmZO-PN765Q2rlFxfTSqu2G28`. Row 1 is headers in every tab.

| Tab | Layout | Role |
|---|---|---|
| **To Add** | A Status · B Queerness · C Name · D Actor · E Show · F Cliches · G Sexuality · H Gender · I Description · J Image · K Form Entry *(new)* | **Write target.** Also dedupe source. |
| **Completed** | same A–J; Status ∈ Imported / Not Imported | Dedupe: skip on match. |
| **Questionable** | same A–J + K Duplicate Flag · L Notes · M Link | Dedupe: skip on match. |
| **To Add (NEW)** | A Show · B Network · C Info · D Link · E Link 2 (one row per *show*) | Legacy. Read-only dedupe (show + name appears in Info). Never written. |
| **To Add (existing)** | A Show · B Info · C Link · D Link 2 | Legacy. Same as above. |
| **Lists** | columns of allowed values: Character Cliches, Char Genders, Char Sexuality, Show Tropes, Actor Gender, Actor Sexuality | Controlled vocabulary for extraction. Read at runtime (cache briefly), don't hard-code. |

Data quirks the builder must handle:

- Some cell values contain invisible characters, e.g. Queerness `Canonical⁠ ⁠` (U+2060 word joiner + space). Normalize (strip zero-width/format chars, collapse whitespace, trim, case-fold) before comparing. When writing a controlled value, write the exact string as it appears in the sheet/Lists, not a cleaned copy.
- Status values sometimes have trailing spaces (`Unreviewed `).
- Placeholder names `Multiple`, `N/A`, `Unknown` exist; never treat them as a match for a real name.
- Header columns should be located by header text, not hard-coded letters, so a reordered column doesn't silently corrupt rows. (Builder's call how strict to be; failing loudly on a missing header is fine.)

The user must add **`Needs Verification`** to the Status column's dropdown/validation if one exists (one-time manual step; note it in rollout).

**New column: `Form Entry`** on **To Add**, added by the user as column K (after Image) so existing column positions don't move. It holds the Gravity Forms entry ID(s) that created or appended to the row, comma-separated (`4521` or `4521, 4602`). Humans use it to find the original submission; the automation uses it for idempotency (see Failure handling). Completed and Questionable don't need it, but if rows are moved there with the column intact, that's fine.

## Flow

1. **Trigger.** On `gform_after_submission` for the contact form. **Spam and moderation-flagged entries are held, not processed** (`Stop_Spammers` still lets the hook fire for both). A held entry is queued later, when an admin un-spams it (entry status spam → active, e.g. `gform_update_status`) or approves a moderation-flagged entry (via the existing `GF_Approvals` approval). The builder picks the exact hooks and how a held entry is recognized; it must not be processed twice. Then, if the "This is about…" field is the new show/actor/character choice, queue a background job (Action Scheduler, following `php/schedulers/` patterns) with the entry ID only. Return immediately. Do nothing else in the request.
2. **Load entry.** The job reads the entry via `GFAPI::get_entry()`. Inputs used: show title, character info text. Submitter name/email stay in GF; they are *not* written to the sheet and *not* sent to Gemini.
3. **Extract (Gemini).** One call per submission. Send: show title, the description text, and the allowed values from Lists (genders, sexualities, cliches; Queerness values from the To Add column's existing values or a fixed list — builder's call). Ask for structured JSON: a list of characters, each with `name` and optional `actor`, `sexuality`, `gender`, `queerness`, `cliches`, plus a per-character `description` snippet if natural. Instruct: only fill a field when the text explicitly states it; otherwise null. Validate the response server-side: any controlled field whose value isn't in the allowed list → blank. Use a Flash-class model with JSON response mode.
4. **Per character, check in order** (stop at first hit):
   1. **Live LWTV.** Find the show (`post_type_shows`) by title — exact title match first, then a normalized comparison. *(Designer's call)* If several shows share the title (reboots), check characters across **all** of them. Any post status except trash counts as "on LWTV" (a draft means someone is already working on it) *(designer's call)*. If found, look for a `post_type_characters` post linked to that show (`lezchars_show_group_{n}_show` meta = show ID) whose title matches the name. Match rule: normalized full-name equality, **or** one name is the first token of the other, in **either direction** (submitted "Satya" matches "Satya Patel", and submitted "Satya Patel" matches "Satya") *(designer's call)*. → **skip**, note "already on LWTV".
   2. **To Add.** Same show + name match rule against columns E/C. → **append** (below).
   3. **Completed.** → **skip**, note "on Completed: {Status}".
   4. **Questionable.** → **skip**, note "on Questionable: {Queerness/Status}".
   5. **Legacy tabs.** Show matches column A and the name appears in the Info text → **skip**, note "in legacy {tab} list".
   6. Otherwise → **new row** on To Add.
   The show-title comparison on the sheet uses the same normalization. *(Designer's call)* For skips, any match is enough. If an **append** would hit more than one To Add row (two "Sam"s on one show), edit none of them: write a new row instead and list the ambiguous row numbers in the entry note, so no human-made row is changed on a guess. A show that exists on LWTV with no matching character is *not* a skip — the character still proceeds through 2–6.
5. **New row:** Status `Needs Verification`; Name; Show (as submitted); Actor/Queerness/Cliches/Sexuality/Gender only if extracted and valid; Description = the submitter's original text (the full text on every character's row, matching how the sheet does it today, e.g. Jay/Motsie); Image blank; Form Entry = the entry ID. Append to the end of To Add.
6. **Append to existing To Add row:** Description becomes `{existing}\n\n— Added {Y-m-d} from form entry #{id} —\n{new text}`. Form Entry gets `, {id}` appended. Status → `Needs Verification`. Any other column that is **blank** on the sheet and was extracted gets filled; non-blank cells are never changed.
7. **Entry note** via `GFAPI::add_note( $entry_id, 0, 'LWTV Robot', … )` summarizing each character's outcome, plus any error.

### Failure handling

- **Gemini fails / times out / returns invalid JSON:** retry with backoff (pattern: `class-tmdb-batch-task.php`). After the final retry, fall back to one To Add row: Name blank (or `Unknown`), Show as submitted, Description = raw text, Status `Needs Verification`. Still run the live-LWTV *show* check; note the fallback on the entry.
- **Gemini returns zero characters:** same fallback row.
- **Sheets API fails:** retry with backoff; after final retry, leave an error note on the entry. No silent drop.
- **Show title missing:** fallback row with Show blank.
- Idempotency: the job must be safe to retry. Record per-entry completion (e.g. GF entry meta `lwtv_sheet_triage_done`) and don't re-run a finished entry. Within a run, before any write, check the To Add **Form Entry** column: if this entry ID is already on a row (new, appended, or fallback), that write already happened — skip it. This covers appends, new rows, and nameless fallback rows alike.

## How it gets made (decided)

- **Lives in this repo, in the plugin.** New class(es) under `plugins/lwtv-plugin/php/plugins/gravity-forms/`, wired from `class-gravity-forms.php` alongside `Stop_Spammers`. Scheduler task under `php/schedulers/`.
- **Sheets access: Google service account, direct from WordPress** via the Sheets REST API with `wp_remote_*`. The user shares the sheet with the service account's email (Editor). No Apps Script.
- **Gemini** via its REST API with `wp_remote_post`. The user already has Google Gemini.
- **Secrets** are constants in `wp-config.php`, following the `TMDB_API` convention. Names are the builder's call (e.g. `LWTV_GEMINI_API_KEY`, `LWTV_GOOGLE_SERVICE_ACCOUNT` as a path to the JSON key file or the JSON itself). If any are undefined, the feature does nothing and logs once — never fatals.
- **Pure logic is unit-tested** (repo's build-first pattern, `tests/unit/`, no WP bootstrap): name/show normalization and match rules, the decide-skip/append/new function given already-fetched sheet rows + LWTV matches, the Description-append formatting, validating Gemini output against allowed lists, building a row array from extracted data + header map. WP/GF/HTTP glue is verified against the running site.

## Left to the builder

- Exact class names/structure; whether to pull in a Google client library or hand-roll the service-account JWT (`openssl_sign`, RS256) — prefer no new heavy Composer dependency.
- Form ID and field IDs: read from the actual form (local site) and keep them in one place (constants or a filter); don't scatter magic numbers.
- Exact Gemini model name, prompt wording, and JSON schema.
- Caching of sheet reads (one batchGet per job of the needed tabs is fine; volume is a few submissions a day).
- Debug logging via `lwtv_plugin()->debug_log()` / `error_log()` conventions.

## Rollout

1. Create the service account; share the sheet with it as Editor; add constants to `wp-config.php` on staging.
2. On the sheet: add `Needs Verification` to the Status dropdown, and add the `Form Entry` header in To Add column K.
3. Test on staging against a **copy** of the sheet (spreadsheet ID should be configurable for exactly this).
4. Production.
