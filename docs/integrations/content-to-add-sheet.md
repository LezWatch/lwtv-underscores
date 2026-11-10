# Content to Add sheet

When someone uses the Contact Us form to tell us about "A new show/actor/character", the site checks what they wrote and adds it to the **LWTV: Content to Add** Google Sheet for a human to review. Nobody has to retype it.

Code: `plugins/lwtv-plugin/php/plugins/gravity-forms/` (`Submission_Triage` queues, `Triage_Runner` does the work, `Gemini_Client`, `Sheets_Client` and `Lwtv_Lookup` talk to the outside), `plugins/lwtv-plugin/php/schedulers/class-sheet-triage-task.php`, and `plugins/lwtv-plugin/php/wp-cli/cli-triage.php` (`wp lwtv triage`).

## What happens

1. The form is submitted. Nothing slow runs during the request. The entry ID is queued on Action Scheduler (group `lwtv`).
2. Gemini reads the show title and the description text and picks out the characters. It is never sent the submitter's name or email. It only fills a controlled field (sexuality, gender, queerness, cliches) when the text says so outright and the value is on the sheet's `Lists` tab. A character is kept only if the text names them, and an actor only if the text names the actor. Anything else stays blank.
3. Each character is checked in this order, and the first hit wins:
   - Already on LWTV (a show with that title and a linked character with that name, drafts included): skipped.
   - Already on the **To Add** tab: the new text is appended to that row.
   - On **Completed** or **Questionable**: skipped.
   - In the old `To Add (NEW)` or `To Add (existing)` lists: skipped.
   - Otherwise: a new row on **To Add**.
4. Rows are written: first the appends to existing To Add rows, then any new rows, each added just past the last row of the tab (a blank row in the middle of the tab is left alone).
5. The entry gets a note from LWTV Robot saying what happened to each character.

Rows the robot writes or changes get Status `Needs Verification`. When it appends to an existing row, the description gets a line like `— Added 2026-11-09 from form entry #4521 —` followed by the new text, and the entry ID is added to `Form Entry`. It never changes any other filled-in cell. If two To Add rows could match, it edits neither and writes a new row, and the note lists the row numbers.

A fallback row (Status `Needs Verification`, the show, the raw text and the entry ID, nothing else) is written instead, so a submission is not lost, when:

- the show title is blank, or the description is blank;
- Gemini returns no usable characters (nobody found, or nobody the text actually names);
- Gemini fails with a permanent error (for example HTTP 400), or fails with a temporary error on the final attempt. Temporary errors are retried first. The entry note includes the short error message. If Gemini answers but names no one the text mentions, the note says "No characters named in the text" instead.

## Setup

You will need to add these to `wp-config.php`:

| Constant | What it is |
| --- | --- |
| `LWTV_GEMINI_EMAIL` | Email address (not used by code, but saved for later) |
| `LWTV_GEMINI_API_KEY` | Gemini API key. |
| `LWTV_GOOGLE_SERVICE_ACCOUNT` | Absolute path to the service account's JSON key file. |
| `LWTV_CONTENT_SHEET_ID` | ID of the spreadsheet to write to. There is no default: production sets the real sheet's ID, and staging or local sites set a copy's. |

If any required constant is missing (or the key file can't be read), the feature does nothing and logs it once. It never breaks the form.

Most of these are created in [Google Cloud](https://console.cloud.google.com)

### Update `wp-config`

In your config, look for the define `WP_ACCESSIBLE_HOSTS` and add `oauth2.googleapis.com,sheets.googleapis.com,generativelanguage.googleapis.com` to it.

eg:

```bash
define( 'WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,*.wordpress.org,api.tvmaze.com,wikidata.org,*.wikidata.org,api.themoviedb.org,image.tmdb.org,*.wikipedia.org,*.tvmaze.com,oauth2.googleapis.com,sheets.googleapis.com,generativelanguage.googleapis.com' );
```

Then add these:

```bash
// Google AI
define( 'LWTV_GEMINI_MODEL', 'gemini-3.8-flash' );
define( 'LWTV_GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta/models/' );
```

### Create Project

If you have not yet, create a project. We use the LezWatchTV project.

### Enable Google Sheets API

Go to **API & Services** > **Library**

Look for **Google Sheets API** and add it.

### Create Google Service Account

Go to **IAM & Admin** > **Service Accounts**

Create a new account

- Name: LWTV Automation
- Description: Automated service account to process content

Once created, click on the **Keys** tab and create a json key. Save it to 1Password and then you will need to add the file _outside_ webroot (i.e. `/home/user/folder/google-lwtv-sheet-automation.json`)

### Add to Google Sheet

Open the sheet, share it with the service account's email address as **Editor**.

On the **To Add** tab, add a header `Form Entry` in column K (the code finds columns by header text, and stops with an error if a header is missing).

Test against a copy of the sheet first, by pointing `LWTV_CONTENT_SHEET_ID` at the copy.

To get the ID, you want to take part of the URL between `/d/` and `/edit/`:

```shell
https://docs.google.com/spreadsheets/d/1Vzszix3_hAbTG7gKwYg_tqfe2LJ6LVKYY6OcNhQ9MhY/edit?usp=sharing
                                       └──────────────── sheet ID ────────────────┘
```

### Create Gemini API Key

The Gemini key comes from [Google AI Studio](https://aistudio.google.com) (Get API key → Create API key). It shows the projects you have, so pick the one where you made the service account. Paste the key into LWTV_GEMINI_API_KEY.

This key is saved in 1Password.

## Held entries

Spam entries and entries flagged for moderation are not sent to the sheet. They are held, and the entry gets a note saying so.

- **Spam** is released when you un-spam the entry.
- **Moderation** is released when the entry is approved by an approval feed.
- Either one can be released by hand: on the entry screen, open the **Content to Add sheet** box and press **Release to Content sheet**. This is the normal way to do it.

An entry is only released once it has been queued. If it can't be queued (the feature isn't configured, Action Scheduler is unavailable, or the entry is already on the sheet), it stays held and the note says why.

If you can't use the button, `wp lwtv triage <entry_id> --force` does the same thing from the command line.

## Command line

```bash
wp lwtv triage 4521              # queue it
wp lwtv triage 4521 --now        # run it right now and print what happened
wp lwtv triage 4521 --force      # clear the held and done markers first
wp lwtv triage 4521 --force --now
```

Without `--force` the command refuses an entry that is held or already done. It also refuses entries that aren't about a new show, actor or character.

`--now` runs as the final attempt. A Gemini failure there is not shown as an error: the fallback row with the raw text is written to the real sheet, and the entry is marked done. Only Sheets, configuration and missing-header errors (and "another run for this entry is in progress") come back as errors.

Re-running with `--force` does not duplicate rows this entry already wrote, because the `Form Entry` column records which entries have been written. One exception: if the first run wrote a fallback row and Gemini now succeeds, the named character rows are added beside the fallback row, which stays.

## When something goes wrong

- **The entry's notes.** Every outcome, and every failure, is written there. The **Content to Add sheet** box on the entry screen says whether it is done, held, queued or not queued.
- **Debug log.** `wp lwtv debug-log tail`, topic `submissions`.
- **Action Scheduler.** Tools, Scheduled Actions, group `lwtv`, hook `lwtv_sheet_triage_task`. Temporary Google or Gemini errors are retried with a growing delay, up to five attempts in all (the first run included). On the last attempt a Gemini error falls back to the raw-text row; a Google Sheets error leaves an error note on the entry. Only one run per entry happens at a time; a second one is retried later. Run `wp lwtv triage <id> --now` to see the error directly.
