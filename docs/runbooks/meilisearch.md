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
| Staging | `staging` | `staging_characters`, … | `["staging_*"]` |
| Local (lwtv.local) | `local` | `local_characters`, … | `["local_*"]` |

Production has no prefix, so its names share no pattern and are listed one by one. Only `characters` exists today; the rest are planned. If Meilisearch refuses a name that doesn't exist yet, mint the key with `["characters"]` and update it when the other indexes arrive.

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

The list shows each key's `uid`, `name` and scope, but never the key value again. A leaked search key only lets someone search; still, revoke and re-mint. A leaked **indexing** key can delete the index: revoke it, mint a new one, update `wp-config.php`, then `wp lwtv meili reindex --fresh`.

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

```bash
wp lwtv meili status            # server up? right index? (expect "Documents: 0" or an index_not_found warning)
wp lwtv meili reindex --dry-run # builds every document, sends nothing; count should match published characters
wp lwtv meili reindex --fresh   # creates the index, applies settings, sends everything
wp lwtv meili status            # document count, and "Settings match the repo file"
```

Every command prints `Index: <name> on <host>` first. **Read that line before anything that writes.**

Then open LezWatch.TV → Search Compare and try a few names.

## When something's wrong

Start with `wp lwtv meili status`. It answers most of the questions below.

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `Meilisearch is not configured. Define in wp-config.php: …` | Constants missing on this environment | Add the ones it names. |
| `User has blocked requests through HTTP` | `WP_HTTP_BLOCK_EXTERNAL` without the host allowed | Add `WP_ACCESSIBLE_HOSTS` (above). |
| `Server unreachable: cURL error 7` / `28` | Container down, or nginx not proxying | On the server: `docker ps`, then `cd docker/meilisearch-lwtv && docker compose up -d`. Check `curl -s http://127.0.0.1:7701/health` says `available`. |
| `HTTP 401` / `HTTP 403: invalid_api_key` | Wrong key, revoked key, or a key scoped to another environment's indexes | Check the prefix matches the key's `indexes`; re-mint if needed. |
| `HTTP 403: invalid_api_key` on **one** line of `status` (e.g. `Index stats:`) while the others work | The key is valid but lacks that action (Meilisearch words a missing permission as "invalid") | Compare the key's `actions` (`GET /keys`) with the list above. Actions can't be added to a key: mint a new one and delete the old. |
| `index_not_found` | Index never built on this environment | `wp lwtv meili reindex --fresh`. |
| `Settings differ from the repo file` | Someone changed `config/characters.json`, or the server was reset | `wp lwtv meili settings`. |
| A character is missing, renamed wrong, or a deleted one still shows up | A sync failed while the server was down (failures are logged and dropped, never retried) | One character: `wp lwtv meili sync <id>`. Lots, or unsure: `wp lwtv meili reindex --fresh`. A plain `reindex` won't remove stale entries. |
| Search Compare: Meilisearch column says "Request failed" | Browser can't reach the server, or the search key is wrong | Check the browser's network tab: the request goes straight to `search.lezwatchtv.com`. |

Saving posts never waits on Meilisearch: syncs are queued in Action Scheduler (`lwtv_meili_character_task`, `lwtv_meili_fanout_task`, group `lwtv`). If the search server is down, editors won't notice. Search just goes stale until it's back and you reindex.

The index holds nothing that isn't in WordPress, so there are no backups to restore. When in doubt, `reindex --fresh` rebuilds it from scratch (7,000+ characters; time it on the first run and note it here).

## Upgrading Meilisearch

1. Change the image tag in `docker-compose.yml` (e.g. `v1.57.0` → the new version). Read the release notes for breaking changes first.
2. `cd docker/meilisearch-lwtv && docker compose pull && docker compose up -d`. `MEILI_UPGRADE_DB=true` migrates the data in place.
3. `curl -s http://127.0.0.1:7701/version -H "Authorization: Bearer $MEILI_MASTER_KEY"` to confirm.
4. On each environment: `wp lwtv meili status`. If anything looks off, `wp lwtv meili reindex --fresh`: the index is fully derived from WordPress, so rebuilding is always safe.

How it works under the hood (documents, ranking, sync hooks): [docs/architecture/meilisearch.md](../architecture/meilisearch.md).
