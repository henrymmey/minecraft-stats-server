# Server Architecture

## Core principle

The server is a modular monolith. HTTP, authorization, domain services, persistence and administrative functions live in one deployable application.

```
HTTP request
   |
   v
Route / Controller
   |
   v
Authentication
   |
   v
Authorization policy
   |
   v
Application service
   |
   v
Domain model / persistence
   |
   v
PostgreSQL
```

## API surfaces

### Ingestion

`/api/v1/ingest/batch`

Authenticated with a client API key.

### Public/read API

`/api/v1/players`
`/api/v1/players/{player}`
`/api/v1/players/{player}/stats`
`/api/v1/players/{player}/events`
`/api/v1/leaderboards`
`/api/v1/online`
`/api/v1/seasons`

Authenticated by a website/integration read key unless a deployment explicitly exposes a public read endpoint.

### Administration

`/api/v1/admin/*`

Authenticated through the browser's OIDC-backed server session.

## Domain entities

- Workspace
- User
- WorkspaceMembership
- Season
- Server
- Player
- PlayerAlias
- StatDefinition
- PlayerStat
- StatHistory
- Session
- Event
- ApiKey
- ApiKeyScope
- ApiKeyPlayerRestriction
- ApiKeyServerRestriction
- ApiKeySeasonRestriction
- AuditLog

## Multi-tenancy

Every player, server, season, statistic and API key belongs to exactly one workspace.

The API never trusts a client-provided workspace ID for authorization. Workspace identity is derived from the authenticated API key or admin session.

## Ingestion

The client submits a batch containing:

- protocol version
- client/mod/Minecraft version
- player UUID/name
- observed server
- session ID
- observed timestamp
- absolute statistic values
- event records

The server resolves:

1. API key
2. workspace
3. player
4. server
5. active/target season
6. key scopes and restrictions

Only after all authorization checks succeed is data persisted.

## Time

Store timestamps in UTC in PostgreSQL. Convert to a user's local timezone only in presentation.

## IDs

Use UUIDs for externally addressable domain objects. PostgreSQL 18 also provides uuidv7(), but generated identifiers should be chosen consistently and not mixed arbitrarily.

## Rate limiting

Rate limits apply per API key and can later be combined with IP-based protection at the reverse proxy.

## Caching

Do not require Redis for the first production version. PostgreSQL and Laravel's normal cache mechanisms are sufficient for the first deployment. Redis remains an optional future optimization.

## Health endpoints

`GET /health/live`
- process/application is alive

`GET /health/ready`
- application is alive
- PostgreSQL is reachable
- schema/migrations are usable

## Error contract

All API errors use a stable machine-readable error code plus a request ID.

Example:

```json
{
  "error": {
    "code": "API_KEY_PLAYER_FORBIDDEN",
    "message": "The API key is not authorized for this player.",
    "request_id": "01J..."
  }
}
```

Never include API keys, session cookies or OIDC client secrets in errors or logs.
