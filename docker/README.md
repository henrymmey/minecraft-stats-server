# Docker Deployment

The production deployment is designed around four services:

- `api`: Laravel application
- `dashboard`: built React application
- `postgres`: PostgreSQL database
- `caddy`: HTTPS reverse proxy

Target topology:

```
Internet
  |
  v
Caddy
  |---- stats.example.com  -> api
  |---- admin.example.com  -> dashboard
  |
  +--> API/Dashboard containers
            |
            v
        PostgreSQL
```

For the simplest deployment, a single hostname may also be used:

- `/api/*` -> API
- `/*` -> Dashboard

PostgreSQL data lives in a named persistent volume.

The image registry target is GitHub Container Registry:

`ghcr.io/henrymmey/minecraft-stats-server`

and:

`ghcr.io/henrymmey/minecraft-stats-dashboard`

Secrets must be supplied through environment variables initially, with Docker Secrets support planned for hardened deployments.

The server container startup sequence should be:

1. validate environment
2. wait for PostgreSQL
3. run migrations
4. cache configuration/routes where applicable
5. start PHP application

Do not automatically perform destructive migrations or seed demo data in production.
