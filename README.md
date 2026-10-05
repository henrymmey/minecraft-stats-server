# Minecraft Stats Server

Self-hostable REST API and backend for the Minecraft Stats Platform.

## Stack

- PHP 8.3+
- Laravel 13
- PostgreSQL 18
- Docker / Docker Compose

## Local development

Run the documented local PostgreSQL stack:

```bash
docker compose -f docker/compose.dev.yml up --build
```

The application listens on `http://localhost:8000`.

For a local non-container setup:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## API

The first client endpoint is:

```
POST /api/v1/ingest/batch
Authorization: Bearer mst_client_<uuid>_<secret>
```

The HTTP contract is maintained in:

https://github.com/henrymmey/minecraft-stats-docs/tree/main/openapi

## Security

API keys are scoped credentials. Raw secrets are never persisted and are shown only once when created.

Client keys should be restricted to the smallest possible set of players, servers and seasons.

See [SECURITY.md](SECURITY.md).

## Project repositories

- Client: https://github.com/henrymmey/minecraft-stats-client
- Server: https://github.com/henrymmey/minecraft-stats-server
- Dashboard: https://github.com/henrymmey/minecraft-stats-dashboard
- Documentation: https://github.com/henrymmey/minecraft-stats-docs

## License

MIT.
