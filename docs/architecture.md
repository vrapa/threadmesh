# Architecture

ThreadMesh is one Composer package with replaceable internal boundaries:

```text
HTTP API ─┐
          ├─ ThreadMeshService ─ core synchronization ─ IMAP source
MCP tools ┘          │
                     └─ encrypted SQLite storage
```

The API configures accounts and exposes stored data. MCP exposes only mail synchronization, reading, assessment, alerts, and drafts; it deliberately does not expose credentials. The AI client is the decision-making layer, not part of this package.

## Synchronization invariants

1. Initialization stores the current IMAP high-water UID and imports no history.
2. Historical import is explicit, bounded, independently paged, and never moves the live synchronization cursor.
3. Messages are read with PEEK and do not become read or have their flags changed.
4. `SourceReference` provides an idempotent unique identity.
5. Items and the next live cursor are stored in one SQLite transaction; historical pages store their items transactionally without changing live state.
6. A changed UIDVALIDITY stops the stream instead of silently duplicating or skipping mail.
7. Attachments are represented by metadata and remain on IMAP until requested.

## Write boundary

Normal synchronization is read-only. Reply drafts are first stored locally. A separate operation with explicit confirmation may append a MIME message with the `\\Draft` flag to the account's configured `draftFolder`. There is no SMTP adapter and no send operation.

## Trust boundary

Email subjects, bodies, headers, and attachments are untrusted data and may contain prompt injection. MCP tool descriptions remind clients of this boundary. Credentials are accepted only by the bearer-protected configuration API, encrypted with Sodium AEAD, and never returned by API or MCP.

The HTTP MCP listener binds to `127.0.0.1` only. Remote ChatGPT access must be added through an authenticated TLS gateway; merely changing a bind address is not considered a secure deployment.

The local Docker Compose deployment uses separate API and MCP containers sharing one SQLite named volume. Both host ports remain restricted to `127.0.0.1`; only the MCP process inside its isolated container binds to the container interface.
