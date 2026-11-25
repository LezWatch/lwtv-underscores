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
