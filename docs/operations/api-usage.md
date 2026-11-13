# API Usage

**LezWatch.TV > API Usage** in wp-admin (admins only) shows who calls the `lwtv/v1` REST API, which routes they call, and which routes are failing. Analytics can't see API calls, so the data comes from the server logs.

## Where the data comes from

`cron/api-roundup.sh` runs daily at 01:15 (`15 1 * * *`). It reads the nginx access logs and appends one row per day, path, consumer and status to `~/lwtv-api-usage/usage.csv`. The raw logs only go back about a week, so this CSV is the long-term record. The admin page only reads it; it never edits or truncates it.

## Setup

Add this to `wp-config.php`:

| Constant | What it is |
| --- | --- |
| `LWTV_API_USAGE_CSV` | Absolute path to `usage.csv`. There is no default. Production points at the file the cron job writes; dev, staging or local sites can point at a copy or a test file. |

```php
define( 'LWTV_API_USAGE_CSV', '/home/<account>/lwtv-api-usage/usage.csv' );
```

If the constant is missing, the page says so. If the file can't be read, the page names the path. Neither breaks the admin.

## Things to know

- If the file or its newest date is more than 36 hours old, the page shows a stale warning. That usually means the cron job stopped.
- The parsed report is cached in a transient keyed on the file's modification time, so a new cron run shows up right away. If you change the report's columns, bump `Api_Usage::CACHE_VERSION`.
- "No hits in this range" does not mean a route is unused or safe to remove. A few weeks of data can't tell a dead route from a quarterly one.
- Known consumers (names, contacts, outreach status, internal flag) are stored in the `lwtv_api_known_consumers` option.
