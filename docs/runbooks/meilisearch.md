# Meilisearch

* Host: DreamHost Dedicated Server
* User: See Mika

Meilisearch is a fast, open-source search and retrieval engine built in Rust for websites, applications, and AI workflows. It delivers sub-50ms search-as-you-type results with built-in typo tolerance, faceted filtering, geosearch, and hybrid keyword-and-semantic search.

Due to the growing size of the database, a Meilisearch self-hosted instance is being used to optimize speed and results.

* [Official Self Hosting Docs](https://www.meilisearch.com/docs/resources/self_hosting)

## Installation

1. Log in to the dedicated server as the admin account.
2. Create folder: `mkdir docker/meilisearch-lwtv/`
3. Create compose file: `touch docker/meilisearch-lwtv/docker-compose.yml`
4. Create a master key: `openssl rand -base64 32`

The docker compose file will look like this:

```yaml
services:
  meilisearch:
    image: getmeili/meilisearch:v1.57.0
    container_name: meilisearch-lwtv
    ports:
      - "127.0.0.1:7701:7700"
    volumes:
      - /home/[USERNAME]/docker/meilisearch-lwtv/data:/meili_data
    environment:
      - MEILI_MASTER_KEY=[MASTER_KEY]
      - MEILI_NO_ANALYTICS=true
      - MEILI_LOG_LEVEL=INFO
      - MEILI_UPGRADE_DB=true
      - MEILI_ENV=production
    logging:
      driver: "json-file"
      options:
        max-size: "100m"      # Keeps logs to a reasonable 100MB
        max-file: "3"
    restart: always
```

While normally you would save the docker data in a base level folder (i.e. `/docker/data/meilisearch-lwtv/`), we save it local to the image here in order to associate the data with the user. If the server is ever rebuilt by Dreamhost, the user data will be migrated automatically. This reduces the chances of lost data.

## API keys

The master key never leaves the server. WordPress gets two keys minted from it, per environment:

| Key | Goes in | Who sees it |
| --- | --- | --- |
| Indexing key | `LWTV_MEILI_INDEX_KEY` | WordPress (PHP) only. Never the browser, never a log. |
| Search key | `LWTV_MEILI_SEARCH_KEY` | Public by design: the browser uses it to search. |

Each environment gets its own pair, scoped to its own indexes, so a local save can never touch production:

| Environment | `LWTV_MEILI_INDEX_PREFIX` | Index names | Key `indexes` |
| --- | --- | --- | --- |
| Production | *(not set)* | `characters`, `shows`, `actors`, `content` | `["characters","shows","actors","content"]` |
| Staging | `staging` | `staging_characters`, `staging_shows`, `staging_actors`, `staging_content` | `["staging_*"]` |
| Local (lwtv.local) | `local` | `local_characters`, `local_shows`, `local_actors`, `local_content` | `["local_*"]` |

Production has no prefix, so its names share no pattern and are listed one by one. All four (`characters`, `shows`, `actors`, `content`) exist in code. If a production key was minted without `shows`, `actors` or `content`, **re-mint it to include them** before indexing those there (the `*` patterns on staging and local already cover `content`) (keys can't be edited; mint a new pair, update `wp-config.php`, delete the old ones).

### Minting a pair

Run these **on the server**, against the loopback port, so the master key never crosses the network. Swap in the environment's name and `indexes` from the table.

1. Log in to the dedicated server as the admin account.
2. Load the master key into the shell (it's in `docker/meilisearch-lwtv/docker-compose.yml`): `export MEILI_MASTER_KEY='…'`
3. Create the indexing key:

   ```bash
   curl -s -X POST 'https://search.lezwatchtv.com/keys' \
     -H "Authorization: Bearer $MEILI_MASTER_KEY" \
     -H 'Content-Type: application/json' \
     --data-binary '{
       "name": "lwtv-local-indexing",
       "description": "LezWatch.TV lwtv.local: WordPress indexing",
       "actions": ["documents.add", "documents.delete", "indexes.create", "indexes.delete", "indexes.get", "settings.get", "settings.update", "stats.get", "tasks.get"],
       "indexes": ["local_*"],
       "expiresAt": null
     }'
   ```

4. Create the search key:

   ```bash
   curl -s -X POST 'https://search.lezwatchtv.com/keys' \
     -H "Authorization: Bearer $MEILI_MASTER_KEY" \
     -H 'Content-Type: application/json' \
     --data-binary '{
       "name": "lwtv-local-search",
       "description": "LezWatch.TV lwtv.local: public search",
       "actions": ["search"],
       "indexes": ["local_*"],
       "expiresAt": null
     }'
   ```

5. Each response has a `key` field. **It is shown only once**, so copy it straight into `wp-config.php` (below).
6. `unset MEILI_MASTER_KEY` and log out.

Why those actions: `indexes.delete` is what `wp lwtv meili reindex --fresh` needs, and `tasks.get` lets the CLI wait for each step to finish. Nothing on the list can read or create other keys.

Don't add `version` or any other global action (`keys.*`, `dumps.*`, `snapshots.*`, …): Meilisearch refuses them on a key scoped to `indexes` (`index_scoped_api_key_with_global_action`). Check the server version on the server with the master key instead (see Upgrading).

### Listing and revoking keys

```bash
curl -s 'http://127.0.0.1:7701/keys' -H "Authorization: Bearer $MEILI_MASTER_KEY"
curl -s -X DELETE 'http://127.0.0.1:7701/keys/<uid>' -H "Authorization: Bearer $MEILI_MASTER_KEY"
```

The list shows each key's `uid`, `name` and scope, but never the key value again. A leaked search key only lets someone search; still, revoke and re-mint. A leaked **indexing** key can delete the index: revoke it, mint a new one, update `wp-config.php`, then `wp lwtv meili reindex --type=all --fresh`.

## WordPress setup

Add to each environment's `wp-config.php`:

```php
define( 'LWTV_MEILI_URL', 'https://search.lezwatchtv.com' );
define( 'LWTV_MEILI_INDEX_KEY', '…' );        // indexing key
define( 'LWTV_MEILI_SEARCH_KEY', '…' );       // search key
define( 'LWTV_MEILI_INDEX_PREFIX', 'local' ); // staging: 'staging'; production: leave this line out
```

If the site sets `WP_HTTP_BLOCK_EXTERNAL` (lwtv.local does), let the search host through, or every request is refused with "User has blocked requests through HTTP":

```php
define( 'WP_ACCESSIBLE_HOSTS', 'search.lezwatchtv.com' ); // comma-separate if there are others already
```

Until the first three constants are set, the Meilisearch code does nothing at all: no hooks, no admin page. That's also the off switch: comment them out to stop all syncing.

To see sync failures, tick **meilisearch** under LezWatch.TV → Debugging → log topics.

### First run on an environment

Do this once per index: characters, then shows, then actors (actors have extra steps first: see [Actors: other names](#actors-other-names)), then content. Sync never creates an index, so until `settings` or `reindex --fresh` runs for a type, saves skip it and admins see "the Shows index … isn't set up on this site" on the Dashboard. That notice is the reminder; running these steps clears it.

```bash
wp lwtv meili status --type=shows            # server up? right index? (expect an index_not_found warning)
wp lwtv meili reindex --type=shows --dry-run # builds every document, sends nothing; count should match published shows
wp lwtv meili reindex --type=shows --fresh   # creates the index, applies settings, sends everything
wp lwtv meili status --type=shows            # document count, and "Settings match the repo file"
```

Swap `--type=shows` for `--type=characters` (or leave it off: characters is the default) for the characters index, or `--type=content` for posts and pages. `--type=all` does every index.

For content, the dry-run count should match published posts plus published pages, minus any with a password (930 on lwtv.local, 2026-11-25: 909 posts, 21 pages). Private, draft and password-protected posts never go in.

Every command prints `Index: <name> on <host>` before it touches each index. **Read that line before anything that writes.**

Then open LezWatch.TV → Search Compare, pick Characters, Shows, Actors or Content, and try a few names (for Content: a show name like "batwoman", or a page like "show scores"). The SearchWP column for Content has few rows: SearchWP has no content engine, so it uses `default` and only the posts and pages in its top 20 show.

### Actors: other names

Actors' former, married and stage names used to live in the excerpt ("Additional Notes"), a workaround from Jetpack Search. They now have their own field, **Other Names** (`lezactors_other_names`), which is searchable but never shown on the site or returned by Meilisearch. Moving them is a manual command, and the order matters on each environment, because live SearchWP search finds dead names through the excerpt until the move:

1. Deploy the code.
2. In SearchWP → Engines → Actors, add the custom field `lezactors_other_names_*_lezactors_other_name` (SearchWP's wildcard is `*`, which matches every row; a `%` is taken literally and matches nothing). Save.
3. See what would happen. Nothing is written, and no names are printed:

   ```bash
   wp lwtv migrate othernames --dry-run
   ```

   Each actor with an excerpt gets a decision: `move` (a list of names), `clear` (blank text), `review` (prose, a URL, a quote: a human decides) or `skip` (Other Names already has rows; nothing is merged).
4. Run it: `wp lwtv migrate othernames`. It moves the `move` rows into Other Names and empties their excerpts, each only after the names read back from the new field; one that doesn't is reported as failed and keeps its excerpt. `review` and `skip` are left alone. It refuses to start if the Other Names field isn't loaded (old theme code). The report lists every actor by name, private ones included (the status column says which), so keep it to the terminal.
5. Deal with the leftovers by hand. Editors can't see the excerpt any more, so the terminal is the way in:

   ```bash
   wp lwtv migrate othernames --dry-run --show-review   # prints each leftover excerpt in full, with an edit link
   wp lwtv migrate othernames --clear=123,456           # empties those excerpts once handled
   ```

   `--show-review` output can contain dead names: don't paste it anywhere public.
6. Rebuild SearchWP's index (the Rebuild Index button on SearchWP's Engines screen), because the migration writes straight to the database and SearchWP doesn't notice.
7. `wp lwtv meili settings --type=actors`, then `wp lwtv meili reindex --type=actors`.

A second dry run should report nothing to move.

Good to know:

- With SearchWP's highlighting on, a search for a former name can show that name in the result's excerpt on the search results page. Accepted (Mika, 2026-11-25): these names aren't secret, and the search is meant to find them.
- Old names stay in actor revisions (WordPress copies the excerpt into each one). Only editors can see revisions, and they can see Other Names anyway, so revisions aren't scrubbed.
- An actor marked **Hide All** never has a document. **Hide Date of Birth** drops the birth year from the document; the death year stays.

## When something's wrong

Start with `wp lwtv meili status`. It answers most of the questions below.

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `Meilisearch is not configured. Define in wp-config.php: …` | Constants missing on this environment | Add the ones it names. |
| `User has blocked requests through HTTP` | `WP_HTTP_BLOCK_EXTERNAL` without the host allowed | Add `WP_ACCESSIBLE_HOSTS` (above). |
| `Server unreachable: cURL error 7` / `28` | Container down, or nginx not proxying | On the server: `docker ps`, then `cd docker/meilisearch-lwtv && docker compose up -d`. Check `curl -s http://127.0.0.1:7701/health` says `available`. |
| `HTTP 401` / `HTTP 403: invalid_api_key` | Wrong key, revoked key, or a key scoped to another environment's indexes | Check the prefix matches the key's `indexes`; re-mint if needed. |
| `HTTP 403: invalid_api_key` on **one** line of `status` (e.g. `Index stats:`) while the others work | The key is valid but lacks that action (Meilisearch words a missing permission as "invalid") | Compare the key's `actions` (`GET /keys`) with the list above. Actions can't be added to a key: mint a new one and delete the old. |
| `index_not_found`, or the Dashboard says "the … index isn't set up on this site" | Index never built on this environment (saves are skipping it) | `wp lwtv meili reindex --type=<type> --fresh`. The notice can lag up to five minutes. |
| `Unknown --type "show"` | Typo | Use `characters`, `shows`, `actors`, `content` or `all`. |
| `Settings differ from the repo file` | Someone changed `config/{type}.json` (for example after pulling a code update), or the server was reset | `wp lwtv meili settings --type=<type>` (the warning names the type). |
| An actor marked Hide All is still findable | Their sync failed (server down) and their status hasn't caught up | `wp lwtv meili reindex --type=actors`: it prunes every document it didn't build, Hide All actors included. |
| `reindex` stops at "Stale-document cleanup" saying `id` isn't filterable | Settings are older than the code | `wp lwtv meili settings --type=<type>`, then `reindex` again. |
| A post's new tag or category name isn't searchable | Renaming a term doesn't re-sync the posts that carry it | `wp lwtv meili reindex --type=content` (about 5 seconds). |
| A private, draft or password-protected post shows up in content results | Its sync failed while the server was down | `wp lwtv meili sync <id>`, or `reindex --type=content`, which prunes it. |
| A character, show, actor, post or page is missing, named wrong, or a deleted one still shows up | A sync failed while the server was down (failures are logged and dropped, never retried) | One post: `wp lwtv meili sync <id>` (fixes it in every index it affects). Lots, or unsure: `wp lwtv meili reindex --type=all`. It re-sends every published post and removes entries that aren't published any more, without emptying the index. |
| Search Compare: Meilisearch column says "Request failed" | Browser can't reach the server, or the search key is wrong | Check the browser's network tab: the request goes straight to `search.lezwatchtv.com`. |

Saving posts never waits on Meilisearch: syncs are queued in Action Scheduler (`lwtv_meili_characters_task`, `lwtv_meili_shows_task`, `lwtv_meili_actors_task`, `lwtv_meili_content_task`, group `lwtv`). If the search server is down, editors won't notice. Search just goes stale until it's back and you reindex.

The index holds nothing that isn't in WordPress, so there are no backups to restore. Two ways to rebuild:

- `wp lwtv meili reindex --type=all`: the everyday repair. Search keeps working while it runs. About 17 seconds for 7,357 characters, and 4 seconds for 930 posts and pages, on lwtv.local (2026-11-25).
- `wp lwtv meili reindex --fresh`: deletes and recreates the index first, so search is empty until it finishes. Use it after a Meilisearch upgrade, or if the index itself looks broken.

## Upgrading Meilisearch

1. Change the image tag in `docker-compose.yml` (e.g. `v1.57.0` → the new version). Read the release notes for breaking changes first.
2. `cd docker/meilisearch-lwtv && docker compose pull && docker compose up -d`. `MEILI_UPGRADE_DB=true` migrates the data in place.
3. `curl -s http://127.0.0.1:7701/version -H "Authorization: Bearer $MEILI_MASTER_KEY"` to confirm.
4. On each environment: `wp lwtv meili status --type=all`. If anything looks off, `wp lwtv meili reindex --type=all --fresh`: the indexes are fully derived from WordPress, so rebuilding is always safe.

Each show's score is part of its search document, so `wp lwtv calc --all` queues one small `lwtv_meili_shows_task` per show (about 2,300). That's expected; they drain on their own. Actor calculations do the same for actors (`lwtv_meili_actors_task`, one per actor recalculated), since the queer flag and character count rank same-name actors.

How it works under the hood (documents, ranking, sync hooks): [docs/architecture/meilisearch.md](../architecture/meilisearch.md).
