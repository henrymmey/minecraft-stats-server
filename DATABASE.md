# Database Model

The following is the planned relational model. Laravel migrations are the executable source once implementation starts.

## workspaces

- id UUID PK
- name
- slug UNIQUE
- created_at
- updated_at

## users

- id UUID PK
- oidc_issuer
- oidc_subject
- email nullable
- display_name nullable
- avatar_url nullable
- created_at
- updated_at
- last_login_at nullable

Unique identity:
`(oidc_issuer, oidc_subject)`

## workspace_memberships

- workspace_id FK
- user_id FK
- role
- created_at

PK:
`(workspace_id, user_id)`

Roles:
- owner
- admin
- analyst
- readonly

## seasons

- id UUID PK
- workspace_id FK
- name
- slug
- started_at nullable
- ended_at nullable
- active boolean
- created_at
- updated_at

Unique:
`(workspace_id, slug)`

## servers

- id UUID PK
- workspace_id FK
- name
- hostname
- port
- display_name
- enabled boolean
- created_at
- updated_at

Unique:
`(workspace_id, hostname, port)`

## players

- id UUID PK
- workspace_id FK
- minecraft_uuid
- current_username
- first_seen_at
- last_seen_at
- public boolean
- created_at
- updated_at

Unique:
`(workspace_id, minecraft_uuid)`

## player_aliases

- player_id FK
- username
- first_seen_at
- last_seen_at

Unique:
`(player_id, username)`

## stat_definitions

- id UUID PK
- workspace_id FK
- key
- name
- category
- unit nullable
- description nullable
- public boolean
- created_at
- updated_at

Unique:
`(workspace_id, key)`

## player_stats

Current/latest values.

- season_id FK
- player_id FK
- stat_definition_id FK
- value BIGINT
- updated_at

Unique:
`(season_id, player_id, stat_definition_id)`

## stat_history

Append-only observations for graphing/auditing.

- id UUID PK
- season_id FK
- player_id FK
- stat_definition_id FK
- value BIGINT
- observed_at

Index:
`(season_id, player_id, stat_definition_id, observed_at)`

## sessions

- id UUID PK
- season_id FK
- player_id FK
- server_id FK
- client_session_id UUID
- started_at
- last_seen_at
- ended_at nullable
- client_version
- minecraft_version
- mod_version

Unique:
`(player_id, client_session_id)`

## events

- id UUID PK
- season_id FK
- player_id FK
- session_id nullable FK
- client_event_id UUID
- type
- occurred_at
- payload JSONB

Unique:
`(workspace_id, client_event_id)` is recommended if workspace_id is included for efficient global deduplication.

## api_keys

- id UUID PK
- workspace_id FK
- name
- prefix
- hash
- description nullable
- enabled
- expires_at nullable
- created_by FK nullable
- last_used_at nullable
- created_at
- revoked_at nullable

The full secret is never persisted.

## api_key_scopes

- api_key_id FK
- scope

PK:
`(api_key_id, scope)`

## api_key_player_restrictions

- api_key_id FK
- player_id FK

PK:
`(api_key_id, player_id)`

## api_key_server_restrictions

- api_key_id FK
- server_id FK

PK:
`(api_key_id, server_id)`

## api_key_season_restrictions

- api_key_id FK
- season_id FK

PK:
`(api_key_id, season_id)`

## audit_logs

- id UUID PK
- workspace_id FK
- user_id FK nullable
- action
- target_type nullable
- target_id nullable
- metadata JSONB
- created_at

Index:
`(workspace_id, created_at)`

## Deletion strategy

Do not use cascading deletion for high-value historical data by default. Administrative deletion should be explicit and audited.

Player/public visibility can be disabled without deleting the underlying record.

## Retention

Retention policies for events and session history must be workspace-configurable. Statistics themselves are normally retained until explicitly removed.
