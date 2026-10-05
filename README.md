# Minecraft Stats Server

Self-hostable REST API and backend for Minecraft client statistics.

## Stack

- PHP 8.5
- Laravel 13
- PostgreSQL 18
- Docker
- OpenID Connect for administrative authentication
- OpenAPI for the public API contract

Laravel 13 requires PHP 8.3+ and currently receives security fixes through March 17, 2028.

## Responsibilities

- Multi-workspace data isolation
- Minecraft client ingestion
- API key management
- Scopes and resource restrictions
- Players, seasons and servers
- Statistics and history
- Sessions and presence
- Events
- Website/read API
- OIDC authentication
- Admin authorization
- Audit log
- Rate limiting
- Health/readiness checks
- Database migrations

## Related repositories

- Client: https://github.com/henrymmey/minecraft-stats-client
- Dashboard: https://github.com/henrymmey/minecraft-stats-dashboard
- Docs/API contract: https://github.com/henrymmey/minecraft-stats-docs

## Development

The first implementation target is a modular Laravel monolith. Do not split the application into microservices unless a concrete scaling requirement justifies it.

## License

MIT.