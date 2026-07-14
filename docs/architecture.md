# Architecture

ThreadMesh separates provider integrations from a small normalized core.

## Boundaries

The core contains immutable domain objects and connector contracts. It has no production dependencies and knows nothing about IMAP, Jira, Bitbucket, databases, queues, or web frameworks.

A connector translates provider records into core `Item` objects and maps actions back to provider operations. It owns authentication, rate limits, pagination, retries, and provider-specific cursors.

The host schedules synchronization, stores items and cursors, encrypts credentials, evaluates rules, and presents a CLI, API, or UI.

## Synchronization invariants

1. A source reference is stable within one connector account.
2. Replaying a cursor must not create duplicate host records.
3. A result cursor is persisted only after all returned items are stored.
4. Cursor values are opaque outside the connector.
5. Provider payloads never become the public normalized API.

## Dependency direction

```text
host application ──> connector ──> core
       └──────────────────────────> core
```
