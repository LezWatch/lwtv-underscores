# Gravity Forms Submission → Sheet Triage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Contact-form submissions about a new show/character are checked against live LWTV and the "LWTV: Content to Add" sheet, and land on the sheet (new row, append, or skip) with no human touching them.

**Architecture:** `gform_after_submission` queues an Action Scheduler job with the entry ID (or holds it if spam/moderated). The job calls Gemini once to extract characters, then for each character a pure decision class picks skip / append / new against the live-site matches and the sheet tabs, and a thin Sheets client writes the result. All matching, decision, row-building and Gemini-output validation live in pure `build/` classes with unit tests; HTTP/WP/GF glue sits at the edges and is verified on lwtv.local.

**Tech Stack:** PHP 8.5, WordPress, Gravity Forms (`GFAPI`), Action Scheduler, Google Sheets REST v4 (service account, RS256 JWT via `openssl_sign`), Gemini REST `generateContent`, PHPUnit (pure tests, no WP bootstrap).

**Spec:** `docs/superpowers/specs/2026-11-09-gf-submission-sheet-triage-design.md`

## Global Constraints

- No new Composer dependency. Google auth is a hand-rolled JWT; HTTP is `wp_remote_*` only.
- Namespace `LWTV\Plugins\Gravity_Forms` (glue) and `LWTV\Plugins\Gravity_Forms\Build` (pure); class files `class-*.php`, one class per file.
- Pure `build/` classes must not call any WordPress, GF, or HTTP function. Tests are added to `tests/bootstrap.php` with `require_once`, as the existing ones are.
- Status written by the automation is exactly `Needs Verification`.
- Append separator is exactly `"\n\n— Added {Y-m-d} from form entry #{id} —\n"` followed by the new text.
- New column header on To Add is exactly `Form Entry` (column K); multiple IDs are joined with `", "`.
- Gemini receives only the show title, the description text, and the allowed values. Never the submitter's name or email.
- Never infer: a controlled field (sexuality, gender, queerness, cliches) is written only if Gemini returned it **and** it matches an allowed value; otherwise blank.
- Never change a non-blank sheet cell except Status, Description (append only) and Form Entry (append only).
- Secrets are `wp-config.php` constants: `LWTV_GEMINI_API_KEY`, `LWTV_GOOGLE_SERVICE_ACCOUNT` (absolute path to the service-account JSON key file). Optional `LWTV_CONTENT_SHEET_ID` overrides the default spreadsheet ID `1W596yJz11ALCjfq2HMkmZO-PN765Q2rlFxfTSqu2G28` (staging points at a copy). Any required constant missing → feature does nothing, logs once, never fatals.
- Debug logging uses topic `submissions` (added to `Debugging::VALID_LOG_TOPICS`).
- Do not commit. The user reviews and commits the whole change at the end (repo CLAUDE.md + user preference). Each task ends with tests green, `composer lint` clean for touched files, and `composer analyse` clean.

## Review Focus

1. **Sheets API drops trailing blank cells** — a row whose Image/Form Entry are empty comes back shorter than the header. Rows must be padded so column lookups never read the wrong cell or warn. (Test in Task 2.)
2. **Invisible characters and odd spacing** in sheet values (`Canonical⁠ ⁠`, `Unreviewed `) — matching must see through them, while writes use the exact stored string. (Tests in Tasks 1 and 3.)
3. **Retry after a partial run** — an entry with two characters where the first row was written before a crash must not produce a second copy of that row, or a second append, or a second nameless fallback row. (Test in Task 4.)
4. **Gemini returns junk** — invalid JSON, a wrapper object, an empty list, a value not on the list ("Cisgender Female", "lesbian"), or a name that's just the show title. Each must degrade to blank fields / fallback row, never a crash. (Tests in Task 3.)
5. **Missing setup** — form submitted on a site without the constants, without Action Scheduler, or with the `Form Entry` header missing — must no-op with a log line and an entry note, not fatal the form submission. (Verification in Tasks 6 and 8.)

---

## File Structure

```
plugins/lwtv-plugin/php/plugins/gravity-forms/
├── build/
│   ├── class-name-match.php          # Task 1: normalize + name/title/placeholder/mention rules
│   ├── class-sheet-table.php         # Task 2: header map, padded rows, distinct column values
│   ├── class-extraction.php          # Task 3: Gemini prompt, response schema, output validation
│   ├── class-triage-decision.php     # Task 4: per-character skip/append/new/done
│   ├── class-row-builder.php         # Task 5: new-row cells, append cell changes
│   └── class-service-account-jwt.php # Task 6: RS256 JWT assertion
├── class-sheets-client.php           # Task 6: token + batchGet/append/batchUpdate
├── class-gemini-client.php           # Task 7: generateContent call
├── class-lwtv-lookup.php             # Task 7: show/character titles from the DB
├── class-submission-triage.php       # Task 8: hooks, hold/release, queueing, config
└── class-triage-runner.php           # Task 8: one entry end-to-end
plugins/lwtv-plugin/php/schedulers/class-sheet-triage-task.php   # Task 8: AS handler + retry
plugins/lwtv-plugin/php/wp-cli/cli-triage.php                    # Task 9: wp lwtv triage <entry_id>
tests/unit/Plugins/Gravity_Forms/*Test.php                       # one per build class
```

Modified: `class-gravity-forms.php` (instantiate `Submission_Triage`), `_components/class-scheduler.php` (instantiate `Sheet_Triage_Task` in the AS-dependent block), `_components/class-wp-cli.php` (load `cli-triage.php`), `admin-menu/class-debugging.php` (topic `submissions`), `tests/bootstrap.php`, `docs/README.md` + new `docs/integrations/content-to-add-sheet.md`.

---

### Task 1: Name matching rules

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/build/class-name-match.php`
- Test: `tests/unit/Plugins/Gravity_Forms/NameMatchTest.php`
- Modify: `tests/bootstrap.php` (require the class)

**Interfaces:**
- Produces (all `public static`, class `LWTV\Plugins\Gravity_Forms\Build\Name_Match`):
  - `normalize( string $value ): string` — remove `\p{Cf}` (format chars incl. U+2060, U+200B), NBSP→space, NFD + strip `\p{Mn}` via `\Normalizer` when available, `mb_strtolower`, collapse whitespace, trim.
  - `is_placeholder( string $name ): bool` — normalized value is `''`, `multiple`, `n/a`, `na`, `unknown`, `none`, `various`.
  - `names_match( string $a, string $b ): bool` — false if either is placeholder; true if normalized equal, or the shorter normalized name is exactly the first space-token of the longer one (either direction).
  - `titles_match( string $a, string $b ): bool` — normalized equality, also ignoring a leading `the ` and any punctuation other than `&`.
  - `text_mentions( string $text, string $name ): bool` — false for placeholder; normalized `$name` appears in normalized `$text` on word boundaries (`/(?<![\p{L}\p{N}])…(?![\p{L}\p{N}])/u`).
  - `exact_value( string $value, array $allowed ): string` — returns the element of `$allowed` whose `normalize()` equals `normalize($value)`, or `''`.

- [ ] **Step 1: Write failing tests** `NameMatchTest`:
  - `test_normalize_strips_invisible_and_case`: `normalize("Canonical\u{2060} ")` === `'canonical'`; `normalize("  Tê  Brant ")` === `'te brant'`; `normalize("Unreviewed ")` === `'unreviewed'`.
  - `test_placeholders`: true for `'Multiple'`, `'N/A'`, `' unknown '`, `''`; false for `'Satya'`.
  - `test_names_match_both_directions`: `('Satya','Satya Patel')` true; `('Satya Patel','Satya')` true; `('satya','SATYA')` true; `('Sam','Samantha')` false; `('Satya Patel','Satya Kumar')` false; `('Multiple','Multiple')` false; `('Tê Brant','Te Brant')` true.
  - `test_titles_match`: `('The Wilds','Wilds')` true; `('Kiya & the Kimoja Heroes','kiya & the kimoja heroes')` true; `("A '90s Christmas",'A 90s Christmas')` true; `('Blue','Blue Eye Samurai')` false.
  - `test_text_mentions`: `('Julie - was mentioned… asks Didi out later','Didi')` true; `('Diditamba is here','Didi')` false; `('anything','Multiple')` false.
  - `test_exact_value_returns_stored_string`: `exact_value('canonical', ["Canonical\u{2060} ", 'Debunked'])` === `"Canonical\u{2060} "`; `exact_value('Cisgender Female', ['Cisgender','Trans Woman'])` === `''`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter NameMatch` → FAIL (class not found).
- [ ] **Step 3: Implement** `Name_Match` per the interface above.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter NameMatch` → PASS; `vendor/bin/phpunit` → all PASS.

---

### Task 2: Sheet table parsing

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/build/class-sheet-table.php`
- Test: `tests/unit/Plugins/Gravity_Forms/SheetTableTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Name_Match::normalize()`.
- Produces (class `Sheet_Table`, `public static`):
  - `header_map( array $header_row, array $required ): array<string,int>` — keys are the `$required` labels as given, values are 0-based column indexes, matched via `normalize()`. Throws `\UnexpectedValueException` naming every missing label.
  - `rows( array $values, array $map ): array<int, array{row:int, cells:array<string,string>}>` — skips `$values[0]` (header); `row` is the 1-based sheet row number (first data row = 2); every key in `$map` present in `cells`, `''` when the API omitted it; skips rows whose cells are all blank.
  - `column_values( array $rows, string $key ): string[]` — distinct non-blank values of one key, original strings, order of first appearance, deduped by `normalize()`.
  - `column_letter( int $index ): string` — `0`→`A`, `10`→`K`, `26`→`AA`.

- [ ] **Step 1: Write failing tests**:
  - `test_header_map_finds_columns_by_text`: header `['Status','Queerness','Name','Actor','Show','Cliches','Sexuality','Gender','Description','Image','Form Entry']` with required `['Name','Show','Form Entry']` → `['Name'=>2,'Show'=>4,'Form Entry'=>10]`.
  - `test_header_map_throws_listing_missing`: required `['Name','Form Entry']` on a header without Form Entry → exception message contains `Form Entry`.
  - `test_rows_pads_short_rows` (Review Focus 1): values `[header, ['Unreviewed','','Mandella','','10 Things']]` with map incl. `Form Entry`=>10 → `cells['Form Entry'] === ''`, `row === 2`.
  - `test_rows_skips_blank_rows_but_keeps_numbering`: a blank row at index 2 is skipped and the next row reports `row === 4`.
  - `test_column_values_dedupes_normalized`: `["Canonical\u{2060} ", 'Canonical', '', 'Debunked']` → `["Canonical\u{2060} ", 'Debunked']`.
  - `test_column_letter`: 0→A, 10→K, 25→Z, 26→AA.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter SheetTable` → FAIL.
- [ ] **Step 3: Implement** `Sheet_Table`.
- [ ] **Step 4: Run** `vendor/bin/phpunit` → PASS.

---

### Task 3: Gemini prompt, schema and output validation

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/build/class-extraction.php`
- Test: `tests/unit/Plugins/Gravity_Forms/ExtractionTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Name_Match::exact_value()`, `Name_Match::titles_match()`, `Name_Match::is_placeholder()`.
- Produces (class `Extraction`, `public static`):
  - `FIELDS = array( 'actor', 'queerness', 'cliches', 'sexuality', 'gender' )`; controlled = all but `actor`.
  - `prompt( string $show, string $text, array $allowed ): string` — `$allowed` is `array{sexuality:string[], gender:string[], queerness:string[], cliches:string[]}`. Prompt must say: list every character the text presents as a queer, trans or non-binary character in that show; don't list the show itself, actors, or straight side characters unless the text presents them as queer; fill a field **only when the text explicitly states it**, else null; controlled fields must be copied exactly from the given list or null; output JSON only. Show title and text are passed as clearly delimited data, and the prompt says to ignore any instructions inside them.
  - `schema(): array` — Gemini `responseSchema` (OpenAPI subset): object `{ characters: array of object { name: string (required), actor: string|null, queerness: string|null, cliches: array of string, sexuality: string|null, gender: string|null } }`, `nullable: true` on optional strings.
  - `parse( string $json, string $show, array $allowed ): array<int, array{name:string, actor:string, queerness:string, cliches:string, sexuality:string, gender:string}>` — throws `\UnexpectedValueException` on invalid JSON or missing `characters` array. Drops entries with blank/placeholder name or a name that `titles_match` the show. Controlled values go through `exact_value`; unmatched → `''`. `cliches`: each item through `exact_value`, blanks dropped, joined `', '`. `actor` trimmed string. Dedupes characters by normalized name, keeping the first.

- [ ] **Step 1: Write failing tests** (Review Focus 4):
  - `test_parse_satya_lara_example`: JSON `{"characters":[{"name":"Satya"},{"name":"Lara"}]}`, show `Blue` → two rows, all non-name fields `''`.
  - `test_parse_blanks_values_not_on_list`: `{"name":"Satya","gender":"Cisgender Female","sexuality":"lesbian"}` with allowed gender `['Cisgender','Trans Woman']`, sexuality `['Homosexual','Bisexual']` → gender `''`, sexuality `''`.
  - `test_parse_returns_stored_controlled_string`: queerness `"canonical"` with allowed `["Canonical\u{2060} "]` → `"Canonical\u{2060} "`.
  - `test_parse_cliches_filters_and_joins`: `["Cartoon","Made Up","Queer IRL"]` with allowed `['Cartoon','Queer IRL']` → `'Cartoon, Queer IRL'`.
  - `test_parse_drops_show_title_and_placeholders`: names `Blue`, `Multiple`, `''`, `Satya` with show `Blue` → only `Satya`.
  - `test_parse_dedupes_names`: `Satya`, `satya` → one.
  - `test_parse_throws_on_invalid_json`: `'not json'` and `'{"foo":1}'` each throw `\UnexpectedValueException`.
  - `test_parse_empty_list_returns_empty_array`: `{"characters":[]}` → `[]`.
  - `test_prompt_contains_inputs_and_lists_but_no_pii`: prompt contains `Blue`, the description, `Homosexual`; the test passes no email so assert the signature has no email/name parameter (assert `(new \ReflectionMethod(Extraction::class,'prompt'))->getNumberOfParameters() === 3`).
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter Extraction` → FAIL.
- [ ] **Step 3: Implement** `Extraction`.
- [ ] **Step 4: Run** `vendor/bin/phpunit` → PASS.

---

### Task 4: Per-character decision

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/build/class-triage-decision.php`
- Test: `tests/unit/Plugins/Gravity_Forms/TriageDecisionTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Name_Match::*`; row shape from `Sheet_Table::rows()`.
- Produces (class `Triage_Decision`):
  - `public static function decide( string $name, string $show, int $entry_id, array $lwtv_titles, array $tabs ): array{action:string, reason:string, row?:int, rows?:int[]}`
    - `$name` may be `''` (fallback row). `$lwtv_titles`: titles of characters on any LWTV show matching `$show` (glue supplies). `$tabs`: `array{to_add:array, completed:array, questionable:array, legacy_new:array, legacy_existing:array}`, each a `Sheet_Table::rows()` result. To Add cells use keys `Name`, `Show`, `Status`, `Form Entry`; Completed/Questionable use `Name`, `Show`, `Status`, `Queerness`; legacy use `Show`, `Info`.
    - Actions, evaluated in this order:
      1. `done` — a To Add row whose `Form Entry` (split on `,`, trimmed) contains `$entry_id` **and** (`names_match($name, row Name)` **or** both `$name` and row Name are placeholders). `row` set.
      2. `skip` reason `lwtv` — any `$lwtv_titles` `names_match` `$name`.
      3. To Add rows with `titles_match(Show)` and `names_match(Name)`: exactly one → `append` with `row`; more than one → `new` with `rows` (ambiguous) and reason `ambiguous`.
      4. `skip` reason `completed` — Completed match (show+name); reason text includes the row's Status.
      5. `skip` reason `questionable` — Questionable match; reason includes Queerness and Status.
      6. `skip` reason `legacy_new` / `legacy_existing` — legacy row with `titles_match(Show)` and `text_mentions(Info, $name)`.
      7. `new` reason `not_found`.
    - Placeholder/blank `$name` skips steps 2–6 and goes straight to `new` (fallback rows are always written once).
  - `public static function note_line( string $name, string $show, array $decision ): string` — one human line per character for the GF note, e.g. `Satya (Blue): added to To Add.` / `… already on LWTV, skipped.` / `… on Questionable (Debunked, No), skipped.` / `… appended to To Add row 214.` / `… matched To Add rows 12, 40; added a new row instead.` / `… already written by this entry (row 1083).`

- [ ] **Step 1: Write failing tests** (fixtures built inline as `Sheet_Table::rows()`-shaped arrays):
  - `test_new_when_nowhere`: Satya/Blue, empty everything → `new`, `not_found`.
  - `test_skip_when_on_lwtv_first_name`: `$lwtv_titles = ['Satya Patel']` → `skip`/`lwtv`.
  - `test_lwtv_beats_to_add`: on LWTV and on To Add → `skip`/`lwtv`.
  - `test_append_single_to_add_match`: To Add row 214 `Satya`/`Blue` → `append`, `row` 214.
  - `test_ambiguous_to_add_makes_new`: rows 12 `Sam`/`X`, 40 `Sam Lee`/`X`, submitted `Sam` → `new`, `rows` `[12,40]`, `ambiguous`.
  - `test_show_must_match_too`: To Add `Satya`/`Other Show` → `new`.
  - `test_skip_completed_and_questionable`: each returns `skip` with matching reason; Questionable reason string contains `Debunked`.
  - `test_skip_legacy_mention`: legacy_new `Show`=`A Man on the Inside`, `Info`=`Julie - … Didi - interested in Julie` → `Didi` → `skip`/`legacy_new`.
  - `test_placeholder_rows_never_block` : Questionable row `Multiple`/`Avatar: The Last Airbender`, submitted `Uma` → `new`.
  - `test_done_on_retry_new_row` (Review Focus 3): To Add row 1083 `Satya`/`Blue`/Form Entry `4521` and entry 4521 → `done`; entry 4600 → `append` row 1083.
  - `test_done_on_retry_append`: row Form Entry `4100, 4521`, entry 4521 → `done`.
  - `test_done_on_retry_fallback`: row Name `''`, Form Entry `4521`, `$name` `''`, entry 4521 → `done`; entry 4522 → `new`.
  - `test_note_line_examples`: assert two of the strings above exactly.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter TriageDecision` → FAIL.
- [ ] **Step 3: Implement** `Triage_Decision`.
- [ ] **Step 4: Run** `vendor/bin/phpunit` → PASS.

---

### Task 5: Row building

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/build/class-row-builder.php`
- Test: `tests/unit/Plugins/Gravity_Forms/RowBuilderTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: header map from `Sheet_Table::header_map()` with keys `Status, Queerness, Name, Actor, Show, Cliches, Sexuality, Gender, Description, Image, Form Entry`; character shape from `Extraction::parse()`.
- Produces (class `Row_Builder`, `public static`):
  - `STATUS = 'Needs Verification'`.
  - `new_row( array $map, array $character, string $show, string $description, int $entry_id ): string[]` — list indexed 0..max(map), `''` where unset. Sets Status, Name, Show, Actor, Queerness, Cliches, Sexuality, Gender from `$character`, Description = `$description`, Image `''`, Form Entry = `(string) $entry_id`.
  - `append_changes( array $map, array $cells, array $character, string $description, int $entry_id, string $date ): array<string,string>` — keys are header labels to write. Always: `Status` → `STATUS`; `Description` → existing + `"\n\n— Added {$date} from form entry #{$entry_id} —\n" . $description` (no leading separator noise if existing is blank: just `$description`); `Form Entry` → existing blank ? id : existing . ', ' . id. For Actor/Queerness/Cliches/Sexuality/Gender: included only if the existing cell is blank (after `normalize`) and the extracted value is non-blank. Never includes Name, Show, Image.
  - `fallback_character(): array` — the character shape with every field `''`.

- [ ] **Step 1: Write failing tests**:
  - `test_new_row_layout`: Satya/Blue/entry 4521 → index 0 `Needs Verification`, 2 `Satya`, 4 `Blue`, 6 `''`, 7 `''`, 8 = description, 10 `'4521'`, count 11.
  - `test_append_description_and_entry`: existing Description `Old text`, Form Entry `''`, date `2026-11-09`, entry 4602 → Description `"Old text\n\n— Added 2026-11-09 from form entry #4602 —\nNew text"`, Form Entry `'4602'`, Status `Needs Verification`.
  - `test_append_entry_list`: existing Form Entry `4521` → `'4521, 4602'`.
  - `test_append_fills_only_blank_cells`: existing Actor `Jolene Purdy`, Gender `''`; extracted Actor `Someone`, Gender `Cisgender` → changes include `Gender`=`Cisgender`, not `Actor`.
  - `test_append_ignores_invisible_only_cell_as_blank`: existing Queerness `"\u{2060} "` counts as blank.
  - `test_append_never_touches_name_show_image`: keys ⊆ {Status, Description, Form Entry, Actor, Queerness, Cliches, Sexuality, Gender}.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter RowBuilder` → FAIL.
- [ ] **Step 3: Implement** `Row_Builder`.
- [ ] **Step 4: Run** `vendor/bin/phpunit` → PASS.

---

### Task 6: Google service-account auth and Sheets client

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/build/class-service-account-jwt.php`
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/class-sheets-client.php`
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/class-transient-error.php`
- Test: `tests/unit/Plugins/Gravity_Forms/ServiceAccountJwtTest.php`
- Modify: `tests/bootstrap.php`, `admin-menu/class-debugging.php` (add `'submissions'` to `VALID_LOG_TOPICS`, alphabetical)

**Interfaces:**
- Produces:
  - `Build\Service_Account_Jwt::build( array $key, int $now, string $scope ): string` — `$key` is the decoded JSON (`client_email`, `private_key`, optional `token_uri`, default `https://oauth2.googleapis.com/token`). Header `{"alg":"RS256","typ":"JWT"}`; claims `iss`=client_email, `scope`, `aud`=token_uri, `iat`=$now, `exp`=$now+3600; base64url without padding; signed with `openssl_sign(..., OPENSSL_ALGO_SHA256)`. Throws `\UnexpectedValueException` if fields missing or signing fails.
  - `Sheets_Client` (glue):
    - `__construct( string $spreadsheet_id, string $key_path )`
    - `get_ranges( array $ranges ): array<string, array>` — `GET https://sheets.googleapis.com/v4/spreadsheets/{id}/values:batchGet?ranges=…&valueRenderOption=FORMATTED_VALUE`, returns range-as-requested ⇒ `values` (or `[]`). Pass tab names quoted: `"'To Add (NEW)'!A:E"`.
    - `append_row( string $tab, array $row ): void` — `POST …/values/{'tab'!A1}:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS`.
    - `update_cells( string $tab, int $row, array $cells_by_letter ): void` — one `POST …/values:batchUpdate` with `valueInputOption=RAW`, one range per cell (`'To Add'!K214`).
    - On `WP_Error`, HTTP 429 or 5xx: throw `Transient_Error` (`class-transient-error.php`, `class Transient_Error extends \RuntimeException {}`). Other 4xx: throw `\RuntimeException` with the API's error message (these are config errors, not retried).
    - Access token cached in transient `lwtv_gsheets_token` for `expires_in - 60` seconds. Scope `https://www.googleapis.com/auth/spreadsheets`.

- [ ] **Step 1: Write failing test** `ServiceAccountJwtTest`:
  - `test_builds_verifiable_rs256_jwt`: generate a key in-test with `openssl_pkey_new(['private_key_bits'=>2048])`, export PEM; build with `$now = 1700000000`; split on `.`; decoded header `alg`=`RS256`; claims `iss`, `aud`=`https://oauth2.googleapis.com/token`, `exp`=1700003600, `scope` as passed; `openssl_verify` of `header.claims` with the public key returns `1`; no `=` in any segment.
  - `test_throws_without_private_key`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ServiceAccountJwt` → FAIL.
- [ ] **Step 3: Implement** `Service_Account_Jwt`, `Transient_Error`, `Sheets_Client`.
- [ ] **Step 4: Run** `vendor/bin/phpunit` → PASS; `composer lint`; `composer analyse`.
- [ ] **Step 5: Live-verify on lwtv.local** (see memory/local wp-cli notes for the socket override): with constants set and the sheet **copy** shared to the service account, `wp eval '$c = new \LWTV\Plugins\Gravity_Forms\Sheets_Client( LWTV_CONTENT_SHEET_ID, LWTV_GOOGLE_SERVICE_ACCOUNT ); var_dump( count( $c->get_ranges( array( "'"'"'To Add'"'"'!A1:K3" ) )["'"'"'To Add'"'"'!A1:K3"] ) );'` prints `int(3)` (or fewer if trailing rows empty). A wrong key path prints a `RuntimeException` message, not a fatal.

---

### Task 7: Gemini client and LWTV lookup

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/class-gemini-client.php`
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/class-lwtv-lookup.php`

**Interfaces:**
- Consumes: `Extraction::prompt()`, `Extraction::schema()`, `Transient_Error`, `Name_Match::titles_match()`.
- Produces:
  - `Gemini_Client::__construct( string $api_key, string $model = self::MODEL )`; `MODEL` constant — use the current stable Flash model name at build time (check Google's model list; `gemini-2.5-flash` was current as of mid-2026).
  - `Gemini_Client::extract( string $show, string $text, array $allowed ): string` — `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` with header `x-goog-api-key`, body `contents[0].parts[0].text = prompt`, `generationConfig = { responseMimeType: 'application/json', responseSchema: schema(), temperature: 0 }`, timeout 30. Returns `candidates[0].content.parts[0].text`. Errors: `WP_Error`/429/5xx/missing candidate → `Transient_Error`; other 4xx → `\RuntimeException`.
  - `Lwtv_Lookup::show_ids( string $title ): int[]` — one `$wpdb` query of `ID, post_title` for `post_type_shows`, `post_status NOT IN ('trash','auto-draft','inherit')`; filter in PHP with `Name_Match::titles_match`. Cache the ID/title list in a 1-hour transient.
  - `Lwtv_Lookup::character_titles( array $show_ids ): string[]` — `$wpdb` join `postmeta` (`meta_key LIKE 'lezchars_show_group_%_show'`, `meta_value IN (…)`) to `posts` with `post_type = 'post_type_characters'` and the same status filter. The join on `wp_posts.post_type` is mandatory: ACF copies this meta onto revisions.

- [ ] **Step 1: Implement** both classes.
- [ ] **Step 2: Live-verify** on lwtv.local:
  - `wp eval 'var_dump( ( new \LWTV\Plugins\Gravity_Forms\Lwtv_Lookup() )->show_ids( "Blue" ) );'` → `array(0) {}`.
  - Pick a known show with characters (e.g. `wp post list --post_type=post_type_shows --posts_per_page=1 --field=post_title`), then `character_titles( show_ids( that title ) )` lists its characters and no duplicates from revisions.
  - With `LWTV_GEMINI_API_KEY` set: `extract( 'Blue', 'Satya is the girlfriend of Lara, Blue\'s sister. She\'s a free spirit who Lara met while busking.', $allowed )` passed through `Extraction::parse()` yields exactly Satya and Lara with blank sexuality/gender.
- [ ] **Step 3:** `composer lint`; `composer analyse` clean.

---

### Task 8: Hooks, hold/release, the runner and the scheduled task

**Files:**
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/class-submission-triage.php`
- Create: `plugins/lwtv-plugin/php/plugins/gravity-forms/class-triage-runner.php`
- Create: `plugins/lwtv-plugin/php/schedulers/class-sheet-triage-task.php`
- Modify: `plugins/lwtv-plugin/php/plugins/class-gravity-forms.php` (`new Submission_Triage();` next to `new Stop_Spammers();`)
- Modify: `plugins/lwtv-plugin/php/_components/class-scheduler.php` (`new Sheet_Triage_Task();` in the AS-available block, plus `use`)

**Interfaces:**
- Consumes: everything above.
- Produces:
  - `Submission_Triage` constants (the one place for form config — read the real values from the contact form on lwtv.local: Forms → the contact form → field IDs):
    - `FORM_ID`, `FIELD_TOPIC`, `TOPIC_VALUE = 'A new show/actor/character'` (verify exact choice value), `FIELD_EMAIL`, `FIELD_SHOW`, `FIELD_INFO`. Each passes through `apply_filters( 'lwtv_submission_triage_config', array(...) )` once.
    - Meta keys: `META_DONE = 'lwtv_sheet_triage_done'`, `META_HELD = 'lwtv_sheet_triage_held'` (`spam` | `moderated`).
  - `Submission_Triage::is_configured(): bool` — all three constants defined and key file readable; if not, `debug_log( 'submissions', … )` once per request.
  - Hooks:
    - `gform_after_submission` ($entry, $form): wrong form or topic → return. `$entry['status'] === 'spam'` → set `META_HELD=spam`, note "Held: marked spam. Will be added to the sheet if un-spammed.", return. `Spammers::is_spammer( email, 'email', 'moderated_keys' )` or the same on `$entry['ip']` → `META_HELD=moderated`, note "Held: flagged for moderation…", return. Else `queue( $entry_id )`.
    - `gform_update_status` ($entry_id, $new, $old): `$old === 'spam' && $new === 'active'` and `META_HELD` set → clear it, `queue()`.
    - `gform_approvals_entry_approved` ($entry, $form): `META_HELD === 'moderated'` → clear, `queue()`.
  - `Submission_Triage::queue( int $entry_id ): bool` — no-op if `META_DONE` set or not configured; `as_has_scheduled_action( Sheet_Triage_Task::AS_HOOK, array( $entry_id, 0 ), 'lwtv' )` guard; `as_schedule_single_action( time() + 10, AS_HOOK, array( $entry_id, 0 ), 'lwtv' )`.
  - `Sheet_Triage_Task::AS_HOOK = 'lwtv_sheet_triage_task'`, `MAX_ATTEMPTS = 5`; handler `run( int $entry_id, int $attempt )`: calls `( new Triage_Runner() )->run( $entry_id, $attempt >= MAX_ATTEMPTS - 1 )`. On `Transient_Error` and attempts left → reschedule `array( $entry_id, $attempt + 1 )` at `time() + min( 3600, 60 * 2 ** $attempt )`. On final failure or `\RuntimeException` → `GFAPI::add_note( …, 'LWTV Robot', 'Sheet triage failed: {message}', 'error' )` and `error_log( 'submissions', … )`.
  - `Triage_Runner::run( int $entry_id, bool $final_attempt ): void`:
    1. Return if `META_DONE`. Load entry; read show + info.
    2. One `get_ranges` for `'To Add'!A:K`, `Completed!A:J`, `Questionable!A:M`, `'To Add (NEW)'!A:E`, `'To Add (existing)'!A:D`, `Lists!A:F`. Build header maps (`To Add` requires all 11 labels incl. `Form Entry`; a missing label throws `\RuntimeException` → not retried, error note). Legacy tabs: Show = column 0, `Info` by header.
    3. Allowed values: gender = Lists `Char Genders`, sexuality = `Char Sexuality`, cliches = `Character Cliches`, queerness = `column_values` of Queerness across To Add + Completed.
    4. If the show field is blank, skip Gemini: characters = `[ Row_Builder::fallback_character() ]`, Show written blank. Otherwise extract via Gemini + `Extraction::parse`. On `Transient_Error` and not final → rethrow. On final, on `\RuntimeException`/`\UnexpectedValueException`, or on an empty result → characters = `[ Row_Builder::fallback_character() ]` and note "Gemini unavailable/unclear; added the raw submission for review."
    5. `$lwtv_titles = character_titles( show_ids( $show ) )`.
    6. For each character: `Triage_Decision::decide( … )`. `new` → `append_row` + add the written row to the in-memory To Add rows (so a later character in the same run sees it). `append` → `update_cells` with `Row_Builder::append_changes()` mapped to letters via `Sheet_Table::column_letter()`. `skip`/`done` → nothing. Collect `note_line()`s.
    7. If show_ids is non-empty and the run used the fallback, add "Show already exists on LWTV." to the note.
    8. One `GFAPI::add_note( $entry_id, 0, 'LWTV Robot', implode( "\n", $lines ), 'success', 'sheet-triage' )`; then `gform_update_meta( $entry_id, META_DONE, time() )`.

- [ ] **Step 1: Implement** the three classes and the two wiring edits.
- [ ] **Step 2:** `vendor/bin/phpunit`, `composer lint`, `composer analyse` → clean.
- [ ] **Step 3: Live-verify on lwtv.local against the sheet copy** (Review Focus 3 and 5):
  - Submit the contact form with topic = new show/character, show `Blue`, the Satya/Lara text → within a minute (run `wp action-scheduler run --group=lwtv` to force), the copy has two new To Add rows (Satya, Lara; Status `Needs Verification`; Form Entry = entry ID; blank sexuality/gender), and the entry has one LWTV Robot note with two lines.
  - Run `wp eval '( new \LWTV\Plugins\Gravity_Forms\Triage_Runner() )->run( ENTRY_ID, false );'` after deleting `lwtv_sheet_triage_done` meta → no new rows; note lines say "already written by this entry".
  - Submit the same text again → both rows get the dated append and Form Entry `A, B`.
  - Submit a character that's on the copy's Questionable tab → skip line in the note, no row.
  - Submit from an email on the moderated keys list → note "Held…", no row; approve via the approval box (or, if this form has no approval feed, use Task 9's CLI) → rows appear.
  - Mark an entry spam then "Not spam" → rows appear.
  - Temporarily rename the copy's `Form Entry` header → error note naming `Form Entry`, form submission itself unaffected.
  - Unset `LWTV_GEMINI_API_KEY` → form submits normally, nothing queued, one debug log line.

---

### Task 9: WP-CLI command and docs

**Files:**
- Create: `plugins/lwtv-plugin/php/wp-cli/cli-triage.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-wp-cli.php` (add the include line with a `// wp lwtv TRIAGE <entry_id>` comment, matching neighbors)
- Create: `docs/integrations/content-to-add-sheet.md`
- Modify: `docs/README.md` (link it)

**Interfaces:**
- Consumes: `Submission_Triage::queue()`, `Triage_Runner::run()`, `Submission_Triage::META_DONE`, `META_HELD`.
- Produces: `wp lwtv triage <entry_id> [--now] [--force]` — default queues; `--now` runs `Triage_Runner::run( $id, true )` inline and prints the note lines; `--force` clears `META_DONE` and `META_HELD` first (this is how a held entry is released if the form has no approval feed). Registered as `\WP_CLI::add_command( 'lwtv triage', 'WP_CLI_LWTV_Triage' )`.

- [ ] **Step 1: Implement** the command.
- [ ] **Step 2: Live-verify:** `wp lwtv triage <held entry id> --force --now` prints per-character lines and writes rows to the copy; `wp lwtv triage 999999` errors cleanly ("Entry not found").
- [ ] **Step 3: Write** `docs/integrations/content-to-add-sheet.md`: what it does (the flow from the spec, short), the three constants, creating the service account and sharing the sheet as Editor, the `Form Entry` column and `Needs Verification` status as sheet requirements, hold/release behavior, the CLI, and where to look when something goes wrong (entry notes, `wp lwtv debug-log` topic `submissions`, Action Scheduler group `lwtv`).
- [ ] **Step 4:** full `vendor/bin/phpunit`, `composer lint`, `composer analyse` → clean. Stop here for the user's review; do not commit.
