# Security Model

## API key storage

API keys are displayed in full only once, immediately after creation.

Persist:

- public prefix
- cryptographic hash
- metadata
- status/expiry/revocation
- permissions/restrictions

Never persist the raw secret.

## Authentication classes

### Client API key

Used by Fabric clients.

Typical scope:
`ingest:write`

Typical restrictions:
- player UUID(s)
- server(s)
- optional season(s)

### Website/read key

Used by a trusted server-side website integration.

Typical scopes:
- players:read
- stats:read
- events:read
- sessions:read
- leaderboards:read
- presence:read

Never grant write or admin scopes to the normal website key.

### Admin authentication

Administrators authenticate through OpenID Connect.

Browser authentication must use a server-managed secure session.

Do not place admin access tokens in localStorage.

## Authorization order for ingestion

1. Parse Bearer credential.
2. Hash and resolve API key.
3. Verify active/not revoked/not expired.
4. Verify required scope.
5. Resolve workspace from the key.
6. Resolve player by Minecraft UUID in that workspace.
7. Enforce player restriction.
8. Resolve registered server.
9. Enforce server restriction.
10. Resolve season.
11. Enforce season restriction if configured.
12. Validate payload and protocol version.
13. Persist transactionally.
14. Return request ID and ingestion result.

## Client credential reality

A key delivered to a Minecraft client is recoverable by the client owner. It must therefore be treated as an authorization credential, not as a cryptographic secret.

Restrictions and revocation are the control plane.

## Rate limiting

Return HTTP 429 and Retry-After when limits are exceeded.

Suggested initial defaults:

- client ingestion: 60 requests/minute/key
- website reads: 120 requests/minute/key
- admin routes: 60 requests/minute/user

Make these values configurable.

## Logging

Never log:

- Authorization headers
- raw API keys
- OIDC client secrets
- session cookie values

Log:

- request ID
- endpoint
- status
- authenticated key ID (not secret)
- workspace ID
- player ID where relevant
- latency

## OIDC

The server must validate:

- issuer
- signature/key material
- audience
- nonce/state as appropriate to the implementation
- token expiry
- required claims

OIDC provider configuration must be deployment-specific.

## Bootstrap

The first owner is established through an explicit one-time bootstrap procedure. Merely being the first person to access the login page must never grant ownership.

## Data isolation

Every workspace-scoped query must include workspace authorization. Never trust workspace IDs supplied by clients.

## HTTPS

Production deployments must use HTTPS. A reverse proxy such as Caddy can terminate TLS.

## Security reports

Use GitHub private vulnerability reporting/security advisories for responsible disclosure once repository security configuration is enabled.
