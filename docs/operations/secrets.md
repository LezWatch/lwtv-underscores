# Secrets

Where the site's API keys and tokens live, how the code reads them, and how to set, migrate and rotate them.

Code: `plugins/lwtv-plugin/php/_helpers/class-secret-registry.php` (`LWTV\_Helpers\Secret_Registry`, pure: the list of credentials and the rules), `plugins/lwtv-plugin/php/_helpers/class-secrets.php` (`LWTV\_Helpers\Secrets`, the WordPress side), `plugins/lwtv-plugin/php/wp-cli/cli-secrets.php` (`wp lwtv secrets`). Tests: `tests/unit/Helpers/SecretRegistryTest.php`.

## The Secrets API

WordPress 7.2 added a Secrets API ([Trac #66187](https://core.trac.wordpress.org/ticket/66187), `wp-includes/secrets.php`). Values are encrypted at rest with `WP_SECRETS_KEY` from `wp-config.php`. There is no plaintext mode. By default each secret is a non-autoloaded `wp_options` row named `_wp_secret_<name>`; a `secrets.php` drop-in can move storage elsewhere. The API has no capability checks of its own, so the plugin enforces access at the edge. The only writer is WP-CLI, so shell access is the gate.

All our secrets are namespaced `lwtv/<name>`.

## Inventory

| Name | What it is | Used by | Old home (still the fallback) |
| --- | --- | --- | --- |
| `postiz-api-key` | Postiz API key, sent as the `Authorization` header | `Postiz\Postiz` | ACF option `options_lwtv_postiz_api_key` (a plain text field editors could see) |
| `tmdb-api-key` | TMDB v3 API key, sent as `?api_key=` | `_Components\CPTs::get_tmdb_info()`, `::get_tmdb_watch_providers()`, `wp lwtv tmdb backfill` | `TMDB_API` constant |
| `gemini-api-key` | Gemini API key, sent as `x-goog-api-key` | `Gravity_Forms\Triage_Runner`, `Submission_Triage::missing_settings()` | `LWTV_GEMINI_API_KEY` constant |
| `google-service-account` | The Google service-account **JSON key itself** | `Gravity_Forms\Sheets_Client` (via `Triage_Runner`) | `LWTV_GOOGLE_SERVICE_ACCOUNT` constant, a **path** to the key file |
| `tvmaze-ical-url` | TVMaze "followed shows" iCal URL. It carries a personal `token` query arg, so the whole URL is the secret. | `_Components\Calendar::download_tvmaze()`, `Calendar\TVMaze::get_timezone()` | `TV_MAZE` constant |

Not moved, on purpose:

| What | Where | Why it stays |
| --- | --- | --- |
| Google OAuth access token | Transient `lwtv_gsheets_token_{md5(client_email)}` | Minted from the service-account key and expires within an hour. The key is the credential; the token is a cache. |
| `LWTV_CONTENT_SHEET_ID` | constant | An identifier, not a credential. Sharing the sheet still needs the service account. |
| `LWTV_GEMINI_API_BASE`, `LWTV_API_USAGE_CSV` | constants | Configuration, not credentials. |
| Third-party plugin credentials (Turnstile, MailerLite, Brevo, MonsterInsights, WP Mail SMTP, Yoast, Postman) | Their own options | Owned by those plugins. Not ours to move. |

## How the code reads a credential

Always `Secrets::get( '<name>' )` (or `Secrets::has()`). Never read the constant or option directly.

1. Ask the store: `wp_get_secret( 'lwtv/<name>' )`, then `->reveal()`.
2. If the store has nothing, or WordPress is older than 7.2, read the old home: the constant, the file the constant points to, or the option. So a site that hasn't migrated yet keeps working.
3. If the store has the secret but can't decrypt it (for example `WP_SECRETS_KEY` is missing or changed), fall back as in step 2 **and** write a line to PHP's error log, at most once an hour per credential: `[Secrets] lwtv/<name>: The stored secret could not be read (...)`. It never fails silently, and never logs a value.

`Secrets::source( '<name>' )` says which one won: `secret`, `constant`, `file`, `option`, `<that>-after-error`, `error` or `none`. The value is resolved once per request and kept in memory for the rest of it, the same exposure the constants had. TMDB backfills read it thousands of times.

### CLI and web cache tiers

Core's store uses `get_option()`, and web requests cache non-autoloaded options in Redis while WP-CLI doesn't load the object cache (see [Caching](../architecture/caching.md#cli-and-web-cache-tiers)). Without help, a `wp lwtv secrets set` from the shell would reach the database while web requests kept reading the old value, or a cached "doesn't exist" that sends them to the fallback. So `Secrets` drops the cached `_wp_secret_lwtv/<name>` row (and its `notoptions` entry) before each first read in a request, the same way `Uncached_Option` does. It's skipped when a `secrets.php` drop-in replaces the store. A drop-in that only swaps the keyring still stores in options, so it still gets this step.

## `wp lwtv secrets`

Values are read from `--from-file`, from stdin when it's piped, or from a hidden prompt. They never land in shell history, and no subcommand prints one.

| Command | What it does |
| --- | --- |
| `status` | For each name: what's in use, whether the store has it, `needs_rotation`, whether a previous version is kept, and whether the fallback is set. No values. |
| `set <name> [--from-file=<path>]` | Validates and stores a value, then reads it back. Replacing a value keeps the old one as the previous version. |
| `import [--dry-run] [--keep-legacy]` | Copies each credential the store doesn't have yet from its old home, then reads it back and compares. Deletes the plaintext option copies of anything now stored. Safe to run again. |
| `verify [<name>...]` | One read-only authenticated call per integration, using whatever `Secrets::get()` resolves. Prints HTTP status codes only. |
| `retire <name>` | Drops the previous version once a replacement works. |
| `delete <name> [--yes]` | Deletes both versions. The fallback, if any, takes over. Deleting a key doesn't revoke it at the provider. |

`set` refuses values that don't fit the name, without echoing the value: an empty value, a key containing whitespace (usually a two-line paste), a TVMaze URL that isn't `https://`, or a service-account value that isn't Google's JSON key with `type: service_account`, `client_email` and `private_key`. That last one catches pasting the file's path instead of its contents.

What `verify` calls:

| Name | Request | Passes on |
| --- | --- | --- |
| `tmdb-api-key` | `GET api.themoviedb.org/3/configuration` | 2xx |
| `gemini-api-key` | `GET` the models list at `LWTV_GEMINI_API_BASE` (default `generativelanguage.googleapis.com/v1beta/models`) | 2xx |
| `google-service-account` | Drops the cached token, mints a new one from the key, reads the sheet's tab list | Token minted and tabs read. Skipped without `LWTV_CONTENT_SHEET_ID`. |
| `postiz-api-key` | `GET <lwtv_postiz_api_url>/integrations` | 2xx |
| `tvmaze-ical-url` | `GET` the feed | 2xx and an iCal body |

Each host has to be allowed by `WP_ACCESSIBLE_HOSTS` wherever `WP_HTTP_BLOCK_EXTERNAL` is on, or that probe fails with "User has blocked requests through HTTP".

## Migrating a site

Do this once per environment (local, staging, production), after the release with this code is deployed.

1. Check `WP_SECRETS_KEY` is defined in `wp-config.php`. Site Health warns if it isn't. Keep a copy of it somewhere safe, such as 1Password: if it's lost, every stored secret has to be set again.
2. `wp lwtv secrets status`. Each name should be in use from its old home.
3. `wp lwtv secrets import --dry-run`, then `wp lwtv secrets import`.
4. `wp lwtv secrets verify`. Every row should be `OK`.
5. **Rotate the Postiz key.** It was a plain option, so it's in every database backup and Duplicator export, and editors could read it on the Auto-Posting screen. `import` flags it `needs_rotation` for that reason. Create a new key in Postiz, `wp lwtv secrets set postiz-api-key`, `wp lwtv secrets verify postiz-api-key`, revoke the old key in Postiz, then `wp lwtv secrets retire postiz-api-key`.
6. Remove the now-unused constants from `wp-config.php`: `TMDB_API`, `LWTV_GEMINI_API_KEY`, `LWTV_GOOGLE_SERVICE_ACCOUNT`, `TV_MAZE`. Run `wp lwtv secrets status` again: `in use` should still say `secret` for all five, and the fallback column `(empty)`.
7. Delete the service-account JSON key file from the server. Its contents are now in the store.
8. In wp-admin, go to ACF → Field Groups and sync **Options - Auto-Posting**. That removes the old API Key text field's database copy. The screen already renders from `acf-json/`, which replaced the field with a status message.

Leaving the constants in place is harmless (the store wins), but then the credential lives in two places, and the copy in `wp-config.php` isn't encrypted.

The import deletes these Postiz rows once the store holds the key: `options_lwtv_postiz_api_key` and `_options_lwtv_postiz_api_key` (the ACF value and its field-key reference), `lwtv_postiz_api_key` and `_lwtv_postiz_api_key` (the same pair without the `options_` prefix, written by `wp lwtv migrate autoposting`), and the `lwtv_postiz_api_key` entry inside the old CMB2 array `lwtv_auto_posting_options`, which is autoloaded.

## Rotating a credential

1. Create the new key at the provider. Don't revoke the old one yet.
2. `wp lwtv secrets set <name>`. The old value is kept as the previous version.
3. `wp lwtv secrets verify <name>`.
4. Revoke the old key at the provider, then `wp lwtv secrets retire <name>`.

If step 3 fails, set the old value again or fix the new one. Nothing reads the previous version automatically.

For the Google service account, create a new JSON key under IAM → Service Accounts → Keys, then `wp lwtv secrets set google-service-account --from-file=<downloaded.json>`. Delete the downloaded file and the old key in Google Cloud. The cached access token is keyed on `client_email`, which is unchanged, so it lives out its hour; `verify` mints a fresh one.

## Postiz settings screen

The Auto-Posting options page no longer has an API Key text field. A message in its place says where the key is read from (`postiz_key_status_field()` in `plugins/class-acf.php`), without showing it. The key can only be set from WP-CLI. The API URL, channels, post type and triggers stay as ordinary ACF options.
